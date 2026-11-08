from datetime import datetime

import numpy as np

from nexora_agente.evento import ZONA_COLOMBIA
from nexora_agente.evidencia import guardar_evidencia


def test_dos_evidencias_en_el_mismo_segundo_no_se_pisan(tmp_path):
    base = tmp_path / "evidencias"
    fecha = datetime(2026, 11, 6, 19, 4, 40, tzinfo=ZONA_COLOMBIA)
    frame = np.zeros((10, 10, 3), np.uint8)

    primera = guardar_evidencia(frame, base, 1, fecha)
    segunda = guardar_evidencia(frame, base, 1, fecha)

    assert primera == "evidencias/cam1/20261106-190440.jpg"
    assert segunda == "evidencias/cam1/20261106-190440-1.jpg"
    assert (base / "cam1" / "20261106-190440-1.jpg").is_file()


def test_si_no_se_puede_guardar_no_hay_ruta(tmp_path, monkeypatch):
    monkeypatch.setattr("nexora_agente.evidencia.cv2.imwrite", lambda *_: False)
    fecha = datetime(2026, 11, 6, 19, 4, 40, tzinfo=ZONA_COLOMBIA)
    assert guardar_evidencia(np.zeros((10, 10, 3), np.uint8), tmp_path / "evidencias", 1, fecha) is None
