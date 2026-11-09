# Despliegue en producción

Un solo contenedor en Render (plan gratis) con la web, el worker y el scheduler bajo supervisord. Las bases van en servicios gratis: Neon (PostgreSQL), MongoDB Atlas M0 y Upstash (Redis).

## Imagen

`docker/produccion/Dockerfile`, en tres etapas: assets del panel con Vite, dependencias de PHP sin las de desarrollo y la imagen final. Para probarla en local:

```bash
docker build -f docker/produccion/Dockerfile -t nexora-prod .
```

Al arrancar, `docker/produccion/iniciar.sh`:

1. arma las cachés de configuración, rutas, vistas y eventos;
2. corre `migrate --force` y `nexora:mongo-instalar` antes de recibir tráfico (el instalador pide la ingesta detenida);
3. levanta `web`, `worker` (`--queue=telegram,default`) y `scheduler`. Si uno se cae, supervisord lo vuelve a levantar.

Si un paso falla, el contenedor no arranca y el log de Render dice por qué.

## Render

`render.yaml` describe el servicio. Al crearlo desde el blueprint, Render pide los valores marcados `sync: false`: `APP_KEY` (`php artisan key:generate --show`), `APP_URL`, `DB_URL`, `MONGODB_URI`, `REDIS_URL`, `TELEGRAM_BOT_TOKEN` y `TELEGRAM_WEBHOOK_SECRET`.

En MongoDB Atlas, *Network Access* tiene que permitir `0.0.0.0/0`: Render gratis no tiene IP de salida fija. Sin eso, `nexora:mongo-instalar` falla y el contenedor no arranca.

Después del primer despliegue, una sola vez y desde el entorno local (Render gratis no tiene Shell). Solo habla con la API de Telegram:

```bash
docker compose exec -e TELEGRAM_BOT_TOKEN='<token>' -e TELEGRAM_WEBHOOK_SECRET='<el mismo de Render>' \
  app php artisan nexora:telegram-configurar --webhook=https://<servicio>.onrender.com
```

### Datos de la demo

La imagen no trae las dependencias de desarrollo, y los seeders de la demo usan Faker. Los datos se cargan una vez desde el entorno local, apuntando a la base de Neon:

```bash
docker compose exec -e DB_URL='<url de Neon>' app php artisan db:seed --force
```

Los usuarios de la demo quedan con la contraseña `password`. Antes de publicar la URL, cambia la de cada uno (los 7 de `UsuariosSeeder`), uno por uno:

```bash
docker compose exec -e DB_URL='<url de Neon>' app php artisan tinker --execute \
  'App\Models\User::where("email", "admin@nexora.test")->update(["password" => bcrypt("<nueva>")]);'
```

Si cambias la de `agente@nexora.test`, pon la misma en `AGENTE_PASSWORD` del `agente/.env`, o el agente no podrá iniciar sesión.

### Antes de que funcione la ingesta

Hasta que Portería tenga su implementación de `ConsultaRegistrosPorteria`, `POST /api/v1/agente/eventos` responde 500. El heartbeat y el resto de la API sí funcionan.

## Diagnóstico (HU39)

- `GET /up` responde 200 solo si PostgreSQL, MongoDB y Redis responden. Si una falla, da 500 y el log dice cuál (`MongoDB no responde.`). No se configura como health check de Render: Render lo llamaría cada ~5 s y cada llamada le hace un `PING` a Upstash. Se usa para diagnosticar a mano.
- Los logs de los tres procesos salen por la salida del contenedor (`LOG_CHANNEL=stderr`) y se ven en el panel de Render.
- Si la web, el worker o el scheduler no logran levantar (supervisord los da por perdidos), el contenedor termina con código 1 y el aviso `Proceso en FATAL`, y Render lo vuelve a levantar.

## Ojo con el plan gratis

- Render duerme el servicio tras 15 min sin tráfico, y con él se detienen el worker y el scheduler. El heartbeat del agente (cada 60 s) lo mantiene despierto mientras haya una cámara conectada.
- Upstash solo acepta la base 0 de Redis: la imagen ya pone `REDIS_DB=0` y `REDIS_CACHE_DB=0`.
- Las colas van en PostgreSQL (`QUEUE_CONNECTION=database`): el worker las revisa cada 3 s, y en Redis eso gastaría más de lo que da Upstash gratis. Por lo mismo, en producción el worker y el scheduler no revisan señales de pausa o reinicio y los mutex del scheduler van a PostgreSQL: `queue:restart`, `queue:pause`, `schedule:interrupt` y `schedule:pause` no tienen efecto allí, y para aplicar un cambio se reinicia el servicio (cada deploy lo hace). A Redis llegan la caché, los locks, el rate limit y un `PING` por cada `/up` que se llame a mano. Igual revisa el consumo en la consola de Upstash durante la demo.
- Ese mismo sondeo a PostgreSQL mantiene despierto el cómputo de Neon mientras el servicio esté arriba. Revisa en la consola de Neon que las horas gratis alcancen para los días de la demo.
