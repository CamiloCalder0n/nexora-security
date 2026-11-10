# Matriz de cumplimiento · Nexora Security

Cada requisito de cada materia → dónde se cumple en el repositorio o en el sistema.
Los requisitos salen del plan maestro (`PLAN_MAESTRO_NEXORA.md`, secciones 1, 2, 4 y 8). Antes de entregar hay que contrastarlos con el enunciado de cada profe.

Estado al 10 de noviembre de 2026, rama `chore/KAN-43-auditoria-final` (PR #1 a #14 ya mergeados en `main`). **Hecho** = está en el código y tiene tests o se probó; **Pendiente** = falta. La auditoría final contrastó esta matriz con el código: hallazgos y riesgos de la demo en `docs/AUDITORIA_FINAL.md`; lo de Santiago en `docs/HALLAZGOS_SANTIAGO.md`.

Las rutas de `backend/` se abrevian así: `Modules/` = `backend/app/Modules/`, `tests/` = `backend/tests/`.

---

## Backend

| Requisito | Estado | Dónde se cumple |
|---|---|---|
| Laravel 13 (PHP 8.4) como monolito modular | Hecho | `backend/`, `Modules/{Identidad,Porteria,Monitoreo,Alertas,Analitica}` |
| Capas Controller → Service → Repository → Model | Hecho | `Modules/*/Http/Controllers`, `Modules/*/Application`, `Modules/*/Infrastructure/Repositories`, `Modules/*/Domain/Models` |
| Migraciones con relaciones 1:1, 1:N y N:M | Hecho | `Modules/Porteria/Infrastructure/Database/Migrations` (residentes 1:1 users, unidad_residente N:M, autorizaciones y registros 1:N), `Modules/Monitoreo/Infrastructure/Database/Migrations` (franjas y cámaras 1:N zona) |
| Factories y seeders de demo | Hecho | `Modules/*/Infrastructure/Database/{Factories,Seeders}`; `php artisan migrate:fresh --seed` |
| Validación con Form Requests y mensajes en español | Hecho | `Modules/*/Http/Requests`, `backend/lang/es/validation.php` |
| Filtros de listados validados (estado y tipo → 422) | Hecho | `FiltroZonasRequest`, `FiltroAutorizacionesRequest`, `FiltroIngresosRequest`, `FiltroMisAutorizacionesRequest`; tests `test_index_filtro_invalido_es_422` y `test_filtro_invalido_es_422` |
| API REST v1 con rutas con nombre y Resources | Hecho | `Modules/*/Routes/api.php` (`api.v1.*`), `Modules/*/Http/Resources` |
| Autenticación con Sanctum (tokens) y login web por sesión | Hecho | `Modules/Identidad/Http/Controllers/Api/V1/AuthController.php`, `Modules/Identidad/Http/Controllers/Web/LoginController.php` |
| Seguridad del login: límite de intentos por cuenta e IP (429), tokens de personas que vencen a las 12 h, agente solo por su login | Hecho | `RateLimiter::for('login')` en `Modules/Identidad/IdentidadServiceProvider.php`, `AutenticacionService::emitirToken`; tests en `tests/Feature/Identidad/AuthApiTest.php` |
| Roles y autorización (middleware + policies) | Hecho | `Modules/Identidad/Http/Middleware/EnsureRol.php`, `Modules/Porteria/Http/Policies/AutorizacionPolicy.php`, tests `tests/Feature/Identidad/RolTest.php` |
| Códigos HTTP 200, 201, 204, 401, 403, 404, 409, 422 (y 202, 429 del agente) | Hecho | tests `tests/Feature/{Porteria/AutorizacionApiTest,Monitoreo/ZonaApiTest,Identidad/AuthApiTest,Monitoreo/IngestaAgenteTest}.php`; carpeta "5. Errores esperados" de Postman |
| Paginación (10) y búsqueda `?search=` | Hecho | repositorios `paginar()`; componente `backend/resources/views/components/tabla.blade.php` |
| Panel web Blade + Bootstrap por roles (frontend implementado) | Hecho | `backend/resources/views/layouts`, vistas en `Modules/*/Resources/views` |
| CRUD web: zonas, residentes y autorizaciones; usuarios solo listado y activar/desactivar | Hecho (usuarios parcial: no se crean ni editan cuentas desde el panel, HU15) | `Modules/Monitoreo/Http/Controllers/Web/ZonaController.php`, `Modules/Porteria/Http/Controllers/Web/{ResidenteController,AutorizacionController}.php`, `Modules/Identidad/Http/Controllers/Web/UsuarioController.php`; tests `tests/Feature/Panel/` |
| Registro de ingresos y salidas (vigilante) y pantallas del residente | Hecho | `Modules/Porteria/Http/Controllers/Web/{IngresoController,MisAutorizacionesController,MisVisitasController}.php`; tests `tests/Feature/Panel/{IngresosPanelTest,ResidentePanelTest}.php` |
| Dashboard con gráficos | Hecho | `Modules/Analitica` (ingresos por día de PostgreSQL y alertas por zona de MongoDB); `tests/Feature/Panel/DashboardTest.php` |
| Páginas de error propias (403, 404, 419, 500, 503) | Hecho | `backend/resources/views/errors/` |
| Tests automáticos en verde | Hecho | `docker compose exec app php artisan test` (331 tests en esta rama) |
| Estilo de código (Pint) | Hecho | `vendor/bin/pint --test` |
| Colección Postman con entorno, sin secretos | Hecho | `postman/Nexora.postman_collection.json`, `postman/Nexora.postman_environment.json` (30 requests, 38 aserciones en verde con newman pasando `--env-var clave_semilla=password`; sin eso todo da 401) |
| Cliente externo de la API (agente de borde) | Hecho | `agente/` (Santiago); contrato `docs/CONTRATO_API_AGENTE.md` |
| Docker local con un comando | Hecho | `docker-compose.yml`, `docker/php/Dockerfile` |
| Despliegue en Render | Hecho | `docker/produccion/`, `render.yaml`, `docs/DESPLIEGUE.md`. Comprobado el 10 de noviembre contra la URL pública: `/up` 200, errores JSON sin traza (`APP_DEBUG=false`), cookies `secure` y `httponly`, webhook sin secreto 404 |
| Documento arquitectura.pdf (v2) y evidencias | Pendiente | `docs/backend/` (vacío) |
| Video de la entrevista con el celador | Pendiente | guion en `PLAN_MAESTRO_NEXORA.md` §9; se graba el lunes 9 |
| Etiquetas v0.1 … v1.0 | Hecho hasta v0.4 / Pendiente v1.0 | existen `docs`, `v0.1`, `v0.2`, `v0.3` y `v0.4`; falta `v1.0` al entregar |
| Datos personales según el rol (HU10, Ley 1581) | Hecho | el residente solo ve sus unidades (`AutorizacionPolicy`); el consejo ve la bitácora de ingresos con el documento del visitante enmascarado (`RegistroPorteria::documentoEnmascarado`, test `test_el_consejo_ve_el_documento_enmascarado`) |
| Novedades y entrega de turno (HU20, KAN-24) | Pendiente | no hay código; quedó fuera del prototipo |
| Exportar evidencia CSV/PDF (HU19, KAN-23) | Pendiente | era "si sobra tiempo"; no se hizo |
| Datos de demo reproducibles | Hecho | `php artisan nexora:demo-reset` (`backend/app/Console/Commands/ReiniciarDemo.php`; se niega en producción, test `tests/Feature/Support/ReiniciarDemoTest.php`); guion en `docs/DEMO.md` |

## Bases de Datos 2 (NoSQL)

| Requisito | Estado | Dónde se cumple |
|---|---|---|
| MongoDB para eventos, alertas y bitácora | Hecho | `Modules/Monitoreo/Infrastructure/Mongo/ColeccionEventos.php`, `Modules/Alertas/Infrastructure/Mongo/{ColeccionAlertas,ColeccionBitacora}.php` |
| Validación de esquema (`$jsonSchema`) | Hecho | métodos `validador()` de cada colección; `php artisan nexora:mongo-instalar` (`backend/app/Support/Mongo/InstalarMongo.php`) |
| Índices (únicos, compuestos) | Hecho | `uuid_unico` en eventos (idempotencia), `estado_fecha` y `zona_estado` en alertas |
| TTL (eliminar datos al vencer su plazo, HU33) | Hecho | índice `ttl_365_dias` en `ColeccionEventos.php` |
| Patrón Subset (últimas evidencias) y referencias extendidas | Hecho | `evidencias_recientes` y `zona {zona_id, nombre}` en `ColeccionAlertas.php` |
| Bitácora con hash encadenado (HU28) | Hecho | `Modules/Alertas/Application/BitacoraService.php`, comando `VerificarBitacora` |
| Consultas de agregación usadas por la aplicación | Hecho | `Modules/Analitica/Infrastructure/Repositories/AlertasLecturaRepository.php` (alertas por zona y % de falsas alarmas) |
| Persistencia políglota justificada (PostgreSQL + MongoDB + Redis) | Hecho (en código) / Pendiente (ADR) | `PLAN_MAESTRO_NEXORA.md` §4.3; falta el ADR en `docs/arquitectura/` |
| Scripts MongoDB 01, 02, 03 de la materia | Pendiente | `database/mongo/` está vacío |
| Documento PROYECTO_CURSO v1.04 y presentación | Pendiente | `docs/bd2/` está vacío |

## Arquitectura de Software

| Requisito | Estado | Dónde se cumple |
|---|---|---|
| Bounded contexts (5 módulos) | Hecho | `Modules/{Identidad,Porteria,Monitoreo,Alertas,Analitica}` |
| Capas hexagonales con puertos y adaptadores | Hecho | puerto `Modules/Porteria/Application/Contracts/ConsultaRegistrosPorteria.php` implementado por `RegistroPorteriaRepository` y usado por `Modules/Alertas/Application/Reglas/SinRegistroEnPorteria.php` |
| Comunicación entre contextos por eventos de dominio en cola (Redis) | Hecho | `EventoRegistrado` → `CorrelacionJob` → `AlertaCreada` → `NotificarTelegramJob`; `IngresoRegistrado` |
| 3 plataformas de entrada (panel web, Telegram, agente de IA) | Hecho | panel Blade, `Modules/Alertas/Application/Telegram`, `agente/` |
| Resiliencia: 202 + cola, rate limit 429, idempotencia por uuid | Hecho | `Modules/Monitoreo/Http/Controllers/Api/V1/AgenteController.php`, `RateLimiter::for('agente')` en `MonitoreoServiceProvider`; tests `IngestaAgenteTest` |
| Resiliencia del agente sin internet (cola local) | Hecho | `agente/nexora_agente/cola.py` |
| Diagnóstico: `/up` y heartbeat de cámaras | Hecho | `bootstrap/app.php` (`health: '/up'`), `MarcarCamarasSinSenal` |
| Modelo de lectura que no tumba el panel si MongoDB falla | Hecho | `Modules/Analitica/Application/DashboardService.php`; test `DashboardTest` |
| Prueba de carga con k6 | Hecho | `pruebas/k6/pico-eventos.js` y resultados en `pruebas/k6/README.md` (PR #11) |
| Documento: problema, C4 niveles 1-2-3, ADRs, declaración de uso de IA | Pendiente | `docs/arquitectura/` está vacío |
| Código en repositorio público | Hecho | `github.com/CamiloCalder0n/nexora-security` |

## Ingeniería de Software

| Requisito | Estado | Dónde se cumple |
|---|---|---|
| Historias de usuario en Jira (KAN) con responsable | Hecho | Jira KAN; tabla de HU en `PLAN_MAESTRO_NEXORA.md` §6 |
| Trazabilidad HU ↔ código (clave Jira en ramas y commits) | Hecho | ramas `feature/KAN-xx-*`, commits Conventional con `KAN-xx` |
| Flujo de Git con Pull Requests revisados | Hecho | PR #1 a #13 en GitHub; CI en `.github/workflows/ci.yml` |
| Integración continua (tests, estilo, higiene) | Hecho | `.github/workflows/ci.yml` (Santiago) |
| Prototipo funcional | Hecho | panel + API + agente; guion de la demo en `docs/DEMO.md`, probado paso a paso en local el 10 de noviembre (falta el aviso "Llegó tu visita" al residente, HU17: ver `docs/HALLAZGOS_SANTIAGO.md`) |
| Pruebas automatizadas como práctica de calidad | Hecho | `backend/tests/` (PHPUnit) y `agente/tests/` (pytest) |
| Documento final (proceso, Pressman/PMBOK, Visión RUP, arquitectura) | Pendiente | `docs/ingsoft/` está vacío |
| Jira al día (HU en "Listo") | Pendiente | revisar al cierre |
| Entrevista con el usuario (celador) | Pendiente | guion en `PLAN_MAESTRO_NEXORA.md` §9 |
