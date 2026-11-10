# Auditoría final · 10 de noviembre de 2026

Rama `chore/KAN-43-auditoria-final`, sobre `main` en `6317d43`. Revisión de solo lectura del código y los documentos, más el guion de `docs/DEMO.md` probado paso a paso en local.

**Gravedad:** CRÍTICO = rompe la demo o es un hueco de seguridad. IMPORTANTE = incumple un requisito de una materia. MENOR = el resto.

**Resultado:** 2 críticos (los dos del guion de la demo, arreglados), 11 importantes (4 arreglados, 7 pendientes) y 12 menores (pendientes). No se encontró ningún hueco de seguridad crítico.

## CHECK

| Paso | Antes | Después |
|---|---|---|
| `migrate:fresh --seed` y `nexora:mongo-instalar` | OK | OK |
| `php artisan test` | 329 tests | 331 tests |
| `vendor/bin/pint --test` | OK (235 archivos) | OK |
| `pytest -q` del agente | 57 | 57 |
| newman con `postman/` | 30 requests, 38 aserciones (con `--env-var clave_semilla=password`) | igual |

## Qué se probó de verdad

En local, con `nexora:demo-reset --force` antes de cada tanda:

- **Agente `--simular`** contra el backend local: 4 eventos recibidos con 202, cola local vacía y **una sola alerta** con 4 eventos.
- **Atender y cerrar** por API con el vigilante: atender 200; cerrar sin clasificación **422**; cerrar con `falsa_alarma` 200; cerrar otra vez 409.
- **Roles en alertas:** residente, consejo y agente reciben 403 en listar, atender y cerrar; sin token 401.
- **Explicado frente a alerta:** con un ingreso registrado desde el panel hacia la zona, el evento no crea alerta; sin ingreso, sí.
- **Supresión:** dos eventos seguidos en otra zona dan una alerta con `num_eventos: 2`.
- **Residente con otra unidad:** 403 en ver, modificar y anular; 403 al crear para una unidad ajena; su listado solo trae sus unidades.
- **Panel con sesión:** el vigilante entra a dashboard, cola de alertas, ingresos y autorizaciones vigentes; recibe 403 en zonas, usuarios y mis visitas. Atender desde el panel sin token CSRF da 419 y con token 200.
- **Webhook de Telegram:** 404 sin secreto y con un secreto equivocado.
- **Producción (solo lectura a la URL pública):** `/up` 200, errores JSON sin traza, cookies `secure` y `httponly`, alertas sin token 401, webhook sin secreto 404.
- **Historial de git:** ningún `.env`, base, video, modelo ni imagen se versionó nunca; ninguna URL con credenciales ni token de bot; ningún commit con firmas automáticas.

No se probó: Telegram de punta a punta (el `.env` local no tiene token del bot), el agente con video y YOLO, k6, ni el panel de Render con sesión.

## Hallazgos

### Críticos

| # | Hallazgo | Archivo | Estado |
|---|---|---|---|
| C1 | El paso 5 del guion (dos pasadas → una alerta) no se podía hacer en el parqueadero: el ingreso del paso 4 explica todo lo que pase en esa zona durante 10 minutos, así que no salía ninguna alerta. | `docs/DEMO.md` | **Arreglado:** el paso 5 usa la cámara 7 (otra zona) y el guion explica por qué. |
| C2 | El guion es local, pero `agente/.env` en el equipo de Cami apunta a Render. El agente dice "recibido", los eventos llegan a producción y el panel local queda vacío. | `docs/DEMO.md`, `agente/.env` (no versionado) | **Arreglado en el guion:** queda en la lista de antes de empezar y en "Si algo falla". El `.env` local lo cambia Cami antes de la demo. |

### Importantes

| # | Hallazgo | Archivo | Estado |
|---|---|---|---|
| I1 | El comando de newman del README falla entero con 401: el entorno de Postman no guarda la clave y el comando no la pasaba. La matriz lo daba por verde. | `README.md`, `docs/DEMO.md`, `docs/MATRIZ_CUMPLIMIENTO.md` | **Arreglado:** `--env-var clave_semilla=password`; 38 aserciones en verde. |
| I2 | El consejo veía el documento completo de cada visitante en *Ingresos*. Su rol es de informes (HU10, Ley 1581). | `Modules/Porteria/Resources/views/ingresos/index.blade.php`, `Domain/Models/RegistroPorteria.php` | **Arreglado:** ve solo los últimos 4 caracteres. Test `test_el_consejo_ve_el_documento_enmascarado`. |
| I3 | `GET /api/v1/autorizaciones` respondía 500 a una cuenta con rol residente sin perfil de residente asociado. | `Modules/Porteria/Application/AutorizacionService.php` | **Arreglado:** lista vacía. Test `test_residente_sin_perfil_recibe_lista_vacia_y_no_un_500`. |
| I4 | La matriz decía cosas que ya no eran ciertas: "CRUD de usuarios" (solo hay listado y activar/desactivar), "no hay etiquetas" (existen `docs` y `v0.1` a `v0.4`), 328 tests, y `APP_DEBUG` sin confirmar. | `docs/MATRIZ_CUMPLIMIENTO.md` | **Arreglado:** matriz al día, con las HU que faltan como Pendiente. |
| I5 | HU20 (KAN-24), novedades y entrega de turno: no hay código. | `Modules/Porteria` | Pendiente (Cami). Decir en la sustentación que quedó fuera del prototipo. |
| I6 | HU15 (KAN-19), gestionar cuentas y vigencias: el panel no crea ni edita cuentas, ni asocia una cuenta a un residente; no hay vigencia de cuentas. | `Modules/Identidad` | Pendiente (Cami). No es un cambio mínimo. |
| I7 | Entregables de documentos vacíos: `docs/arquitectura/`, `docs/bd2/`, `docs/ingsoft/`, `docs/backend/` y `database/mongo/` solo tienen `.gitkeep`. La matriz ya los marca Pendiente. | esas carpetas | Pendiente (ambos). Es lo que más pesa para tres de las cuatro materias. |
| I8 | HU17 (KAN-21), aviso "Llegó tu visita" por Telegram: `IngresoRegistrado` se emite y nadie lo escucha. | `Modules/Alertas` | Pendiente (Santiago, #7 en `HALLAZGOS_SANTIAGO.md`). |
| I9 | `--simular` genera de 1 a 3 personas al azar: un ingreso de 1 persona no explica un evento de 3 y el paso 4 falla al azar. | `agente/nexora_agente/simulador.py` | Pendiente (Santiago, #8). Mitigado en el guion: registrar el ingreso con 3 personas. |
| I10 | El bot no puede tener el webhook de Render y el long polling local a la vez (409), y el `.env` local no tiene token. | operación | Pendiente (Santiago, #9). Documentado en el guion. |
| I11 | Faltan la etiqueta `v1.0` y el video de la entrevista con el celador. | repo, entrega | Pendiente (ambos). |

### Menores (pendientes)

| # | Hallazgo | Archivo |
|---|---|---|
| M1 | `GET /api/user` de la plantilla devuelve el `User` crudo del propio usuario (teléfono, `telegram_chat_id`), fuera de `/v1` y sin Resource. | `backend/routes/api.php` (compartido; #10 de Santiago) |
| M2 | Postman no cubre alertas ni el webhook. | `postman/` (#11) |
| M3 | Comentario viejo sobre `statefulApi`, `Routes/web.php` de Alertas vacío y entrada `panel.alertas.index` del menú que no existe (no se muestra). | `Modules/Alertas/Routes`, `layouts/partials/menu.blade.php` (#12) |
| M4 | `trustProxies(at: '*')`: fuera de Render se puede falsear la IP del límite de login. El límite por cuenta sigue. | `backend/bootstrap/app.php` (#13) |
| M5 | El README del agente no avisa que hay que revisar `API_URL` antes de correrlo. | `agente/README.md` (#14) |
| M6 | El consejo puede buscar por documento en *Ingresos*: no lo ve, pero la búsqueda le confirma si un número existe. | `RegistroPorteriaRepository::paginar` |
| M7 | `ingresosPorDia` y `buscarParaCorrelacion` traen las filas y cuentan u ordenan en PHP. Con los datos de la demo no se nota. | `RegistroPorteriaRepository` |
| M8 | Bootstrap, los iconos y Chart.js se cargan desde un CDN: sin internet el panel se ve sin estilos. | `layouts/partials/head.blade.php`, `dashboard.blade.php` |
| M9 | En local (`APP_DEBUG=true`) los 403 y 404 en JSON traen la traza completa. En Render no. Estorba al mostrar un 403 en pantalla. | `backend/.env` local |
| M10 | `tests/Unit/ExampleTest.php` solo afirma `true`. | `backend/tests/Unit` |
| M11 | Queda el comando `inspire` de la plantilla. | `backend/routes/console.php` (compartido) |
| M12 | `docs/ESTADO_CAMI.md` es del 9 de noviembre y habla de PR ya mergeados. | `docs/ESTADO_CAMI.md` |

No se encontraron: rutas sin autorización, mass assignment (todos los modelos usan `Fillable` y los controladores `validated()`), salidas sin escapar en Blade, formularios sin CSRF, `TODO` ni `dd()` olvidados, ni datos personales en los logs.

## Los 5 riesgos de la demo en vivo

| # | Riesgo | Cómo se nota | Plan B |
|---|---|---|---|
| 1 | **El agente o el panel apuntan a backends distintos** (local frente a Render). | El agente dice "recibido" y el panel no muestra nada. | Antes de empezar, revisar `API_URL` en `agente/.env`. Si pasa en vivo: corregirlo y volver a correr el agente (10 segundos). Decidir desde ya si la demo es local o contra Render y no mezclar. |
| 2 | **Telegram no llega**: sin token en local, webhook de Render ocupando el bot, o sin red en el celular. | No aparece el mensaje ni el botón "Atender". | La alerta sale igual en *Cola de alertas* y se atiende desde ahí. Tener una captura del mensaje de Telegram de un ensayo. No tocar el webhook de producción en la sala. |
| 3 | **Un paso de correlación sale al revés**: alerta cuando debía quedar explicado (más personas que el ingreso) o nada cuando debía alertar (ingreso reciente o alerta abierta en la zona). | Paso 4 con alerta, o paso 5 sin alerta. | Ingreso con 3 personas hacia "Parqueadero sótano"; para la supresión, cámara 7. Si se enreda: `nexora:demo-reset --force` (unos 30 segundos) y repetir el paso. |
| 4 | **Sin internet en la sala, o Render dormido**. El panel carga Bootstrap y Chart.js de un CDN; Render tarda en despertar tras 15 minutos sin tráfico y las cuotas gratis de Neon y Upstash son limitadas. | Panel sin estilos, gráficos vacíos o la URL pública que no responde. | Compartir datos desde el celular. Abrir `/up` de Render 5 minutos antes. Si Render no responde, hacer toda la demo en local. Tener capturas del dashboard. |
| 5 | **Bloqueos por límites y códigos HTTP confusos**: 5 intentos de login por minuto, 60 eventos por minuto del agente (tras k6 queda un minuto en 429), newman sin la clave (todo 401) y trazas largas en los 403 de local. | 429 al entrar, el agente frenado, newman en rojo. | `php artisan cache:clear` limpia los límites. Dejar k6 para el final. Correr newman con `--env-var clave_semilla=password`. Mostrar el 403 del residente desde el panel y no con curl. |
