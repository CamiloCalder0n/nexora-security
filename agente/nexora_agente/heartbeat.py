import logging
import threading
from collections.abc import Callable

from nexora_agente.cliente_api import ClienteApi, CredencialesInvalidas, SinConexion

log = logging.getLogger(__name__)

REVISION_CAMARA_SEG = 5.0


def latir(
    cliente: ClienteApi,
    camara_id: int,
    intervalo: float,
    detener: threading.Event,
    camara_viva: Callable[[], bool] = lambda: True,
) -> None:
    """Heartbeat de la cámara cada `intervalo` segundos; el backend la marca sin_senal a los 5 min sin él.

    Solo late si la cámara está entregando frames: si el video se cae y el proceso sigue vivo,
    el backend tiene que enterarse (HU13).
    """
    avisado = False
    while not detener.is_set():
        if not camara_viva():
            if not avisado:
                log.warning("Cámara %s sin frames; no se envía heartbeat hasta que vuelva", camara_id)
                avisado = True
            detener.wait(min(intervalo, REVISION_CAMARA_SEG))
            continue
        avisado = False
        try:
            r = cliente.heartbeat(camara_id)
            if r.status == 404:
                log.error("La cámara %s no existe en el backend", camara_id)
            elif r.status != 204:
                log.warning("Heartbeat respondió %s", r.status)
        except SinConexion as e:
            log.warning("Heartbeat sin conexión: %s", e)
        except CredencialesInvalidas as e:
            log.warning("Heartbeat con login rechazado, revisar AGENTE_EMAIL y AGENTE_PASSWORD: %s", e)
        except Exception:
            log.exception("Error inesperado en el heartbeat")
        detener.wait(intervalo)
