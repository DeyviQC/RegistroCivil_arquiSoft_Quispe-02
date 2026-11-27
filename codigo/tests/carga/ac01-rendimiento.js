// AC01 Rendimiento / AC03 Escalabilidad — k6
//
// Uso (ver docs/calidad/AC01-rendimiento.md):
//   k6 run -e BASE=http://registro-civil.test -e TOKEN=<PRUEBAS_CARGA_TOKEN> -e VUS=20 -e DURACION=2m tests/carga/ac01-rendimiento.js
//
// Dos perfiles simultáneos: 70 % ciudadanos (búsqueda pública y seguimiento) y
// 30 % personal (búsqueda del archivo y bandeja). Cada grupo de peticiones lleva
// la etiqueta "operacion" para obtener el percentil 95 por operación.
import http from 'k6/http';
import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';

const BASE = __ENV.BASE || 'http://registro-civil.test';
const VUS = parseInt(__ENV.VUS || '20', 10);
const DURACION = __ENV.DURACION || '2m';
const CABECERAS = { 'X-Prueba-Carga': __ENV.TOKEN || '' };
// Search cases exported by "php artisan pruebas:generar-datos" (DATOS=datos-50k.json for AC03).
const datos = new SharedArray('casos', () => [JSON.parse(open(`./${__ENV.DATOS || 'datos.json'}`))]);

const ciudadanos = Math.max(1, Math.round(VUS * 0.7));
export const options = {
    scenarios: {
        ciudadano: { executor: 'constant-vus', exec: 'ciudadano', vus: ciudadanos, duration: DURACION },
        personal: { executor: 'constant-vus', exec: 'personal', vus: Math.max(1, VUS - ciudadanos), duration: DURACION },
    },
    thresholds: {
        http_req_failed: ['rate<0.01'],
        'http_req_duration{operacion:busqueda_publica}': ['p(95)<1000'],
        'http_req_duration{operacion:seguimiento}': ['p(95)<1000'],
        'http_req_duration{operacion:busqueda_archivo}': ['p(95)<1000'],
        'http_req_duration{operacion:bandeja}': ['p(95)<1000'],
    },
    summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'max'],
    // Staff log in once per virtual user and keep the session, like a real working day.
    noCookiesReset: true,
};

const azar = (lista) => lista[Math.floor(Math.random() * lista.length)];
const token = (html) => (html.match(/name="_token" value="([^"]+)"/) || [])[1];
const op = (nombre, extra = {}) => Object.assign({ headers: CABECERAS, tags: { operacion: nombre } }, extra);
// Log every failed request with its status, so errors under load can be explained.
http.setResponseCallback(http.expectedStatuses({ min: 200, max: 399 }));
const vigilar = (r, nombre) => { if (r.status < 200 || r.status >= 400) console.warn(`fallo ${nombre}: estado=${r.status} error=${r.error || '-'} duracion=${Math.round(r.timings.duration)}ms`); return r; };

export function ciudadano() {
    const caso = azar(datos[0].busquedas);
    const inicio = vigilar(http.get(`${BASE}/tramite`, op('portada')), 'portada');
    check(inicio, { 'portada 200': (r) => r.status === 200 });

    // Public search: a real act must be found (redirect to the applicant form).
    const busqueda = http.post(`${BASE}/tramite/buscar`, {
        _token: token(inicio.body), terminos: '1', tipo: caso.tipo, titular: caso.titular, fecha: caso.fecha,
    }, op('busqueda_publica', { redirects: 0 }));
    check(busqueda, { 'búsqueda encuentra el acta': (r) => r.status === 302 && (r.headers.Location || '').includes('/tramite/datos') });
    sleep(1);

    const pagina = vigilar(http.get(`${BASE}/tramite/seguimiento`, op('seguimiento')), 'seguimiento');
    check(pagina, { 'seguimiento 200': (r) => r.status === 200 });
    const persona = azar(datos[0].seguimiento);
    const recuperar = http.post(`${BASE}/tramite/seguimiento`, { _token: token(pagina.body), dni: persona.dni, email: persona.email },
        op('seguimiento', { redirects: 0 }));
    check(recuperar, { 'seguimiento pide código': (r) => r.status === 302 && (r.headers.Location || '').includes('/tramite/codigo') });
    sleep(1);
}

export function personal() {
    if (__ITER === 0) {
        const login = http.get(`${BASE}/login`, op('login'));
        const r = http.post(`${BASE}/login`, { _token: token(login.body), username: 'asistente', password: 'Asistente123!' }, op('login'));
        const ok = check(r, { 'login correcto': (res) => res.status === 200 && res.url.includes('/dashboard') });
        if (!ok) console.warn(`login fallido: estado=${r.status} url=${r.url} error=${r.error || '-'} duracion=${Math.round(r.timings.duration)}ms`);
    }
    const caso = azar(datos[0].busquedas);
    const porNombre = vigilar(http.get(`${BASE}/actas?nombre=${encodeURIComponent(caso.nombre)}`, op('busqueda_archivo')), 'archivo');
    check(porNombre, { 'archivo por nombre 200': (r) => r.status === 200 });
    const porNumero = http.get(`${BASE}/actas?numero_acta=${encodeURIComponent(caso.numero)}`, op('busqueda_archivo'));
    check(porNumero, { 'archivo encuentra el número': (r) => r.status === 200 && r.body.includes(caso.numero) });
    sleep(1);

    const bandeja = vigilar(http.get(`${BASE}/atencion-ciudadana`, op('bandeja')), 'bandeja');
    check(bandeja, { 'bandeja 200': (r) => r.status === 200 && r.body.includes('Solicitudes ciudadanas') });
    const porDni = http.get(`${BASE}/atencion-ciudadana?dni=${azar(datos[0].seguimiento).dni}`, op('bandeja'));
    check(porDni, { 'bandeja por DNI 200': (r) => r.status === 200 });
    sleep(1);
}
