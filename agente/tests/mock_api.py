"""Backend falso que responde según docs/CONTRATO_API_AGENTE.md. Solo para pruebas.

    python -m tests.mock_api --puerto 8000

Credenciales: agente@nexora.test / secreto. Cámaras: 1 (vigilada) y 2.
"""

import argparse
import json
import re
import threading
import uuid
from datetime import datetime, timedelta
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

EMAIL, PASSWORD = "agente@nexora.test", "secreto"
CAMARAS = {
    1: {"id": 1, "nombre": "CAM-01 Parqueadero", "estado": "activa",
        "zona": {"id": 3, "nombre": "Parqueadero sótano", "prioridad_base": 3}, "vigilada_ahora": True},
    2: {"id": 2, "nombre": "CAM-02 Portería", "estado": "activa",
        "zona": {"id": 1, "nombre": "Portería", "prioridad_base": 2}, "vigilada_ahora": False},
}
UUID_V4 = re.compile(r"^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$")


class Estado:
    def __init__(self):
        self.tokens: set[str] = set()
        self.eventos: dict[str, dict] = {}
        self.heartbeats: list[int] = []
        self.forzar_status: int | None = None  # p. ej. 500 o 429 para simular fallas


def _errores_evento(e: dict) -> dict:
    errores = {}
    if not UUID_V4.match(str(e.get("uuid", ""))):
        errores["uuid"] = ["Debe ser un UUID v4."]
    if e.get("camara_id") not in CAMARAS:
        errores["camara_id"] = ["La cámara no existe."]
    if e.get("tipo_objeto") not in ("persona", "vehiculo"):
        errores["tipo_objeto"] = ["Debe ser persona o vehiculo."]
    if not isinstance(e.get("cantidad"), int) or not 1 <= e["cantidad"] <= 50:
        errores["cantidad"] = ["Entero entre 1 y 50."]
    if not isinstance(e.get("confianza"), (int, float)) or not 0 <= e["confianza"] <= 1:
        errores["confianza"] = ["Número entre 0 y 1."]
    try:
        fecha = datetime.fromisoformat(e["fecha_hora"])
        ahora = datetime.now(fecha.tzinfo)
        if fecha.tzinfo is None or not ahora - timedelta(hours=24) <= fecha <= ahora + timedelta(minutes=5):
            raise ValueError
    except (KeyError, TypeError, ValueError):
        errores["fecha_hora"] = ["ISO 8601 con zona, máx. 24 h atrás y 5 min adelante."]
    return errores


def crear_handler(estado: Estado):
    class Handler(BaseHTTPRequestHandler):
        def log_message(self, *_):
            pass

        def _responder(self, status: int, cuerpo: dict | None = None, headers: dict | None = None):
            datos = json.dumps(cuerpo).encode() if cuerpo is not None else b""
            self.send_response(status)
            self.send_header("Content-Type", "application/json")
            for k, v in (headers or {}).items():
                self.send_header(k, v)
            self.send_header("Content-Length", str(len(datos)))
            self.end_headers()
            self.wfile.write(datos)

        def _cuerpo(self) -> dict:
            largo = int(self.headers.get("Content-Length") or 0)
            return json.loads(self.rfile.read(largo) or b"{}")

        def _autorizado(self) -> bool:
            token = self.headers.get("Authorization", "").removeprefix("Bearer ")
            if token in estado.tokens:
                return True
            self._responder(401, {"message": "Unauthenticated."})
            return False

        def _forzado(self) -> bool:
            if estado.forzar_status is None:
                return False
            headers = {"Retry-After": "1"} if estado.forzar_status == 429 else None
            self._responder(estado.forzar_status, {"message": "forzado"}, headers)
            return True

        def do_GET(self):
            if self._forzado():
                return
            if self.path != "/api/v1/agente/camaras":
                self._responder(404, {"message": "No encontrado"})
            elif self._autorizado():
                self._responder(200, {"data": list(CAMARAS.values())})

        def do_POST(self):
            if self._forzado():
                return
            cuerpo = self._cuerpo()
            if self.path == "/api/v1/agente/login":
                if not cuerpo.get("email") or not cuerpo.get("password"):
                    self._responder(422, {"message": "Datos inválidos", "errors": {"email": ["Requerido."]}})
                elif (cuerpo["email"], cuerpo["password"]) != (EMAIL, PASSWORD):
                    self._responder(401, {"message": "Credenciales inválidas"})
                else:
                    token = f"1|{uuid.uuid4().hex}"
                    estado.tokens.add(token)
                    self._responder(200, {"token": token, "usuario": {"id": 9, "nombre": "Agente portería", "rol": "agente"}})
                return
            if not self._autorizado():
                return
            if self.path == "/api/v1/agente/eventos":
                if errores := _errores_evento(cuerpo):
                    self._responder(422, {"message": "Datos inválidos", "errors": errores})
                elif cuerpo["uuid"] in estado.eventos:
                    self._responder(200, {"data": {"uuid": cuerpo["uuid"], "estado": "duplicado"}})
                else:
                    estado.eventos[cuerpo["uuid"]] = cuerpo
                    self._responder(202, {"data": {"uuid": cuerpo["uuid"], "estado": "recibido"}})
                return
            if m := re.fullmatch(r"/api/v1/agente/camaras/(\d+)/heartbeat", self.path):
                camara_id = int(m.group(1))
                if camara_id not in CAMARAS:
                    self._responder(404, {"message": "Cámara no encontrada"})
                else:
                    estado.heartbeats.append(camara_id)
                    self._responder(204)
                return
            self._responder(404, {"message": "No encontrado"})

    return Handler


def iniciar(puerto: int = 0) -> tuple[ThreadingHTTPServer, Estado]:
    estado = Estado()
    servidor = ThreadingHTTPServer(("127.0.0.1", puerto), crear_handler(estado))
    threading.Thread(target=servidor.serve_forever, daemon=True).start()
    return servidor, estado


if __name__ == "__main__":
    p = argparse.ArgumentParser()
    p.add_argument("--puerto", type=int, default=8000)
    servidor, _ = iniciar(p.parse_args().puerto)
    print(f"Mock del backend en http://127.0.0.1:{servidor.server_port}/api/v1 (Ctrl+C para salir)")
    try:
        threading.Event().wait()
    except KeyboardInterrupt:
        servidor.shutdown()
