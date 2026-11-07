# Nexora Security

Sistema de vigilancia para un conjunto residencial. Detecta presencia en las cámaras sin identificar personas, la cruza con los registros de portería y, si nadie la explica, alerta al vigilante por Telegram en segundos.

## Plataformas

1. **Panel web** (Laravel + Blade): vigilante, administradora, consejo y residentes.
2. **Bot de Telegram:** alertas al vigilante y avisos de visitas al residente.
3. **Agente de borde con IA** (Python + OpenCV + YOLOv8): analiza el video dentro del conjunto y envía solo los eventos a la API.

## Levantar en local

*Se completa cuando el entorno esté listo.*

## Integrantes

- Juan Camilo Calderón
- Santiago Steven Ruiz Carreño
