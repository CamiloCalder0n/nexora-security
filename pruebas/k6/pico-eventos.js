// Pico de detecciones contra POST /api/v1/agente/eventos (ArqSoft, §4.4 del plan).
//
// Una cámara que de pronto manda el doble de lo que permite el contrato (60 eventos por minuto
// por token): el backend responde 202 y encola mientras hay cupo, y 429 con Retry-After cuando
// se pasa. Ninguna otra respuesta es aceptable. Uso y advertencias en pruebas/k6/README.md.
import http from 'k6/http';
import { check } from 'k6';

const BASE_URL = __ENV.BASE_URL || 'http://host.docker.internal:8000';
const TOKEN = __ENV.TOKEN;
const CAMARA_ID = Number(__ENV.CAMARA_ID);

export const options = {
  scenarios: {
    // 2 eventos por segundo durante 60 s: 120 en un minuto, el doble del cupo.
    pico: { executor: 'constant-arrival-rate', rate: 2, timeUnit: '1s', duration: '60s', preAllocatedVUs: 10 },
  },
  thresholds: {
    checks: ['rate==1'],
    http_req_failed: ['rate==0'], // fuera de 202 y 429, todo cuenta como falla (incluidos los 5xx)
    'http_reqs{status:202}': ['count>=55', 'count<=62'],
    'http_req_duration{status:202}': ['p(95)<500'],
  },
};

http.setResponseCallback(http.expectedStatuses(202, 429));

function ahoraEnColombia() {
  const t = new Date(Date.now() - 5 * 3600 * 1000).toISOString().slice(0, 19);
  return `${t}-05:00`;
}

export function setup() {
  if (!TOKEN || !CAMARA_ID) {
    throw new Error('Faltan TOKEN (token Sanctum del agente) o CAMARA_ID (una cámara que exista).');
  }
}

export default function () {
  const r = http.post(`${BASE_URL}/api/v1/agente/eventos`, JSON.stringify({
    uuid: crypto.randomUUID(),
    camara_id: CAMARA_ID,
    tipo_objeto: 'persona',
    cantidad: 1,
    confianza: 0.9,
    fecha_hora: ahoraEnColombia(),
  }), {
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${TOKEN}` },
  });

  // En el último segundo de la ventana Laravel puede responder Retry-After: 0; lo que importa es que venga.
  check(r, { '429 trae Retry-After': (res) => res.status !== 429 || res.headers['Retry-After'] !== undefined });
}
