import threading
from dataclasses import dataclass

import requests

from nexora_agente.evento import ahora_colombia


class SinConexion(Exception):
    """No se pudo hablar con el backend (sin red, timeout o servidor caído)."""


class CredencialesInvalidas(Exception):
    """El backend rechazó el login del agente (401 o 422)."""


@dataclass(frozen=True)
class Respuesta:
    status: int
    cuerpo: dict
    retry_after: float | None = None


def _segundos(retry_after: str | None) -> float | None:
    try:
        return float(retry_after) if retry_after else None
    except ValueError:  # Retry-After también puede venir como fecha HTTP
        return None


class ClienteApi:
    """Cliente de docs/CONTRATO_API_AGENTE.md. Guarda el token en memoria y lo renueva ante un 401."""

    def __init__(self, base_url: str, email: str, password: str, device_name: str, timeout: float):
        self.base_url = base_url.rstrip("/")
        self.email = email
        self.password = password
        self.device_name = device_name
        self.timeout = timeout
        self.token: str | None = None
        self._sesion = requests.Session()
        self._sesion.headers.update({"Accept": "application/json", "Content-Type": "application/json"})
        self._lock_login = threading.Lock()

    def _pedir(self, metodo: str, ruta: str, cuerpo: dict | None = None, token: str | None = None) -> Respuesta:
        headers = {"Authorization": f"Bearer {token}"} if token else {}
        try:
            r = self._sesion.request(metodo, self.base_url + ruta, json=cuerpo, headers=headers, timeout=self.timeout)
        except requests.RequestException as e:
            raise SinConexion(str(e)) from e
        try:
            datos = r.json() if r.content else {}
        except ValueError:
            datos = {"message": r.text[:200]}
        return Respuesta(r.status_code, datos, _segundos(r.headers.get("Retry-After")))

    def login(self) -> None:
        with self._lock_login:
            self._login()

    def _login(self) -> None:
        r = self._pedir(
            "POST",
            "/agente/login",
            {"email": self.email, "password": self.password, "device_name": self.device_name},
        )
        if r.status == 200:
            self.token = r.cuerpo["token"]
        elif r.status in (401, 422):
            raise CredencialesInvalidas(r.cuerpo.get("message", str(r.status)))
        else:
            raise SinConexion(f"login respondió {r.status}")

    def _token_vigente(self, rechazado: str | None = None) -> str:
        """Token para usar; hace login solo si no hay o si es el que el backend acaba de rechazar.

        Va dentro del lock porque el enviador y el heartbeat pueden recibir 401 a la vez:
        el segundo hilo reutiliza el token que renovó el primero en vez de loguearse otra vez.
        """
        with self._lock_login:
            if self.token is None or self.token == rechazado:
                self._login()
            return self.token

    def _autenticado(self, metodo: str, ruta: str, cuerpo: dict | None = None) -> Respuesta:
        token = self._token_vigente()
        r = self._pedir(metodo, ruta, cuerpo, token)
        if r.status == 401:
            r = self._pedir(metodo, ruta, cuerpo, self._token_vigente(rechazado=token))
        return r

    def camaras(self) -> list[dict]:
        r = self._autenticado("GET", "/agente/camaras")
        if r.status != 200:
            raise SinConexion(f"GET /agente/camaras respondió {r.status}")
        return r.cuerpo.get("data", [])

    def enviar_evento(self, payload: dict) -> Respuesta:
        return self._autenticado("POST", "/agente/eventos", payload)

    def heartbeat(self, camara_id: int) -> Respuesta:
        return self._autenticado(
            "POST", f"/agente/camaras/{camara_id}/heartbeat", {"fecha_hora": ahora_colombia().isoformat()}
        )
