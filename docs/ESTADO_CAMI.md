# Estado de Cami · 9 de noviembre de 2026

## Qué quedó

| Paso | Resultado |
|---|---|
| A · Merge de main | En las dos ramas (`aff4458`, `8ce1bdf`). Prueba de punta a punta con el agente en `--simular`: la zona con ingreso en portería queda **explicada** y la zona sin ingreso **crea alerta** (las siguientes se agrupan). Sin errores 500 en la ingesta. |
| B · PR de KAN-13 | Push de `feature/KAN-13-porteria-api` y PR **#12** "feat: reglas de portería y API v1" contra `main`. |
| C · Panel (KAN-15) | 2b: CRUD de zonas, residentes, autorizaciones y usuarios. 2c: registro de ingresos y salidas, autorizaciones vigentes, mis autorizaciones y mis visitas. 2d: dashboard (ingresos de PostgreSQL y alertas por zona de la colección `alertas`, solo lectura) y **Cola de alertas** del vigilante (Santiago no tiene vista; la hice en Analítica y quedó en el menú). Filtros `estado` y `tipo` → 422. Revisión final del frontend con 15 hallazgos, todos arreglados. Un commit por bloque, cada uno con su CHECK. |
| D · Postman | `postman/Nexora.postman_collection.json` y `postman/Nexora.postman_environment.json`: 30 requests y 38 pruebas (200, 201, 202, 204, 401, 403, 404, 409, 422) en verde con newman. Sin contraseñas guardadas. |
| E · Matriz | `docs/MATRIZ_CUMPLIMIENTO.md`, una tabla por materia con hecho/pendiente y dónde se cumple. |
| F · PR de KAN-15 | Push de `feature/KAN-15-panel-web` y PR **#13** "feat: panel web por roles y dashboard" contra `feature/KAN-13-porteria-api` (el #12 aún no está mergeado). |

## Último CHECK (rama `feature/KAN-15-panel-web`)

1. `migrate:fresh --seed`: OK
2. `php artisan test`: **296 tests en verde** (1265 aserciones), incluidos los de Santiago
3. `pint --test`: OK
4. Archivos de carpetas prohibidas: ninguno, salvo lo de **zonas** en `Modules/Monitoreo` (ver abajo)
5. Mensaje del último commit: sin firmas

## PR

- #12 · https://github.com/CamiloCalder0n/nexora-security/pull/12
- #13 · https://github.com/CamiloCalder0n/nexora-security/pull/13

## Fallas en módulos de Santiago (no las toqué)

- **CI rojo en el job Backend:** `ConnectionTimeoutException` contra MongoDB en los tests que usan Mongo. Pasa también en `main` y lo arregla su PR #10 (abierto). En local todo pasa.
- **Idempotencia del agente sin el paso de instalación de MongoDB:** si no se corre `php artisan nexora:mongo-instalar`, la colección `eventos` queda sin el índice único `uuid_unico` y un evento reenviado responde 202 "recibido" en lugar de 200 "duplicado". El paso está en el README, pero `migrate:fresh` no lo hace. Sugerencia para Santiago: correrlo en el arranque del contenedor o advertirlo en `/up`.
- **Atender/Cerrar alertas desde el panel:** resuelto al fusionar `main` en el #13. La cola lee con `AlertaService::activas()` (el mismo caso de uso de `GET /api/v1/alertas`) y los botones llaman a `atender`/`cerrar` con la sesión del panel (`statefulApi()` activo en `bootstrap/app.php`).

## Qué necesito de ti

1. ~~Revisar y mergear el #12~~ (hecho; el #13 ya tiene `main` fusionado).
2. **Confirmar lo de zonas en Monitoreo:** el CRUD web (`Http/Controllers/Web/ZonaController.php`, `Resources/views/zonas/`, `Routes/web.php`) y el filtro de la API viven en el módulo de Santiago. Lo tomé como parte de "la API de zonas que ya es mía" porque tus rutas sin commit ya lo ponían ahí; que Santiago lo revise en el #13.
3. ~~`statefulApi()` en `bootstrap/app.php`~~ (activado en el #13).
4. **La checklist del frontend del profe y la Guía 03 no están en el repo.** Reconstruí 2b, 2c y 2d desde tus rutas sin commit y el plan maestro, y la revisión usó una checklist estándar. Si tienes la del profe, pásamela para cruzarla.
5. ~~Usuarios desactivados con la sesión web abierta~~: resuelto con el middleware `CerrarSesionInactiva` de Identidad en los grupos `web` y `api`; cierra la sesión en la siguiente petición.
6. **Asignar la cuenta de usuario a un residente** desde el panel requiere un método en un Service de Identidad. Hoy solo se muestra si ya tiene cuenta.
7. Pendientes de la matriz que no son código: documentos de `docs/arquitectura`, `docs/bd2`, `docs/ingsoft` y `docs/backend`, scripts de `database/mongo`, k6, etiquetas y entrevista.
