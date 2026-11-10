# Nexora Security

Sistema de vigilancia para un conjunto residencial. Un agente de borde detecta presencia en las cámaras **sin identificar personas**, el backend la cruza con los registros de portería y, si nadie la explica, alerta al vigilante por Telegram en segundos.

Un solo sistema para 4 materias: Backend, Bases de Datos 2 (NoSQL), Arquitectura de Software e Ingeniería de Software. Qué cumple cada una y dónde: [`docs/MATRIZ_CUMPLIMIENTO.md`](docs/MATRIZ_CUMPLIMIENTO.md).

## Plataformas

1. **Panel web** (Laravel 13 + Blade + Bootstrap): administradora, vigilante, consejo y residentes.
2. **Bot de Telegram:** alertas al vigilante con botón *Atender*.
3. **Agente de borde con IA** (Python + OpenCV + YOLOv8): analiza el video dentro del conjunto y envía solo los eventos a la API. Ver [`agente/README.md`](agente/README.md).

## Cómo funciona

```
cámara → agente (YOLOv8, sin identificar) → POST /api/v1/agente/eventos (202 + cola en Redis)
       → Monitoreo guarda el evento en MongoDB → CorrelacionJob
       → ¿hay ingreso en portería en la ventana de la zona?
            sí → evento explicado
            no → Alerta (MongoDB) → Telegram al vigilante → atender → cerrar con clasificación
```

Monolito modular con 5 contextos en `backend/app/Modules/`: **Identidad**, **Porteria**, **Monitoreo**, **Alertas** y **Analitica**. Cada uno con capas `Domain`, `Application`, `Infrastructure` y `Http`; se hablan por Services, puertos o eventos de dominio.

| Base | Qué guarda |
|---|---|
| PostgreSQL 16 | usuarios, residentes, unidades, autorizaciones, registros de portería, zonas y cámaras |
| MongoDB 8 | eventos (TTL de 365 días), alertas (patrón Subset) y bitácora con hash encadenado |
| Redis 7 | colas, caché y límites de intentos |

## Levantar en local

Requisitos: Docker con Compose.

```bash
cp backend/.env.example backend/.env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan nexora:mongo-instalar   # colecciones, validación e índices de MongoDB
# http://localhost:8000
```

`docker compose` levanta `app` (web en el puerto 8000), `worker` (colas), `scheduler`, `pgsql`, `mongo` y `redis`.

Usuarios de prueba (solo local, contraseña `password`):

| Correo | Rol |
|---|---|
| `admin@nexora.test` | Administradora: zonas, residentes, autorizaciones y usuarios |
| `vigilante@nexora.test` | Vigilante: ingresos y salidas, cola de alertas |
| `residente@nexora.test` | Residente: mis autorizaciones y mis visitas |
| `consejo@nexora.test` | Consejo: dashboard |
| `agente@nexora.test` | Agente de borde (solo API) |

Para dejar todo como recién instalado (PostgreSQL con los seeders, MongoDB vacío con sus índices y caché vacía):

```bash
docker compose exec app php artisan nexora:demo-reset   # se niega con APP_ENV=production
```

## Comandos

| Comando | Para qué |
|---|---|
| `php artisan nexora:mongo-instalar` | Crea o actualiza validadores e índices de MongoDB |
| `php artisan nexora:demo-reset` | Reinicia los datos de demo (nunca en producción) |
| `php artisan nexora:telegram-codigo <email>` | Código para vincular una cuenta con el bot (`/start <código>`) |
| `php artisan nexora:telegram-escuchar` | Recibe los mensajes del bot por long polling (local, sin webhook) |
| `php artisan nexora:verificar-bitacora` | Comprueba la cadena de hashes de la bitácora |
| `php artisan nexora:escalar-alertas`, `nexora:camaras-sin-senal`, `nexora:reencolar-pendientes` | Tareas del scheduler |

## Pruebas

```bash
docker compose exec app php artisan test        # PHPUnit
docker compose exec app vendor/bin/pint --test  # estilo
cd agente && pytest -q                           # agente (con su venv, ver agente/README.md)
npx newman run postman/Nexora.postman_collection.json -e postman/Nexora.postman_environment.json
```

Prueba de carga con k6: [`pruebas/k6/README.md`](pruebas/k6/README.md).

## API

API REST v1 en `/api/v1` con tokens Sanctum. Login: `POST /api/v1/login` (personas; el token vence a las 12 horas) y `POST /api/v1/agente/login` (agente; el token no vence). Ambos permiten 5 intentos por minuto por cuenta y 20 por IP.

- Contrato con el agente: [`docs/CONTRATO_API_AGENTE.md`](docs/CONTRATO_API_AGENTE.md).
- Colección Postman con entorno y pruebas de 200 a 429: [`postman/`](postman/).

## Documentación

| Documento | Contenido |
|---|---|
| [`PLAN_MAESTRO_NEXORA.md`](PLAN_MAESTRO_NEXORA.md) | Plan completo: decisiones, arquitectura, HU, cronograma |
| [`docs/DEMO.md`](docs/DEMO.md) | Guion de la sustentación |
| [`docs/MATRIZ_CUMPLIMIENTO.md`](docs/MATRIZ_CUMPLIMIENTO.md) | Requisito por materia → dónde se cumple |
| [`docs/DESPLIEGUE.md`](docs/DESPLIEGUE.md) | Producción en Render, Neon, Atlas y Upstash |
| [`docs/HALLAZGOS_SANTIAGO.md`](docs/HALLAZGOS_SANTIAGO.md) | Hallazgos de la última auditoría en los módulos de Santiago |
| [`docs/guias/`](docs/guias/) | Guías de trabajo por día |

## Equipo y flujo de trabajo

- **Juan Camilo Calderón:** Identidad, Porteria, Analitica y sus vistas del panel.
- **Santiago Steven Ruiz Carreño:** agente, Monitoreo, Alertas, Docker de producción y despliegue.

Ramas `feature/KAN-xx-descripcion`, commits Conventional en español con la clave de Jira y todo cambio por Pull Request revisado por el otro. El CI (`.github/workflows/ci.yml`) corre los tests, el estilo y la higiene del PR.

## Privacidad

El agente no reconoce rostros ni identidades: solo cuenta presencia de personas. Las imágenes de evidencia se quedan en el PC del agente y se borran a los 30 días; los eventos en MongoDB vencen a los 365 días (Ley 1581 de 2012). Ningún secreto se versiona: van en `backend/.env` local o en el panel de Render.
