import uuid

import pytest

from nexora_agente.cola import ColaLocal, espera_backoff


def evento(n: int) -> dict:
    return {"uuid": str(uuid.uuid4()), "camara_id": 1, "tipo_objeto": "persona", "cantidad": n}


@pytest.fixture
def ruta(tmp_path):
    return tmp_path / "cola.db"


def test_saca_en_orden_de_llegada(ruta):
    cola = ColaLocal(ruta)
    a, b = evento(1), evento(2)
    cola.encolar(a)
    cola.encolar(b)
    assert cola.siguiente().payload == a
    cola.confirmar(a["uuid"])
    assert cola.siguiente().payload == b
    cola.confirmar(b["uuid"])
    assert cola.siguiente() is None


def test_mismo_uuid_no_se_duplica(ruta):
    cola = ColaLocal(ruta)
    a = evento(1)
    cola.encolar(a)
    cola.encolar(a)
    assert cola.contar() == {"pendientes": 1, "rechazados": 0}


def test_mover_a_rechazados(ruta):
    cola = ColaLocal(ruta)
    a = evento(1)
    cola.encolar(a)
    cola.rechazar(a["uuid"], "422: camara_id no existe")
    assert cola.contar() == {"pendientes": 0, "rechazados": 1}
    assert cola.siguiente() is None


def test_pospuesto_no_sale_hasta_su_hora(ruta):
    cola = ColaLocal(ruta)
    a, b = evento(1), evento(2)
    cola.encolar(a)
    cola.encolar(b)
    cola.posponer(a["uuid"], 30)
    pendiente = cola.siguiente()
    assert pendiente.payload == b
    cola.posponer(b["uuid"], 30)
    assert cola.siguiente() is None


def test_sobrevive_a_reiniciar_el_proceso(ruta):
    cola = ColaLocal(ruta)
    a = evento(1)
    cola.encolar(a)
    cola.posponer(a["uuid"], 0)
    cola.cerrar()

    otra = ColaLocal(ruta)
    pendiente = otra.siguiente()
    assert pendiente.payload == a
    assert pendiente.intentos == 1


def test_un_evento_que_se_reintenta_no_bloquea_a_los_de_atras(ruta):
    cola = ColaLocal(ruta)
    a, b = evento(1), evento(2)
    cola.encolar(a)
    cola.encolar(b)
    cola.posponer(a["uuid"], 0)  # a falló y ya le toca otra vez
    assert cola.siguiente().payload == b


def test_backoff_crece_hasta_60():
    assert [espera_backoff(i) for i in range(1, 8)] == [2, 4, 8, 16, 32, 60, 60]
