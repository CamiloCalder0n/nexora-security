# Nexora Security · Plan maestro (4 materias, 1 sistema)

Equipo: Juan Camilo Calderón (Cami) · Santiago Steven Ruiz Carreño (Santiago)
Entrega límite: **martes 10 de noviembre de 2026**
Plan: jueves 5 → martes 10 (6 días)

---

## 1. Decisiones tomadas

| # | Decisión | Por qué |
|---|---|---|
| D1 | **Un solo sistema y un solo repositorio** para las 4 materias | Todos los profes lo aprobaron; 4 proyectos en 6 días no da |
| D2 | **Un solo conjunto residencial** | Alcance de prototipo |
| D3 | **Backend 100 % Laravel 13** (PHP 8.4) como **monolito modular** con capas hexagonales | Lo exige Backend; ArqSoft pide capas y bounded contexts; microservicios no caben en 6 días |
| D4 | **PostgreSQL + MongoDB + Redis** | PostgreSQL: datos con relaciones (Backend). MongoDB: eventos, alertas y bitácora (BD2). Redis: colas y caché (ArqSoft, resiliencia) |
| D5 | **Alertas por Telegram** | Gratis, llega al celular sin instalar nada |
| D6 | **Demo con video .mp4** (y la webcam como extra) | Repetible, no depende de cámaras reales |
| D7 | **Todo gratis**: Render (app), Neon (PostgreSQL), MongoDB Atlas M0, Upstash (Redis), Telegram, YOLOv8 | Restricción del equipo |
| D8 | **Jira (KAN)** para tareas y **GitHub** para el código, un solo repo compartido | Ya tienen las 40 HU en Jira |
| D9 | Panel web en **Blade + Bootstrap**, que en el panel de alertas consume la API con JS | Es lo más práctico; el desacoplamiento se justifica en un ADR |

---

## 2. Qué aporta cada materia (todo del mismo sistema)

| Materia | Qué se entrega | Dónde vive en el repo |
|---|---|---|
| **Backend** | Laravel (panel + API) + agente como cliente externo + Docker + Render + Postman + video de la entrevista | `backend/`, `agente/`, `docs/backend/` |
| **BD2 (NoSQL)** | Documento v1.04 + scripts MongoDB (ya existen) + presentación tipo emprendimiento | `database/mongo/`, `docs/bd2/` |
| **Arquitectura de Software** | Documento: problema, bounded contexts, C4 1-2-3, persistencia políglota, resiliencia, ADRs, declaración de IA + código en repo público | `docs/arquitectura/` |
| **IngSoft** | Documento final + prototipo funcional + Jira al día | `docs/ingsoft/` |

**Matriz de cumplimiento** (se llena al final en `docs/MATRIZ_CUMPLIMIENTO.md`): cada requisito de cada profe → archivo o pantalla donde se cumple.

---

## 3. Actores (4) y plataformas (3)

| Actor | Qué hace | Por dónde |
|---|---|---|
| **Vigilante (celador)** | Registra ingresos, recibe y atiende alertas, anota novedades del turno | Panel web (tablet de portería) + Telegram |
| **Administradora** | Configura zonas, horarios, cámaras, cuentas; consulta historial | Panel web |
| **Consejo** | Ve informes y estadísticas (solo lectura) | Panel web |
| **Residente** | Autoriza visitas, ve sus visitas, recibe aviso cuando llega alguien | Panel web (rol residente) + Telegram |

Plataformas de entrada (ArqSoft pide 3):
1. **Panel web** (Blade) → el frontend implementado.
2. **Bot de Telegram** → alertas al vigilante (con botón "Atender") y avisos al residente.
3. **Agente de borde con IA** → red de ingesta (Python + OpenCV + YOLOv8). Es el cliente externo de Backend.

Extra: la **API REST v1** documentada (Postman) cuenta como pasarela para terceros.

---

## 4. Arquitectura

### 4.1 Bounded contexts (módulos)

| Contexto | Responsabilidad | BD | Dueño |
|---|---|---|---|
| **Identidad** | Usuarios, roles, Sanctum, sesiones | PostgreSQL | Cami |
| **Portería** | Unidades, residentes, autorizaciones de visita, registros de ingreso | PostgreSQL | Cami |
| **Monitoreo** | Zonas, franjas, cámaras, ingesta de eventos, cámaras caídas | PostgreSQL (config) + MongoDB (eventos) | Santiago |
| **Alertas** | Correlación, alertas, supresión, escalamiento, bitácora, Telegram | MongoDB + Redis | Santiago |
| **Analítica** | Dashboard, informes del consejo (usa las consultas de BD2) | MongoDB (lectura) | Cami |

Los contextos se hablan por **eventos de dominio** de Laravel, en cola (Redis):
`EventoRegistrado` (Monitoreo) → `CorrelacionJob` (Alertas) → `AlertaCreada` → `NotificarTelegramJob`.
`IngresoRegistrado` (Portería) → aviso al residente por Telegram.

### 4.2 Capas dentro de cada módulo (hexagonal)

```
backend/app/Modules/<Contexto>/
  Domain/          Entidades, reglas puras, interfaces (puertos)
  Application/     Services (casos de uso): CorrelacionService, AlertaService…
  Infrastructure/  Repositories (Eloquent / Mongo), adaptador Telegram
  Http/            Controllers (Web y Api\V1), Form Requests, Resources
```
Cumple a la vez "Controller → Service → Repository → Model" (Backend) y capas con puertos y adaptadores (ArqSoft).

### 4.3 Dónde va cada dato

| PostgreSQL (relacional, 1:1 · 1:N · N:M) | MongoDB (volumen, TTL) | Redis |
|---|---|---|
| users, residentes (1:1 users), unidades, unidad_residente (N:M), zonas, franjas, camaras (N:1 zona), autorizaciones, registros_porteria | eventos (TTL 365 días), alertas, bitacora (con hash encadenado) | colas, caché de zonas activas, rate limit |

Nota: en BD2 todo se modeló en MongoDB como ejercicio de la materia. En la implementación, lo transaccional pasa a PostgreSQL y Mongo guarda lo que crece sin límite. Se explica en un ADR.

### 4.4 Resiliencia y control de carga (ArqSoft)

| Problema | Solución |
|---|---|
| Pico de detecciones | `POST /detecciones` responde **202** y encola; un worker procesa (nivelación de carga) |
| Un agente que se vuelve loco | **Rate limit** por token (`throttle`) → 429 |
| Reenvíos duplicados | **Idempotencia** por `uuid` del evento (índice único en Mongo) |
| Se cae el internet | **Cola local SQLite** en el agente + reintento con espera creciente |
| Telegram falla | Reintentos del job con backoff; la alerta igual queda en el panel |
| Consultas repetidas de zonas | **Caché Redis** de zonas/franjas activas |
| Crecimiento de eventos | Índices zona + fecha y **TTL** en Mongo |
| Saber si algo está caído | `/up` + heartbeat de cámaras + logs |
| Prueba | **k6** (gratis) simulando un pico: se ve el 202, el 429 y que la cola lo absorbe |

### 4.5 Despliegue

- **Render (gratis)**: un Web Service Docker con supervisord: `php artisan serve` + `queue:work` + `schedule:work` en el mismo contenedor (los workers aparte son pagos en Render).
- **Neon**: PostgreSQL · **Atlas M0**: MongoDB · **Upstash**: Redis.
- **Local**: `docker compose up` levanta todo (app, pg, mongo, redis) con un comando (HU34).
- Agente: corre en el portátil (simula el PC del conjunto) contra la URL pública.

---

## 5. Repositorio

Un solo repo **público** en GitHub: `nexora-security` (Santiago como colaborador).

```
nexora-security/
├─ backend/            Laravel (panel Blade + API + workers)
├─ agente/             Python: captura, YOLOv8, cola local, cliente API
├─ database/mongo/     Scripts 01, 02, 03 de BD2
├─ postman/            Colección + environment (sin secretos)
├─ docs/
│  ├─ arquitectura/    Documento ArqSoft + ADRs + diagramas
│  ├─ backend/         arquitectura.pdf (v2) + evidencias
│  ├─ bd2/             PROYECTO_CURSO v1.04 + presentación
│  ├─ ingsoft/         Documento final
│  └─ MATRIZ_CUMPLIMIENTO.md
├─ docker-compose.yml
├─ CLAUDE.md           Reglas para Claude Code (las de siempre)
└─ README.md
```

### Flujo de Git
- `main` protegida: **nada entra sin Pull Request** revisado por el otro.
- Ramas: `feature/KAN-6-registro-ingreso` (con la clave de Jira para que se enlacen).
- Commits en español, Conventional Commits: `feat(porteria): registrar ingreso KAN-6`.
- **Sin rastro de IA**: ni `Co-Authored-By` de Claude ni "Generated with Claude Code" en commits o PR. El repo trae `.claude/settings.json` con `"attribution": {"commit": "", "pr": ""}`.
- Etiquetas: `docs`, `v0.1`, `v0.2`, `v0.3`, `v0.4`, `v1.0` (las de Backend).
- **Nunca** `.env`, ni el video .mp4 grande, ni tokens en el repo.
- **Todo el esquema de PostgreSQL (migraciones, modelos, factories, seeders) lo hace Cami**, incluidas zonas, franjas y cámaras. Santiago hace MongoDB y la lógica de Monitoreo y Alertas encima.
- Cada uno trabaja **solo en sus módulos** → casi no hay conflictos. Archivos compartidos (`routes/`, `composer.json`, `docker-compose.yml`) se tocan avisando por WhatsApp.

### Uso de Claude
- **Santiago (Claude Max)** lleva lo más pesado en código: esqueleto de módulos de Alertas/Monitoreo, agente, Docker y deploy.
- **Cami (Claude Pro, con límite)**: tareas enfocadas (Portería, panel, dashboard) y revisión de PR.
- Este chat: guías paso a paso para Claude Code, documentos de cada materia, guion de entrevista, revisión.

---

## 6. Jira: cómo organizarlo

- **Épicas** = contextos: Identidad, Portería, Monitoreo, Alertas, Analítica, Infraestructura.
- **Sprint 1 · 5–10 nov** con todas las HU marcadas como prototipo.
- Etiqueta `prototipo` o `futuro` en cada HU; asignar responsable; mover a "En curso" y "Listo".
- Opcional: app gratuita "GitHub for Jira" para que ramas y commits con `KAN-x` aparezcan en la historia.

### Reparto de las 40 HU

**Cami (Identidad, Portería, Analítica, panel)**

| HU | Jira | Título | Día |
|---|---|---|---|
| HU30 | KAN-34 | Exigir autenticación y cerrar sesiones inactivas | Vie |
| HU10 | KAN-14 | Recibir solo la información que corresponde al rol | Vie |
| HU15 | KAN-19 | Gestionar cuentas y vigencias | Sáb |
| HU02 | KAN-6 | Registrar ingreso de personas y vehículos | Sáb |
| HU16 | KAN-20 | Registrar el ingreso vehicular | Sáb |
| HU09 | KAN-13 | Autorizar anticipadamente el ingreso de un visitante | Sáb |
| HU18 | KAN-22 | Consultar las visitas de mi unidad | Sáb |
| HU17 | KAN-21 | Ser avisado de las visitas a mi unidad (Telegram) | Dom |
| HU20 | KAN-24 | Registrar novedades y entregar el turno | Dom |
| HU05 | KAN-9 | Consultar el historial para reconstruir un hecho | Dom |
| HU08 | KAN-12 | Informe mensual de indicadores (consejo) | Dom |
| HU36 | KAN-40 | Usar la aplicación en cualquier teléfono (responsive) | Dom |
| HU19 | KAN-23 | Exportar la evidencia de un incidente (CSV/PDF) | *si sobra tiempo* |

**Santiago (Monitoreo, Alertas, agente, infraestructura)**

| HU | Jira | Título | Día |
|---|---|---|---|
| HU26 | KAN-30 | Detectar presencia sin identificar personas (YOLO) | Jue |
| HU35 | KAN-39 | Conectar con las cámaras (RTSP / .mp4) | Jue |
| HU27 | KAN-31 | Mantener el video dentro de la copropiedad | Jue |
| HU06 | KAN-10 | Configurar zonas, horarios y prioridades | Vie |
| HU01 | KAN-5 | Recibir alerta de evento no explicado (correlación) | Vie |
| HU04 | KAN-8 | Controlar el volumen de alertas repetidas | Vie |
| HU38 | KAN-42 | Explicar por qué se generó cada alerta | Vie |
| HU31 | KAN-35 | Agregar un tipo de evento sin tocar el motor | Vie |
| HU11 | KAN-15 | Ver la cola de alertas activas | Sáb |
| HU03 | KAN-7 | Clasificar y cerrar alertas | Sáb |
| HU24 | KAN-28 | Atender una alerta en menos de 30 s (botón Telegram) | Sáb |
| HU21 | KAN-25 | Recibir la alerta en menos de 10 s | Sáb |
| HU12 | KAN-16 | Escalar las alertas no atendidas | Sáb |
| HU13 | KAN-17 | Detectar cámaras caídas | Sáb |
| HU28 | KAN-32 | Detectar alteración de la bitácora (hash) | Sáb |
| HU33 | KAN-37 | Eliminar datos al vencer su plazo (TTL) | Vie |
| HU23 | KAN-27 | Seguir alertando sin internet (cola del agente) | Dom |
| HU34 | KAN-38 | Desplegar con un solo comando (Docker) | Dom |
| HU39 | KAN-43 | Diagnosticar fallas (health + logs) | Dom |
| HU40 | KAN-44 | Aislar la falla de una cámara | Dom |
| HU25 | KAN-29 | Procesar 4 cámaras sin retraso | *si sobra tiempo* |

**Se cumplen por diseño, sin tarea aparte**: HU37 (KAN-41, Telegram solo texto, sin video), HU22 (KAN-26, se mide en el dashboard: % de falsas alarmas).

**Trabajo futuro (etiqueta `futuro`)**: HU07 (KAN-11), HU14 (KAN-18), HU29 (KAN-33), HU32 (KAN-36).

---

## 7. Cronograma (5 al 10 de noviembre)

| Día | Cami | Santiago | Juntos / cierre del día |
|---|---|---|---|
| **Jue 5** | ✅ Repo creado + docs (Paso 0) | — | — |
| **Vie 6 (noche)** | Guía 01: esqueleto Laravel, Docker, módulos, roles | Guía 01: agente con IA (si puede) | — |
| **Sáb 7** | Guía 02: **todo el esquema PostgreSQL** (PR temprano), reglas de Portería, API v1 zonas + autorizaciones | Termina el agente · Guía 02: Mongo, ingesta, correlación, alertas, bitácora, escalamiento | Agente → API local → alerta · etiquetas `v0.1` y `v0.2` |
| **Dom 8** | Guía 03: panel Blade completo (roles, CRUD, ingresos, cola de alertas, 404/500), dashboard, informes, Postman | Guía 03: Telegram, Docker de producción, deploy (Render + Neon + Atlas + Upstash), agente contra URL pública, k6 | Todo en la URL pública · etiquetas `v0.3` y `v0.4` |
| **Lun 9** | Documento ArqSoft + arquitectura.pdf v2 de Backend | Documento final IngSoft + BD2 v1.04 + presentación | **Entrevista con el celador** · README, matriz, Jira |
| **Mar 10** | Ensayo y entregas | Ensayo y entregas | Etiqueta `v1.0` |

**La entrevista con el celador** se graba el lunes 9 (guion en la sección 9). Si se puede antes, mejor.

---

## 8. Demo (sirve para todas las sustentaciones)

1. La administradora tiene la zona "Parqueadero" activa a esa hora.
2. Corre el agente con el .mp4 → persona sin registro → **alerta en Telegram en segundos**.
3. El vigilante toca "Atender" en Telegram, luego la cierra en el panel como falsa alarma (sin clasificar → 422).
4. El vigilante registra un domiciliario hacia el parqueadero → la persona pasa otra vez → **evento explicado, sin alerta**.
5. Dos pasadas seguidas sin registro → **una sola alerta** con 2 eventos (supresión).
6. El residente recibe en Telegram "Llegó tu visita".
7. El consejo abre el dashboard: alertas por zona y % de falsas alarmas.
8. (ArqSoft) k6 lanza un pico → 202 + cola, y 429 al pasarse del límite.

---

## 9. Guion de la entrevista (celador)

**Antes de grabar**: explicarle que es para un proyecto de la universidad, pedirle permiso para grabar (Ley 1581) y no grabar placas, caras de residentes ni la pantalla de cámaras del conjunto. Video horizontal, buena luz, sin ruido. Pueden subir solo los 3–5 minutos más importantes.

1. ¿Cuántos son en el turno y qué tienen que cubrir al mismo tiempo?
2. ¿Cada cuánto puede mirar las cámaras durante un turno normal?
3. ¿Cómo registran hoy a visitantes, domiciliarios y vehículos?
4. Cuéntenos de la última vez que pasó algo y se enteraron tarde.
5. ¿Qué zonas y horarios le preocupan más?
6. Si le llegara un aviso al celular cada vez que hay alguien sin registro, ¿le serviría? ¿Qué le molestaría?
7. ¿Cuántos avisos al día aguantaría antes de apagarlo?
8. ¿Le parecería bien que el sistema no reconozca caras, solo que hay una persona?

---

## 10. Confirmado

- El martes 10 aplica para las 4 materias.
- Documento final de IngSoft: no hay plantilla; se arma con lo que pide la materia (proceso y buenas prácticas, Pressman / PMBOK, Visión RUP completa, arquitectura y prototipo).
- Documento de ArqSoft: según el enunciado del Proyecto Final (C4 1-2-3, bounded contexts, persistencia políglota, resiliencia, ADRs) + declaración de uso de IA.
- Repo en la cuenta de Cami (`CamiloCalder0n/nexora-security`), Santiago como colaborador.
- Entrevista con el celador: lunes 9 a más tardar.
