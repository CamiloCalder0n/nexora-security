import argparse
import json
import logging

import cv2

from nexora_agente.captura import Captura
from nexora_agente.cola import ColaLocal
from nexora_agente.config import Config
from nexora_agente.detector import Detector
from nexora_agente.evento import ahora_colombia, armar_evento
from nexora_agente.evidencia import guardar_evidencia
from nexora_agente.filtro import Filtro

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
    filtro = Filtro(config.persistencia_min, config.persistencia_ventana, config.cooldown_seg)
    cola = ColaLocal(config.cola_db)
    log.info("Cámara %s · fuente %s · dispositivo %s", args.camara_id, args.fuente, detector.dispositivo)

    try:
        for frame in Captura(args.fuente, config.fps).frames():
            hallazgos = filtro.procesar(detector.detectar(frame))
            if hallazgos:
                fecha_hora = ahora_colombia()
                ruta = guardar_evidencia(frame, config.evidencias_dir, args.camara_id, fecha_hora)
                for h in hallazgos:
                    evento = armar_evento(args.camara_id, h.tipo, h.cantidad, h.confianza, fecha_hora, ruta)
                    cola.encolar(evento)
                    if args.sin_api:
                        print("enviaría:", json.dumps(evento, ensure_ascii=False), flush=True)
            if args.mostrar:
                cv2.imshow(f"Nexora · cámara {args.camara_id}", detector.dibujar())
                if cv2.waitKey(1) & 0xFF == ord("q"):
                    break
    except KeyboardInterrupt:
        pass
    finally:
        log.info("Cola local: %s", cola.contar())
        cola.cerrar()
        if args.mostrar:
            cv2.destroyAllWindows()
