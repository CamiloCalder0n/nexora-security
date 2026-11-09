import logging
import time
from datetime import datetime
from pathlib import Path

import cv2
import numpy as np

log = logging.getLogger(__name__)


def guardar_evidencia(frame: np.ndarray, base: Path, camara_id: int, fecha_hora: datetime) -> str | None:
    """Guarda el frame en el disco del conjunto y devuelve la ruta relativa (None si no se pudo).

    La imagen nunca sale del PC (Ley 1581): a la API solo viaja esta ruta.
    """
    carpeta = base / f"cam{camara_id}"
    carpeta.mkdir(parents=True, exist_ok=True)
    nombre = f"{fecha_hora:%Y%m%d-%H%M%S}.jpg"
    sufijo = 1
    while (carpeta / nombre).exists():  # otro evento en el mismo segundo: no pisar su imagen
        nombre = f"{fecha_hora:%Y%m%d-%H%M%S}-{sufijo}.jpg"
        sufijo += 1
    if not cv2.imwrite(str(carpeta / nombre), frame):
        return None
    return f"{base.name}/cam{camara_id}/{nombre}"


def borrar_vencidas(base: Path, camara_id: int, dias: int) -> int:
    """Borra las imágenes de la cámara con más de `dias` días (Ley 1581: no guardar más de lo necesario)."""
    limite = time.time() - dias * 86400
    borradas = 0
    for f in (base / f"cam{camara_id}").glob("*.jpg"):
        try:
            if f.stat().st_mtime < limite:
                f.unlink()
                borradas += 1
        except OSError as e:  # abierta en un visor, sin permisos o ya borrada: se intenta en la próxima
            log.warning("No se pudo borrar la evidencia %s: %s", f.name, e)
    return borradas
