# Guía 01 · Santiago · Agente de borde con IA (YOLOv8)

**Día:** jueves 5 · **Dueño:** Santiago · **Rama:** `feature/KAN-30-agente-ia`
**Jira:** KAN-30 (HU26), KAN-39 (HU35), KAN-31 (HU27), KAN-27 (HU23), KAN-44 (HU40)
**Objetivo del día:** el agente lee un `.mp4`, detecta personas y vehículos con YOLOv8, decide cuándo es un evento y arma el JSON del contrato. Hoy **no necesita el backend**: funciona en modo `--sin-api` (imprime y guarda en la cola local). Mañana se conecta.

> Antes de esta guía: leer `PLAN_MAESTRO_NEXORA.md` y `docs/CONTRATO_API_AGENTE.md`.
> Esperar a que Cami cree el repo y te invite (Paso 0 de su guía, unos 10 min).

---

## Reglas para Claude Code

1. Un paso a la vez; al terminar cada paso, verificar y **PARAR** hasta que Santiago diga "sigue".
2. **Claude Code NO hace `git commit`, `git push` ni `git tag`**: propone el mensaje y Santiago lo ejecuta.
3. Trabajar **solo en `agente/`**. No tocar `backend/` (eso es de Cami hoy).
4. Nunca subir videos, imágenes de evidencia, el modelo `.pt`, la base SQLite ni `.env`.
5. **Privacidad (HU26/HU27):** el agente no reconoce caras ni guarda video. Solo detecta *que hay* una persona o un vehículo. La imagen de evidencia se guarda **en el disco local** y a la API solo va su ruta.
6. Seguir el contrato al pie de la letra. Si hay que cambiarlo, avisar a Cami primero.
7. **Sin rastro de IA en Git:** los mensajes de commit y las descripciones de PR **no llevan** `Co-Authored-By: Claude…`, ni "Generated with Claude Code", ni ninguna mención a Claude o a IA. El repo ya trae `.claude/settings.json` con la atribución apagada.

---

## Paso 0 · Preparar el entorno (Santiago, a mano)

```bash
git clone https://github.com/CamiloCalder0n/nexora-security.git
cd nexora-security
git checkout -b feature/KAN-30-agente-ia
brew install python@3.12        # si no lo tienes
cd agente
python3.12 -m venv .venv
source .venv/bin/activate
```

**Video de prueba:** graben con el celular (horizontal, 30–60 s) un pasillo o parqueadero:
- escena A: una persona pasa sola;
- escena B: nadie (10 s);
- escena C: un carro o moto entrando.

Guárdenlo como `agente/videos/parqueadero.mp4` (está en `.gitignore`; compártanlo por Drive).

Arrancar Claude Code en la raíz del repo:
```
Lee PLAN_MAESTRO_NEXORA.md, docs/CONTRATO_API_AGENTE.md y docs/guias/GUIA_01_SANTIAGO_AGENTE.md.
Sigue la guía desde el Paso 1, un paso a la vez. Trabaja solo en agente/. No hagas commits.
```

---

## Paso 1 · Estructura y dependencias

```
agente/
├─ nexora_agente/
│  ├─ __init__.py
│  ├─ __main__.py        (permite: python -m nexora_agente ...)
│  ├─ config.py          (lee .env: API_URL, AGENTE_EMAIL, AGENTE_PASSWORD, umbrales)
│  ├─ captura.py         (OpenCV: .mp4, webcam o RTSP; muestreo a N fps; reinicia el .mp4 al acabar)
│  ├─ detector.py        (YOLOv8n; devuelve [(clase, confianza, caja)])
│  ├─ filtro.py          (umbral, persistencia, cooldown → decide si hay evento)
│  ├─ evidencia.py       (guarda un .jpg local y devuelve la ruta)
│  ├─ cola.py            (SQLite: pendientes y rechazados)
│  ├─ cliente_api.py     (login, camaras, eventos, heartbeat; modo sin_api)
│  ├─ simulador.py       (eventos de prueba sin cámara)
│  └─ main.py            (une todo; argumentos de consola)
├─ tests/
│  ├─ test_filtro.py
│  └─ test_cola.py
├─ requirements.txt      (ultralytics, opencv-python, requests, python-dotenv, pytest)
├─ .env.example
└─ README.md
```

```bash
pip install -r requirements.txt
```

**Verificación:** `python -c "import ultralytics, cv2, requests; print('ok')"`

**Commit (Santiago):** `chore(agente): estructura del agente de borde y dependencias KAN-30`

---

## Paso 2 · Captura + detección (la IA)

- `captura.py`: `cv2.VideoCapture(fuente)` donde fuente es una ruta `.mp4`, `0` (webcam) o `rtsp://...`. Toma frames a **5 fps** (salta los demás). Si es `.mp4` y se acaba, vuelve al inicio. Si es RTSP y se cae, reintenta cada 5 s sin tumbar el proceso.
- `detector.py`: `YOLO("yolov8n.pt")` (se descarga solo la primera vez). Clases COCO usadas:
  - `0` → `persona`
  - `2, 3, 5, 7` (carro, moto, bus, camión) → `vehiculo`
  - `conf=0.5`; en Mac usar `device="mps"` si está disponible, si no `cpu`.
- `main.py --mostrar`: abre una ventana con las cajas dibujadas (`results.plot()`) para la demo. `q` para salir.

**Verificación:**
```bash
python -m nexora_agente --fuente videos/parqueadero.mp4 --camara-id 1 --sin-api --mostrar
```
Se ven las cajas sobre la persona y el carro, y en consola: `persona x1 conf 0.88`.

**Commit:** `feat(agente): captura de video y detección con YOLOv8 KAN-30 KAN-39`

---

## Paso 3 · Filtro: cuándo una detección se vuelve evento

Para no inundar al vigilante (riesgo de falsas alarmas):

| Regla | Valor por defecto (en `.env`) |
|---|---|
| Confianza mínima | `UMBRAL_CONFIANZA=0.5` |
| Persistencia: el objeto debe aparecer en al menos 2 de las últimas 3 muestras | `PERSISTENCIA=2/3` |
| Cooldown: máximo un evento por cámara y tipo cada N segundos | `COOLDOWN_SEG=10` |

Cuando pasa el filtro, se arma el evento **exactamente** con el formato del contrato (`uuid`, `camara_id`, `tipo_objeto`, `cantidad`, `confianza`, `fecha_hora` con `-05:00`, `ruta_evidencia`).

`evidencia.py`: guarda el frame en `evidencias/cam<ID>/<AAAAMMDD-HHMMSS>.jpg` y devuelve esa ruta relativa.

**Tests** `test_filtro.py`: una detección aislada **no** genera evento; dos seguidas sí; una tercera dentro del cooldown no.

**Verificación:** `pytest -q` en verde; con el `.mp4`, en consola sale un JSON por evento (no uno por frame).

**Commit:** `feat(agente): filtro de umbral, persistencia y cooldown KAN-30`

---

## Paso 4 · Cola local SQLite (seguir funcionando sin internet, HU23)

`cola.py` con un archivo `agente/cola.db`:
- tabla `pendientes(uuid PK, payload TEXT, intentos INT, proximo_intento TEXT, creado_at TEXT)`;
- tabla `rechazados(uuid, payload, motivo, creado_at)`.

Flujo: **todo** evento entra primero a `pendientes`. Un hilo "enviador" toma los pendientes en orden y aplica la tabla de códigos del contrato:

| Respuesta | Acción |
|---|---|
| 202 / 200 | borrar de pendientes |
| 401 | login otra vez y reintentar |
| 422 | mover a `rechazados` y loguear |
| 429 | esperar `Retry-After` |
| 5xx o sin red | `intentos+1`, espera 2, 4, 8… hasta 60 s |

En modo `--sin-api` el enviador solo imprime "enviaría: {...}" y deja los eventos en la cola (sirve para probar la cola).

**Tests** `test_cola.py`: encolar, sacar en orden, mover a rechazados, que sobrevive a reiniciar el proceso.

**Commit:** `feat(agente): cola local SQLite con reintentos KAN-27`

---

## Paso 5 · Cliente API + heartbeat + simulador

- `cliente_api.py`: `login()`, `camaras()`, `enviar_evento(payload)`, `heartbeat(camara_id)`, con `timeout=5` y headers del contrato. Guarda el token en memoria.
- Heartbeat: un hilo que cada **60 s** llama `POST /agente/camaras/{id}/heartbeat`.
- `simulador.py`: `python -m nexora_agente --simular --camara-id 1 --cada 15` genera eventos falsos (persona o vehículo al azar) sin cámara. Es el respaldo de la demo.
- **Una cámara = un proceso** (`--camara-id`): si una cámara falla, las demás siguen (HU40). En el README, cómo correr 2 cámaras en 2 terminales.

Mientras el backend no exista, probar contra un servidor falso mínimo:
```bash
python -m http.server 8000   # o un mock con Flask que responda 202
```
(Claude Code puede crear `tests/mock_api.py` con Flask que responda según el contrato, solo para pruebas.)

**Verificación:** con el mock corriendo, el agente hace login, envía eventos y heartbeat; al apagar el mock los eventos se quedan en `pendientes` y salen solos cuando se vuelve a prender.

**Commit:** `feat(agente): cliente API según contrato, heartbeat y simulador KAN-27 KAN-44`

---

## Paso 6 · README del agente y Pull Request

`agente/README.md`: qué hace, privacidad (sin biometría, video local), instalación, `.env`, comandos (`--fuente`, `--camara-id`, `--sin-api`, `--mostrar`, `--simular`), cómo probar sin internet.

```bash
git push -u origin feature/KAN-30-agente-ia
```
Abrir el **Pull Request** hacia `main` con título `Agente de borde con IA (KAN-30)` y en la descripción la lista de HU cubiertas. **Cami lo revisa y lo aprueba.**

---

## Al terminar el día

- [ ] Con el `.mp4` se ven las cajas y salen eventos en el formato del contrato.
- [ ] Filtro probado (`pytest` en verde).
- [ ] Cola local que aguanta "sin internet".
- [ ] PR abierto y revisado por Cami.
- [ ] Jira: KAN-30, KAN-39, KAN-31 en "Listo"; KAN-27 y KAN-44 "En curso" (terminan el domingo contra la URL pública).
