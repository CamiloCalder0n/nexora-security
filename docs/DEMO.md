# Demo de la sustentación · Nexora Security

Guion de unos 15 minutos que sirve para las 4 materias. Cada paso dice qué se muestra, qué debe pasar y qué materia lo aprovecha.

## Antes de empezar (10 min antes)

```bash
docker compose up -d
docker compose exec app php artisan nexora:demo-reset --force   # PostgreSQL, MongoDB y caché desde cero
docker compose exec app php artisan nexora:telegram-codigo vigilante@nexora.test
docker compose exec app php artisan nexora:telegram-codigo residente@nexora.test
docker compose exec app php artisan nexora:telegram-escuchar    # en otra terminal, si no hay webhook
```

- En el celular, enviar al bot `/start <código>` con la cuenta del vigilante y con la del residente.
- **El agente va contra el mismo backend que el panel.** Revisar `API_URL` en `agente/.env`: para la demo local debe ser `http://localhost:8000/api/v1` (con `AGENTE_PASSWORD=password`). Si quedó la URL de Render, los eventos llegan a producción y en el panel local no pasa nada.
- **Telegram en local:** hace falta `TELEGRAM_BOT_TOKEN` en `backend/.env`. Si ese bot tiene el webhook de Render registrado, `nexora:telegram-escuchar` responde 409 y no escucha. O se hace la demo de Telegram contra Render, o se usa un segundo bot solo para local. No quitar el webhook de producción antes de la sustentación.
- `nexora:demo-reset` se niega a correr con `APP_ENV=production`: en Render no se usa.
- Las zonas de demo se vigilan las 24 horas, así que la demo funciona a cualquier hora.
- Pestañas abiertas: panel como **vigilante** (http://localhost:8000), panel como **consejo** en ventana privada, Telegram y una terminal en `agente/`.
- Usuarios (contraseña `password`): `admin@`, `vigilante@`, `residente@`, `consejo@` y `agente@nexora.test`.
- El login permite 5 intentos por minuto por cuenta y 20 por IP. Si alguien se equivoca de clave varias veces, `php artisan cache:clear`.

## Guion

| # | Qué se hace | Qué debe pasar | Materias |
|---|---|---|---|
| 1 | La **administradora** abre *Zonas* y muestra el "Parqueadero sótano": franja, prioridad, ventana de correlación y cámaras. | La zona está activa ahora. | Backend (CRUD, Form Requests), Arq (contexto Monitoreo) |
| 2 | Arrancar el agente con el video: `python -m nexora_agente --fuente videos/parqueadero.mp4 --camara-id 5` (CAM-05; o `--simular --camara-id 5 --cada 15`). Cuando llegue la alerta, **detener el agente con Ctrl+C**: si sigue corriendo, al cerrar la alerta en el paso 3 abre otra. | El agente detecta a la persona sin identificarla, la API responde **202** y en segundos llega la **alerta a Telegram**. | Arq (agente de borde, cola en Redis, eventos de dominio), BD2 (evento en MongoDB) |
| 3 | El vigilante toca **Atender** en Telegram. En el panel, *Cola de alertas*, intenta cerrarla sin clasificar. | Sin clasificación responde **422**; al clasificarla como falsa alarma se cierra y queda en la bitácora. | Backend (códigos HTTP), BD2 (`$jsonSchema`, bitácora con hash encadenado) |
| 4 | El vigilante registra en *Registrar ingreso* un domiciliario con zona "Parqueadero sótano" y **3 personas**, y se vuelve a correr el agente con la cámara 5. Con `--simular` cada evento trae de 1 a 3 personas al azar: un ingreso de 1 persona no explica un evento de 3. | El evento queda **explicado**: no hay alerta. | Arq (puerto `ConsultaRegistrosPorteria`), Backend (Porteria) |
| 5 | Dos pasadas seguidas sin registro **en otra zona**: `--simular --camara-id 7 --cada 5` (CAM-07, Perímetro norte) y Ctrl+C tras el segundo evento. En el parqueadero no sirve: el ingreso del paso 4 explica todo lo que pase allí durante 10 minutos. | **Una sola alerta** con 2 eventos (supresión por ventana de silencio). | Arq (resiliencia), BD2 (patrón Subset `evidencias_recientes`) |
| 6 | Registrar la visita de una autorización del residente y abrir *Mis visitas* con su cuenta. | La visita aparece en su pantalla. **Pendiente:** el aviso "Llegó tu visita" por Telegram aún no está hecho (nadie escucha `IngresoRegistrado`); no prometerlo en la demo. | Backend, Ing (HU del residente) |
| 7 | El residente entra al panel y trata de ver una autorización de otra unidad (o `GET /api/v1/autorizaciones/{id}` con su token). | **403**. Solo ve lo de sus unidades. | Backend (policies, roles) |
| 8 | El **consejo** abre el dashboard. | Ingresos por día (PostgreSQL) y alertas por zona con % de falsas alarmas (agregación en MongoDB). | BD2 (agregaciones), Backend (gráficos) |
| 9 | (Arq) Pico de carga con k6 (`pruebas/k6/README.md`). | **202** + cola mientras hay cupo y **429** con `Retry-After` al pasarse; el worker vacía la cola. | Arq (nivelación de carga) |
| 9b | (Backend) Colección Postman con newman: `npx newman run postman/Nexora.postman_collection.json -e postman/Nexora.postman_environment.json --env-var clave_semilla=password`. Sin `--env-var` todo responde 401: el entorno no guarda la clave. | 30 requests y 38 aserciones en verde. | Backend |
| 10 | (Ing) Mostrar Jira, un PR revisado, el CI y la matriz `docs/MATRIZ_CUMPLIMIENTO.md`. | Trazabilidad HU → rama → commit → PR. | Ing |

## Si algo falla

| Síntoma | Qué hacer |
|---|---|
| No llega nada a Telegram | Revisar `TELEGRAM_BOT_TOKEN` en `backend/.env`, que el `/start` se hizo y que el `worker` está arriba (`docker compose ps`). La alerta igual aparece en *Cola de alertas*. |
| El agente responde 401 | Revisar `AGENTE_EMAIL`/`AGENTE_PASSWORD` del agente; si hubo muchos intentos, `php artisan cache:clear` (429). |
| El evento queda "duplicado" o no crea alerta | Ya hay una alerta abierta en la zona (supresión) o un ingreso en esa zona en los últimos 10 minutos (queda explicado). Usar otra cámara, cerrar la alerta o volver a correr `nexora:demo-reset --force`. |
| Sale una alerta en el paso 4 | El evento traía más personas que el ingreso registrado, o el ingreso quedó con otra zona. Registrar otro ingreso con 3 personas hacia "Parqueadero sótano". |
| El panel no muestra nada y el agente dice "recibido" | El agente está enviando a Render: corregir `API_URL` en `agente/.env`. |
| El agente no tiene red | Sigue detectando y guarda en su cola local; al volver la red, reenvía. Sirve como demo de resiliencia. |
| Plan B sin cámara ni video | `python -m nexora_agente --simular --camara-id <id> --cada 10`. |
| Plan C sin agente | Colección Postman: carpeta del agente (`postman/`). |
