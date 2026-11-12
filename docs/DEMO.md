# Demo de la sustentación · Nexora Security

Guion de unos 15 minutos que sirve para las 4 materias. Se sigue **en este orden**; cada paso dice qué se hace, qué debe pasar y qué materia lo aprovecha.

## Antes de empezar (10 min antes)

```bash
docker compose up -d
docker compose exec app php artisan nexora:demo-preparar        # cierra alertas abiertas y deja las cámaras activas
docker compose exec app php artisan nexora:telegram-codigo vigilante@nexora.test
docker compose exec app php artisan nexora:telegram-escuchar    # en otra terminal, si no hay webhook
```

- En el celular, enviar al bot `/start <código>` con la cuenta del vigilante.
- **El agente va contra el mismo backend que el panel.** Revisar `API_URL` en `agente/.env`: para la demo local debe ser `http://localhost:8000/api/v1` (con `AGENTE_PASSWORD=password`). Si quedó la URL de Render, los eventos llegan a producción y en el panel local no pasa nada.
- **Telegram en local:** hace falta `TELEGRAM_BOT_TOKEN` en `backend/.env`. Si ese bot tiene el webhook de Render registrado, `nexora:telegram-escuchar` responde 409 y no escucha. O se hace la demo de Telegram contra Render, o se usa un segundo bot solo para local. No quitar el webhook de producción antes de la sustentación.
- Las zonas de demo se vigilan las 24 horas, así que la demo funciona a cualquier hora.
- Pestañas abiertas: panel como **vigilante** con *Monitoreo → Eventos en vivo* (http://localhost:8000/panel/monitoreo/eventos), otra con *Cola de alertas*, panel como **consejo** en ventana privada, Telegram y una terminal en `agente/`.
- Usuarios (contraseña `password`): `admin@`, `vigilante@`, `residente@`, `consejo@` y `agente@nexora.test`.
- El login permite 5 intentos por minuto por cuenta y 20 por IP. Si alguien se equivoca de clave varias veces, `php artisan cache:clear`.

### `nexora:demo-preparar`

Deja el sistema listo **sin borrar datos**:

- Cierra como `sin_novedad` todas las alertas abiertas o atendidas. Cada cierre pasa por `AlertaService` y queda en la bitácora con su hash encadenado; al final corre `nexora:verificar-bitacora`.
- Pone todas las cámaras en `activa` con `ultima_conexion = now`.
- Al empezar muestra a qué hosts de PostgreSQL, MongoDB y Redis se conecta, para ver si va contra local o contra la nube.

**Contra la nube (Render, Atlas, Upstash…):** se le pasan las URLs por variables, sin tocar `backend/.env`. Con `APP_ENV=production` pide confirmación; `--force` la salta.

```bash
docker compose exec \
  -e DB_URL='postgresql://usuario:clave@host:5432/nexora' \
  -e MONGODB_URI='mongodb+srv://usuario:clave@cluster/nexora' \
  -e REDIS_URL='rediss://default:clave@host:6379' \
  -e APP_ENV=production \
  app php artisan nexora:demo-preparar            # agregar --force para no confirmar
```

Las URLs reales están en el panel de Render; no se copian a ningún archivo del repo.

`nexora:demo-reset --force` sigue existiendo para empezar de cero (borra PostgreSQL, MongoDB y la caché), pero **no** corre con `APP_ENV=production`.

## Guion

| # | Qué se hace | Qué debe pasar | Materias |
|---|---|---|---|
| 1 | **Preparar:** `nexora:demo-preparar` (ver arriba). Mostrar *Cola de alertas* vacía. | "N alertas cerradas", todas las cámaras activas y la bitácora íntegra. | BD2 (bitácora con hash), Arq (comando de operación) |
| 2 | **Registrar el ingreso:** el vigilante abre *Registrar ingreso* y registra un domiciliario hacia la zona **Acceso peatonal principal** con **3 personas**. | El ingreso aparece en *Ingresos*. Desde ahora y por 10 minutos (ventana de correlación de la zona) esa zona tiene quién explique hasta 3 personas. | Backend (Portería, Form Requests), BD2 (PostgreSQL) |
| 3 | **Video:** en `agente/`, `python -m nexora_agente --fuente videos/edificio.mp4 --camara-id 1` (CAM-01 Peatonal principal). | El agente detecta personas sin identificarlas y la API responde **202**. | Arq (agente de borde, cola en Redis), BD2 (evento en MongoDB) |
| 4 | **Evento explicado:** mostrar *Eventos en vivo*. | La fila nueva aparece resaltada sin recargar, con badge **explicado**: el ingreso del paso 2 la justifica y **no** hay alerta. Detener el agente con Ctrl+C. | Arq (puerto `ConsultaRegistrosPorteria`, eventos de dominio), Backend (panel) |
| 5 | **Esperar 10 min o usar otra cámara:** lo rápido es `python -m nexora_agente --fuente videos/edificio.mp4 --camara-id 2` (CAM-02, Acceso peatonal posterior, sin ingreso registrado). Si se prefiere la misma cámara, esperar a que venzan los 10 minutos del ingreso. | En *Eventos en vivo* la fila sale con badge **alerta**; las siguientes de esa zona salen **suprimido** (se agrupan en la misma alerta). | Arq (motor de reglas), BD2 (patrón Subset `evidencias_recientes`) |
| 6 | **Alerta en Telegram:** mostrar el celular. Detener el agente con Ctrl+C (si sigue, al cerrar la alerta abre otra). | Llega el mensaje con prioridad, zona y cámara, motivo, hora y el botón **Atender**. | Arq (cola + job de notificación) |
| 7 | **Atender:** el vigilante toca **Atender** en Telegram. | En *Cola de alertas* la alerta pasa a **atendida** y queda en la bitácora. | Backend, BD2 (bitácora) |
| 8 | **Cerrar:** en *Cola de alertas*, abrir *Cerrar* y enviar **sin clasificación**; luego elegir "Falsa alarma" y cerrar. | Primero **422** ("Para cerrar la alerta hay que clasificarla…", visible en el formulario y en la pestaña Red de las herramientas del navegador); luego se cierra y queda en la bitácora. | Backend (códigos HTTP, validación de dominio), BD2 (`$jsonSchema`) |
| 9 | **Dashboard:** el **consejo** abre el dashboard. | Ingresos por día (PostgreSQL) y alertas por zona con % de falsas alarmas (agregación en MongoDB), ya con la alerta recién cerrada. | BD2 (agregaciones), Backend (gráficos) |
| 10 | **Postman:** en la colección, *1. Autenticación → Login admin* (guarda el token en el entorno) y luego *5. Errores esperados → 422 · zona sin datos*. Para todo de una: `npx newman run postman/Nexora.postman_collection.json -e postman/Nexora.postman_environment.json --env-var clave_semilla=password`. | Login **200** con el token Sanctum; el request inválido responde **422** con los errores por campo. Con newman, todo en verde. Sin `clave_semilla` todo responde 401: el entorno no guarda la clave. | Backend (API REST, Sanctum) |
| 11 | **Historial (RF005):** el **admin** abre *Historial de eventos*, filtra por la zona **Acceso peatonal principal** y resultado **Generó alerta**, cambia el orden a "más antiguos" y toca **Exportar CSV**. | Salen los eventos de los pasos 3 a 5 con su resultado; el CSV abre en Excel con tildes y sin la ruta de la evidencia. | Backend (filtros con Form Request, CSV), BD2 (consulta en MongoDB) |
| 12 | **Evidencia con motivo (RF007):** en una fila con evidencia, **Ver**; enviar primero un motivo corto y luego uno real ("Revisión de la alerta del paso 5"). | Con motivo corto vuelve al formulario con el error; con motivo válido muestra la evidencia. El **consejo** no ve el botón **Ver** y si entra por URL recibe **403**. | Backend (roles, validación), Ley 1581 |
| 13 | **Auditoría (RF007):** el **vigilante** intenta abrir `/panel/analitica/informe-mensual` (o `/panel/identidad/auditoria`). Luego el **admin** abre *Auditoría de accesos*, busca "vigilante" y exporta el CSV. | El vigilante recibe **403**. En la auditoría aparecen la consulta de evidencia del paso 12 con su motivo y el acceso denegado del vigilante, con usuario, rol, recurso, fecha e IP. | Backend (middleware de roles), Arq (trazabilidad) |
| 14 | **Informe mensual (RF008):** el **consejo** abre *Informe mensual* del mes actual y toca **Exportar CSV**. | Ingresos del mes, alertas por estado, clasificación y zona, % de falsas alarmas, escaladas y tiempo de atención, ya con la alerta cerrada en el paso 8. | BD2 (agregaciones `$facet`), Backend |

### Extras si sobra tiempo

| Qué se hace | Qué debe pasar | Materias |
|---|---|---|
| El **residente** entra al panel: no ve *Historial*, *Informe mensual* ni *Auditoría* en el menú y por URL recibe **403**. | Menú y rutas por rol. | Backend (roles) |
| El residente trata de ver una autorización de otra unidad (o `GET /api/v1/autorizaciones/{id}` con su token). | **403**. Solo ve lo de sus unidades. | Backend (policies, roles) |
| Pico de carga con k6 (`pruebas/k6/README.md`). | **202** + cola mientras hay cupo y **429** con `Retry-After` al pasarse; el worker vacía la cola. | Arq (nivelación de carga) |
| Jira, un PR revisado, el CI y `docs/MATRIZ_CUMPLIMIENTO.md`. | Trazabilidad HU → rama → commit → PR. | Ing |

## Plan B

| Si falla… | Hacer esto |
|---|---|
| **El video** (no abre, no hay modelo, el PC va lento) | `python -m nexora_agente --simular --camara-id 1 --cada 15`. Ojo: cada evento simulado trae de 1 a 3 personas al azar; con el ingreso de 3 personas siempre queda explicado. |
| **El video detecta más de 3 personas** y sale alerta en el paso 4 | Mostrarlo como lo que es: el ingreso no explica tantas personas. Registrar otro ingreso con más personas hacia la misma zona y repetir, o pasar a `--simular`. |
| **El agente entero** (Python, red del PC) | Postman, carpeta *4. Agente de borde → Enviar evento* con `camara_id` 1 y luego 2. El evento aparece igual en *Eventos en vivo*. |
| **Telegram** (no llega el mensaje, 409 del webhook) | La alerta aparece igual en *Cola de alertas*: atender y cerrar desde el panel. Revisar `TELEGRAM_BOT_TOKEN`, que el `/start` se hizo y que el `worker` está arriba (`docker compose ps`). |
| **No aparece nada en Eventos en vivo** y el agente dice "recibido" | El agente está enviando a Render: corregir `API_URL` en `agente/.env`. Si el evento queda **pendiente**, el `worker` está caído: `docker compose up -d worker`. |
| **El evento de la otra cámara sale explicado o suprimido** | Ya había un ingreso en esa zona en los últimos 10 minutos o una alerta abierta ahí. Usar otra cámara (`--camara-id 7`, Perímetro norte) o volver a correr `nexora:demo-preparar`. |
| **El agente responde 401 o 429** | Revisar `AGENTE_EMAIL`/`AGENTE_PASSWORD`; si hubo muchos intentos, `php artisan cache:clear`. |
| **El agente no tiene red** | Sigue detectando y guarda en su cola local; al volver la red, reenvía. Sirve como demo de resiliencia. |
| **Docker local no arranca** | Hacer la demo contra Render: preparar con `nexora:demo-preparar` pasándole las URLs de la nube (ver arriba), `API_URL` del agente apuntando a Render y el panel en la URL pública. |
| **Todo lo anterior** | Mostrar un video grabado de la demo completa (grabarlo antes del día) y newman en verde como evidencia del API. |
