# Guía 01 · Cami · Repo, esqueleto Laravel modular y Docker

**Día:** jueves 5 · **Dueño:** Cami · **Jira:** épica Infraestructura
**Objetivo del día:** que exista el repo, que `docker compose up` levante Laravel + PostgreSQL + MongoDB + Redis, y que estén creados los 5 módulos vacíos para que mañana cada uno trabaje en lo suyo sin chocar.

> Antes de esta guía: leer `PLAN_MAESTRO_NEXORA.md` y `docs/CONTRATO_API_AGENTE.md`.

---

## Reglas para Claude Code (aplican en todo el proyecto)

1. **Un paso a la vez.** Al terminar cada paso: mostrar qué cambió, correr la verificación y **PARAR** hasta que Cami diga "sigue".
2. **Claude Code NO hace `git commit`, `git push` ni `git tag`.** Al final de cada paso propone el mensaje de commit y Cami lo ejecuta.
3. **Nunca** versionar `.env`, videos, imágenes de evidencia, `vendor/`, `node_modules/` ni tokens. Los secretos solo en `.env` local o en el panel de Render.
4. Trabajar **solo** en las carpetas del paso. No tocar `agente/` (es de Santiago).
5. Código y comentarios en español donde sea natural (nombres de dominio en español: `Zona`, `Alerta`, `RegistroPorteria`). Sin comentarios obvios.
6. Si algo del plan no cuadra con lo que encuentras (versión, paquete, error), **avisar antes de improvisar**.
7. Economía de tokens: no leer `vendor/` ni archivos enormes; usar `grep`/`ls`.
8. **Sin rastro de IA en Git:** los mensajes de commit y las descripciones de PR que proponga Claude Code **no llevan** `Co-Authored-By: Claude…`, ni "Generated with Claude Code", ni emojis de robot, ni ninguna mención a Claude o a IA. Solo el mensaje en Conventional Commits con la clave Jira.

---

## Paso 0 · Crear el repo en GitHub (lo hace Cami a mano, 5 min)

1. GitHub → **New repository** → nombre `nexora-security` → **Public** → sin README, sin .gitignore, sin licencia.
2. Settings → Collaborators → **Add people** → Santiago (que acepte la invitación).
3. En tu Mac:
   ```bash
   cd ~/Desktop   # o donde guardes tus proyectos
   git clone https://github.com/CamiloCalder0n/nexora-security.git
   cd nexora-security
   mkdir -p docs/guias
   ```
4. Copia en el repo: `PLAN_MAESTRO_NEXORA.md` (raíz), `docs/CONTRATO_API_AGENTE.md`, `docs/guias/GUIA_01_CAMI_ESQUELETO.md` y `docs/guias/GUIA_01_SANTIAGO_AGENTE.md`.
5. Abre la carpeta en VS Code, abre la terminal y arranca Claude Code:
   ```
   claude
   ```
   Prompt:
   ```
   Lee PLAN_MAESTRO_NEXORA.md, docs/CONTRATO_API_AGENTE.md y docs/guias/GUIA_01_CAMI_ESQUELETO.md.
   Sigue la guía desde el Paso 1, un paso a la vez, respetando las reglas. No hagas commits.
   ```

---

## Paso 1 · Estructura del monorepo + CLAUDE.md

Crear:

```
nexora-security/
├─ backend/              (vacío por ahora, el Paso 2 crea Laravel aquí)
├─ agente/               (solo un .gitkeep; lo llena Santiago)
├─ database/mongo/       (.gitkeep; aquí irán los scripts 01, 02, 03 de BD2)
├─ postman/              (.gitkeep)
├─ docker/php/           (Dockerfile del Paso 3)
├─ docs/arquitectura/ docs/backend/ docs/bd2/ docs/ingsoft/ docs/guias/
├─ .gitignore
├─ .claude/settings.json
├─ CLAUDE.md
└─ README.md
```

**`.gitignore` raíz** (además del que trae Laravel): `.env`, `.env.*` (menos `.env.example`), `.DS_Store`, `*.mp4`, `*.mov`, `agente/.venv/`, `agente/evidencias/`, `agente/*.db`, `agente/videos/`, `__pycache__/`, `*.pt`.

**`CLAUDE.md`** (lo leen las sesiones de Claude Code de los dos). Contenido:
- Qué es Nexora en 3 líneas y las 4 materias.
- Las 8 reglas de arriba (incluida la de **sin rastro de IA en commits ni PR**).
- Dueños: Cami → `backend/app/Modules/{Identidad,Porteria,Analitica}` y vistas del panel de esos módulos. Santiago → `agente/`, `backend/app/Modules/{Monitoreo,Alertas}`, Docker de producción y deploy. Archivos compartidos (`composer.json`, `bootstrap/`, `config/`, `docker-compose.yml`, `routes/`): avisar antes de tocarlos.
- Arquitectura: monolito modular; cada módulo con `Domain/`, `Application/` (Services), `Infrastructure/` (Repositories, adaptadores), `Http/` (Controllers Web y Api\V1, Requests, Resources). Un módulo **no** usa modelos de otro módulo directamente salvo por su Service o por eventos.
- Bases: PostgreSQL (relacional), MongoDB (eventos, alertas, bitácora), Redis (colas, caché).
- Ramas `feature/KAN-xx-descripcion`, commits Conventional en español con la clave Jira, PR revisado por el otro.
- Comandos útiles (los del Paso 6).

**README.md**: título, descripción corta, las 3 plataformas, cómo levantar en local (se completa en el Paso 6), integrantes.

**`.claude/settings.json`** (se sube al repo, así aplica para los dos):
```json
{
  "$schema": "https://json.schemastore.org/claude-code-settings.json",
  "attribution": {
    "commit": "",
    "pr": ""
  }
}
```
Esto le quita a Claude Code la línea de coautor en los commits y la firma en los PR.

**Verificación:** `tree -L 2 -a -I .git` (o `ls -R` corto). Dentro de Claude Code, `/status` debe mostrar "Project settings" en *Setting sources*.

**Commit (Cami):** `chore: estructura del monorepo y CLAUDE.md`

---

## Paso 2 · Crear Laravel 13 en `backend/`

Cami tiene PHP 8.4 y Composer en el Mac (de los talleres). Desde la raíz del repo:

```bash
composer create-project laravel/laravel backend "^13.0"
```

Luego, dentro de `backend/`:
- `.env.example`: `APP_NAME="Nexora Security"`, `APP_TIMEZONE=America/Bogota`, `APP_LOCALE=es`, y dejar listas las variables del Paso 3 (pgsql, mongodb, redis) **sin contraseñas reales**.
- Borrar `database/database.sqlite` si se creó (no usamos SQLite).
- En `config/app.php`: `timezone` y `locale` desde el `.env`.

**Verificación:** `cd backend && php artisan --version` muestra Laravel 13.

**Commit (Cami):** `feat(backend): crear proyecto Laravel 13`

---

## Paso 3 · Docker local: app + worker + PostgreSQL + MongoDB + Redis

**`docker/php/Dockerfile`** (sirve para local; Santiago lo adapta a producción después):
- Base `php:8.4-cli`.
- Paquetes de sistema: `git unzip libpq-dev libzip-dev libicu-dev libssl-dev`.
- Extensiones: `pdo_pgsql pgsql zip intl bcmath pcntl` con `docker-php-ext-install`, y **`mongodb`** con `pecl install mongodb && docker-php-ext-enable mongodb`.
- Composer copiado desde `composer:2`.
- `WORKDIR /var/www/backend`.

**`docker-compose.yml`** (raíz):

| Servicio | Imagen | Puerto | Comando / notas |
|---|---|---|---|
| `app` | build `docker/php` | `8000:8000` | `php artisan serve --host=0.0.0.0 --port=8000`; volumen `./backend:/var/www/backend` |
| `worker` | mismo build | — | `php artisan queue:work --tries=3 --backoff=5` |
| `scheduler` | mismo build | — | `php artisan schedule:work` |
| `pgsql` | `postgres:16-alpine` | `5432:5432` | db `nexora`, user `nexora`, pass `secret` (solo local); volumen `pgdata` |
| `mongo` | `mongo:8` | `27017:27017` | volumen `mongodata` |
| `redis` | `redis:7-alpine` | `6379:6379` | — |

`depends_on` con healthchecks para pgsql, mongo y redis.

**`.env` local** (copiado de `.env.example`, **no se sube**):
```
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=nexora
DB_USERNAME=nexora
DB_PASSWORD=secret

MONGODB_URI=mongodb://mongo:27017
MONGODB_DATABASE=nexora_security

REDIS_CLIENT=predis
REDIS_HOST=redis
REDIS_PORT=6379
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=database
```

**Verificación:**
```bash
docker compose up -d --build
docker compose ps            # todo "running" / "healthy"
docker compose exec app php -m | grep -E "mongodb|pdo_pgsql"
```

**Commit (Cami):** `chore(docker): entorno local con PostgreSQL, MongoDB y Redis`

---

## Paso 4 · Paquetes: Sanctum, MongoDB y Redis

Todo **dentro del contenedor** (así el Mac no necesita la extensión de Mongo):

```bash
docker compose exec app composer require mongodb/laravel-mongodb predis/predis
docker compose exec app php artisan install:api      # instala Sanctum y routes/api.php
```

- `config/database.php`: agregar la conexión `mongodb` (`driver => 'mongodb'`, `dsn => env('MONGODB_URI')`, `database => env('MONGODB_DATABASE')`).
- `bootstrap/app.php`: `shouldRenderJsonWhen` para `api/*` (como en el taller) y `$middleware->trustProxies(at: '*')` (para Render).
- `php artisan migrate`.

**Verificación** (los tres deben responder):
```bash
docker compose exec app php artisan migrate:status
docker compose exec app php artisan tinker --execute="dump(DB::connection('mongodb')->getMongoDB()->command(['ping'=>1])->toArray());"
docker compose exec app php artisan tinker --execute="dump(Illuminate\Support\Facades\Redis::ping());"
```

**Commit (Cami):** `feat(backend): instalar Sanctum y conectar MongoDB y Redis`

---

## Paso 5 · Estructura modular (los 5 bounded contexts)

Crear en `backend/app/Modules/` los módulos **Identidad, Porteria, Monitoreo, Alertas, Analitica**, cada uno con:

```
<Modulo>/
  Domain/                 (.gitkeep)
  Application/            (.gitkeep)   → aquí van los Services
  Infrastructure/
    Repositories/         (.gitkeep)
    Database/Migrations/  (.gitkeep)
  Http/
    Controllers/Web/      (.gitkeep)
    Controllers/Api/V1/   (.gitkeep)
    Requests/             (.gitkeep)
    Resources/            (.gitkeep)
  Routes/web.php          (grupo vacío: prefix según módulo, name 'panel.<modulo>.')
  Routes/api.php          (grupo vacío: prefix 'v1', name 'api.v1.<modulo>.')
  Resources/views/        (.gitkeep)
  <Modulo>ServiceProvider.php
```

Cada `ServiceProvider`:
- `loadMigrationsFrom(__DIR__.'/Infrastructure/Database/Migrations')`
- `loadViewsFrom(__DIR__.'/Resources/views', '<modulo>')` (ej. `porteria::ingresos.index`)
- registrar `Routes/web.php` con middleware `web` y `Routes/api.php` con prefijo `api` y middleware `api`.

Registrar los 5 providers en `bootstrap/providers.php`. En `composer.json` el namespace `App\Modules\` ya cae dentro de `App\` (no hace falta autoload extra; verificar).

**Verificación:** `docker compose exec app php artisan route:list` corre sin errores · `php artisan about` lista los providers.

**Commit (Cami):** `refactor(backend): estructura modular por bounded context`

---

## Paso 6 · Identidad mínima: roles, middleware y usuarios semilla

En `Modules/Identidad`:
- `Domain/Rol.php`: enum string `admin`, `vigilante`, `residente`, `consejo`, `agente`.
- Migración: agregar a `users` las columnas `rol` (string, index) y `activo` (boolean, default true).
- `User` (en `app/Models/User.php`, el de Laravel): cast `rol => Rol::class`, `HasApiTokens`.
- Middleware `EnsureRol` con alias `rol` → uso: `->middleware('rol:admin,consejo')`; responde 403 si no tiene el rol y 401 si no hay sesión/token.
- `Database/Seeders/UsuariosSeeder.php` (llamado desde `DatabaseSeeder`): un usuario por rol, correos `admin@nexora.test`, `vigilante@nexora.test`, `residente@nexora.test`, `consejo@nexora.test`, `agente@nexora.test`, contraseña `password` **solo para local**.
- Test `tests/Feature/Identidad/RolTest.php`: ruta de prueba protegida → 401 sin login, 403 con rol equivocado, 200 con el rol correcto.

Agregar al README la sección **Levantar en local**:
```bash
cp backend/.env.example backend/.env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan test
# http://localhost:8000
```

**Verificación:** `migrate:fresh --seed` sin errores y `php artisan test` en verde.

**Commit (Cami):** `feat(identidad): roles de usuario, middleware de rol y usuarios semilla KAN-34`

**Push (Cami):** `git push origin main`. Avísale a Santiago que ya puede hacer `git pull`.

---

## Paso 7 · Proteger `main` (Cami, a mano, 2 min)

GitHub → Settings → Branches → **Add branch protection rule** → `main` → marcar **Require a pull request before merging** (1 aprobación). Desde aquí todo entra por PR.

---

## Paso 8 · Jira (Cami, a mano, 10 min)

1. Crear épicas: **Identidad, Portería, Monitoreo, Alertas, Analítica, Infraestructura**.
2. Crear **Sprint 1 · 5–10 nov** y meter todas las HU marcadas como prototipo en la sección 6 del plan.
3. Asignar cada HU (Cami o Santiago) según la tabla del plan y ponerle la etiqueta `prototipo` o `futuro`.
4. Iniciar el sprint. Mover KAN-34 a **En curso**.
5. (Opcional) Instalar la app gratuita **GitHub for Jira** y conectar el repo, para que las ramas y commits con `KAN-xx` aparezcan en cada historia.

---

## Al terminar el día

- [ ] `docker compose up` levanta los 6 servicios.
- [ ] `migrate --seed` y `php artisan test` en verde.
- [ ] 5 módulos creados y registrados.
- [ ] `main` protegida; Santiago tiene acceso y ya hizo pull.
- [ ] Jira con sprint, épicas y responsables.
