<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zonas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80)->unique();
            $table->enum('tipo', [
                'acceso_peatonal',
                'acceso_vehicular',
                'parqueadero',
                'perimetro',
                'zona_comun',
                'servicio',
            ]);
            $table->unsignedTinyInteger('prioridad_base')->default(2);
            $table->unsignedSmallInteger('ventana_correlacion_min')->default(10);
            $table->unsignedSmallInteger('ventana_silencio_min')->default(5);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zonas');
    }
};
