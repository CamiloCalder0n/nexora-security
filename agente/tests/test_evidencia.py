import os
import time
from datetime import datetime
from pathlib import Path

import numpy as np

from nexora_agente.evento import ZONA_COLOMBIA
from nexora_agente.evidencia import borrar_vencidas, guardar_evidencia


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


def test_borra_solo_las_evidencias_vencidas_de_esa_camara(tmp_path):
    vieja, nueva, otra_camara = tmp_path / "cam1/vieja.jpg", tmp_path / "cam1/nueva.jpg", tmp_path / "cam2/vieja.jpg"
    for f in (vieja, nueva, otra_camara):
        f.parent.mkdir(exist_ok=True)
        f.write_bytes(b"x")
    hace_40_dias = time.time() - 40 * 86400
    os.utime(vieja, (hace_40_dias, hace_40_dias))
    os.utime(otra_camara, (hace_40_dias, hace_40_dias))

    assert borrar_vencidas(tmp_path, 1, 30) == 1
    assert not vieja.exists() and nueva.exists() and otra_camara.exists()
    assert borrar_vencidas(tmp_path, 3, 30) == 0  # cámara sin carpeta todavía


def test_una_evidencia_que_no_se_puede_borrar_no_tumba_la_limpieza(tmp_path, monkeypatch):
    for nombre in ("abierta.jpg", "otra.jpg"):
        f = tmp_path / "cam1" / nombre
        f.parent.mkdir(exist_ok=True)
        f.write_bytes(b"x")
        os.utime(f, (0, 0))
    original = Path.unlink

    def unlink(self, *a, **k):
        if self.name == "abierta.jpg":
            raise PermissionError("en uso por otro proceso")
        return original(self, *a, **k)

    monkeypatch.setattr(Path, "unlink", unlink)
    assert borrar_vencidas(tmp_path, 1, 30) == 1
    assert (tmp_path / "cam1/abierta.jpg").exists() and not (tmp_path / "cam1/otra.jpg").exists()
