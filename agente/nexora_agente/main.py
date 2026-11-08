import argparse
import logging

import cv2

from nexora_agente.captura import Captura
from nexora_agente.config import Config
from nexora_agente.detector import Detector

log = logging.getLogger("nexora_agente")


def _argumentos() -> argparse.Namespace:
    p = argparse.ArgumentParser(prog="nexora_agente", description="Agente de borde de Nexora Security")
    p.add_argument("--fuente", help="ruta .mp4, índice de webcam (0) o URL rtsp://")
    p.add_argument("--camara-id", type=int, required=True, help="id de la cámara en el backend")
    p.add_argument("--sin-api", action="store_true", help="no llama al backend")
    p.add_argument("--mostrar", action="store_true", help="ventana con las cajas detectadas (q para salir)")
    args = p.parse_args()
    if not args.fuente:
        p.error("falta --fuente")
    return args


def main() -> None:
    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s: %(message)s")
    args = _argumentos()
    config = Config.desde_entorno()
    detector = Detector(config.modelo, config.umbral_confianza)
    log.info("Cámara %s · fuente %s · dispositivo %s", args.camara_id, args.fuente, detector.dispositivo)

    try:
        for frame in Captura(args.fuente, config.fps).frames():
            for d in detector.detectar(frame):
                log.info("%s conf %.2f", d.tipo, d.confianza)
            if args.mostrar:
                cv2.imshow(f"Nexora · cámara {args.camara_id}", detector.dibujar())
                if cv2.waitKey(1) & 0xFF == ord("q"):
                    break
    except KeyboardInterrupt:
        pass
    finally:
        if args.mostrar:
            cv2.destroyAllWindows()
