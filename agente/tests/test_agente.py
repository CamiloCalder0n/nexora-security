import argparse
import os
import time

import pytest

from nexora_agente.config import Config, _retencion
from nexora_agente.main import Agente


def agente(tmp_path, simular=False, sin_api=True):
    # Config armada a mano: el test no depende del agente/.env de cada máquina.
    config = Config(
        api_url="http://localhost", agente_email="", agente_password="", device_name="pytest",
        umbral_confianza=0.5, persistencia_min=2, persistencia_ventana=3, cooldown_seg=10, fps=5,
        heartbeat_seg=60, timeout_seg=5, modelo="yolov8n.pt",
        datos_dir=tmp_path, retencion_evidencias_dias=30,
    )
    args = argparse.Namespace(simular=simular, sin_api=sin_api, camara_id=1)
    return Agente(args, config)


def test_camara_viva_solo_con_frames_recientes(tmp_path):
    a = agente(tmp_path)
    assert not a.camara_viva()  # todavía no llega ningún frame
    a.ultimo_frame = time.monotonic() - 100
    assert a.camara_viva()
    a.ultimo_frame = time.monotonic() - 121  # más de 2 × heartbeat
    assert not a.camara_viva()
    a.cerrar()


def test_las_pruebas_sin_api_no_comparten_cola_con_el_modo_real(tmp_path):
    prueba = agente(tmp_path)
    prueba.registrar({"uuid": "3f1c2b9e-3a4d-4c55-9e0a-1b2c3d4e5f60"})
    prueba.cerrar()
    real = agente(tmp_path, sin_api=False)
    assert real.cola.contar() == {"pendientes": 0, "rechazados": 0}
    real.cerrar()


def test_el_simulador_siempre_esta_vivo(tmp_path):
    a = agente(tmp_path, simular=True)
    assert a.camara_viva()
    a.cerrar()


def test_la_limpieza_borra_al_arrancar_y_se_detiene_al_cerrar(tmp_path):
    a = agente(tmp_path)
    vieja = tmp_path / "evidencias/cam1/vieja.jpg"
    vieja.parent.mkdir(parents=True)
    vieja.write_bytes(b"x")
    os.utime(vieja, (0, 0))
    a.arrancar_limpieza()
    a.cerrar()  # despierta el hilo, que estaba esperando un día
    assert not vieja.exists()
    assert not any(h.is_alive() for h in a.hilos)


@pytest.mark.parametrize("valor", ["0", "-5"])
def test_retencion_menor_a_un_dia_se_rechaza(valor):
    with pytest.raises(ValueError):
        _retencion(valor)
