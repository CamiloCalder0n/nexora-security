<?php

namespace Tests\Feature\Alertas;

use App\Models\User;
use App\Modules\Alertas\Application\AlertaService;
use App\Modules\Alertas\Application\Contracts\BotTelegram;
use App\Modules\Alertas\Application\Telegram\ProcesadorTelegram;
use App\Modules\Alertas\Application\Telegram\TelegramNoDisponible;
use App\Modules\Alertas\Application\Telegram\VinculacionTelegram;
use App\Modules\Alertas\Domain\Events\AlertaCreada;
use App\Modules\Alertas\Domain\Events\AlertaEscalada;
use App\Modules\Alertas\Domain\Models\Alerta;
use App\Modules\Alertas\Domain\Models\EntradaBitacora;
use App\Modules\Alertas\Infrastructure\Jobs\NotificarTelegramJob;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Monitoreo\Domain\Events\CamaraSinSenal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\DocumentosMongo;
use Tests\Concerns\LimpiaMongo;
use Tests\TestCase;
use Throwable;

class TelegramTest extends TestCase
{
    use DocumentosMongo, LimpiaMongo, RefreshDatabase;

    private const TOKEN = '123456:TOKEN-SECRETO-DE-PRUEBA';

    private int $update = 1;

    /** Respuesta de Telegram por método; '*' para el resto. Cada test la ajusta. */
    private array $respuestas = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['alertas.telegram.token' => self::TOKEN, 'alertas.telegram.webhook_secret' => 'secreto-del-webhook-123']);
        Http::preventStrayRequests();
        $this->telegramResponde();
        Http::fake(function (Request $request) {
            $metodo = basename(parse_url($request->url(), PHP_URL_PATH));
            [$cuerpo, $status] = $this->respuestas[$metodo] ?? $this->respuestas['*'];
            if ($cuerpo instanceof Throwable) {
                throw $cuerpo;
            }

            return Http::response($cuerpo, $status);
        });
    }

    private function telegramResponde(mixed $respuesta = ['ok' => true, 'result' => true], int $status = 200, string $metodo = '*'): void
    {
        $this->respuestas[$metodo] = [$respuesta, $status];
    }

    private function vinculado(Rol $rol, string $chatId, array $datos = []): User
    {
        return User::factory()->conRol($rol)->create(['telegram_chat_id' => $chatId, ...$datos]);
    }

    /** @return list<Request> */
    private function enviados(string $metodo): array
    {
        return Http::recorded(fn (Request $r) => str_ends_with($r->url(), "/{$metodo}"))->map(fn ($par) => $par[0])->values()->all();
    }

    private function actualizacion(array $datos): array
    {
        return ['update_id' => $this->update++, ...$datos];
    }

    private function mensajePrivado(string $chatId, string $texto): array
    {
        return $this->actualizacion(['message' => ['message_id' => 1, 'chat' => ['id' => (int) $chatId, 'type' => 'private'], 'text' => $texto]]);
    }

    private function boton(string $chatId, string $datos): array
    {
        return $this->actualizacion(['callback_query' => [
            'id' => 'cb-'.$this->update, 'from' => ['id' => (int) $chatId], 'data' => $datos,
            'message' => ['message_id' => 77, 'chat' => ['id' => (int) $chatId]],
        ]]);
    }

    // --- Avisos -------------------------------------------------------------------------------

    public function test_alerta_nueva_llega_solo_a_vigilantes_activos_vinculados_con_boton_atender(): void
    {
        $vigilante = $this->vinculado(Rol::Vigilante, '1001');
        $this->vinculado(Rol::Vigilante, '1002', ['activo' => false]);
        $this->vinculado(Rol::Residente, '1003');
        $this->vinculado(Rol::Admin, '1004');
        User::factory()->conRol(Rol::Vigilante)->create();
        $alerta = $this->crearAlerta(['zona' => ['zona_id' => 1, 'nombre' => 'Torre <B>'], 'motivo' => 'Persona & sin registro']);

        AlertaCreada::dispatch($alerta->id);

        $enviados = $this->enviados('sendMessage');
        $this->assertCount(1, $enviados);
        $this->assertSame($vigilante->telegram_chat_id, $enviados[0]['chat_id']);
        $this->assertStringContainsString('Torre &lt;B&gt;', $enviados[0]['text']);
        $this->assertStringContainsString('Persona &amp; sin registro', $enviados[0]['text']);
        $this->assertStringContainsString('prioridad alta', $enviados[0]['text']);
        $this->assertSame("atender:{$alerta->id}", $enviados[0]['reply_markup']['inline_keyboard'][0][0]['callback_data']);
    }

    public function test_escalada_avisa_tambien_a_la_administracion(): void
    {
        $this->vinculado(Rol::Vigilante, '2001');
        $this->vinculado(Rol::Admin, '2002');
        $alerta = $this->crearAlerta();

        AlertaEscalada::dispatch($alerta->id, 2);

        $enviados = $this->enviados('sendMessage');
        $this->assertEqualsCanonicalizing(['2001', '2002'], array_map(fn ($r) => $r['chat_id'], $enviados));
        $this->assertStringContainsString('SIN ATENDER · nivel 2', $enviados[0]['text']);
    }

    public function test_el_mismo_aviso_no_se_repite_pero_cada_escalamiento_si_sale(): void
    {
        $this->vinculado(Rol::Vigilante, '3001');
        $alerta = $this->crearAlerta();

        AlertaCreada::dispatch($alerta->id);
        AlertaCreada::dispatch($alerta->id);
        AlertaEscalada::dispatch($alerta->id, 1);
        AlertaEscalada::dispatch($alerta->id, 2);

        $this->assertCount(3, $this->enviados('sendMessage'));
    }

    public function test_no_avisa_una_alerta_que_ya_atendieron(): void
    {
        $this->vinculado(Rol::Vigilante, '4001');
        $alerta = $this->crearAlerta(['estado' => 'atendida']);

        AlertaCreada::dispatch($alerta->id);

        $this->assertSame([], $this->enviados('sendMessage'));
    }

    public function test_sin_token_no_se_llama_a_telegram(): void
    {
        config(['alertas.telegram.token' => null]);
        $this->app->forgetInstance(BotTelegram::class);
        $this->vinculado(Rol::Vigilante, '5001');

        AlertaCreada::dispatch($this->crearAlerta()->id);

        Http::assertNothingSent();
    }

    public function test_la_caida_de_una_camara_llega_a_telegram(): void
    {
        $this->vinculado(Rol::Vigilante, '5101');

        event(new CamaraSinSenal(7, 'CAM-07', 1, 'Parqueadero', now()->subMinutes(8)->toIso8601String()));

        $enviados = $this->enviados('sendMessage');
        $this->assertCount(1, $enviados);
        $this->assertStringContainsString('CAM-07', $enviados[0]['text']);
    }

    public function test_si_el_usuario_bloqueo_el_bot_se_desvincula_y_no_se_reintenta(): void
    {
        $vigilante = $this->vinculado(Rol::Vigilante, '6001');
        $this->telegramResponde(['ok' => false, 'error_code' => 403, 'description' => 'Forbidden: bot was blocked by the user'], 403);

        NotificarTelegramJob::dispatch($this->crearAlerta()->id, $vigilante->id);

        $this->assertNull($vigilante->fresh()->telegram_chat_id);
        $this->assertCount(1, $this->enviados('sendMessage'));
    }

    public function test_chat_inexistente_desvincula_pero_otro_rechazo_no(): void
    {
        $perdido = $this->vinculado(Rol::Vigilante, '6101');
        $this->telegramResponde(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: chat not found'], 400);
        NotificarTelegramJob::dispatch($this->crearAlerta()->id, $perdido->id);
        $this->assertNull($perdido->fresh()->telegram_chat_id);

        $otro = $this->vinculado(Rol::Vigilante, '6102');
        $this->telegramResponde(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: message is too long'], 400);
        NotificarTelegramJob::dispatch($this->crearAlerta()->id, $otro->id);
        $this->assertSame('6102', $otro->fresh()->telegram_chat_id);
        $this->assertCount(2, $this->enviados('sendMessage'));
    }

    public function test_si_telegram_esta_caido_el_job_falla_para_reintentarse(): void
    {
        $vigilante = $this->vinculado(Rol::Vigilante, '7001');
        $this->telegramResponde(['ok' => false, 'description' => 'Bad Gateway'], 502);

        $this->expectException(TelegramNoDisponible::class);
        NotificarTelegramJob::dispatchSync($this->crearAlerta()->id, $vigilante->id);
    }

    public function test_con_429_espera_lo_que_pide_telegram_y_en_el_ultimo_intento_falla(): void
    {
        $vigilante = $this->vinculado(Rol::Vigilante, '7201');
        $alerta = $this->crearAlerta();
        $this->telegramResponde(['ok' => false, 'description' => 'Too Many Requests', 'parameters' => ['retry_after' => 7]], 429);

        $job = (new NotificarTelegramJob($alerta->id, $vigilante->id))->withFakeQueueInteractions();
        app()->call([$job, 'handle']);
        $job->assertReleased(7);

        $ultimo = (new NotificarTelegramJob($alerta->id, $vigilante->id))->withFakeQueueInteractions();
        $ultimo->job->attempts = $ultimo->tries;
        $this->expectException(TelegramNoDisponible::class);
        app()->call([$ultimo, 'handle']);
    }

    public function test_el_aviso_va_por_su_propia_cola(): void
    {
        $this->assertSame('telegram', (new NotificarTelegramJob('x', 1))->queue);
    }

    public function test_los_errores_nunca_llevan_el_token(): void
    {
        $vigilante = $this->vinculado(Rol::Vigilante, '7101');
        $this->telegramResponde(new ConnectionException('cURL error 28 for https://api.telegram.org/bot'.self::TOKEN.'/sendMessage'));

        try {
            NotificarTelegramJob::dispatchSync($this->crearAlerta()->id, $vigilante->id);
            $this->fail('Debió fallar');
        } catch (Throwable $e) {
            $this->assertInstanceOf(TelegramNoDisponible::class, $e);
            $this->assertStringNotContainsString('TOKEN-SECRETO', $e->getMessage());
        }
    }

    // --- Vinculación --------------------------------------------------------------------------

    public function test_vincular_con_codigo_valido_y_de_un_solo_uso(): void
    {
        $vigilante = User::factory()->conRol(Rol::Vigilante)->create(['name' => 'Óscar Peña']);
        $codigo = app(VinculacionTelegram::class)->generarCodigo($vigilante);
        $procesador = app(ProcesadorTelegram::class);

        $procesador->procesar($this->mensajePrivado('8001', '/start '.strtolower($codigo)));
        $procesador->procesar($this->mensajePrivado('8002', "/start {$codigo}"));

        $this->assertSame('8001', $vigilante->fresh()->telegram_chat_id);
        $this->assertNull($vigilante->fresh()->telegram_codigo);
        [$exito, $repetido] = $this->enviados('sendMessage');
        $this->assertStringContainsString('Listo, Óscar Peña', $exito['text']);
        $this->assertStringContainsString('no sirve', $repetido['text']);
    }

    /** Redis devuelve los números como texto: con la caché en memoria esto no se ve. */
    public function test_vincular_funciona_con_la_cache_en_redis(): void
    {
        // Prefijo propio por corrida (Redis conserva las marcas de update_id) y limpieza al final:
        // este test usa el Redis real y no puede dejar claves sueltas.
        config(['cache.default' => 'redis', 'cache.prefix' => 'nexora_test_'.uniqid().'_']);
        try {
            Cache::put('ping', 1, 5);
        } catch (Throwable) {
            $this->markTestSkipped('Redis no está disponible.');
        }
        $vigilante = User::factory()->conRol(Rol::Vigilante)->create();
        $codigo = app(VinculacionTelegram::class)->generarCodigo($vigilante);
        $actualizacion = $this->mensajePrivado('8051', "/start {$codigo}");

        try {
            app(ProcesadorTelegram::class)->procesar($actualizacion);

            $this->assertSame('8051', $vigilante->fresh()->telegram_chat_id);
        } finally {
            Cache::deleteMultiple(['ping', "telegram:update:{$actualizacion['update_id']}", "telegram:codigo:{$codigo}"]);
            RateLimiter::clear('telegram:respuestas:8051');
        }
    }

    public function test_el_codigo_vence(): void
    {
        $vigilante = User::factory()->conRol(Rol::Vigilante)->create();
        $codigo = app(VinculacionTelegram::class)->generarCodigo($vigilante);

        $this->travel(config('alertas.telegram.minutos_codigo') + 1)->minutes();
        app(ProcesadorTelegram::class)->procesar($this->mensajePrivado('8101', "/start {$codigo}"));

        $this->assertNull($vigilante->fresh()->telegram_chat_id);
    }

    public function test_una_cuenta_inactiva_no_se_vincula(): void
    {
        $vigilante = User::factory()->conRol(Rol::Vigilante)->create();
        $codigo = app(VinculacionTelegram::class)->generarCodigo($vigilante);
        $vigilante->forceFill(['activo' => false])->save();

        app(ProcesadorTelegram::class)->procesar($this->mensajePrivado('8201', "/start {$codigo}"));

        $this->assertNull($vigilante->fresh()->telegram_chat_id);
    }

    public function test_un_grupo_no_se_vincula(): void
    {
        $vigilante = User::factory()->conRol(Rol::Vigilante)->create();
        $codigo = app(VinculacionTelegram::class)->generarCodigo($vigilante);

        app(ProcesadorTelegram::class)->procesar($this->actualizacion(['message' => [
            'message_id' => 1, 'chat' => ['id' => -100123, 'type' => 'group'], 'text' => "/start {$codigo}",
        ]]));

        $this->assertNull($vigilante->fresh()->telegram_chat_id);
        Http::assertNothingSent();
    }

    public function test_adivinar_codigos_tiene_limite(): void
    {
        $vigilante = User::factory()->conRol(Rol::Vigilante)->create();
        $codigo = app(VinculacionTelegram::class)->generarCodigo($vigilante);
        $procesador = app(ProcesadorTelegram::class);

        foreach (range(1, 5) as $_) {
            $procesador->procesar($this->mensajePrivado('8301', '/start AAAAAA'));
        }
        $procesador->procesar($this->mensajePrivado('8301', "/start {$codigo}"));

        $this->assertNull($vigilante->fresh()->telegram_chat_id);
        $this->assertStringContainsString('Demasiados intentos', last($this->enviados('sendMessage'))['text']);
    }

    public function test_un_chat_queda_con_una_sola_cuenta(): void
    {
        $anterior = $this->vinculado(Rol::Vigilante, '8401');
        $nuevo = User::factory()->conRol(Rol::Admin)->create();
        $codigo = app(VinculacionTelegram::class)->generarCodigo($nuevo);

        app(ProcesadorTelegram::class)->procesar($this->mensajePrivado('8401', "/start {$codigo}"));

        $this->assertNull($anterior->fresh()->telegram_chat_id);
        $this->assertSame('8401', $nuevo->fresh()->telegram_chat_id);
    }

    public function test_ayuda_dice_con_que_cuenta_esta_vinculado(): void
    {
        $this->vinculado(Rol::Vigilante, '8501', ['name' => 'Ana <Ruiz>']);
        $procesador = app(ProcesadorTelegram::class);

        $procesador->procesar($this->mensajePrivado('8501', '/ayuda'));
        $procesador->procesar($this->mensajePrivado('8502', '/start'));

        [$vinculado, $nuevo] = $this->enviados('sendMessage');
        $this->assertStringContainsString('Ana &lt;Ruiz&gt;', $vinculado['text']);
        $this->assertStringContainsString('/start CODIGO', $nuevo['text']);
    }

    // --- Botón "Atender" ----------------------------------------------------------------------

    public function test_atender_desde_telegram_cambia_la_alerta_y_quita_el_boton(): void
    {
        $vigilante = $this->vinculado(Rol::Vigilante, '9001', ['name' => 'Pedro Celador']);
        $alerta = $this->crearAlerta();

        app(ProcesadorTelegram::class)->procesar($this->boton('9001', "atender:{$alerta->id}"));

        $alerta->refresh();
        $this->assertSame('atendida', $alerta->estado->value);
        $this->assertSame($vigilante->id, $alerta->atendida_por['usuario_id']);
        $this->assertSame('alerta_atendida', EntradaBitacora::first()->tipo);
        $this->assertStringContainsString('Alerta atendida', $this->enviados('answerCallbackQuery')[0]['text']);
        $editado = $this->enviados('editMessageText')[0];
        $this->assertSame(77, $editado['message_id']);
        $this->assertStringContainsString('Atendida por Pedro Celador', $editado['text']);
        $this->assertArrayNotHasKey('reply_markup', $editado->data());
    }

    public function test_un_admin_tambien_atiende_y_si_telegram_falla_al_responder_la_alerta_queda_atendida(): void
    {
        $admin = $this->vinculado(Rol::Admin, '9051');
        $alerta = $this->crearAlerta();
        $this->telegramResponde(['ok' => false, 'description' => 'Bad Gateway'], 502, 'editMessageText');
        $this->telegramResponde(['ok' => false, 'description' => 'Bad Gateway'], 502, 'answerCallbackQuery');

        app(ProcesadorTelegram::class)->procesar($this->boton('9051', "atender:{$alerta->id}"));

        $this->assertSame('atendida', $alerta->fresh()->estado->value);
        $this->assertSame($admin->id, $alerta->fresh()->atendida_por['usuario_id']);
    }

    public function test_el_segundo_en_tocar_atender_ve_quien_ya_la_atendio(): void
    {
        $this->vinculado(Rol::Vigilante, '9101', ['name' => 'Pedro']);
        $this->vinculado(Rol::Vigilante, '9102');
        $alerta = $this->crearAlerta();
        $procesador = app(ProcesadorTelegram::class);

        $procesador->procesar($this->boton('9101', "atender:{$alerta->id}"));
        $procesador->procesar($this->boton('9102', "atender:{$alerta->id}"));

        $this->assertStringContainsString('ya está atendida (la atendió Pedro)', $this->enviados('answerCallbackQuery')[1]['text']);
        $this->assertSame(1, EntradaBitacora::where('tipo', 'alerta_atendida')->count());
    }

    public function test_si_la_zona_esta_ocupada_pide_intentar_de_nuevo(): void
    {
        $this->vinculado(Rol::Vigilante, '9151');
        $alerta = $this->crearAlerta();
        $lock = Cache::lock('alertas:zona:1', 10);
        $lock->get();

        try {
            app(ProcesadorTelegram::class)->procesar($this->boton('9151', "atender:{$alerta->id}"));
        } finally {
            $lock->release();
        }

        $this->assertSame('abierta', $alerta->fresh()->estado->value);
        $this->assertStringContainsString('intenta de nuevo', $this->enviados('answerCallbackQuery')[0]['text']);
    }

    public function test_un_residente_o_un_desconocido_no_atienden(): void
    {
        $this->vinculado(Rol::Residente, '9201');
        $alerta = $this->crearAlerta();
        $procesador = app(ProcesadorTelegram::class);

        $procesador->procesar($this->boton('9201', "atender:{$alerta->id}"));
        $procesador->procesar($this->boton('9999', "atender:{$alerta->id}"));

        $this->assertSame('abierta', $alerta->fresh()->estado->value);
        $this->assertCount(2, $this->enviados('answerCallbackQuery'));
    }

    public function test_un_vigilante_inactivo_no_atiende(): void
    {
        $this->vinculado(Rol::Vigilante, '9251', ['activo' => false]);
        $alerta = $this->crearAlerta();

        app(ProcesadorTelegram::class)->procesar($this->boton('9251', "atender:{$alerta->id}"));

        $this->assertSame('abierta', $alerta->fresh()->estado->value);
    }

    public function test_el_spam_de_un_desconocido_no_gasta_el_cupo_ni_frena_los_botones(): void
    {
        $this->vinculado(Rol::Vigilante, '9261');
        $alerta = $this->crearAlerta();
        $procesador = app(ProcesadorTelegram::class);

        foreach (range(1, 20) as $_) {
            $procesador->procesar($this->mensajePrivado('9262', 'hola'));
        }
        foreach (range(1, 10) as $_) {
            $procesador->procesar($this->mensajePrivado('9261', '/ayuda'));
        }
        $procesador->procesar($this->boton('9261', "atender:{$alerta->id}"));

        $respuestas = collect($this->enviados('sendMessage'))->countBy(fn ($r) => $r['chat_id']);
        $this->assertSame(6, $respuestas['9262']);
        $this->assertSame(10, $respuestas['9261']);
        $this->assertSame('atendida', $alerta->fresh()->estado->value);
    }

    public function test_datos_de_boton_manipulados_no_rompen_nada(): void
    {
        $this->vinculado(Rol::Vigilante, '9301');
        $procesador = app(ProcesadorTelegram::class);

        foreach (['atender:{"$ne":null}', 'atender:'.str_repeat('f', 24), 'borrar:todo', ''] as $datos) {
            $procesador->procesar($this->boton('9301', $datos));
        }

        $this->assertSame(0, Alerta::where('estado', 'atendida')->count());
        $this->assertCount(4, $this->enviados('answerCallbackQuery'));
    }

    public function test_una_actualizacion_repetida_se_procesa_una_vez(): void
    {
        $this->vinculado(Rol::Vigilante, '9401');
        $alerta = $this->crearAlerta();
        $actualizacion = $this->boton('9401', "atender:{$alerta->id}");

        app(ProcesadorTelegram::class)->procesar($actualizacion);
        app(ProcesadorTelegram::class)->procesar($actualizacion);

        $this->assertCount(1, $this->enviados('answerCallbackQuery'));
    }

    // --- Webhook ------------------------------------------------------------------------------

    public function test_webhook_sin_el_secreto_correcto_es_404(): void
    {
        $this->postJson('/api/v1/telegram/webhook', $this->mensajePrivado('1', '/ayuda'))->assertNotFound();
        $this->postJson('/api/v1/telegram/webhook', $this->mensajePrivado('1', '/ayuda'), ['X-Telegram-Bot-Api-Secret-Token' => 'otro'])
            ->assertNotFound();

        config(['alertas.telegram.webhook_secret' => null]);
        $this->postJson('/api/v1/telegram/webhook', $this->mensajePrivado('1', '/ayuda'), ['X-Telegram-Bot-Api-Secret-Token' => ''])
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_webhook_con_secreto_procesa_y_responde_200_aunque_falle(): void
    {
        $cabecera = ['X-Telegram-Bot-Api-Secret-Token' => 'secreto-del-webhook-123'];

        $this->postJson('/api/v1/telegram/webhook', $this->mensajePrivado('10001', '/ayuda'), $cabecera)->assertOk();
        $this->assertCount(1, $this->enviados('sendMessage'));

        $this->telegramResponde(['ok' => false, 'description' => 'Bad Gateway'], 502);
        $this->postJson('/api/v1/telegram/webhook', $this->mensajePrivado('10001', '/ayuda'), $cabecera)->assertOk();
        $this->postJson('/api/v1/telegram/webhook', ['basura' => true], $cabecera)->assertOk();
    }

    public function test_si_falla_la_cache_del_dedup_no_procesa_y_telegram_reintenta(): void
    {
        $this->withoutExceptionHandling();
        Cache::shouldReceive('add')->andThrow(new \RuntimeException('Redis caído'));

        try {
            $this->postJson('/api/v1/telegram/webhook', $this->mensajePrivado('10101', '/ayuda'),
                ['X-Telegram-Bot-Api-Secret-Token' => 'secreto-del-webhook-123']);
            $this->fail('El webhook no debió responder 200');
        } catch (\RuntimeException $e) {
            $this->assertSame('Redis caído', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    // --- Comandos -----------------------------------------------------------------------------

    public function test_escuchar_procesa_una_tanda(): void
    {
        $alerta = $this->crearAlerta();
        $this->vinculado(Rol::Vigilante, '11001');
        $this->telegramResponde(['ok' => true, 'result' => [$this->boton('11001', "atender:{$alerta->id}")]], metodo: 'getUpdates');

        $this->artisan('nexora:telegram-escuchar', ['--una-vez' => true])->assertSuccessful();

        $this->assertSame('atendida', $alerta->fresh()->estado->value);
    }

    public function test_escuchar_con_webhook_activo_explica_que_hacer(): void
    {
        $this->telegramResponde(['ok' => false, 'error_code' => 409, 'description' => 'Conflict: can\'t use getUpdates method while webhook is active'], 409);

        $this->artisan('nexora:telegram-escuchar', ['--una-vez' => true])
            ->expectsOutputToContain('--sin-webhook')
            ->assertFailed();
    }

    public function test_escuchar_con_otro_proceso_escuchando_lo_dice(): void
    {
        $this->telegramResponde(['ok' => false, 'error_code' => 409, 'description' => 'Conflict: terminated by other getUpdates request; make sure that only one bot instance is running'], 409);

        $this->artisan('nexora:telegram-escuchar', ['--una-vez' => true])
            ->expectsOutputToContain('otro proceso escuchando')
            ->assertFailed();
    }

    public function test_escuchar_una_vez_sin_red_falla(): void
    {
        $this->telegramResponde(new ConnectionException('sin red'));

        $this->artisan('nexora:telegram-escuchar', ['--una-vez' => true])->assertFailed();
    }

    public function test_configurar_registra_el_webhook_en_la_ruta_real_con_el_secreto(): void
    {
        $this->telegramResponde(['ok' => true, 'result' => ['username' => 'NexoraBot', 'url' => 'x']]);

        $this->artisan('nexora:telegram-configurar', ['--webhook' => 'https://nexora.onrender.com/'])->assertSuccessful();

        $registro = $this->enviados('setWebhook')[0];
        $this->assertSame('https://nexora.onrender.com/api/v1/telegram/webhook', $registro['url']);
        $this->assertSame('secreto-del-webhook-123', $registro['secret_token']);
        $this->assertSame(['message', 'callback_query'], $registro['allowed_updates']);
        $this->postJson(parse_url($registro['url'], PHP_URL_PATH), $this->mensajePrivado('1', '/ayuda'),
            ['X-Telegram-Bot-Api-Secret-Token' => $registro['secret_token']])->assertOk();
    }

    public function test_configurar_exige_https_y_un_secreto_fuerte(): void
    {
        $this->artisan('nexora:telegram-configurar', ['--webhook' => 'http://nexora.test'])->assertFailed();
        config(['alertas.telegram.webhook_secret' => 'corto']);
        $this->artisan('nexora:telegram-configurar', ['--webhook' => 'https://nexora.test'])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_codigo_para_un_usuario_activo(): void
    {
        $this->telegramResponde(['ok' => true, 'result' => ['username' => 'NexoraBot']]);
        User::factory()->conRol(Rol::Vigilante)->create(['email' => 'v@nexora.test']);

        $this->artisan('nexora:telegram-codigo', ['email' => 'v@nexora.test'])
            ->expectsOutputToContain('https://t.me/NexoraBot?start=')
            ->assertSuccessful();
        $this->artisan('nexora:telegram-codigo', ['email' => 'nadie@nexora.test'])->assertFailed();
    }

    public function test_atender_por_el_servicio_sigue_igual_sin_telegram(): void
    {
        $vigilante = User::factory()->conRol(Rol::Vigilante)->create();

        app(AlertaService::class)->atender($this->crearAlerta(), $vigilante);

        Http::assertNothingSent();
    }
}
