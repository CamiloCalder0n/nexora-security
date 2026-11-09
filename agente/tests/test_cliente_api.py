import socket
import threading

import pytest

from nexora_agente.cliente_api import ClienteApi, CredencialesInvalidas, SinConexion
from nexora_agente.cola import ColaLocal
from nexora_agente.enviador import Enviador
from nexora_agente.heartbeat import latir
from nexora_agente.evento import ahora_colombia, armar_evento
from tests import mock_api


@pytest.fixture
def mock():
    servidor, estado = mock_api.iniciar()
    yield f"http://127.0.0.1:{servidor.server_port}/api/v1", estado
    servidor.shutdown()
    servidor.server_close()


def cliente(url, password=mock_api.PASSWORD):
    return ClienteApi(url, mock_api.EMAIL, password, "pytest", timeout=2)


def evento(**cambios):
    e = armar_evento(1, "persona", 1, 0.8, ahora_colombia(), "evidencias/cam1/x.jpg")
    return {**e, **cambios}


def puerto_libre() -> int:
    with socket.socket() as s:
        s.bind(("127.0.0.1", 0))
        return s.getsockname()[1]


def test_login_y_camaras(mock):
    url, _ = mock
    c = cliente(url)
    camaras = c.camaras()
    assert c.token.startswith("1|")
    assert camaras[0]["zona"]["nombre"] == "Parqueadero sótano"


def test_credenciales_invalidas(mock):
    url, _ = mock
    with pytest.raises(CredencialesInvalidas):
        cliente(url, password="mala").login()


def test_evento_202_y_duplicado_200(mock):
    url, estado = mock
    c = cliente(url)
    e = evento()
    assert c.enviar_evento(e).status == 202
    r = c.enviar_evento(e)
    assert r.status == 200 and r.cuerpo["data"]["estado"] == "duplicado"
    assert list(estado.eventos) == [e["uuid"]]


def test_evento_invalido_422(mock):
    url, _ = mock
    r = cliente(url).enviar_evento(evento(tipo_objeto="perro"))
    assert r.status == 422 and "tipo_objeto" in r.cuerpo["errors"]


def test_vuelve_a_loguearse_si_el_token_vence(mock):
    url, estado = mock
    c = cliente(url)
    c.login()
    estado.tokens.clear()
    assert c.enviar_evento(evento()).status == 202


def test_heartbeat_204_y_404(mock):
    url, estado = mock
    c = cliente(url)
    assert c.heartbeat(1).status == 204
    assert c.heartbeat(99).status == 404
    assert estado.heartbeats == [1]


def test_sin_servidor_lanza_sin_conexion():
    with pytest.raises(SinConexion):
        cliente(f"http://127.0.0.1:{puerto_libre()}/api/v1").login()


def test_hilo_de_heartbeat(mock):
    url, estado = mock
    detener = threading.Event()
    hilo = threading.Thread(target=latir, args=(cliente(url), 1, 0.05, detener))
    hilo.start()
    detener.wait(0.3)
    detener.set()
    hilo.join()
    assert len(estado.heartbeats) >= 2


def test_cola_se_vacia_al_volver_el_servidor(tmp_path):
    puerto = puerto_libre()
    url = f"http://127.0.0.1:{puerto}/api/v1"
    cola = ColaLocal(tmp_path / "cola.db")
    enviador = Enviador(cola, cliente(url))
    eventos = [evento(), evento()]
    for e in eventos:
        cola.encolar(e)

    enviador.procesar_uno()  # sin servidor: se queda en la cola
    assert cola.contar()["pendientes"] == 2

    servidor, estado = mock_api.iniciar(puerto)
    try:
        for e in eventos:
            cola.posponer(e["uuid"], 0, contar_intento=False)
        enviador.procesar_uno()
        enviador.procesar_uno()
        assert cola.contar() == {"pendientes": 0, "rechazados": 0}
        assert set(estado.eventos) == {e["uuid"] for e in eventos}
    finally:
        servidor.shutdown()
        servidor.server_close()


def test_dos_hilos_con_401_a_la_vez_usan_el_token_renovado(mock):
    url, estado = mock
    for _ in range(20):
        c = cliente(url)
        c.login()
        estado.tokens.clear()
        barrera = threading.Barrier(2)
        respuestas = []

        def pedir(accion):
            barrera.wait()
            respuestas.append(accion().status)

        hilos = [
            threading.Thread(target=pedir, args=(lambda: c.enviar_evento(evento()),)),
            threading.Thread(target=pedir, args=(lambda: c.heartbeat(1),)),
        ]
        for h in hilos:
            h.start()
        for h in hilos:
            h.join()
        assert sorted(respuestas) == [202, 204]
        assert len(estado.tokens) == 1  # un solo login para los dos


def test_sin_frames_recientes_no_hay_heartbeat(mock):
    url, estado = mock
    detener = threading.Event()
    hilo = threading.Thread(target=latir, args=(cliente(url), 1, 0.05, detener, lambda: False))
    hilo.start()
    detener.wait(0.3)
    detener.set()
    hilo.join()
    assert estado.heartbeats == []


def test_429_y_500_reales_del_backend(mock, tmp_path):
    url, estado = mock
    c = cliente(url)
    c.login()
    cola = ColaLocal(tmp_path / "cola.db")
    e = evento()
    cola.encolar(e)
    enviador = Enviador(cola, c)

    estado.forzar_status = 429
    assert enviador.procesar_uno() == 1.0  # Retry-After del backend
    estado.forzar_status = 500
    cola.posponer(e["uuid"], 0, contar_intento=False)
    assert enviador.procesar_uno() == 1.0  # 500: se revisa la cola en 1 s, el evento espera su backoff
    assert cola.contar() == {"pendientes": 1, "rechazados": 0}
