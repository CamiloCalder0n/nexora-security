# Guía 02 · Santiago · Monitoreo y Alertas (MongoDB, ingesta, correlación)

**Día:** sábado 7 (después de terminar la Guía 01 del agente) · **Dueño:** Santiago · **Rama:** `feature/KAN-5-correlacion-alertas`
**Jira:** KAN-5 (HU01), KAN-8 (HU04), KAN-42 (HU38), KAN-35 (HU31), KAN-7 (HU03), KAN-16 (HU12), KAN-17 (HU13), KAN-32 (HU28), KAN-37 (HU33)
**Requisito:** la Guía 01 de Cami en `main` y el PR del **modelo de datos** de Cami (Guía 02, Pasos 1 a 3) mergeado. Antes de empezar:
```bash
git checkout main && git pull
git checkout -b feature/KAN-5-correlacion-alertas
docker compose up -d
docker compose exec app php artisan migrate:fresh --seed
```

**Objetivo:** que un evento que llega del agente se guarde en MongoDB, se correlacione con portería y, si nada lo explica, cree o agrupe una alerta con su bitácora. El envío a Telegram es la Guía 03 (domingo).

Reglas de siempre: un paso a la vez, Claude Code no hace commits, sin rastro de IA en commits ni PR, solo tocar `Modules/Monitoreo` y `Modules/Alertas` (si hace falta tocar algo compartido, avisar a Cami).

---

## Paso 1 · MongoDB: modelos e índices

Copiar los scripts de BD2 a `database/mongo/` (`01_NEXORA_CREACION_OBJETOS.js`, `02_NEXORA_CARGA_DATOS.js`, `03_NEXORA_CONSULTAS.js`) **tal como se entregaron**.

Modelos con `MongoDB\Laravel\Eloquent\Model` (`$connection = 'mongodb'`), nombres de campos iguales a BD2 y `schema_version` en todos los documentos:

| Modelo | Colección | Campos clave |
|---|---|---|
| `Monitoreo\Domain\Models\Evento` | `eventos` | `uuid`, `fecha_hora`, `tipo_objeto`, `cantidad`, `confianza`, `ruta_evidencia`, `resultado` (pendiente / explicado / alerta / suprimido / fuera_franja), `explicado` bool, `camara{camara_id,nombre}`, `zona{zona_id,nombre}`, `registro_id` null, `alerta_id` null |
| `Alertas\Domain\Models\Alerta` | `alertas` | `fecha_hora`, `tipo` (sin_registro / camara_sin_senal), `prioridad`, `motivo`, `regla`, `estado` (abierta / atendida / cerrada), `nivel_escalamiento`, `clasificacion` null (falsa_alarma / sin_novedad / incidente), `observacion` null, `camara{}`, `zona{}`, `eventos_ids[]`, `num_eventos`, `evidencias_recientes[3]`, `atendida_por{usuario_id,nombre}` null, `fecha_atencion` null, `fecha_cierre` null |
| `Alertas\Domain\Models\EntradaBitacora` | `bitacora` | `fecha_hora`, `tipo`, `descripcion`, `estado_anterior`, `estado_nuevo`, `usuario{usuario_id,nombre,rol}`, `alerta_id`, **`hash_anterior`**, **`hash`** |

Comando `php artisan nexora:mongo-indices` (idempotente) que crea:
- `eventos.uuid` único (idempotencia) · `eventos(zona.zona_id, fecha_hora)` · **TTL** en `eventos.fecha_hora` de 365 días (HU33)
- `alertas(estado, fecha_hora)` · `alertas(zona.zona_id, estado)`
- `bitacora.fecha_hora`

**Verificación:** el comando corre dos veces sin error; en `mongosh` (`docker compose exec mongo mongosh nexora_security`) `db.eventos.getIndexes()` muestra el TTL.

**Commit:** `feat(monitoreo): modelos e índices de MongoDB para eventos, alertas y bitácora KAN-37`

---

## Paso 2 · Endpoints del agente (según el contrato)

Grupo `api.v1.agente.*`, middleware `auth:sanctum` + `rol:agente`:

| Ruta | Respuesta |
|---|---|
| `GET /api/v1/agente/camaras` | `CamaraAgenteResource` con `zona` y `vigilada_ahora` (`Zona::estaVigilada(now())`, cacheado 60 s en Redis) |
| `POST /api/v1/agente/eventos` | `StoreEventoRequest` (las reglas del contrato). Si el `uuid` ya existe → **200 duplicado**. Si no, guarda el evento con `resultado = pendiente`, despacha **`CorrelacionJob`** a la cola y responde **202** |
| `POST /api/v1/agente/camaras/{camara}/heartbeat` | actualiza `ultima_conexion`; si estaba `sin_senal` la pone `activa`; **204** |

Rate limit con nombre `agente` (60 por minuto por token) en `AppServiceProvider` → **429** con `Retry-After`.

**Tests:** 202 · 200 duplicado · 422 · 401 sin token · 403 con un token que no es de agente · 429 al pasar el límite.

**Commit:** `feat(monitoreo): ingesta de eventos del agente con cola, idempotencia y rate limit KAN-5`

---

## Paso 3 · Motor de reglas y correlación

**Reglas enchufables (HU31):** interfaz `Alertas\Domain\Reglas\ReglaDeteccion` con `aplica(Evento): bool` y `evaluar(Evento): ResultadoRegla`. Las reglas se registran con un *tag* del contenedor (`nexora.reglas`): agregar una regla nueva = crear una clase y registrarla, **sin tocar el motor**. Regla inicial: `SinRegistroEnPorteria`.

**`CorrelacionService`** (lo llama `CorrelacionJob`):
1. Carga la zona de la cámara (PostgreSQL). Si `! $zona->estaVigilada($evento->fecha_hora)` → `fuera_franja`, fin.
2. Usa el puerto de Cami **`ConsultaRegistrosPorteria::buscarParaCorrelacion(zona, fecha_hora, ventana_correlacion_min, cantidad)`**. Si hay registro → `explicado = true`, `registro_id`, fin.
3. Si no → `AlertaService::crearOAgrupar($evento, regla)`.

**`AlertaService`**:
- `crearOAgrupar`: si existe una alerta **abierta** de la misma zona creada hace menos de `ventana_silencio_min` → agrega el evento (`eventos_ids`, `num_eventos`, `evidencias_recientes` con máximo 3) y el evento queda `suprimido`, **sin notificar** (HU04). Si no → crea la alerta con `motivo` legible, por ejemplo *"Persona en Parqueadero sótano a las 22:30 sin registro en portería (ventana ±10 min)"* (HU38), y dispara el evento **`AlertaCreada`** (la Guía 03 lo conecta a Telegram).
- `atender(alerta, usuario)` → `atendida`, `atendida_por`, `fecha_atencion`.
- `cerrar(alerta, usuario, clasificacion, observacion)`: sin clasificación → **422**; `incidente` sin observación → **422** (HU03).
- Cada cambio de estado escribe en la **bitácora**.

**`BitacoraService`** (HU28): `hash = sha256(json([hash_anterior, secuencia, fecha_hora, tipo, descripcion, estado_anterior, estado_nuevo, usuario, alerta_id]))`: cubre todos los campos y el JSON separa uno de otro. Lo que una cadena de hashes no detecta es que borren las últimas entradas. Comando `php artisan nexora:verificar-bitacora` que recorre la cadena y dice "OK" o en qué entrada se rompió.

**Tests:** un evento con registro en la ventana → explicado; sin registro → alerta; dos eventos en 5 min → 1 alerta con `num_eventos = 2`; cerrar sin clasificar → 422; alterar una entrada → el verificador lo detecta.

**Commit:** `feat(alertas): correlación con portería, supresión de repetidas y bitácora con hash KAN-5 KAN-8 KAN-32 KAN-35`

---

## Paso 4 · Tareas programadas: escalamiento y cámaras caídas

En el scheduler (`routes/console.php` o en el provider del módulo):
- **Cada minuto, `nexora:camaras-sin-senal`** (HU13): las cámaras `activa` con `ultima_conexion` de hace más de 5 min pasan a `sin_senal` y se crea una alerta `camara_sin_senal` (una sola por cámara mientras siga caída).
- **Cada minuto, `nexora:escalar-alertas`** (HU12): las alertas `abierta` de prioridad alta (3) sin atender por más de 5 min → `nivel_escalamiento + 1`, entrada en bitácora y evento `AlertaEscalada` (en la Guía 03 avisa a la administradora por Telegram).
- **Cada 10 min:** `AutorizacionService::expirarVencidas()` (es de Cami, solo se agenda aquí).

**Prueba de punta a punta (local):**
```bash
# terminal 1
docker compose up
# terminal 2: el agente de la Guía 01 contra localhost
python -m nexora_agente --fuente videos/parqueadero.mp4 --camara-id 1 --mostrar
```
Ver en `mongosh` cómo llegan los eventos y se crean alertas; registrar un ingreso con `tinker` y ver que el siguiente evento queda `explicado`.

**Commit:** `feat(alertas): escalamiento de alertas y detección de cámaras sin señal KAN-16 KAN-17`

**Push → PR → Cami revisa → merge → etiqueta `v0.2`.**

---

## Al terminar el día

- [ ] Agente → API local → evento en Mongo → alerta o "explicado".
- [ ] Supresión, cierre con clasificación y bitácora verificada.
- [ ] Escalamiento y cámaras sin señal corriendo en el `scheduler`.
- [ ] `php artisan test` en verde.
- [ ] Jira al día.
