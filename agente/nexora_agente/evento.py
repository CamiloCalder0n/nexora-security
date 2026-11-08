import uuid
from datetime import datetime
from zoneinfo import ZoneInfo

ZONA_COLOMBIA = ZoneInfo("America/Bogota")


def ahora_colombia() -> datetime:
    return datetime.now(ZONA_COLOMBIA).replace(microsecond=0)


def armar_evento(
    camara_id: int,
    tipo_objeto: str,
    cantidad: int,
    confianza: float,
    fecha_hora: datetime,
    ruta_evidencia: str | None,
) -> dict:
    """Payload de `POST /agente/eventos`, campo por campo como en docs/CONTRATO_API_AGENTE.md."""
    evento = {
        "uuid": str(uuid.uuid4()),
        "camara_id": camara_id,
        "tipo_objeto": tipo_objeto,
        "cantidad": cantidad,
        "confianza": round(confianza, 2),
        "fecha_hora": fecha_hora.isoformat(),
    }
    if ruta_evidencia:
        evento["ruta_evidencia"] = ruta_evidencia
    return evento
