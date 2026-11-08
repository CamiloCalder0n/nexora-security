from nexora_agente.captura import ocultar_credenciales


def test_oculta_usuario_y_clave_de_la_url_rtsp():
    assert ocultar_credenciales("rtsp://admin:clave123@192.168.1.20:554/stream") == "rtsp://***@192.168.1.20:554/stream"
    assert ocultar_credenciales("rtsp://admin:p@ss@10.0.0.1/s") == "rtsp://***@10.0.0.1/s"
    assert ocultar_credenciales("videos/parqueadero.mp4") == "videos/parqueadero.mp4"
