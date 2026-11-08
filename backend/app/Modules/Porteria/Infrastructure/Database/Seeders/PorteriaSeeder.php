<?php

namespace App\Modules\Porteria\Infrastructure\Database\Seeders;

use App\Models\User;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Monitoreo\Domain\Models\Zona;
use App\Modules\Porteria\Domain\Models\Autorizacion;
use App\Modules\Porteria\Domain\Models\RegistroPorteria;
use App\Modules\Porteria\Domain\Models\Residente;
use App\Modules\Porteria\Domain\Models\Unidad;
use App\Modules\Porteria\Domain\TipoIngreso;
use App\Modules\Porteria\Domain\TipoResidente;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/** Requiere UsuariosSeeder y ZonasSeeder. */
class PorteriaSeeder extends Seeder
{
    private const TORRES = 3;

    private const APTOS_POR_TORRE = ['101', '102', '201', '202', '301', '302', '401', '402'];

    public function run(): void
    {
        $unidades = $this->crearUnidades();
        $residentes = $this->crearResidentes();
        $this->asignarUnidades($unidades, $residentes);

        $vigilante = User::where('email', 'vigilante@nexora.test')->firstOrFail();
        $zonas = Zona::pluck('id', 'nombre');

        $usadas = $this->crearAutorizaciones($residentes);
        $this->crearRegistros($usadas, $unidades, $zonas, $vigilante);
    }

    private function crearUnidades(): Collection
    {
        $unidades = collect();

        foreach (range(1, self::TORRES) as $torre) {
            foreach (self::APTOS_POR_TORRE as $numero) {
                $unidades->push(Unidad::create(['torre' => (string) $torre, 'numero' => $numero]));
            }
        }

        return $unidades;
    }

    private function crearResidentes(): Collection
    {
        $conCuenta = User::where('rol', Rol::Residente)->orderBy('id')->get()
            ->map(fn (User $user) => Residente::factory()->create([
                'user_id' => $user->id,
                'nombre' => $user->name,
                'telefono' => $user->telefono,
                'tipo_residente' => TipoResidente::Propietario,
            ]));

        return $conCuenta->concat(Residente::factory()->count(30 - $conCuenta->count())->create());
    }

    /**
     * Los primeros 24 residentes son titulares de una unidad cada uno; los otros 6
     * comparten unidad (N:M), y la primera residente con cuenta tiene además una segunda unidad.
     */
    private function asignarUnidades(Collection $unidades, Collection $residentes): void
    {
        $unidades->each(fn (Unidad $unidad, int $i) => $unidad->residentes()->attach($residentes[$i]->id, ['es_titular' => true]));

        $residentes->slice($unidades->count())->values()
            ->each(fn (Residente $residente, int $i) => $unidades[$i * 4 + 1]->residentes()->attach($residente->id, ['es_titular' => false]));

        $residentes->first()->unidades()->attach($unidades->last()->id, ['es_titular' => false]);
    }

    /** 15 autorizaciones: 8 vigentes, 5 usadas, 1 expirada y 1 anulada. Devuelve las usadas. */
    private function crearAutorizaciones(Collection $residentes): Collection
    {
        $estados = [
            ...array_fill(0, 8, 'vigente'),
            ...array_fill(0, 5, 'usada'),
            'expirada',
            'anulada',
        ];

        $usadas = collect();

        foreach ($estados as $i => $estado) {
            $residente = $residentes[$i % 6];
            $unidad = $residente->unidades()->first();

            $factory = Autorizacion::factory()->paraResidente($residente, $unidad);
            $factory = match ($estado) {
                'usada' => $factory->usada(),
                'expirada' => $factory->expirada(),
                'anulada' => $factory->anulada(),
                default => $factory,
            };

            $autorizacion = $factory->create();

            if ($estado === 'usada') {
                $usadas->push($autorizacion);
            }
        }

        return $usadas;
    }

    /** 40 registros en los últimos 30 días, todos hechos por el vigilante; 5 salen de autorizaciones usadas. */
    private function crearRegistros(Collection $usadas, Collection $unidades, Collection $zonas, User $vigilante): void
    {
        foreach ($usadas as $autorizacion) {
            $tipo = $autorizacion->placa ? TipoIngreso::Vehiculo : TipoIngreso::Visitante;

            RegistroPorteria::factory()->create([
                'tipo_ingreso' => $tipo,
                'nombre_visitante' => $autorizacion->visitante_nombre,
                'documento' => $autorizacion->visitante_documento,
                'placa' => $autorizacion->placa,
                'cantidad_personas' => 1,
                'unidad_id' => $autorizacion->unidad_id,
                'zona_id' => $this->zonaPara($tipo, $zonas),
                'registrado_por' => $vigilante->id,
                'autorizacion_id' => $autorizacion->id,
                'fecha_ingreso' => $autorizacion->usada_at,
                'fecha_salida' => $autorizacion->usada_at->copy()->addMinutes(fake()->numberBetween(20, 180)),
            ]);
        }

        foreach (range(1, 40 - $usadas->count()) as $_) {
            $tipo = fake()->randomElement(TipoIngreso::cases());

            RegistroPorteria::factory()->create([
                'tipo_ingreso' => $tipo,
                'placa' => $tipo === TipoIngreso::Vehiculo ? strtoupper(fake()->bothify('???###')) : null,
                'cantidad_personas' => in_array($tipo, [TipoIngreso::Domiciliario, TipoIngreso::Vehiculo], true) ? 1 : fake()->numberBetween(1, 4),
                'unidad_id' => $unidades->random()->id,
                'zona_id' => $this->zonaPara($tipo, $zonas),
                'registrado_por' => $vigilante->id,
            ]);
        }
    }

    private function zonaPara(TipoIngreso $tipo, Collection $zonas): int
    {
        $nombre = match ($tipo) {
            TipoIngreso::Vehiculo => fake()->randomElement(['Acceso vehicular', 'Parqueadero sótano']),
            TipoIngreso::Domiciliario => 'Acceso peatonal principal',
            TipoIngreso::Contratista => fake()->randomElement(['Acceso peatonal posterior', 'Acceso vehicular']),
            TipoIngreso::Visitante => fake()->randomElement(['Acceso peatonal principal', 'Acceso peatonal posterior']),
        };

        return $zonas[$nombre];
    }
}
