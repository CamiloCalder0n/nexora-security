import random

from nexora_agente.evento import ahora_colombia, armar_evento


def evento_simulado(camara_id: int) -> dict:
    """Evento de prueba sin cámara: respaldo de la demo si el video falla."""
    tipo = random.choice(["persona", "vehiculo"])
    cantidad = random.randint(1, 3) if tipo == "persona" else 1
    confianza = random.uniform(0.55, 0.95)
    return armar_evento(camara_id, tipo, cantidad, confianza, ahora_colombia(), None)
