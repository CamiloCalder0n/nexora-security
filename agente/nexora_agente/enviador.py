import logging
import threading

from nexora_agente.cliente_api import ClienteApi, CredencialesInvalidas, SinConexion
from nexora_agente.cola import ColaLocal, Pendiente, espera_backoff

log = logging.getLogger(__name__)

ESPERA_COLA_VACIA_SEG = 1.0
ESPERA_429_SEG = 5.0
ERROR_INESPERADO_SEG = 5.0
SERVIDOR_CAIDO = (502, 503, 504)


class Enviador:
    """Saca eventos de la cola local y aplica la tabla de códigos del contrato."""

    def __init__(self, cola: ColaLocal, cliente: ClienteApi):
        self.cola = cola
        self.cliente = cliente
        # Caídas del backend seguidas: la espera crece con ellas y no con los intentos de cada evento,
        # porque siempre hay uno nuevo con 0 intentos en la cola.
        self._fallas_seguidas = 0
        # Un 500 puede ser de un evento roto; si justo después también falla uno nuevo, el caído es el backend.
        self._hubo_500 = False

    def procesar_uno(self) -> float:
        """Intenta enviar el siguiente pendiente y devuelve cuántos segundos esperar antes del próximo."""
        pendiente = self.cola.siguiente()
        if pendiente is None:
            return ESPERA_COLA_VACIA_SEG

        try:
            r = self.cliente.enviar_evento(pendiente.payload)
        except SinConexion as e:
            return self._reintentar_despues(pendiente, f"sin conexión: {e}", caido=True)
        except CredencialesInvalidas as e:
            return self._reintentar_despues(pendiente, f"login rechazado, revisar AGENTE_EMAIL y AGENTE_PASSWORD: {e}", caido=True)

        if r.status < 500:
            self._fallas_seguidas, self._hubo_500 = 0, False
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
        # 502/503/504: el servidor está caído o arrancando (deploy en Render). Otro 5xx puede ser de este
        # evento; si el anterior también fue un 5xx y este evento es nuevo (sin intentos), el caído es el
        # backend. Los eventos que ya fallaron antes (uno o varios rotos) nunca disparan la espera global.
        caido = r.status in SERVIDOR_CAIDO
        if r.status >= 500 and not caido:
            caido = self._hubo_500 and pendiente.intentos == 0
            self._hubo_500 = True
        return self._reintentar_despues(pendiente, f"respuesta {r.status}", caido=caido)

    def _reintentar_despues(self, pendiente: Pendiente, motivo: str, caido: bool = False) -> float:
        espera = espera_backoff(pendiente.intentos + 1)
        self.cola.posponer(pendiente.uuid, espera)
        log.warning("Evento %s queda en cola (%s); reintento en %ss", pendiente.uuid, motivo, espera)
        if caido:
            # No se intenta con los demás hasta que pase la espera global (2, 4, 8… 60 s).
            self._fallas_seguidas += 1
            return espera_backoff(self._fallas_seguidas)
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
