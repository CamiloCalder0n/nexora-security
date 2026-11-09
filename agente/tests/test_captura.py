from nexora_agente import captura
from nexora_agente.captura import Captura, ocultar_credenciales


def test_oculta_usuario_y_clave_de_la_url_rtsp():
    assert ocultar_credenciales("rtsp://admin:clave123@192.168.1.20:554/stream") == "rtsp://***@192.168.1.20:554/stream"
    assert ocultar_credenciales("rtsp://admin:p@ss@10.0.0.1/s") == "rtsp://***@10.0.0.1/s"
    assert ocultar_credenciales("videos/parqueadero.mp4") == "videos/parqueadero.mp4"


class VideoFalso:
    """Imita cv2.VideoCapture: entrega `n` frames numerados y luego se acaba."""

    def __init__(self, n, fps=30.0):
        self.restantes, self.fps, self.leidos = n, fps, 0

    def isOpened(self):
        return True

    def get(self, _prop):
        return self.fps

    def read(self):
        if self.restantes == 0:
            return False, None
        self.restantes -= 1
        self.leidos += 1
        return True, self.leidos - 1

    def release(self):
        pass


def test_un_mp4_a_30_fps_muestreado_a_5_fps_entrega_uno_de_cada_seis(tmp_path, monkeypatch):
    video = tmp_path / "prueba.mp4"
    video.write_bytes(b"")
    monkeypatch.setattr(captura.time, "sleep", lambda _s: None)
    frames = list(Captura(str(video), 5)._leer(VideoFalso(30)))
    assert frames == [0, 6, 12, 18, 24]


def test_si_la_camara_en_vivo_se_cae_la_vuelve_a_abrir(monkeypatch):
    aperturas = []

    def abrir(_fuente):
        aperturas.append(1)
        return VideoFalso(2)

    monkeypatch.setattr(captura.cv2, "VideoCapture", abrir)
    monkeypatch.setattr(captura.time, "sleep", lambda _s: None)
    reloj = iter(range(0, 10_000))
    monkeypatch.setattr(captura.time, "monotonic", lambda: next(reloj))  # cada lectura avanza 1 s: no se descarta nada

    frames = []
    for frame in Captura("rtsp://camara/s", 5).frames():
        frames.append(frame)
        if len(frames) == 4:
            break
    assert frames == [0, 1, 0, 1] and len(aperturas) == 2
