# Imagen de producción (Render, plan gratis): un solo contenedor con la web, el worker y el
# scheduler bajo supervisord. Se construye desde la raíz del repo:
#   docker build -f docker/produccion/Dockerfile -t nexora-prod .

# 1. Assets del panel (Vite): sin public/build, @vite falla en producción.
FROM node:22-alpine AS assets
WORKDIR /app
COPY backend/package.json backend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY backend/ ./
RUN npm run build

# 2. Dependencias de PHP sin las de desarrollo.
FROM composer:2 AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY backend/ ./
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# 3. Imagen final.
FROM php:8.4-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libzip-dev libicu-dev libssl-dev supervisor \
    && docker-php-ext-install pdo_pgsql zip intl bcmath pcntl opcache \
    && MAKEFLAGS="-j4" pecl install mongodb < /dev/null && docker-php-ext-enable mongodb \
    && rm -rf /var/lib/apt/lists/* /tmp/pear \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www/backend
COPY --from=vendor --chown=www-data:www-data /app ./
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
COPY docker/produccion/supervisord.conf /etc/supervisor/conf.d/nexora.conf
COPY --chmod=755 docker/produccion/iniciar.sh /usr/local/bin/iniciar
COPY --chmod=755 docker/produccion/salir-si-falla.sh /usr/local/bin/salir-si-falla

USER www-data
# predis va en composer; sin esto Laravel busca la extensión phpredis, que la imagen no trae.
# Upstash solo acepta la base 0 de Redis: la caché (por defecto la 1) va ahí, con su prefijo.
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr PORT=8000 PHP_CLI_SERVER_WORKERS=4 \
    REDIS_CLIENT=predis REDIS_DB=0 REDIS_CACHE_DB=0
EXPOSE 8000
CMD ["iniciar"]
