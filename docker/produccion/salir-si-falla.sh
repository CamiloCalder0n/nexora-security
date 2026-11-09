#!/bin/sh
# Listener de supervisord: si la web, el worker o el scheduler quedan en FATAL (no levantan tras
# varios intentos), deja una marca y detiene supervisord. iniciar.sh sale entonces con código 1 y
# Render vuelve a levantar el contenedor, en vez de dejarlo vivo sin servir o sin procesar avisos.
printf 'READY\n'
while read -r cabecera; do
  largo=$(printf '%s' "$cabecera" | sed -n 's/.*len:\([0-9]*\).*/\1/p')
  carga=$(head -c "$largo")
  echo "Proceso en FATAL ($carga): se reinicia el contenedor." >&2
  touch /tmp/nexora-fatal
  kill -TERM "$(cat /tmp/supervisord.pid)" || echo "No se pudo detener supervisord." >&2
  printf 'RESULT 2\nOK'
  printf 'READY\n'
done
