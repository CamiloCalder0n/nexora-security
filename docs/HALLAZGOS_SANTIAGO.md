# Hallazgos para Santiago · 9 de noviembre de 2026

Salen de la auditoría de punta a punta de la rama `chore/KAN-43-pulido-final`: Newman contra localhost, agente en `--simular`, casos de negocio por API y recorrido del panel por rol, revisando los logs. **No hay hallazgos de severidad alta ni media.** No toqué nada de esto: son de tus módulos o del despliegue.

## Lo que se probó y está bien en tus módulos

- **Ingesta del agente:** 15 de 15 eventos de `--simular` recibidos y la cola local quedó vacía. Además: 422 por tipos malos, cámara inexistente, `ruta_evidencia` con `..` y fecha futura; el uuid se normaliza a minúsculas y el reenvío responde 200 "duplicado"; 401 sin token y 403 con otro rol; el heartbeat responde 204, o 404 si la cámara no existe; el límite de 60 por minuto responde 429.
- **Correlación:** con un ingreso en portería el evento queda *explicado* y no crea alerta; sin ingreso crea la alerta `sin_registro` con el motivo de HU38.
- **Supresión:** 4 eventos en la ventana producen una sola alerta con `num_eventos: 4`.
- **Cierre:** 422 sin clasificación, con una clasificación inválida o con `incidente` sin observación; 403 si cierra un residente; 200 con `sin_novedad`.
- **Worker y scheduler:** los jobs tardan de 4 a 23 ms y no hay 5xx; `escalar-alertas`, `camaras-sin-senal` y `reencolar-pendientes` corren sin fallos.

## Hallazgos

| # | Severidad | Hallazgo | Dónde | Sugerencia |
|---|---|---|---|---|
| 1 | Baja (alta si pasa en producción) | Con `APP_DEBUG=true`, el JSON de los 403 y 429 trae `exception`, `file`, `line` y `trace`, unos 14 kB con rutas internas. En local da igual. | Panel de Render; `render.yaml` | Confirmar que Render tiene `APP_DEBUG=false` (y `APP_ENV=production`, que además bloquea `nexora:demo-reset`). |
| 2 | Baja | `migrate:fresh --seed` no limpia MongoDB. Los eventos y las alertas viejas quedan apuntando a zonas y registros de PostgreSQL que ya no existen. | Monitoreo y Alertas (colecciones) | Para la demo y las pruebas manuales ya está `php artisan nexora:demo-reset` (KAN-43). Si te sirve, úsalo en las guías de tu parte. |
| 3 | Baja | La colección Postman no cubre `GET /alertas`, `POST /alertas/{id}/atender`, `POST /alertas/{id}/cerrar` (el 422 sin clasificar) ni `POST /telegram/webhook`. | `postman/` (compartido) | Agregar una carpeta "Alertas" con el 200 de atender, el 422 y el 200 de cerrar, y el 401 del webhook con un secreto equivocado. |
| 4 | Baja | Tras una ráfaga que pasa el límite, el agente queda frenado un minuto con 429. Es lo que dice el contrato. | `docs/CONTRATO_API_AGENTE.md` | Solo dejar escrito en el README del agente que hay que esperar 60 s entre corridas de k6 y la demo. |
| 5 | Baja | Tu "Cola de alertas" no tiene vista propia en Alertas. Hoy vive en Analítica (`panel.analitica.cola-alertas`) y llama a tu `AlertaService`. | `Modules/Analitica` / `Modules/Alertas` | Decidir si se queda así (funciona y tiene tests) o si la mueves a Alertas. |
| 6 | Pendiente de demo | El aviso al residente "Llegó tu visita" por Telegram (paso 6 del §8 del plan) no existe. Porteria ya emite `IngresoRegistrado`, pero nadie lo escucha. | `Modules/Alertas/Application/Telegram` | Agregar un listener de `IngresoRegistrado` que avise al residente de la unidad si tiene `telegram_chat_id`. Si no alcanza, el paso queda fuera del guion (`docs/DEMO.md`). |

## Lo que arreglé de mi lado en esta auditoría

- `POST /api/v1/login` ya no le da token al agente: solo entra por `/agente/login`, que es lo que usa `agente/nexora_agente/cliente_api.py`.
- Las excepciones de negocio (credenciales inválidas y reglas de portería) ya no se escriben como `ERROR` en `laravel.log`.
- El logo del panel lleva a `panel.inicio`. Antes le daba 403 al residente.
- El login tiene un límite de intentos por cuenta y por IP, y los tokens de personas vencen a las 12 h. El del agente no vence.
- Las zonas del `ZonasSeeder` de Monitoreo se vigilan las 24 h para que la demo funcione a cualquier hora. Es el único cambio en tu módulo: revísalo en el PR.

---

# Auditoría final · 10 de noviembre de 2026

Salen de `docs/AUDITORIA_FINAL.md` (rama `chore/KAN-43-auditoria-final`). No toqué nada de esto. **Ninguno es crítico**; el primero es el único que incumple algo que el plan prometía.

Lo que revisé de tu parte y está bien: `/api/v1/alertas` responde 401 sin token y 403 a residente, consejo y agente (index, atender y cerrar); cerrar sin clasificación da 422 y cerrar dos veces 409; con la sesión del panel exige CSRF (419 sin token); el webhook responde 404 sin secreto o con uno malo y compara con `hash_equals`; la ingesta tiene `throttle:agente`. En Render (consultas de solo lectura a la URL pública): `/up` 200, errores JSON sin traza, cookies `secure` y `httponly`. `render.yaml`, `docker/produccion` y `docs/DESPLIEGUE.md` coinciden entre sí (Virginia, `rediss://`, `APP_TIMEZONE`, colas en `database`). No hay secretos ni archivos prohibidos en el historial de git.

| # | Gravedad | Hallazgo | Dónde | Propuesta |
|---|---|---|---|---|
| 7 | Importante | Sigue sin existir el aviso "Llegó tu visita" (HU17, paso 6 del §8 del plan). `IngresoRegistrado` se emite y nadie lo escucha. Es el #6 de arriba, aún abierto. | `Modules/Alertas/Application/Telegram`, `AlertasServiceProvider` | Listener en cola `telegram` que busque los residentes de `registro->unidad_id` con `telegram_chat_id` y les mande un texto sin datos del visitante más allá del nombre. Si no alcanza hoy, no se promete en la demo (`docs/DEMO.md` ya lo dice). |
| 8 | Importante (demo) | `--simular` genera de 1 a 3 personas al azar. La regla solo explica el evento si el ingreso tiene `cantidad_personas >=` la del evento, así que el paso "evento explicado" falla al azar con un ingreso de 1 persona. | `agente/nexora_agente/simulador.py` | Opción `--cantidad` (o fijar 1 en el simulador). Mientras tanto el guion pide registrar el ingreso con 3 personas. |
| 9 | Importante (demo) | El mismo bot no puede tener el webhook de Render y a la vez `nexora:telegram-escuchar` en local (409). Hoy `backend/.env` local no tiene `TELEGRAM_BOT_TOKEN`. | Operación del bot | Decidir antes de la sustentación: Telegram se muestra contra Render, o se crea un segundo bot para local. No correr `--sin-webhook` con el bot de producción. |
| 10 | Menor | `GET /api/user` (plantilla de Laravel) devuelve el modelo `User` crudo, fuera de `/v1` y sin Resource: incluye `telefono`, `telegram_chat_id` y `email_verified_at` del propio usuario. No expone datos de otros. | `backend/routes/api.php` (compartido) | Borrar la ruta: `GET /api/v1/yo` ya cubre el caso con `UsuarioResource`. Si me dices, lo hago yo. |
| 11 | Menor | La colección Postman sigue sin carpeta de alertas (#3 de arriba). El 422 de "cerrar sin clasificar" solo se puede mostrar desde el panel. | `postman/` | Carpeta "Alertas": atender 200, cerrar 422, cerrar 200, webhook 404. |
| 12 | Menor | Comentario viejo: dice que las alertas van "hoy con token Sanctum; con la sesión del panel cuando Identidad active statefulApi". Ya está activo. `Routes/web.php` de Alertas está vacío y el menú tiene una entrada `panel.alertas.index` que no existe (no se muestra). | `Modules/Alertas/Routes/api.php`, `Routes/web.php`; `resources/views/layouts/partials/menu.blade.php` (mío) | Actualizar el comentario. La entrada del menú la quito yo cuando decidas si la cola se queda en Analítica (#5). |
| 13 | Menor | `trustProxies(at: '*')`: fuera de Render cualquiera puede fijar `X-Forwarded-For` y saltarse el límite por IP del login. El límite por cuenta sigue aplicando. | `backend/bootstrap/app.php` (compartido) | Dejarlo así para la entrega; anotarlo en el documento de arquitectura como deuda. |
| 14 | Menor | El agente guarda `cola-cam<N>.db` en `agente/` y `agente/.env` en mi equipo apunta a Render. Correr el agente "para probar" en local manda eventos a producción. | `agente/README.md` | Una línea en el README: revisar `API_URL` antes de cada corrida. Ya está en `docs/DEMO.md`. |
