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
