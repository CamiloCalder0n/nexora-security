# Prueba de carga (k6)

`pico-eventos.js` simula una cámara que manda el doble de lo que permite el contrato del agente: 2 eventos por segundo durante un minuto contra `POST /api/v1/agente/eventos`, con un límite de 60 por minuto por token. Muestra la nivelación de carga del §4.4 del plan: el backend acepta con 202 y encola mientras hay cupo, responde 429 con `Retry-After` cuando se pasa, y el worker vacía la cola.

## Antes de correrla

- **Solo en local**, o en la URL pública con una cámara y zona de demo. Cada corrida guarda 60 eventos reales y crea al menos una alerta, que avisa por Telegram a los vigilantes vinculados.
- **Un token solo para la prueba** (el cupo es por token) y **60 s entre corridas**: si el mismo token mandó eventos en el último minuto, el cupo ya está gastado.
- `CAMARA_ID` debe ser una cámara que exista. Si no, la API responde 422.

En local, el token se saca así:

```bash
docker compose exec app php artisan tinker --execute \
  'echo App\Models\User::where("email", "agente@nexora.test")->first()->createToken("k6")->plainTextToken;'
```

## Correrla

```bash
docker run --rm -i -e TOKEN=<token> -e CAMARA_ID=<id> grafana/k6:2.3.0 run - < pruebas/k6/pico-eventos.js
```

Por defecto apunta a `http://host.docker.internal:8000`, el backend de `docker compose`. En Linux hay que agregar `--add-host=host.docker.internal:host-gateway`. Para otra URL, `-e BASE_URL=…`.

Supone el límite por defecto de 60 por minuto (`AGENTE_EVENTOS_POR_MINUTO`). Falla si alguna respuesta no es 202 ni 429, si un 429 llega sin `Retry-After`, si los aceptados no rondan los 60 o si el p95 de los 202 pasa de 500 ms.

## Resultado en local (2026-11-08)

Medido con una base local limpia. Los eventos y la alerta se borraron después:

| Medida | Valor |
|---|---|
| Aceptados (202) | 60 |
| Limitados (429, con `Retry-After`) | 61 |
| Peticiones en total | 121: k6 también dispara una justo en t = 60 s |
| Otras respuestas (5xx incluidas) | 0 |
| p95 de los 202 | 43 ms |
| Eventos en `pendiente` al terminar el pico | 0: el worker vació la cola |
| Alertas creadas | 1: la supresión agrupó los 60 eventos de la misma zona |
| Jobs fallidos (`failed_jobs`) | 0 |

Las tres últimas filas salen de MongoDB (`db.eventos.countDocuments({resultado: "pendiente"})`, `db.alertas.countDocuments()`) y de PostgreSQL (`select count(*) from failed_jobs`).
