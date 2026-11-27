// AC05 Integridad — decisiones simultáneas sobre la misma solicitud (k6)
//
// Uso: k6 run -e BASE=http://registro-civil.test -e TOKEN=<PRUEBAS_CARGA_TOKEN> -e TRAMITES=11,12,13,14,15 tests/carga/ac05-concurrencia.js
//
// 10 usuarios del personal por solicitud abren la misma solicitud (misma versión) y deciden en el
// mismo instante: la mitad aprueba y la otra mitad rechaza. Con el bloqueo de fila y el control de
// versión, exactamente UNA decisión debe aplicarse por solicitud y las demás deben ser rechazadas.
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';

const BASE = __ENV.BASE || 'http://registro-civil.test';
const CABECERAS = { 'X-Prueba-Carga': __ENV.TOKEN || '' };
const TRAMITES = (__ENV.TRAMITES || '').split(',').filter(Boolean);
const POR_TRAMITE = 10;

const aplicadas = new Counter('decisiones_aplicadas');
const rechazadas = new Counter('decisiones_rechazadas');
const inesperadas = new Counter('respuestas_inesperadas');

export const options = {
    scenarios: { carrera: { executor: 'per-vu-iterations', vus: TRAMITES.length * POR_TRAMITE, iterations: 1, maxDuration: '2m' } },
    thresholds: { respuestas_inesperadas: ['count==0'] },
};

// Everyone fires at the same wall-clock instant, a few seconds after setup.
export function setup() { return { inicio: Date.now() + 10000 }; }

const token = (html) => (html.match(/name="_token" value="([^"]+)"/) || [])[1];

export default function (data) {
    const tramite = TRAMITES[Math.floor((__VU - 1) / POR_TRAMITE)];
    const login = http.get(`${BASE}/login`, { headers: CABECERAS });
    http.post(`${BASE}/login`, { _token: token(login.body), username: 'asistente', password: 'Asistente123!' }, { headers: CABECERAS });
    const pagina = http.get(`${BASE}/atencion-ciudadana/${tramite}`, { headers: CABECERAS });
    const version = (pagina.body.match(/name="version" value="(\d+)"/) || [])[1];
    check(pagina, { 'solicitud abierta con su versión': () => pagina.status === 200 && !!version });

    const espera = (data.inicio - Date.now()) / 1000;
    if (espera > 0) sleep(espera);

    const aprueba = __VU % 2 === 0;
    const r = http.post(`${BASE}/atencion-ciudadana/${tramite}`, Object.assign(
        { _token: token(pagina.body), _method: 'PATCH', version },
        aprueba ? { estado: 'APROBADA' } : { estado: 'RECHAZADA', mensaje: 'Rechazo de la prueba de concurrencia AC05.' },
    ), { headers: CABECERAS });

    if (/Solicitud aprobada|Respuesta enviada al ciudadano/.test(r.body)) aplicadas.add(1, { tramite });
    else if (/La solicitud cambió|Cambio de estado no permitido/.test(r.body)) rechazadas.add(1, { tramite });
    else { inesperadas.add(1); console.warn(`respuesta inesperada tramite=${tramite} estado=${r.status}`); }
}
