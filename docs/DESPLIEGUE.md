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

### Región

El servicio se crea en **Virginia** (`region: virginia` en `render.yaml`), porque Neon, Atlas y Upstash están en `us-east-1`. Desde Oregon, cada consulta cruzaría el país. **Render no deja cambiar la región después de crear el servicio:** si quedó en otra, hay que borrarlo y crearlo de nuevo desde el blueprint, con las mismas variables.

### Zona horaria

`APP_TIMEZONE=America/Bogota` viene en `render.yaml`. Sin ella la app queda en UTC y las alertas en Telegram, en el panel y en la cola salen 5 horas corridas (04:45 en vez de 23:45). Las columnas de fecha de PostgreSQL no guardan la zona: si ya había datos cargados con la app en UTC, se ven 5 horas corridos y conviene volver a cargarlos (ver *Datos de la demo*). MongoDB guarda en UTC y no se afecta.

### Redis de Upstash: `rediss://`

`REDIS_URL` tiene que empezar con **`rediss://`** (doble s, con TLS). Upstash la muestra como `redis://...`: si se pega así, Upstash corta la conexión sin TLS y `/up` responde 500 con `Redis no responde.` en el log. Cambia solo el esquema; el usuario, la clave, el host y el puerto quedan igual.

En MongoDB Atlas, *Network Access* tiene que permitir `0.0.0.0/0`: Render gratis no tiene IP de salida fija. Sin eso, `nexora:mongo-instalar` falla y el contenedor no arranca.

Después del primer despliegue, una sola vez y desde el entorno local (Render gratis no tiene Shell). Solo habla con la API de Telegram:

```bash
docker compose exec -e TELEGRAM_BOT_TOKEN='<token>' -e TELEGRAM_WEBHOOK_SECRET='<el mismo de Render>' \
  app php artisan nexora:telegram-configurar --webhook=https://<servicio>.onrender.com
```

### Datos de la demo

La imagen no trae las dependencias de desarrollo, y los seeders de la demo usan Faker. Los datos se cargan una vez desde el entorno local, apuntando a la base de Neon.

Guarda la URL en una variable de la terminal, para que no quede en el historial de cada comando ni se pegue mal. Antes de usarla, revisa que apunta a Neon y no a la base local; el `echo` muestra solo el comienzo:

```bash
export NEON='<url de Neon>'
echo ${NEON:0:20}   # debe empezar con postgresql://
docker compose exec -e DB_URL="$NEON" app php artisan db:seed --force
```

Los usuarios de la demo quedan con la contraseña `password`. Antes de publicar la URL, cambia la de cada uno (los 7 de `UsuariosSeeder`), uno por uno:

```bash
docker compose exec -e DB_URL="$NEON" app php artisan tinker --execute \
  'App\Models\User::where("email", "admin@nexora.test")->update(["password" => bcrypt("<nueva>")]);'
```

Si cambias la de `agente@nexora.test`, pon la misma en `AGENTE_PASSWORD` del `agente/.env`, o el agente no podrá iniciar sesión.

### Vincular Telegram

Cada vigilante, y quien deba recibir avisos, vincula su cuenta con el bot una sola vez. El código se genera desde el entorno local, pero tiene que quedar en el Redis de Upstash (la caché de producción) y validarse contra el usuario de Neon. Por eso el comando corre con la base, el Redis y la caché de la nube:

```bash
export NEON='<url de Neon>'
export UPSTASH='rediss://<url de Upstash>'
echo ${NEON:0:20} ${UPSTASH:0:20}   # postgresql://… y rediss://… (doble s)
docker compose exec -e DB_URL="$NEON" -e REDIS_URL="$UPSTASH" -e REDIS_DB=0 -e REDIS_CACHE_DB=0 -e CACHE_STORE=redis \
  app php artisan nexora:telegram-codigo vigilante@nexora.test
```

Luego, desde la cuenta de Telegram de esa persona, se le escribe al bot `/start CODIGO`. El bot responde que quedó vinculada. El código es de un solo uso y vence; si vence, se genera otro. Si el comando corre sin esas variables, el código queda en el Redis local y el bot responde "Ese código no sirve".

## Diagnóstico (HU39)

- `GET /up` responde 200 solo si PostgreSQL, MongoDB y Redis responden. Si una falla, da 500 y el log dice cuál (`MongoDB no responde.`). No se configura como health check de Render: Render lo llamaría cada ~5 s y cada llamada le hace un `PING` a Upstash. Se usa para diagnosticar a mano.
- Los logs de los tres procesos salen por la salida del contenedor (`LOG_CHANNEL=stderr`) y se ven en el panel de Render.
- Si la web, el worker o el scheduler no logran levantar (supervisord los da por perdidos), el contenedor termina con código 1 y el aviso `Proceso en FATAL`, y Render lo vuelve a levantar.

## Ojo con el plan gratis

- Render duerme el servicio tras 15 min sin tráfico, y con él se detienen el worker y el scheduler. El heartbeat del agente (cada 60 s) lo mantiene despierto mientras haya una cámara conectada.
- Upstash solo acepta la base 0 de Redis: la imagen ya pone `REDIS_DB=0` y `REDIS_CACHE_DB=0`.
- Las colas van en PostgreSQL (`QUEUE_CONNECTION=database`): el worker las revisa cada 3 s, y en Redis eso gastaría más de lo que da Upstash gratis. Por lo mismo, en producción el worker y el scheduler no revisan señales de pausa o reinicio y los mutex del scheduler van a PostgreSQL: `queue:restart`, `queue:pause`, `schedule:interrupt` y `schedule:pause` no tienen efecto allí, y para aplicar un cambio se reinicia el servicio (cada deploy lo hace). A Redis llegan la caché, los locks, el rate limit y un `PING` por cada `/up` que se llame a mano. Igual revisa el consumo en la consola de Upstash durante la demo.
- Ese mismo sondeo a PostgreSQL mantiene despierto el cómputo de Neon mientras el servicio esté arriba. Revisa en la consola de Neon que las horas gratis alcancen para los días de la demo.
