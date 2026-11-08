---
name: validar-flujos
description: Tercera capa de validación de Nexora Security, después de las dos rondas de /pr-review. Con lo que ya está construido se crean datos de uso y flujos reales (camino feliz, bordes, errores, abuso y seguridad, concurrencia, caídas) que ejercitan la funcionalidad por sus entradas reales (API, agente, worker, scheduler, comandos) y se comprueba el estado que dejan. Si algo falla se vuelve a las dos revisiones. Se usa con `/validar-flujos` en la rama actual o desde `/pr-autopilot`.
metadata:
  short-description: Capa 3, validar la funcionalidad con datos y flujos reales
---

# Validar flujos · Nexora (capa 3)

## Cuándo usarla

- `/validar-flujos` en una rama que ya pasó **dos rondas seguidas** de `/pr-review` con `veredicto=listo`.
- Desde `/pr-autopilot`, antes del push.
- Sobre un PR ya abierto, cuando se le agregan cambios.

Las revisiones miran el código. Esta capa mira **el sistema funcionando**: ¿aguanta lo que le va a pasar en la vida real y lo que le haría alguien con malas intenciones?

## Regla de oro

Si cualquier flujo falla, **no se arregla aquí y ya**:

1. Se corrige la causa.
2. Se vuelve a la **capa 1 y 2**, es decir, dos rondas seguidas de `/pr-review` limpias.
3. Se repite **esta capa completa**, no solo el flujo que falló.

Luz verde = las tres capas en verde en la misma versión del código.

## Paso 1 · Levantar el sistema real

```bash
docker compose up -d                      # app, worker, scheduler, pgsql, mongo, redis
docker compose exec app php artisan migrate               # nunca migrate:fresh: borra lo que haya en la base
docker compose exec app php artisan nexora:mongo-instalar   # índices y validador de Mongo (desde #5)
cd agente && source .venv/bin/activate    # si la rama toca el agente
```

Usar la base de **desarrollo**, nunca la de producción. Los datos de prueba se crean con seeders o factories, o con el propio flujo (el agente mandando eventos, un vigilante cerrando alertas). Nada de datos reales de residentes (Ley 1581).

**Antes del primer flujo, tomar la línea base** (ver Paso 4): sin ella no hay cómo dejar la base como estaba.

## Paso 2 · Armar la matriz de flujos

Sale de la guía del día, de las HU que cubre la rama y de `docs/CONTRATO_API_AGENTE.md`. Cada fila es un flujo con su resultado esperado **antes** de correrlo. Cubrir siempre estas familias:

| Familia | Ejemplos en Nexora |
|---|---|
| **Camino feliz** | El agente detecta una persona → 202 → correlación → alerta en Mongo → bitácora. Un ingreso registrado explica el evento siguiente. |
| **Bordes** | Fechas en el límite (−24 h, +5 min), franja que cruza la medianoche, `cantidad` 1 y 50, `confianza` 0 y 1, ventana de silencio justo en el borde. |
| **Errores esperados** | 401 sin token, 403 con otro rol, 404, 422 por cada regla, 409 en una transición inválida. Mensajes claros, sin traza ni datos internos. |
| **Abuso y seguridad** | Inyección de operadores de Mongo (`{"uuid": {"$ne": null}}`), cadenas enormes o con caracteres raros, IDs ajenos (IDOR), token de otro rol, reenvío del mismo uuid, ráfaga por encima del rate limit, campos extra que no deben guardarse (mass assignment), rutas de evidencia con `../`. |
| **Concurrencia** | Dos eventos de la misma zona a la vez (una sola alerta), dos workers escribiendo la bitácora (cadena intacta), atender mientras escala. |
| **Caídas y recuperación** | Backend apagado mientras el agente envía (la cola local lo guarda y luego lo vacía), Redis o Mongo caídos (el error se ve, no se pierde nada), reintento del job. |
| **Privacidad** | Nada de imágenes, video, caras ni placas en la API, los logs o Telegram. Las credenciales RTSP enmascaradas en los logs. |
| **Observabilidad** | Lo que pasó se reconstruye con logs, bitácora y `nexora:verificar-bitacora`. |

Lo que no aplica a la rama se marca `no aplica` con el motivo. No se borra la fila.

## Paso 3 · Ejecutar por las entradas reales

- **HTTP**: `curl` o el cliente del agente contra `http://localhost:8000/api/v1`, con tokens de Sanctum de cada rol. Se crean con tinker o con el login, nunca escritos a mano en el repo.
- **Agente**: `python -m nexora_agente --fuente videos/... --camara-id N` o `--simular`.
- **Cola y tareas**: el `worker` y el `scheduler` del compose, o `php artisan nexora:...` a mano.
- **Estado**: comprobar en PostgreSQL (`tinker`), MongoDB (`mongosh`), Redis y `storage/logs/laravel.log` que quedó lo que debía quedar, y nada más.

Los scripts de exploración van al **scratchpad**, no al repo. Si un flujo es **reproducible y determinista**, se convierte en test automatizado dentro del PR, en `tests/Feature/...` o `agente/tests/`, para que no vuelva a romperse.

## Paso 4 · Limpieza (obligatoria, salga verde o rojo)

Todo lo que la validación crea o cambia se borra o se restaura al terminar, también si un flujo falla o el script se cae a la mitad. La base local queda igual que antes de empezar: nada de datos de prueba estancados.

1. **Línea base al empezar**: id máximo de cada tabla que se va a tocar en PostgreSQL (`users`, `personal_access_tokens`, `registros_porteria`, `autorizaciones`, `failed_jobs`…), `_id` máximo de cada colección de Mongo (`eventos`, `alertas`, `bitacora`), estado de lo que se va a modificar (cámaras, vínculos de Telegram) y las claves de Redis de caché.
2. **Al terminar**, en un `finally` (o un context manager), borrar las filas y documentos posteriores a la línea base (hijos antes que padres), restaurar lo modificado y borrar las claves de Redis nuevas, sin tocar las colas.
3. **Verificar** que no quedó diferencia contra la línea base e incluirlo en el reporte.

Lo que no se hace nunca: `migrate:fresh`, borrar la base o una tabla entera, `FLUSHDB` en Redis, ni tocar datos que existían antes de la línea base. Los tests automáticos que usen servicios reales (Redis, Mongo de desarrollo) también limpian lo que crean.

## Paso 5 · Reporte

```text
VALIDATION_RESULT rama=<rama|#pr> flujos=<n> ok=<n> fallas=<n> no_aplica=<n> limpieza=<ok|pendiente> veredicto=<verde|volver_a_revision>

| # | Familia | Flujo | Esperado | Observado | Resultado |
| - | ------- | ----- | -------- | --------- | --------- |

Evidencia: <comandos clave y salidas cortas, sin tokens ni secretos>
Tests nuevos agregados: <lista o "ninguno">
```

- `verde`: todos los flujos `ok` o `no aplica` con motivo, y `limpieza=ok`.
- `volver_a_revision`: hubo al menos una falla. Se lista qué se corrigió y se vuelve a la capa 1.

En el PR se resume la matriz en una sección **"Validación de flujos"** (familias, cuántos flujos, tests agregados), sin pegar tokens ni datos personales.
