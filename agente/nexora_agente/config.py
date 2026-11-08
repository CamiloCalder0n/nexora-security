import os
from dataclasses import dataclass
from pathlib import Path

from dotenv import load_dotenv

RAIZ_AGENTE = Path(__file__).resolve().parent.parent


def _persistencia(valor: str) -> tuple[int, int]:
    minimo, ventana = (int(x) for x in valor.split("/"))
    if not 1 <= minimo <= ventana:
        raise ValueError(f"PERSISTENCIA inválida: {valor} (usar m/n con 1 <= m <= n)")
    return minimo, ventana


@dataclass(frozen=True)
class Config:
    api_url: str
    agente_email: str
    agente_password: str
    device_name: str
    umbral_confianza: float
    persistencia_min: int
    persistencia_ventana: int
    cooldown_seg: float
    fps: float
    heartbeat_seg: float
    timeout_seg: float
    modelo: str
    cola_db: Path
    evidencias_dir: Path

    @classmethod
    def desde_entorno(cls) -> "Config":
        load_dotenv(RAIZ_AGENTE / ".env")
        minimo, ventana = _persistencia(os.getenv("PERSISTENCIA", "2/3"))
        return cls(
            api_url=os.getenv("API_URL", "http://localhost:8000/api/v1").rstrip("/"),
            agente_email=os.getenv("AGENTE_EMAIL", ""),
            agente_password=os.getenv("AGENTE_PASSWORD", ""),
            device_name=os.getenv("DEVICE_NAME", "agente-portatil-1"),
            umbral_confianza=float(os.getenv("UMBRAL_CONFIANZA", "0.5")),
            persistencia_min=minimo,
            persistencia_ventana=ventana,
            cooldown_seg=float(os.getenv("COOLDOWN_SEG", "10")),
            fps=float(os.getenv("FPS", "5")),
            heartbeat_seg=float(os.getenv("HEARTBEAT_SEG", "60")),
            timeout_seg=float(os.getenv("TIMEOUT_SEG", "5")),
            modelo=os.getenv("MODELO", "yolov8n.pt"),
            cola_db=RAIZ_AGENTE / os.getenv("COLA_DB", "cola.db"),
            evidencias_dir=RAIZ_AGENTE / "evidencias",
        )
