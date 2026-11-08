---
name: pr-review
description: Revisión en cuatro pasadas de una rama o PR de Nexora Security antes de publicarlo — 1 limpieza (código basura, duplicado, reutilización improductiva), 2 bugs (lógica, bordes, concurrencia, fallas), 3 detalles (contrato del agente, dueños, privacidad Ley 1581, secretos, tests, commits), 4 diseño (estructura, capas, trazabilidad, escalabilidad, sobreingeniería). Úsala con `/pr-review` (rama actual contra main, antes del push) o `/pr-review <número>` (PR ya abierto, por ejemplo el del compañero).
metadata:
  short-description: Revisar una rama o PR de Nexora en cuatro pasadas
---

# PR Review · Nexora

## Cuándo usarla

- `/pr-review`: revisa la **rama actual contra `origin/main`** antes de hacer push. Es el modo normal: un PR no se abre hasta que esta revisión sale limpia.
- `/pr-review <número>`: revisa un PR ya abierto en `CamiloCalder0n/nexora-security` (por ejemplo, Santiago revisa el de Cami o al revés). Con `--comentar` deja los hallazgos como comentarios en las líneas del PR, en el formato de abajo, para que `/address-review` los pueda corregir.

Esta skill **no corrige nada**: solo reporta. Los arreglos los hace quien la invoca (o `/address-review` en modo PR).

## Preparar el diff

```bash
git fetch origin main
# modo local
git diff origin/main...HEAD --stat
git diff origin/main...HEAD
git log origin/main..HEAD --format='%h %an <%ae> | %s%n%b'
# modo PR
gh pr view <n> -R CamiloCalder0n/nexora-security --json title,body,headRefName,author,files,commits
gh pr diff <n> -R CamiloCalder0n/nexora-security
```

Lee primero `CLAUDE.md`, `docs/CONTRATO_API_AGENTE.md` y la guía del día (`docs/guias/`) que corresponde a la rama. No leas `vendor/`, `node_modules/` ni `composer.lock` completos.

## Pasada 1 · Limpieza (código basura y reutilización improductiva)

Pregunta en cada archivo cambiado: *¿esto tiene que existir?*

- Código muerto: funciones, clases, imports, variables, rutas o `use` que nadie usa; `dd()`, `dump()`, `var_dump`, `print()` de depuración, `console.log`, bloques comentados.
- Duplicación: lógica copiada en dos sitios en vez de un Service o helper; constantes mágicas repetidas (umbrales, ventanas, cooldown) que deberían venir de `config/` o `.env`.
- Reutilización improductiva: abstracciones que solo tienen un uso y no lo justifican (interfaces sin puerto real, wrappers que solo delegan), o lo contrario, reimplementar algo que Laravel / Python ya traen (validación a mano en vez de Form Request, `json_encode` a mano en vez de Resource, reintentos a mano cuando hay `$backoff` en el Job).
- Archivos que sobran: `.gitkeep` en carpetas que ya tienen archivos, scaffolding de Laravel sin usar (`ExampleTest`, `welcome.blade.php` si ya hay otra vista), archivos generados.
- Comentarios obvios (regla 5 de CLAUDE.md) y nombres mezclados sin razón (dominio en español: `Zona`, `Alerta`, `RegistroPorteria`).
- Dependencias nuevas en `composer.json` / `requirements.txt` que no se usan.
- Ineficiencias claras: N+1 en Eloquent, consultas dentro de bucles, cargar colecciones enteras de Mongo para contar.

## Pasada 2 · Bugs (¿hace lo que dice, en todos los casos?)

La corre, igual que la pasada 4, alguien que no escribió el código. Leer cada cambio buscando entradas concretas que den un resultado incorrecto, un 500 o datos inconsistentes. Cada hallazgo lleva su escenario: *con esta entrada / este estado → pasa esto*.

- Lógica: condiciones invertidas, off-by-one, comparaciones estrictas con tipos que cambian (Redis y `.env` devuelven texto), `null` no contemplado, zonas horarias (`America/Bogota` vs UTC), franjas que cruzan la medianoche.
- Bordes: listas vacías, límites exactos de la validación, valores máximos, cadenas raras (unicode, muy largas), ids que no existen.
- Concurrencia: dos peticiones o dos workers a la vez (doble creación, carreras entre leer y escribir, locks que vencen a mitad de la operación, `ShouldBeUnique`), idempotencia de jobs y de reintentos.
- Fallas: qué pasa si Redis, Mongo, PostgreSQL o la API externa fallan a mitad de camino; si la operación queda a medias, si el reintento duplica o pierde algo, si una excepción se traga sin log.
- Estados: transiciones no permitidas (alerta cerrada que se atiende), recursos inactivos (cámara, zona, usuario) que se usan igual.

Un bug sospechado se confirma con un test que falle antes del arreglo; si no se puede reproducir, se marca `(dudoso)`.

## Pasada 3 · Detalles (lo que se pudo escapar)

**Contrato del agente** (`docs/CONTRATO_API_AGENTE.md`) — si la rama toca `agente/` o `Modules/Monitoreo`:
- Rutas exactas: `POST /api/v1/agente/login`, `GET /agente/camaras`, `POST /agente/eventos`, `POST /agente/camaras/{id}/heartbeat`.
- Nombres de campos iguales: `uuid`, `camara_id`, `tipo_objeto` (`persona`/`vehiculo`), `cantidad` 1–50, `confianza` 0–1, `fecha_hora` ISO con `-05:00`, `ruta_evidencia` opcional.
- Códigos: 202 recibido, 200 duplicado, 204 heartbeat, 401, 403, 404, 422, 429 con `Retry-After`. Formato `{ "data": { ... } }`.
- El agente: 202/200 borra de la cola, 401 relogin, 422 a `rechazados` sin reintentar, 429 espera `Retry-After`, 5xx/sin red backoff 2, 4, 8… máx 60 s.
- Si el contrato cambió, el `.md` del contrato se actualizó en el mismo PR y se avisó al otro.

**Dueños** (regla 4): Cami → `Modules/{Identidad,Porteria,Analitica}`; Santiago → `agente/`, `Modules/{Monitoreo,Alertas}`, Docker de producción. Si el diff toca lo del otro o archivos compartidos (`composer.json`, `bootstrap/`, `config/`, `docker-compose.yml`, `routes/`), el PR lo dice en la descripción.

**Privacidad (HU26/HU27, Ley 1581)**: el agente no reconoce caras ni guarda video; la imagen de evidencia queda en disco local y a la API solo va la ruta. Telegram solo texto (HU37). Nada de biometría.

**Secretos y archivos prohibidos** (regla 3): ni `.env`, tokens, contraseñas reales, URLs de BD, `*.mp4`, `*.pt`, `*.db`, `evidencias/`, `vendor/`, `node_modules/`.
```bash
git diff origin/main...HEAD --name-only | grep -E '\.env$|\.mp4$|\.pt$|\.db$|evidencias/|vendor/|node_modules/'
git diff origin/main...HEAD | grep -nE '(TOKEN|SECRET|PASSWORD|API_KEY)\s*=\s*[^$ ]{6,}|mongodb\+srv://|postgres(ql)?://[^ ]*:[^ ]*@'
```

**Tests y verificación** (correr, no suponer):
```bash
# backend
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint --test
# agente
cd agente && .venv/bin/pytest -q
```
Cada regla nueva de la guía tiene su test (por ejemplo: 202, 200 duplicado, 422, 401, 403, 429; filtro de persistencia y cooldown; cola que sobrevive al reinicio).

**Git** (regla 8 y flujo):
- Rama `feature/KAN-xx-descripcion` (o `chore/…`, `fix/…`).
- Commits en Conventional Commits en español con clave Jira: `feat(alertas): supresión de repetidas KAN-8`.
- Autor de todos los commits = el dueño de la rama (Santiago Ruiz o Cami), nunca Claude.
- **Cero rastro de IA**: ni `Co-Authored-By: Claude`, ni "Generated with Claude Code", ni emojis de robot, ni menciones a IA en commits, PR o comentarios de código.
```bash
git log origin/main..HEAD --format='%an <%ae>%n%B' | grep -niE 'claude|anthropic|co-authored|generated with|🤖'
```

**Cumplimiento de la guía**: cada paso de la guía de la rama está hecho o el PR dice qué quedó pendiente; las HU que el PR dice cubrir realmente se cumplen.

## Pasada 4 · Diseño (¿está bien construido y no sobra nada?)

La corre alguien que **no** escribió el código (otro revisor o un subagente sin el contexto del autor), leyendo el diff completo y la guía o las HU que cubre.

**Arquitectura** (ArqSoft y Backend):
- Capas: Controller → Service (`Application/`) → Repository (`Infrastructure/`) → Model. Nada de lógica de negocio en controllers ni en jobs.
- Un módulo **no** usa modelos de otro módulo; usa su Service, su puerto (interfaz en `Domain/`) o un evento de Laravel.
- Validación en Form Requests, salida en API Resources, rutas con nombre (`api.v1.agente.*`).
- Lo pesado va a cola (Job) y las colas tienen `tries`/`backoff`.
- PostgreSQL para relacional, MongoDB para eventos/alertas/bitácora, Redis para colas/caché/rate limit. Mongo con `schema_version` y los nombres de campos de BD2.

**Estructura y diseño**: clases cohesivas con una responsabilidad clara; nada de métodos largos que mezclan validación, reglas, persistencia y textos (código espagueti); el dominio no depende de modelos Eloquent de otro módulo; los servicios exponen métodos con intención (`marcarExplicado`) y no setters genéricos (`marcar(array $extra)`); la misma regla o constante vive en un solo lugar.

**Sobreingeniería** (se reporta con la misma fuerza que lo que falta): interfaces con una sola implementación que nadie sustituye, métodos que solo reenvían la llamada, configuración que nadie va a cambiar, opciones y comandos sin HU, reimplementar lo que Mongo, PostgreSQL o Laravel ya hacen solos. Pregunta: *¿con la mitad del código se cumple la misma HU?*

**Escalabilidad**: índices para cada consulta que se hace (sobre todo las que corren en cada evento o alerta), consultas acotadas, N+1, trabajo lento fuera de la petición (cola), locks cortos, claves de caché con TTL, crecimiento sin límite (archivos en disco, colecciones sin TTL, colas locales).

**Trazabilidad**: cada función nueva sale de una HU o paso de la guía; los commits llevan la clave Jira correcta (si un commit cubre dos HU, lleva las dos); el PR dice qué HU cubre y qué trabajo técnico de soporte no tiene HU. Lo que no tiene HU ni hace falta para una, sobra.

## Formato de los hallazgos

Cada hallazgo, en una línea (y así va el comentario en el PR con `--comentar`):

```
[severidad] [pasada] archivo:línea — qué está mal. Sugerencia: cómo arreglarlo.
```

- Severidad: `bloqueante` (bug con escenario concreto, rompe contrato, test, seguridad, privacidad, secreto, rastro de IA), `importante` (arquitectura, sobreingeniería clara, falta un índice de una consulta frecuente, duplicación clara, falta un test de la guía), `menor` (estilo, nombres, comentario sobrante).
- Pasada: `limpieza`, `bugs`, `detalle` o `diseño`.
- Solo hallazgos verificados leyendo el código; si no estás seguro, márcalo `(dudoso)` y explica qué habría que revisar.

## Resultado

```text
REVIEW_RESULT rama=<rama|#pr> limpieza=<n> bugs=<n> detalle=<n> diseño=<n> bloqueantes=<n> veredicto=<listo|corregir>

| Severidad | Pasada | Archivo:línea | Hallazgo |
| --------- | ------ | ------------- | -------- |

Verificación: php artisan test <ok|falla|no aplica> · pint <ok|falla|no aplica> · pytest <ok|falla|no aplica>
```

`veredicto=listo` solo si no hay bloqueantes ni importantes **y** las verificaciones pasaron. En el flujo de Santiago hacen falta **dos rondas seguidas** con `listo` (la segunda después de aplicar los arreglos de la primera) y después la capa 3, `/validar-flujos`, en verde, antes de hacer push y abrir el PR. Si la capa 3 falla, se vuelve a empezar desde aquí. Ver `/pr-autopilot`.

Para publicar comentarios en un PR (`--comentar`):
```bash
gh api repos/CamiloCalder0n/nexora-security/pulls/<n>/comments \
  -f body="[importante] [detalle] ... Sugerencia: ..." \
  -f commit_id="$(gh pr view <n> -R CamiloCalder0n/nexora-security --json headRefOid -q .headRefOid)" \
  -f path=<archivo> -F line=<línea> -f side=RIGHT
```
