import logging
import threading
from collections.abc import Callable

from nexora_agente.cliente_api import ClienteApi, CredencialesInvalidas, SinConexion
from nexora_agente.cola import ColaLocal, Pendiente, espera_backoff

log = logging.getLogger(__name__)

ESPERA_COLA_VACIA_SEG = 1.0
ESPERA_429_SEG = 5.0
REVISION_CAMARA_SEG = 5.0
SERVIDOR_CAIDO = (502, 503, 504)
ERROR_INESPERADO_SEG = 5.0


class Enviador:
    """Saca eventos de la cola local y aplica la tabla de códigos del contrato."""

    def __init__(self, cola: ColaLocal, cliente: ClienteApi):
        self.cola = cola
        self.cliente = cliente

    def procesar_uno(self) -> float:
        """Intenta enviar el siguiente pendiente y devuelve cuántos segundos esperar antes del próximo."""
        pendiente = self.cola.siguiente()
        if pendiente is None:
            return ESPERA_COLA_VACIA_SEG

        try:
            r = self.cliente.enviar_evento(pendiente.payload)
        except (SinConexion, CredencialesInvalidas) as e:
            return self._reintentar_despues(pendiente, f"sin conexión: {e}", falla_global=True)

        if r.status in (200, 202):
            self.cola.confirmar(pendiente.uuid)
            log.info("Evento %s %s", pendiente.uuid, r.cuerpo.get("data", {}).get("estado", r.status))
            return 0
        if r.status == 429:
            espera = r.retry_after or ESPERA_429_SEG
            self.cola.posponer(pendiente.uuid, espera, contar_intento=False)
            log.warning("429: el backend pide esperar %ss", espera)
            return espera
        if r.status == 422:
            motivo = f"422: {r.cuerpo.get('errors') or r.cuerpo.get('message', '')}"
            self.cola.rechazar(pendiente.uuid, motivo)
            log.error("Evento %s rechazado (%s), no se reintenta", pendiente.uuid, motivo)
            return 0

        # 401 tras renovar el token, 5xx u otro código inesperado (403, 404…): el contrato solo
        # descarta el 422, así que el evento se conserva y se reintenta con espera creciente.
        if r.status != 401 and r.status < 500:
            log.error("Respuesta inesperada %s en /agente/eventos: %s", r.status, r.cuerpo.get("message", ""))
        # 502/503/504: el servidor está caído o arrancando (deploy en Render): no se insiste con los demás.
        return self._reintentar_despues(
            pendiente, f"respuesta {r.status}", falla_global=r.status in SERVIDOR_CAIDO
        )

    def _reintentar_despues(self, pendiente: Pendiente, motivo: str, falla_global: bool = False) -> float:
        espera = espera_backoff(pendiente.intentos + 1)
        self.cola.posponer(pendiente.uuid, espera)
        log.warning("Evento %s queda en cola (%s); reintento en %ss", pendiente.uuid, motivo, espera)
        if falla_global:
            return espera  # sin red o sin login: no tiene sentido intentar los demás ya
        # La falla puede ser solo de este evento: se vuelve a mirar la cola enseguida para que los
        # demás (y los que lleguen) no esperen su backoff (HU21). Mirar SQLite local no cuesta nada.
        return ESPERA_COLA_VACIA_SEG

    def correr(self, detener: threading.Event) -> None:
        while not detener.is_set():
            try:
                espera = self.procesar_uno()
            except Exception:
                # El hilo no puede morir: si muere, los eventos se acumulan sin que nadie lo note.
                log.exception("Error inesperado en el enviador; sigo en %ss", ERROR_INESPERADO_SEG)
                espera = ERROR_INESPERADO_SEG
            detener.wait(espera)


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
        except (SinConexion, CredencialesInvalidas) as e:
            log.warning("Heartbeat sin conexión: %s", e)
        except Exception:
            log.exception("Error inesperado en el heartbeat")
        detener.wait(intervalo)
