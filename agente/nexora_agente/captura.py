import logging
import re
import time
from collections.abc import Iterator
from pathlib import Path

import cv2
import numpy as np

log = logging.getLogger(__name__)

REINTENTO_SEG = 5


def ocultar_credenciales(fuente: str) -> str:
    """rtsp://usuario:clave@host → rtsp://***@host, para no dejar la clave de la cámara en los logs."""
    return re.sub(r"://[^/]*@", "://***@", fuente)


def _normalizar(fuente: str) -> str | int:
    return int(fuente) if fuente.isdigit() else fuente


class Captura:
    """Lee un .mp4, la webcam o un flujo RTSP y entrega frames a `fps` muestras por segundo.

    El .mp4 vuelve a empezar al acabarse; si la webcam o el RTSP se caen, reintenta cada
    5 s sin tumbar el proceso. Nada se graba: los frames solo viven en memoria.
    """

    def __init__(self, fuente: str, fps: float):
        self.fuente = _normalizar(fuente)
        self.nombre = ocultar_credenciales(fuente)
        self.es_archivo = isinstance(self.fuente, str) and Path(self.fuente).is_file()
        self.intervalo = 1.0 / fps
        if isinstance(self.fuente, str) and not self.es_archivo and "://" not in self.fuente:
            raise FileNotFoundError(f"No existe el video: {self.fuente}")

    def _abrir(self) -> cv2.VideoCapture | None:
        cap = cv2.VideoCapture(self.fuente)
        if cap.isOpened():
            return cap
        cap.release()
        return None

    def frames(self) -> Iterator[np.ndarray]:
        while True:
            cap = self._abrir()
            if cap is None:
                log.warning("No se pudo abrir la fuente %s; reintento en %ss", self.nombre, REINTENTO_SEG)
                time.sleep(REINTENTO_SEG)
                continue
            yield from self._leer(cap)
            cap.release()
            if not self.es_archivo:
                log.warning("Se perdió la fuente %s; reintento en %ss", self.nombre, REINTENTO_SEG)
                time.sleep(REINTENTO_SEG)

    def _leer(self, cap: cv2.VideoCapture) -> Iterator[np.ndarray]:
        # En un archivo se salta frames según sus fps y se respeta el tiempo real,
        # para que el filtro y el cooldown se comporten igual que con una cámara.
        fps_origen = cap.get(cv2.CAP_PROP_FPS) or 30.0
        salto = max(1, round(fps_origen * self.intervalo)) if self.es_archivo else 1
        indice = -1
        siguiente = time.monotonic()
        while True:
            ok, frame = cap.read()
            if not ok:
                return
            indice += 1
            espera = siguiente - time.monotonic()
            if self.es_archivo:
                if indice % salto:
                    continue
                if espera > 0:
                    time.sleep(espera)
            elif espera > 0:
                continue
            siguiente = max(siguiente + self.intervalo, time.monotonic())
            yield frame
