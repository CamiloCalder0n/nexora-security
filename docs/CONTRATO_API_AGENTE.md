# Contrato API · Agente de borde ↔ Backend (v1)

Este archivo lo respetan los dos lados. Si alguien necesita cambiarlo, lo avisa y se actualiza aquí primero.

- Base URL local: `http://localhost:8000/api/v1`
- Base URL producción: `https://<app>.onrender.com/api/v1`
- Siempre enviar: `Accept: application/json` y `Content-Type: application/json`
- Autenticación: `Authorization: Bearer <token>` (Sanctum). El agente es un usuario con rol `agente`.
- Fechas: ISO 8601 con zona horaria de Colombia, ej. `2026-11-06T19:04:40-05:00`.
- Los nombres de campos siguen el modelo de MongoDB de BD2 (`fecha_hora`, `tipo_objeto`, `confianza`, `ruta_evidencia`).

---

## 1. Login del agente

`POST /agente/login`

```json
{ "email": "agente@nexora.test", "password": "********", "device_name": "agente-portatil-1" }
```

| Código | Respuesta |
|---|---|
| 200 | `{ "token": "1|abc...", "usuario": { "id": 9, "nombre": "Agente portería", "rol": "agente" } }` |
| 401 | `{ "message": "Credenciales inválidas" }` |
| 422 | `{ "message": "...", "errors": { "email": ["..."] } }` |

---

## 2. Cámaras que debe vigilar

`GET /agente/camaras`

| Código | Respuesta |
|---|---|
| 200 | ver abajo |
| 401 | token vencido o inválido → volver a hacer login |

```json
{
  "data": [
    {
      "id": 1,
      "nombre": "CAM-01 Parqueadero",
      "estado": "activa",
      "zona": { "id": 3, "nombre": "Parqueadero sótano", "prioridad_base": 3 },
      "vigilada_ahora": true
    }
  ]
}
```

`vigilada_ahora` = la zona tiene una franja horaria vigente en este momento. El agente igual puede enviar eventos fuera de franja (el backend los guarda como `fuera_franja`), pero puede usar este campo para no gastar CPU.

---

## 3. Enviar un evento (detección)

`POST /agente/eventos`

```json
{
  "uuid": "6f1c2b9e-3a4d-4c55-9e0a-1b2c3d4e5f60",
  "camara_id": 1,
  "tipo_objeto": "persona",
  "cantidad": 2,
  "confianza": 0.87,
  "fecha_hora": "2026-11-06T19:04:40-05:00",
  "ruta_evidencia": "evidencias/cam1/20261106-190440.jpg"
}
```

| Campo | Regla |
|---|---|
| `uuid` | obligatorio, UUID v4, lo genera el agente; sirve para no duplicar |
| `camara_id` | obligatorio, entero JSON (no texto), existe en `camaras` |
| `tipo_objeto` | obligatorio: `persona` o `vehiculo` |
| `cantidad` | obligatorio, entero JSON 1–50 (ni `true` ni `"5"`) |
| `confianza` | obligatorio, número JSON 0–1 (no texto); máximo de las detecciones del frame |
| `fecha_hora` | obligatorio, ISO 8601, no más de 24 h en el pasado ni 5 min en el futuro |
| `ruta_evidencia` | opcional, ruta **local y relativa** de la imagen en el PC del conjunto (la imagen nunca se sube). Solo `A-Z a-z 0-9 _ - / .`, sin `..` y sin `/` al inicio; máx. 255 |

| Código | Significado | Qué hace el agente |
|---|---|---|
| **202** | `{ "data": { "uuid": "...", "estado": "recibido" } }` → quedó en cola para correlacionar | borrar de la cola local |
| **200** | `{ "data": { "uuid": "...", "estado": "duplicado" } }` → ya existía ese uuid | borrar de la cola local |
| 401 | token inválido | login y reintentar |
| 422 | datos inválidos | mover a "rechazados" y registrar en el log (no reintentar) |
| 429 | demasiadas peticiones; trae `Retry-After` (segundos) | esperar ese tiempo y reintentar |
| 5xx / sin red | servidor caído o sin internet | dejar en cola y reintentar con espera creciente (2, 4, 8… máx. 60 s) |

Límite: **60 eventos por minuto por token** (rate limit).

---

## 4. Heartbeat de la cámara

`POST /agente/camaras/{id}/heartbeat`

```json
{ "fecha_hora": "2026-11-06T19:05:00-05:00" }
```

| Código | Significado |
|---|---|
| 204 | ok (actualiza `ultima_conexion` y pone la cámara `activa`) |
| 401 | login otra vez |
| 404 | la cámara no existe |

El agente lo envía **cada 60 s** por cámara. Si el backend no recibe heartbeat en **5 min**, marca la cámara `sin_senal` y crea una alerta `camara_sin_senal`.

---

## 5. Qué pasa después (para entender, no lo hace el agente)

1. El backend guarda el evento en MongoDB (`eventos`) y encola `CorrelacionJob`.
2. Si la zona no está en franja → `fuera_franja`, fin.
3. Busca un registro de portería de esa zona en la ventana de correlación (±10 min). Si hay → `explicado: true`, guarda `registro_id`, fin.
4. Si no hay → mira si existe una alerta abierta de esa zona dentro de la ventana de silencio:
   - sí → agrega el evento a esa alerta (`eventos_ids`, `num_eventos`), **sin notificar**;
   - no → crea una alerta `sin_registro` y la envía a Telegram.
