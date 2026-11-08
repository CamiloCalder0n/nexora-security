import threading
import uuid

import pytest

from nexora_agente.cliente_api import Respuesta, SinConexion
from nexora_agente.cola import ColaLocal
from nexora_agente.enviador import Enviador


class ClienteFalso:
    def __init__(self, respuesta):
        self.respuesta = respuesta

    def enviar_evento(self, payload):
        if isinstance(self.respuesta, Exception):
            raise self.respuesta
        return self.respuesta


UUID_PENDIENTE = str(uuid.uuid4())


@pytest.fixture
def cola(tmp_path):
    c = ColaLocal(tmp_path / "cola.db")
    c.encolar({"uuid": UUID_PENDIENTE})
    return c


@pytest.mark.parametrize("status", [202, 200])
def test_aceptado_o_duplicado_sale_de_la_cola(cola, status):
    Enviador(cola, ClienteFalso(Respuesta(status, {"data": {"estado": "ok"}}))).procesar_uno()
    assert cola.contar() == {"pendientes": 0, "rechazados": 0}


def test_solo_el_422_va_a_rechazados(cola):
    Enviador(cola, ClienteFalso(Respuesta(422, {"errors": {"camara_id": ["no existe"]}}))).procesar_uno()
    assert cola.contar() == {"pendientes": 0, "rechazados": 1}


def test_429_espera_retry_after_sin_contar_intento(cola):
    espera = Enviador(cola, ClienteFalso(Respuesta(429, {}, retry_after=7))).procesar_uno()
    assert espera == 7
    assert cola.contar()["pendientes"] == 1
    assert cola.siguiente() is None
    cola.posponer(UUID_PENDIENTE, 0, contar_intento=False)
    assert cola.siguiente().intentos == 0


@pytest.mark.parametrize(
    "respuesta",
    [Respuesta(503, {}), Respuesta(401, {}), Respuesta(403, {}), Respuesta(404, {}), SinConexion("caído")],
)
def test_falla_temporal_queda_en_cola_con_backoff(cola, respuesta):
    espera = Enviador(cola, ClienteFalso(respuesta)).procesar_uno()
    assert espera == (2 if isinstance(respuesta, SinConexion) or respuesta.status == 503 else 1)
    assert cola.contar() == {"pendientes": 1, "rechazados": 0}
    assert cola.siguiente() is None


def test_error_inesperado_no_mata_el_hilo(cola):
    class ClienteQueExplota:
        llamadas = 0

        def enviar_evento(self, payload):
            ClienteQueExplota.llamadas += 1
            raise RuntimeError("bug")

    detener = threading.Event()
    enviador = Enviador(cola, ClienteQueExplota())
    hilo = threading.Thread(target=enviador.correr, args=(detener,))
    hilo.start()
    detener.wait(0.2)
    assert hilo.is_alive()
    detener.set()
    hilo.join()
    assert ClienteQueExplota.llamadas == 1


def test_un_evento_que_siempre_falla_no_frena_a_los_demas(tmp_path):
    cola = ColaLocal(tmp_path / "cola.db")
    malo, bueno = {"uuid": str(uuid.uuid4())}, {"uuid": str(uuid.uuid4())}
    cola.encolar(malo)
    cola.encolar(bueno)

    class ClienteSelectivo:
        def enviar_evento(self, payload):
            return Respuesta(500 if payload["uuid"] == malo["uuid"] else 202, {})

    enviador = Enviador(cola, ClienteSelectivo())
    enviador.procesar_uno()  # el malo falla
    cola.posponer(malo["uuid"], 0)  # pasó su espera
    enviador.procesar_uno()
    assert cola.contar()["pendientes"] == 1
    assert cola.siguiente().uuid == malo["uuid"]


def test_un_evento_roto_no_retrasa_a_los_que_estan_listos(tmp_path):
    cola = ColaLocal(tmp_path / "cola.db")
    malo, bueno = {"uuid": str(uuid.uuid4())}, {"uuid": str(uuid.uuid4())}
    cola.encolar(malo)
    cola.encolar(bueno)
    for _ in range(5):  # el malo ya acumula intentos: su backoff es de 60 s
        cola.posponer(malo["uuid"], 0)

    class Cliente:
        def enviar_evento(self, payload):
            return Respuesta(500 if payload["uuid"] == malo["uuid"] else 202, {})

    enviador = Enviador(cola, Cliente())
    assert enviador.procesar_uno() == 0  # sale el bueno primero
    cola.posponer(malo["uuid"], 0)  # al malo le toca reintentar
    cola.encolar({"uuid": str(uuid.uuid4())})  # y llega un evento nuevo detrás
    assert enviador.procesar_uno() == 1  # el malo falla: se revisa la cola en 1 s, no en 60
    assert enviador.procesar_uno() == 0  # el que llegó después sale enseguida
    assert cola.contar()["pendientes"] == 1
    assert cola.siguiente() is None  # el malo sí espera su backoff


def test_sin_red_si_espera_el_backoff_completo(cola):
    cola.encolar({"uuid": str(uuid.uuid4())})
    assert Enviador(cola, ClienteFalso(SinConexion("caído"))).procesar_uno() == 2


def test_servidor_caido_con_muchos_pendientes_no_se_martilla(tmp_path):
    cola = ColaLocal(tmp_path / "cola.db")
    for _ in range(100):
        cola.encolar({"uuid": str(uuid.uuid4())})
    llamadas = []

    class Caido:
        def enviar_evento(self, payload):
            llamadas.append(payload["uuid"])
            return Respuesta(503, {})

    enviador = Enviador(cola, Caido())
    assert enviador.procesar_uno() == 2  # espera global: no se prueba el siguiente enseguida
    assert len(llamadas) == 1
