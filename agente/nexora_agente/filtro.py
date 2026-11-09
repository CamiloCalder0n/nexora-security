import time
from collections import deque
from collections.abc import Callable
from dataclasses import dataclass

from nexora_agente.detector import Deteccion


@dataclass(frozen=True)
class Hallazgo:
    tipo: str
    cantidad: int
    confianza: float


class Filtro:
    """Decide cuándo las detecciones se vuelven un evento, para no inundar al vigilante.

    Un tipo de objeto genera evento si aparece en la muestra actual y en al menos
    `persistencia_min` de las últimas `ventana` muestras, y no hubo otro evento de ese
    tipo en los últimos `cooldown_seg` segundos.
    """

    def __init__(
        self,
        persistencia_min: int,
        ventana: int,
        cooldown_seg: float,
        reloj: Callable[[], float] = time.monotonic,
    ):
        self.persistencia_min = persistencia_min
        self.ventana = ventana
        self.cooldown_seg = cooldown_seg
        self.reloj = reloj
        self._historial: dict[str, deque[bool]] = {}
        self._ultimo_evento: dict[str, float] = {}

    def procesar(self, detecciones: list[Deteccion]) -> list[Hallazgo]:
        ahora = self.reloj()
        por_tipo: dict[str, list[Deteccion]] = {}
        for d in detecciones:
            por_tipo.setdefault(d.tipo, []).append(d)

        hallazgos = []
        for tipo in por_tipo.keys() | self._historial.keys():
            historial = self._historial.setdefault(tipo, deque(maxlen=self.ventana))
            presentes = por_tipo.get(tipo, [])
            historial.append(bool(presentes))
            if not presentes or sum(historial) < self.persistencia_min:
                continue
            ultimo = self._ultimo_evento.get(tipo)
            if ultimo is not None and ahora - ultimo < self.cooldown_seg:
                continue
            self._ultimo_evento[tipo] = ahora
            hallazgos.append(
                Hallazgo(
                    tipo=tipo,
                    cantidad=min(len(presentes), 50),
                    confianza=max(d.confianza for d in presentes),
                )
            )
        return sorted(hallazgos, key=lambda h: h.tipo)
