# Nexora Security

Sistema de vigilancia para un conjunto residencial: un agente de borde con IA detecta presencia en las cámaras sin identificar personas, el backend la cruza con los registros de portería y, si nadie la explica, alerta al vigilante por Telegram.
Es un solo sistema y un solo repositorio para 4 materias: **Backend**, **Bases de Datos 2 (NoSQL)**, **Arquitectura de Software** e **Ingeniería de Software**.
Plan completo: `PLAN_MAESTRO_NEXORA.md`. Contrato con el agente: `docs/CONTRATO_API_AGENTE.md`. Guías por día: `docs/guias/`.

## Reglas

En estas reglas, "el dueño" es quien dirige la sesión (Cami o Santiago).

1. **Un paso a la vez.** Al terminar cada paso: mostrar qué cambió, correr la verificación y **PARAR** hasta que el dueño diga "sigue".
2. **Claude Code NO hace `git commit`, `git push` ni `git tag`.** Al final de cada paso propone el mensaje de commit y el dueño lo ejecuta.
3. **Nunca** versionar `.env`, videos, imágenes de evidencia, `vendor/`, `node_modules/` ni tokens. Los secretos solo en `.env` local o en el panel de Render.
4. Trabajar **solo** en las carpetas del paso y del dueño de la sesión (ver "Dueños"). No tocar lo del otro.
5. Código y comentarios en español donde sea natural (nombres de dominio en español: `Zona`, `Alerta`, `RegistroPorteria`). Sin comentarios obvios.
6. Si algo del plan no cuadra con lo que encuentras (versión, paquete, error), **avisar antes de improvisar**.
7. Economía de tokens: no leer `vendor/` ni archivos enormes; usar `grep`/`ls`.
8. **Sin rastro de IA en Git:** los mensajes de commit y las descripciones de PR **no llevan** `Co-Authored-By: Claude…`, ni "Generated with Claude Code", ni emojis de robot, ni ninguna mención a Claude o a IA. Solo el mensaje en Conventional Commits con la clave Jira.

## Dueños

- **Cami:** `backend/app/Modules/{Identidad,Porteria,Analitica}` y las vistas del panel de esos módulos.
- **Santiago:** `agente/`, `backend/app/Modules/{Monitoreo,Alertas}`, Docker de producción y deploy.
- **Archivos compartidos** (`composer.json`, `bootstrap/`, `config/`, `docker-compose.yml`, `routes/`): avisar antes de tocarlos.

## Arquitectura

Monolito modular. Cada módulo vive en `backend/app/Modules/<Modulo>/` con estas capas:

- `Domain/`: entidades, reglas puras e interfaces (puertos).
- `Application/`: Services (casos de uso).
- `Infrastructure/`: Repositories y adaptadores.
- `Http/`: Controllers (Web y `Api\V1`), Requests y Resources.

Un módulo **no** usa modelos de otro módulo directamente, salvo por su Service o por eventos.

## Bases de datos

- **PostgreSQL:** datos relacionales.
- **MongoDB:** eventos, alertas y bitácora.
- **Redis:** colas y caché.

## Git

- Ramas: `feature/KAN-xx-descripcion`.
- Commits: Conventional Commits en español con la clave Jira, ej. `feat(porteria): registrar ingreso KAN-6`.
- Todo cambio entra por Pull Request revisado por el otro.

## Comandos útiles

```bash
cp backend/.env.example backend/.env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan test
# http://localhost:8000
```
