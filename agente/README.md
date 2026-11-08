# Agente de borde · Nexora Security

Corre en el PC del conjunto. Lee el video de una cámara, detecta con YOLOv8 si hay **personas o vehículos** y le manda el evento al backend según [`docs/CONTRATO_API_AGENTE.md`](../docs/CONTRATO_API_AGENTE.md). Es el cliente externo de la API.

## Privacidad (HU26, HU27)

- Solo detecta **que hay** una persona o un vehículo. No reconoce caras, no identifica a nadie y no lee placas.
- El video no se graba: los frames se procesan en memoria.
- Cuando hay un evento se guarda **una imagen** en `agente/evidencias/cam<ID>/` en este PC. A la API solo viaja la ruta; la imagen nunca sale del conjunto (Ley 1581). Al arrancar, y luego una vez al día, se borran las de más de `RETENCION_EVIDENCIAS_DIAS` (30 por defecto).

## Cómo decide que hay un evento

| Regla | Valor (`.env`) |
|---|---|
| Muestreo | `FPS=5` |
| Confianza mínima | `UMBRAL_CONFIANZA=0.5` |
| Persistencia: aparecer en 2 de las últimas 3 muestras | `PERSISTENCIA=2/3` |
| Máximo un evento por tipo cada N segundos | `COOLDOWN_SEG=10` |

## Instalación

```bash
cd agente
python3.12 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env      # y poner AGENTE_PASSWORD
```

El modelo `yolov8n.pt` se descarga solo la primera vez. En Mac usa la GPU (`mps`) si está disponible.

## Comandos

```bash
# video de prueba, sin backend, con ventana de cajas (q para salir)
python -m nexora_agente --fuente videos/parqueadero.mp4 --camara-id 1 --sin-api --mostrar

# contra el backend del .env (local o Render)
python -m nexora_agente --fuente videos/parqueadero.mp4 --camara-id 1

# webcam o cámara IP
python -m nexora_agente --fuente 0 --camara-id 1
python -m nexora_agente --fuente rtsp://usuario:clave@192.168.1.20:554/stream --camara-id 2

# respaldo de la demo: eventos falsos sin cámara
python -m nexora_agente --simular --camara-id 1 --cada 15
```

| Opción | Qué hace |
|---|---|
| `--fuente` | ruta `.mp4` (se repite al acabar), índice de webcam o `rtsp://` (reintenta cada 5 s si se cae) |
| `--camara-id` | id de la cámara en el backend |
| `--sin-api` | no llama a la API; imprime lo que enviaría y lo deja en una cola aparte (`cola-cam<ID>-sinapi.db`) que nunca se manda al backend y se puede borrar |
| `--mostrar` | ventana con las cajas detectadas |
| `--simular`, `--cada` | eventos al azar cada N segundos, sin cámara |

**Varias cámaras (HU40):** un proceso por cámara, cada uno en su terminal. Si una falla, las demás siguen.

```bash
python -m nexora_agente --fuente videos/parqueadero.mp4 --camara-id 1   # terminal 1
python -m nexora_agente --fuente 0 --camara-id 2                        # terminal 2
```

## Sin internet (HU23)

Todo evento entra primero a la cola SQLite de su cámara (`agente/cola-cam<ID>.db`) y solo se borra cuando el backend lo confirma:

| Respuesta | Qué hace |
|---|---|
| 202 / 200 | lo borra de la cola |
| 401 | login otra vez y reintenta |
| 422 | lo pasa a `rechazados` y no lo reintenta |
| 429 | espera lo que diga `Retry-After` |
| 5xx, sin red u otro código | lo deja y reintenta a los 2, 4, 8… hasta 60 s |

Si el backend está caído (sin red, 502/503/504, o un 500 seguido del 500 de un evento nuevo), el agente deja de intentar con todos y espera 2, 4, 8… hasta 60 s entre intentos. Un 500 de un evento que ya había fallado se toma como problema de ese evento: espera su propio turno (2, 4, 8… 60 s) y los demás siguen saliendo. Por eso, si el backend devuelve 500 toda la noche con una cola atrasada y sin detecciones nuevas, el agente revisa la cola cada segundo (cada evento respeta su propia espera), a lo sumo una petición por segundo por cámara.

Si el corte dura más de 24 h, el backend rechaza esos eventos por viejos (422) y quedan en `rechazados`.

Un evento que falla una y otra vez pasa al final de la fila y no frena a los demás. Por eso, después de un corte, los eventos pueden llegar en otro orden; no importa, porque el backend correlaciona por `fecha_hora`.

Además manda un heartbeat por cámara cada 60 s, **solo si la cámara está entregando frames**. Si el video se cae, deja de latir cuando pasan 2 minutos sin frames, y el backend marca la cámara sin señal 5 minutos después del último heartbeat: en total, entre 5 y 7 minutos.

En los logs del agente la URL RTSP sale sin usuario ni clave (`rtsp://***@…`). Ojo: si la cámara no responde, OpenCV imprime por su cuenta un aviso en la consola con la URL completa; no compartan esa salida.

Ver la cola:
```bash
sqlite3 cola-cam1.db "select count(*) from pendientes; select motivo from rechazados;"
```

### Probarlo sin el backend

```bash
python -m tests.mock_api --puerto 8000        # terminal 1: backend falso (agente@nexora.test / secreto)
python -m nexora_agente --fuente videos/parqueadero.mp4 --camara-id 1   # terminal 2
```

Apagar el mock con Ctrl+C: los eventos se quedan en `pendientes`. Al prenderlo otra vez salen solos.

## Tests

```bash
pytest -q
```

Cubren el filtro (persistencia y cooldown), el formato del evento, la cola (orden, rechazados, reinicio, espera creciente), la tabla de códigos del enviador y el cliente contra el mock.

## Qué no se sube al repo

`.env`, `.venv/`, `videos/`, `evidencias/`, `cola-cam*.db` y `*.pt` (están en `.gitignore`).
