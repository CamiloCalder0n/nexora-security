import argparse
import json
import logging
import signal
import threading
import time

from nexora_agente.cliente_api import ClienteApi, CredencialesInvalidas, SinConexion
from nexora_agente.cola import ColaLocal
from nexora_agente.config import Config
from nexora_agente.enviador import Enviador
from nexora_agente.evento import ahora_colombia, armar_evento
from nexora_agente.heartbeat import latir
from nexora_agente.simulador import evento_simulado

log = logging.getLogger("nexora_agente")

LIMPIEZA_EVIDENCIAS_SEG = 24 * 60 * 60


def _argumentos() -> argparse.Namespace:
    p = argparse.ArgumentParser(prog="nexora_agente", description="Agente de borde de Nexora Security")
    p.add_argument("--fuente", help="ruta .mp4, índice de webcam (0) o URL rtsp://")
    p.add_argument("--camara-id", type=int, required=True, help="id de la cámara en el backend")
    p.add_argument("--sin-api", action="store_true", help="no llama al backend; los eventos quedan en la cola")
    p.add_argument("--mostrar", action="store_true", help="ventana con las cajas detectadas (q para salir)")
    p.add_argument("--simular", action="store_true", help="genera eventos falsos sin cámara")
    p.add_argument("--cada", type=float, default=15, help="segundos entre eventos simulados (con --simular)")
    args = p.parse_args()
    if not args.simular and not args.fuente:
        p.error("falta --fuente (o usar --simular)")
    return args


def _terminar(*_):
    raise KeyboardInterrupt


class Agente:
    def __init__(self, args: argparse.Namespace, config: Config):
        self.args = args
        self.config = config
        # Una cola por cámara: cada proceso es independiente y la falla de uno no toca a los demás (HU40).
        # Las pruebas con --sin-api van a otra cola: si no, la siguiente corrida real las mandaría al backend.
        sufijo = "-sinapi" if args.sin_api else ""
        self.cola = ColaLocal(config.datos_dir / f"cola-cam{args.camara_id}{sufijo}.db")
        self.detener = threading.Event()
        self.hilos: list[threading.Thread] = []
        self.ultimo_frame: float | None = None

    def camara_viva(self) -> bool:
        if self.args.simular:
            return True
        return self.ultimo_frame is not None and time.monotonic() - self.ultimo_frame < 2 * self.config.heartbeat_seg

    def registrar(self, evento: dict) -> None:
        self.cola.encolar(evento)
        if self.args.sin_api:
            print("enviaría:", json.dumps(evento, ensure_ascii=False), flush=True)
        else:
            log.info(
                "Evento %s %s x%s conf %.2f en cola",
                evento["uuid"],
                evento["tipo_objeto"],
                evento["cantidad"],
                evento["confianza"],
            )

    def conectar(self) -> None:
        c = self.config
        if not (c.agente_email and c.agente_password):
            raise SystemExit("Faltan AGENTE_EMAIL y AGENTE_PASSWORD en agente/.env (o usar --sin-api)")
        cliente = ClienteApi(c.api_url, c.agente_email, c.agente_password, c.device_name, c.timeout_seg)
        try:
            camara = next((x for x in cliente.camaras() if x["id"] == self.args.camara_id), None)
            if camara is None:
                log.error("La cámara %s no está asignada a este agente en el backend", self.args.camara_id)
            else:
                log.info(
                    "%s · zona %s · vigilada ahora: %s",
                    camara["nombre"],
                    camara["zona"]["nombre"],
                    camara["vigilada_ahora"],
                )
        except CredencialesInvalidas as e:
            raise SystemExit(f"Login rechazado: {e}") from e
        except SinConexion as e:
            log.warning("Backend no disponible (%s); los eventos esperan en la cola local", e)

        for nombre, objetivo, argumentos in (
            ("enviador", Enviador(self.cola, cliente).correr, (self.detener,)),
            ("heartbeat", latir, (cliente, self.args.camara_id, c.heartbeat_seg, self.detener, self.camara_viva)),
        ):
            hilo = threading.Thread(target=objetivo, args=argumentos, name=nombre, daemon=True)
            hilo.start()
            self.hilos.append(hilo)

    def simular(self) -> None:
        log.info("Simulador · cámara %s · un evento cada %ss", self.args.camara_id, self.args.cada)
        while not self.detener.is_set():
            self.registrar(evento_simulado(self.args.camara_id))
            self.detener.wait(self.args.cada)

    def vigilar(self) -> None:
        # Imports pesados (OpenCV, YOLO) solo cuando hay cámara; --simular arranca al instante.
        import cv2

        from nexora_agente.captura import Captura, ocultar_credenciales
        from nexora_agente.detector import Detector
        from nexora_agente.evidencia import guardar_evidencia
        from nexora_agente.filtro import Filtro

        c, camara_id = self.config, self.args.camara_id
        detector = Detector(c.modelo, c.umbral_confianza)
        filtro = Filtro(c.persistencia_min, c.persistencia_ventana, c.cooldown_seg)
        log.info(
            "Cámara %s · fuente %s · dispositivo %s",
            camara_id,
            ocultar_credenciales(self.args.fuente),
            detector.dispositivo,
        )
        try:
            for frame in Captura(self.args.fuente, c.fps).frames():
                self.ultimo_frame = time.monotonic()
                hallazgos = filtro.procesar(detector.detectar(frame))
                if hallazgos:
                    fecha_hora = ahora_colombia()
                    ruta = guardar_evidencia(frame, c.evidencias_dir, camara_id, fecha_hora)
                    for h in hallazgos:
                        self.registrar(armar_evento(camara_id, h.tipo, h.cantidad, h.confianza, fecha_hora, ruta))
                if self.args.mostrar:
                    cv2.imshow(f"Nexora · cámara {camara_id}", detector.dibujar())
                    if cv2.waitKey(1) & 0xFF == ord("q"):
                        break
        finally:
            if self.args.mostrar:
                cv2.destroyAllWindows()

    def arrancar_limpieza(self) -> None:
        """En todos los modos: al arrancar y luego una vez al día, aunque la cámara no entregue frames (Ley 1581)."""
        hilo = threading.Thread(target=self.limpiar_evidencias, name="evidencias", daemon=True)
        hilo.start()
        self.hilos.append(hilo)

    def limpiar_evidencias(self) -> None:
        from nexora_agente.evidencia import borrar_vencidas

        dias = self.config.retencion_evidencias_dias
        while True:
            borradas = borrar_vencidas(self.config.evidencias_dir, self.args.camara_id, dias)
            if borradas:
                log.info("Evidencias de más de %s días borradas: %s", dias, borradas)
            if self.detener.wait(LIMPIEZA_EVIDENCIAS_SEG):
                return

    def cerrar(self) -> None:
        self.detener.set()
        for hilo in self.hilos:
            hilo.join(timeout=self.config.timeout_seg + 1)
        log.info("Cola local: %s", self.cola.contar())
        self.cola.cerrar()


def main() -> None:
    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(threadName)s: %(message)s")
    signal.signal(signal.SIGTERM, _terminar)
    args = _argumentos()
    agente = Agente(args, Config.desde_entorno())
    try:
        agente.arrancar_limpieza()
        if not args.sin_api:
            agente.conectar()
        if args.simular:
            agente.simular()
        else:
            agente.vigilar()
    except KeyboardInterrupt:
        pass
    finally:
        agente.cerrar()
