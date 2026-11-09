# Nexora Security

Sistema de vigilancia para un conjunto residencial. Detecta presencia en las cámaras sin identificar personas, la cruza con los registros de portería y, si nadie la explica, alerta al vigilante por Telegram en segundos.

## Plataformas

1. **Panel web** (Laravel + Blade): vigilante, administradora, consejo y residentes.
2. **Bot de Telegram:** alertas al vigilante y avisos de visitas al residente.
3. **Agente de borde con IA** (Python + OpenCV + YOLOv8): analiza el video dentro del conjunto y envía solo los eventos a la API.

## Levantar en local

```bash
cp backend/.env.example backend/.env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan nexora:mongo-instalar   # colecciones, validación e índices de MongoDB
docker compose exec app php artisan test
# http://localhost:8000
```

Usuarios de prueba (solo local, contraseña `password`): `admin@nexora.test`, `vigilante@nexora.test`, `residente@nexora.test`, `consejo@nexora.test`, `agente@nexora.test`.

## Integrantes

- Juan Camilo Calderón
- Santiago Steven Ruiz Carreño
