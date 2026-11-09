from dataclasses import dataclass

import numpy as np

# Clases COCO que interesan: solo "hay una persona" o "hay un vehículo", sin identificar a nadie.
CLASES = {0: "persona", 2: "vehiculo", 3: "vehiculo", 5: "vehiculo", 7: "vehiculo"}


@dataclass(frozen=True)
class Deteccion:
    tipo: str
    confianza: float
    caja: tuple[int, int, int, int]


def _dispositivo() -> str:
    import torch

    return "mps" if torch.backends.mps.is_available() else "cpu"


class Detector:
    def __init__(self, modelo: str, umbral: float):
        from ultralytics import YOLO

        self.modelo = YOLO(modelo)
        self.umbral = umbral
        self.dispositivo = _dispositivo()
        self.ultimo_resultado = None

    def detectar(self, frame: np.ndarray) -> list[Deteccion]:
        resultado = self.modelo.predict(
            frame,
            conf=self.umbral,
            classes=list(CLASES),
            device=self.dispositivo,
            verbose=False,
        )[0]
        self.ultimo_resultado = resultado
        return [
            Deteccion(
                tipo=CLASES[int(caja.cls)],
                confianza=round(float(caja.conf), 2),
                caja=tuple(int(v) for v in caja.xyxy[0].tolist()),
            )
            for caja in resultado.boxes
        ]

    def dibujar(self) -> np.ndarray | None:
        return self.ultimo_resultado.plot() if self.ultimo_resultado is not None else None
