import argparse
import time

from nexora_agente.config import Config
from nexora_agente.main import Agente


def agente(tmp_path, simular=False):
    # Config armada a mano: el test no depende del agente/.env de cada máquina.
    config = Config(
        api_url="http://localhost", agente_email="", agente_password="", device_name="pytest",
        umbral_confianza=0.5, persistencia_min=2, persistencia_ventana=3, cooldown_seg=10, fps=5,
        heartbeat_seg=60, timeout_seg=5, modelo="yolov8n.pt",
        cola_db=tmp_path / "cola.db", evidencias_dir=tmp_path / "evidencias",
    )
    args = argparse.Namespace(simular=simular, sin_api=True, camara_id=1)
    return Agente(args, config)


def test_camara_viva_solo_con_frames_recientes(tmp_path):
    a = agente(tmp_path)
    assert not a.camara_viva()  # todavía no llega ningún frame
    a.ultimo_frame = time.monotonic() - 100
    assert a.camara_viva()
    a.ultimo_frame = time.monotonic() - 121  # más de 2 × heartbeat
    assert not a.camara_viva()
    a.cerrar()


def test_el_simulador_siempre_esta_vivo(tmp_path):
    a = agente(tmp_path, simular=True)
    assert a.camara_viva()
    a.cerrar()
