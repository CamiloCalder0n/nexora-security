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
- `nexora:demo-reset` se niega a correr con `APP_ENV=production`: en Render no se usa.
- Las zonas de demo se vigilan las 24 horas, así que la demo funciona a cualquier hora.
- Pestañas abiertas: panel como **vigilante** (http://localhost:8000), panel como **consejo** en ventana privada, Telegram y una terminal en `agente/`.
- Usuarios (contraseña `password`): `admin@`, `vigilante@`, `residente@`, `consejo@` y `agente@nexora.test`.
- El login permite 5 intentos por minuto por cuenta y 20 por IP. Si alguien se equivoca de clave varias veces, `php artisan cache:clear`.

## Guion

| # | Qué se hace | Qué debe pasar | Materias |
|---|---|---|---|
| 1 | La **administradora** abre *Zonas* y muestra el "Parqueadero sótano": franja, prioridad, ventana de correlación y cámaras. | La zona está activa ahora. | Backend (CRUD, Form Requests), Arq (contexto Monitoreo) |
| 2 | Arrancar el agente con el video: `python -m nexora_agente --fuente videos/parqueadero.mp4 --camara-id <id de CAM-05>` (o `--simular --camara-id <id> --cada 15`). | El agente detecta a la persona sin identificarla, la API responde **202** y en segundos llega la **alerta a Telegram**. | Arq (agente de borde, cola en Redis, eventos de dominio), BD2 (evento en MongoDB) |
| 3 | El vigilante toca **Atender** en Telegram. En el panel, *Cola de alertas*, intenta cerrarla sin clasificar. | Sin clasificación responde **422**; al clasificarla como falsa alarma se cierra y queda en la bitácora. | Backend (códigos HTTP), BD2 (`$jsonSchema`, bitácora con hash encadenado) |
| 4 | El vigilante registra en *Ingresos* un domiciliario hacia el parqueadero y la persona vuelve a pasar. | El evento queda **explicado**: no hay alerta. | Arq (puerto `ConsultaRegistrosPorteria`), Backend (Porteria) |
| 5 | Dos pasadas seguidas sin registro. | **Una sola alerta** con 2 eventos (supresión por ventana de silencio). | Arq (resiliencia), BD2 (patrón Subset `evidencias_recientes`) |
| 6 | Registrar la visita de una autorización del residente y abrir *Mis visitas* con su cuenta. | La visita aparece en su pantalla. **Pendiente:** el aviso "Llegó tu visita" por Telegram aún no está hecho (nadie escucha `IngresoRegistrado`); no prometerlo en la demo. | Backend, Ing (HU del residente) |
| 7 | El residente entra al panel y trata de ver una autorización de otra unidad (o `GET /api/v1/autorizaciones/{id}` con su token). | **403**. Solo ve lo de sus unidades. | Backend (policies, roles) |
| 8 | El **consejo** abre el dashboard. | Ingresos por día (PostgreSQL) y alertas por zona con % de falsas alarmas (agregación en MongoDB). | BD2 (agregaciones), Backend (gráficos) |
| 9 | (Arq) Pico de carga con k6 (`pruebas/k6/README.md`). | **202** + cola mientras hay cupo y **429** con `Retry-After` al pasarse; el worker vacía la cola. | Arq (nivelación de carga) |
| 10 | (Ing) Mostrar Jira, un PR revisado, el CI y la matriz `docs/MATRIZ_CUMPLIMIENTO.md`. | Trazabilidad HU → rama → commit → PR. | Ing |

## Si algo falla

| Síntoma | Qué hacer |
|---|---|
| No llega nada a Telegram | Revisar `TELEGRAM_BOT_TOKEN` en `backend/.env`, que el `/start` se hizo y que el `worker` está arriba (`docker compose ps`). La alerta igual aparece en *Cola de alertas*. |
| El agente responde 401 | Revisar `AGENTE_EMAIL`/`AGENTE_PASSWORD` del agente; si hubo muchos intentos, `php artisan cache:clear` (429). |
| El evento queda "duplicado" o no crea alerta | Ya hay una alerta abierta en la zona (supresión) o un ingreso reciente. Cerrar la alerta o volver a correr `nexora:demo-reset --force`. |
| El agente no tiene red | Sigue detectando y guarda en su cola local; al volver la red, reenvía. Sirve como demo de resiliencia. |
| Plan B sin cámara ni video | `python -m nexora_agente --simular --camara-id <id> --cada 10`. |
| Plan C sin agente | Colección Postman: carpeta del agente (`postman/`). |
