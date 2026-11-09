#!/bin/sh
# Arranque en producción: deja el esquema al día antes de que entre tráfico y luego levanta
# web, worker y scheduler. Si algo falla aquí, el contenedor no arranca y el log dice por qué.
set -e
cd /var/www/backend

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

php artisan migrate --force
# Va antes de levantar el worker y la web de esta versión. En un deploy de Render la versión anterior
# sigue atendiendo mientras tanto: si un cambio de esquema endurece el validador, detener el agente antes.
php artisan nexora:mongo-instalar

# supervisord en segundo plano para poder salir con error si un proceso quedó en FATAL (ver
# salir-si-falla.sh); el TERM del deploy se le reenvía para que se apague en orden.
rm -f /tmp/nexora-fatal
supervisord -c /etc/supervisor/conf.d/nexora.conf &
supervisor=$!
detenido=0
trap 'detenido=1; kill -TERM "$supervisor" 2>/dev/null || true' TERM INT
estado=0
wait "$supervisor" || estado=$?
wait "$supervisor" 2>/dev/null || true
[ -f /tmp/nexora-fatal ] && exit 1
# Apagado pedido (deploy): 0. Si supervisord murió solo con error, se propaga.
[ "$detenido" = 1 ] && exit 0
exit "$estado"
