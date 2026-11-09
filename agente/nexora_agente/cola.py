import json
import sqlite3
import threading
from dataclasses import dataclass
from datetime import datetime, timedelta, timezone
from pathlib import Path

ESPERA_MAXIMA_SEG = 60

ESQUEMA = """
CREATE TABLE IF NOT EXISTS pendientes (
    uuid TEXT PRIMARY KEY,
    payload TEXT NOT NULL,
    intentos INTEGER NOT NULL DEFAULT 0,
    proximo_intento TEXT NOT NULL,
    creado_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS rechazados (
    uuid TEXT NOT NULL,
    payload TEXT NOT NULL,
    motivo TEXT NOT NULL,
    creado_at TEXT NOT NULL
);
"""


def _ahora() -> datetime:
    return datetime.now(timezone.utc)


def espera_backoff(intentos: int) -> float:
    """2, 4, 8… segundos según los intentos fallidos, máximo 60."""
    return float(min(2**intentos, ESPERA_MAXIMA_SEG))


@dataclass(frozen=True)
class Pendiente:
    uuid: str
    payload: dict
    intentos: int


class ColaLocal:
    """Cola persistente: todo evento entra aquí primero y solo sale cuando el backend lo confirma.

    Sobrevive a reinicios del proceso y a cortes de internet (HU23).
    """

    def __init__(self, ruta: Path | str):
        self._lock = threading.Lock()
        self._db = sqlite3.connect(str(ruta), check_same_thread=False, isolation_level=None)
        self._db.executescript(ESQUEMA)

    def encolar(self, payload: dict) -> None:
        ahora = _ahora().isoformat()
        with self._lock:
            self._db.execute(
                "INSERT OR IGNORE INTO pendientes (uuid, payload, proximo_intento, creado_at) VALUES (?, ?, ?, ?)",
                (payload["uuid"], json.dumps(payload, ensure_ascii=False), ahora, ahora),
            )

    def siguiente(self) -> Pendiente | None:
        """El pendiente listo que lleva más tiempo esperando.

        Se ordena por próximo intento (no por creación) para que un evento que falla una y otra vez
        no bloquee a los de atrás: al reintentarse pasa al final de la fila.
        """
        with self._lock:
            fila = self._db.execute(
                "SELECT uuid, payload, intentos FROM pendientes WHERE proximo_intento <= ? "
                "ORDER BY proximo_intento, rowid LIMIT 1",
                (_ahora().isoformat(),),
            ).fetchone()
        return Pendiente(fila[0], json.loads(fila[1]), fila[2]) if fila else None

    def confirmar(self, uuid: str) -> None:
        with self._lock:
            self._db.execute("DELETE FROM pendientes WHERE uuid = ?", (uuid,))

    def posponer(self, uuid: str, segundos: float, contar_intento: bool = True) -> None:
        proximo = (_ahora() + timedelta(seconds=segundos)).isoformat()
        with self._lock:
            self._db.execute(
                "UPDATE pendientes SET intentos = intentos + ?, proximo_intento = ? WHERE uuid = ?",
                (1 if contar_intento else 0, proximo, uuid),
            )

    def rechazar(self, uuid: str, motivo: str) -> None:
        with self._lock:
            self._db.execute("BEGIN")
            try:
                self._db.execute(
                    "INSERT INTO rechazados (uuid, payload, motivo, creado_at) "
                    "SELECT uuid, payload, ?, ? FROM pendientes WHERE uuid = ?",
                    (motivo, _ahora().isoformat(), uuid),
                )
                self._db.execute("DELETE FROM pendientes WHERE uuid = ?", (uuid,))
            except Exception:
                self._db.execute("ROLLBACK")
                raise
            self._db.execute("COMMIT")

    def contar(self) -> dict[str, int]:
        with self._lock:
            return {
                tabla: self._db.execute(f"SELECT COUNT(*) FROM {tabla}").fetchone()[0]
                for tabla in ("pendientes", "rechazados")
            }

    def cerrar(self) -> None:
        with self._lock:
            self._db.close()
