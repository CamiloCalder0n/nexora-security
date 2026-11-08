import re
from datetime import datetime

from nexora_agente.detector import Deteccion
from nexora_agente.evento import ZONA_COLOMBIA, armar_evento
from nexora_agente.filtro import Filtro

PERSONA = Deteccion("persona", 0.8, (0, 0, 10, 10))


class Reloj:
    def __init__(self):
        self.t = 0.0

    def __call__(self):
        return self.t


def nuevo_filtro(reloj):
    return Filtro(persistencia_min=2, ventana=3, cooldown_seg=10, reloj=reloj)


def test_una_deteccion_aislada_no_genera_evento():
    reloj = Reloj()
    filtro = nuevo_filtro(reloj)
    assert filtro.procesar([PERSONA]) == []
    reloj.t = 0.2
    assert filtro.procesar([]) == []
    reloj.t = 0.4
    assert filtro.procesar([]) == []


def test_dos_seguidas_generan_un_evento():
    reloj = Reloj()
    filtro = nuevo_filtro(reloj)
    filtro.procesar([PERSONA])
    reloj.t = 0.2
    hallazgos = filtro.procesar([PERSONA, Deteccion("persona", 0.91, (5, 5, 9, 9))])
    assert len(hallazgos) == 1
    assert hallazgos[0].tipo == "persona"
    assert hallazgos[0].cantidad == 2
    assert hallazgos[0].confianza == 0.91


def test_dentro_del_cooldown_no_repite_y_despues_si():
    reloj = Reloj()
    filtro = nuevo_filtro(reloj)
    filtro.procesar([PERSONA])
    reloj.t = 0.2
    assert len(filtro.procesar([PERSONA])) == 1
    reloj.t = 5
    assert filtro.procesar([PERSONA]) == []
    reloj.t = 10.3
    assert len(filtro.procesar([PERSONA])) == 1


def test_cooldown_es_por_tipo():
    reloj = Reloj()
    filtro = nuevo_filtro(reloj)
    vehiculo = Deteccion("vehiculo", 0.7, (0, 0, 1, 1))
    filtro.procesar([PERSONA])
    reloj.t = 0.2
    filtro.procesar([PERSONA])
    reloj.t = 0.4
    filtro.procesar([PERSONA, vehiculo])
    reloj.t = 0.6
    assert [h.tipo for h in filtro.procesar([PERSONA, vehiculo])] == ["vehiculo"]


def test_evento_sigue_el_contrato():
    fecha = datetime(2026, 11, 6, 19, 4, 40, tzinfo=ZONA_COLOMBIA)
    evento = armar_evento(1, "persona", 2, 0.873, fecha, "evidencias/cam1/20261106-190440.jpg")
    assert set(evento) == {"uuid", "camara_id", "tipo_objeto", "cantidad", "confianza", "fecha_hora", "ruta_evidencia"}
    assert re.fullmatch(r"[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}", evento["uuid"])
    assert evento["fecha_hora"] == "2026-11-06T19:04:40-05:00"
    assert evento["confianza"] == 0.87
    assert "ruta_evidencia" not in armar_evento(1, "persona", 1, 0.5, fecha, None)
