# Guía 02 · Cami · Modelo de datos (PostgreSQL), Portería y API v1

**Día:** sábado 7 · **Dueño:** Cami · **Rama:** `feature/KAN-6-datos-porteria`
**Jira:** KAN-6 (HU02), KAN-20 (HU16), KAN-13 (HU09), KAN-22 (HU18), KAN-14 (HU10), KAN-19 (HU15)
**Requisito:** la Guía 01 terminada y en `main`.

**Objetivo:** dejar **todo el esquema de PostgreSQL** (de todos los módulos) con modelos, relaciones, factories y seeders, las reglas de Portería y la API v1 de **zonas** y **autorizaciones** con Resources. Esto es la etiqueta **`v0.1`** de Backend.

> **Cambio de dueños:** para no chocar, **Cami hace todas las migraciones y modelos de PostgreSQL** (también `zonas`, `franjas` y `camaras`, aunque sean de Monitoreo). Santiago hace MongoDB y la lógica de Monitoreo y Alertas encima de estos modelos. **Haz el PR de los Pasos 1 a 3 apenas estén**, porque Santiago los necesita para arrancar.

Las reglas de la Guía 01 siguen vigentes, incluida la de commits y PR sin rastro de IA.

---

## Paso 1 · Migraciones de PostgreSQL

Cada migración va en la carpeta de su módulo (`Modules/<Modulo>/Infrastructure/Database/Migrations`).

| Tabla | Módulo | Campos | Relación |
|---|---|---|---|
| `users` (alter) | Identidad | + `telefono` null, `telegram_chat_id` null, `telegram_codigo` (6 chars, único, null) | — |
| `unidades` | Portería | `torre` (string 5), `numero` (string 10), unique(torre, numero) | — |
| `residentes` | Portería | `user_id` FK **unique** null, `nombre` 120, `documento` 20 unique, `telefono` 20 null, `tipo_residente` (propietario / arrendatario) | **1:1** con users |
| `unidad_residente` | Portería | `unidad_id` FK, `residente_id` FK, `es_titular` bool, unique(unidad, residente), cascade | **N:M** (pivote) |
| `zonas` | Monitoreo | `nombre` 80 unique, `tipo` (acceso_peatonal, acceso_vehicular, parqueadero, perimetro, zona_comun, servicio), `prioridad_base` (1–3), `ventana_correlacion_min` (def. 10), `ventana_silencio_min` (def. 5), `activa` bool | — |
| `franjas` | Monitoreo | `zona_id` FK cascade, `dia_semana` (0–6, null = todos), `hora_inicio` time, `hora_fin` time | **1:N** zona → franjas |
| `camaras` | Monitoreo | `zona_id` FK, `nombre` 40 unique, `url_stream` 255 null, `estado` (activa / sin_senal / inactiva), `ultima_conexion` timestamp null | **1:N** zona → cámaras |
| `autorizaciones` | Portería | `unidad_id` FK, `residente_id` FK, `visitante_nombre` 120, `visitante_documento` 20, `placa` 10 null, `valida_desde`, `valida_hasta`, `estado` (vigente / usada / anulada / expirada), `usada_at` null | **1:N** unidad → autorizaciones |
| `registros_porteria` | Portería | `tipo_ingreso` (visitante / domiciliario / vehiculo / contratista), `nombre_visitante` 120, `documento` 20, `placa` 10 null, `cantidad_personas` (def. 1), `unidad_id` FK, `zona_id` FK (por dónde entra), `registrado_por` FK users, `autorizacion_id` FK **unique** null, `fecha_ingreso`, `fecha_salida` null. Índice `(zona_id, fecha_ingreso)` | **1:N** unidad, zona, vigilante · **1:1** con autorización |

Las `franjas` cruzan la medianoche cuando `hora_fin < hora_inicio` (ej. 18:00 → 06:00).

**Verificación:** `docker compose exec app php artisan migrate:fresh` sin errores.

**Commit:** `feat(datos): migraciones de PostgreSQL para portería y monitoreo KAN-6`

---

## Paso 2 · Modelos Eloquent con relaciones

En `Modules/<Modulo>/Domain/Models/` (User se queda en `app/Models`):

- `User`: `hasOne(Residente)`, `hasMany(RegistroPorteria, 'registrado_por')`.
- `Residente`: `belongsTo(User)`, `belongsToMany(Unidad)->withPivot('es_titular')`, `hasMany(Autorizacion)`.
- `Unidad`: `belongsToMany(Residente)`, `hasMany(Autorizacion)`, `hasMany(RegistroPorteria)`, accesor `etiqueta` → "Torre 1 - 101".
- `Zona`: `hasMany(Camara)`, `hasMany(Franja)`, método **`estaVigilada(CarbonInterface $momento): bool`** (revisa las franjas, incluso las que cruzan medianoche).
- `Franja`: `belongsTo(Zona)`, método `cubre(CarbonInterface $momento)`.
- `Camara`: `belongsTo(Zona)`.
- `Autorizacion`: `belongsTo(Unidad)`, `belongsTo(Residente)`, `hasOne(RegistroPorteria)`, scope `vigentes()`.
- `RegistroPorteria`: `belongsTo(Unidad)`, `belongsTo(Zona)`, `belongsTo(User, 'registrado_por')`, `belongsTo(Autorizacion)`.

Usar **enums de PHP** para los campos con valores fijos (`TipoIngreso`, `EstadoAutorizacion`, `EstadoCamara`, `TipoZona`) en el `Domain/` de cada módulo, con `casts`.

**Tests unitarios:** `Zona::estaVigilada()` con una franja normal y otra que cruza medianoche.

**Commit:** `feat(datos): modelos Eloquent con relaciones 1:1, 1:N y N:M KAN-6`

---

## Paso 3 · Factories y seeders (un conjunto de demo creíble)

Mismos nombres que en BD2 para que todo cuadre.

- **Usuarios:** Marta Rueda (`admin`), Óscar Peña (`vigilante`), Gustavo Barrera (`consejo`), `agente@nexora.test` (`agente`) y 3 residentes con cuenta (`residente`). Contraseña `password` solo en local.
- **Unidades:** 3 torres × 8 apartamentos = 24.
- **Residentes:** 30 (3 con usuario), repartidos en las unidades; algunas unidades con 2 residentes (N:M real).
- **Zonas (8, las de BD2):** Acceso peatonal principal, Acceso peatonal posterior, Acceso vehicular, Parqueadero sótano, Perímetro norte, Perímetro sur, Cuarto de basuras, Salón social y BBQ.
- **Franjas:** perímetros y cuarto de basuras de 18:00 a 06:00; parqueadero y accesos **todo el día** (para que la demo funcione a cualquier hora).
- **Cámaras:** CAM-01 a CAM-10 (alguna zona con 2 cámaras).
- **Autorizaciones:** 15 (vigentes, usadas, una expirada y una anulada).
- **Registros de portería:** 40 en los últimos 30 días, hechos por Óscar.

Factories para todo, con `fake('es_ES')`.

**Verificación:** `php artisan migrate:fresh --seed` sin errores. En `tinker`: `Unidad::first()->residentes`, `Zona::first()->camaras`, `User::where('rol','residente')->first()->residente`.

**Commit:** `feat(datos): factories y seeders del conjunto de demo KAN-6`

👉 **Push y PR ya** (`feat: modelo de datos PostgreSQL`). Santiago lo aprueba y lo usa.

---

## Paso 4 · Reglas de Portería (Services y Repositories)

`Modules/Porteria/Application/`:
- **`AutorizacionService`**
  - `crear(Residente, datos)`: `valida_hasta > valida_desde`, máximo 7 días de duración, la unidad debe ser del residente (si no → 403).
  - `anular(Autorizacion)`: solo si está `vigente`.
  - `consumir(Autorizacion, momento)`: si ya está usada o anulada → **409**; si está fuera de la ventana → la marca `expirada` y lanza **422**.
  - `expirarVencidas()`: la usa el scheduler cada 10 minutos.
- **`RegistroPorteriaService`**
  - `registrar(vigilante, datos)`: si trae `autorizacion_id`, la consume (en transacción). Guarda y dispara el evento **`IngresoRegistrado`** (Santiago lo escucha para avisar al residente por Telegram).
  - `registrarSalida(RegistroPorteria)`.

`Modules/Porteria/Infrastructure/Repositories/`: `AutorizacionRepository`, `RegistroPorteriaRepository` con filtros (fecha, unidad, tipo), búsqueda y `paginate(10)`.

**Puerto para Alertas** (lo usa la correlación de Santiago): interfaz `Modules/Porteria/Application/Contracts/ConsultaRegistrosPorteria` con
`buscarParaCorrelacion(int $zonaId, CarbonInterface $momento, int $ventanaMin, int $cantidad): ?RegistroPorteria`,
implementada en el repositorio y registrada en el `PorteriaServiceProvider`.

`AutorizacionPolicy`: el residente solo ve y modifica autorizaciones de **sus** unidades (HU10).

**Tests feature:** consumir dos veces → 409; fuera de ventana → 422; un residente con una unidad ajena → 403.

**Commit:** `feat(porteria): reglas de autorizaciones y registro de ingreso KAN-13 KAN-6`

---

## Paso 5 · API v1 con Sanctum y Resources

Rutas con nombre y agrupadas (`api.v1.*`):

| Método | Ruta | Quién | Códigos |
|---|---|---|---|
| POST | `/api/v1/login` · `/api/v1/logout` | todos | 200, 401, 422 |
| POST | `/api/v1/agente/login` | rol `agente` (ver el contrato) | 200, 401, 422 |
| GET, POST | `/api/v1/zonas` | admin | 200, 201, 401, 403, 422 |
| GET, PUT, DELETE | `/api/v1/zonas/{zona}` | admin | 200, 204, 404, 422 |
| GET, POST | `/api/v1/autorizaciones` | residente (solo las suyas), admin | 200, 201, 401, 422 |
| GET, PUT, DELETE | `/api/v1/autorizaciones/{autorizacion}` | dueño o admin | 200, 204, 403, 404, 409 |

- `ZonaResource` (con `franjas` y `camaras` cuando se cargan), `AutorizacionResource` (con `unidad.etiqueta`).
- Form Requests: `StoreZonaRequest`, `UpdateZonaRequest`, `StoreAutorizacionRequest`, `UpdateAutorizacionRequest`.
- `paginate(10)` + `?search=` en los dos `index`.
- **DELETE de una autorización** = anularla (204).

**Tests feature** de los 6 códigos que pide el profe (200, 201, 204, 401, 404, 422).

**Commit:** `feat(api): API v1 de zonas y autorizaciones con Sanctum y Resources KAN-34`

**Push → PR → Santiago revisa → merge → etiqueta:**
```bash
git checkout main && git pull
git tag v0.1 && git push --tags
```

---

## Al terminar el día

- [ ] `migrate:fresh --seed` funciona y `php artisan test` en verde.
- [ ] PR del modelo de datos mergeado temprano (Santiago lo necesita).
- [ ] API de zonas y autorizaciones probada en Postman a mano.
- [ ] Etiqueta `v0.1`.
- [ ] Jira: KAN-6, KAN-13, KAN-14, KAN-20, KAN-22 en "Listo".
