// EQ-01 Rendimiento y EQ-03 Escalabilidad — carga progresiva hasta 2 000 usuarios simultáneos (k6)
//
// Uso (ver sprint-2/06-atributos-de-calidad.md):
//   k6 run -e BASE=http://127.0.0.1:8020 -e TOKEN=<PRUEBAS_CARGA_TOKEN> tests/carga/eq-progresiva.js
// Variables opcionales: NIVELES=50,100,250,500,1000,1500,2000  SUBIDA=30  MANTENER=90 (segundos)
//                       PERSONAL=10  DATOS=datos-50k.json  SALIDA=eq-progresiva-resumen.json
//
// Each level ramps up for SUBIDA seconds and then holds for MANTENER seconds. Only the hold ("meseta")
// is measured, and every request is tagged with its level, so the p95 is reported per level.
// Citizens grow with each level; staff stay at a fixed number of people, as in a real municipality.
import http from 'k6/http';
import { check, sleep } from 'k6';
import exec from 'k6/execution';
import { SharedArray } from 'k6/data';

const BASE = __ENV.BASE || 'http://127.0.0.1:8020';
const CABECERAS = { 'X-Prueba-Carga': __ENV.TOKEN || '' };
const NIVELES = (__ENV.NIVELES || '50,100,250,500,1000,1500,2000').split(',').map((n) => parseInt(n, 10));
const SUBIDA = parseInt(__ENV.SUBIDA || '30', 10);
const MANTENER = parseInt(__ENV.MANTENER || '90', 10);
const PERSONAL = parseInt(__ENV.PERSONAL || '10', 10);
const META_P95_MS = 2000; // EQ-01: p95 ≤ 2 s
const OPERACIONES = ['busqueda_publica', 'seguimiento', 'busqueda_archivo', 'bandeja'];
const datos = new SharedArray('casos', () => [JSON.parse(open(`./${__ENV.DATOS || 'datos-50k.json'}`))]);

const etiqueta = (nivel) => `n${String(nivel).padStart(4, '0')}`;
const etapas = [];
const ventanas = []; // [start s, end s, tag]
let t = 0;
for (const nivel of NIVELES) {
    const ciudadanos = Math.max(1, nivel - PERSONAL);
    etapas.push({ duration: `${SUBIDA}s`, target: ciudadanos }, { duration: `${MANTENER}s`, target: ciudadanos });
    ventanas.push([t + SUBIDA, t + SUBIDA + MANTENER, etiqueta(nivel)]);
    t += SUBIDA + MANTENER;
}
etapas.push({ duration: '20s', target: 0 });
const TOTAL = t + 20;

const umbrales = { http_req_failed: [{ threshold: 'rate<0.01', abortOnFail: false }] };
for (const [, , e] of ventanas) {
    umbrales[`http_req_duration{etapa:${e}}`] = [`p(95)<${META_P95_MS}`];
    umbrales[`http_req_failed{etapa:${e}}`] = ['rate<0.01'];
    umbrales[`http_reqs{etapa:${e}}`] = ['count>=0'];
    for (const op of OPERACIONES) umbrales[`http_req_duration{etapa:${e},operacion:${op}}`] = [`p(95)<${META_P95_MS}`];
}

export const options = {
    scenarios: {
        ciudadano: { executor: 'ramping-vus', exec: 'ciudadano', startVUs: 0, stages: etapas, gracefulRampDown: '10s' },
        personal: { executor: 'constant-vus', exec: 'personal', vus: PERSONAL, duration: `${TOTAL}s` },
    },
    thresholds: umbrales,
    summaryTrendStats: ['avg', 'med', 'p(90)', 'p(95)', 'max'],
    // Staff keep their login session for the whole test; each citizen iteration is a new person (cookies cleared below).
    noCookiesReset: true,
};

const etapaActual = () => {
    const s = exec.instance.currentTestRunDuration / 1000;
    const v = ventanas.find(([a, b]) => s >= a && s < b);
    return v ? v[2] : 'transicion';
};
const azar = (lista) => lista[Math.floor(Math.random() * lista.length)];
const pausa = (min, max) => sleep(min + Math.random() * (max - min));
const token = (html) => ((html || '').match(/name="_token" value="([^"]+)"/) || [])[1];
const op = (nombre, extra = {}) => Object.assign({ headers: CABECERAS, timeout: '30s', tags: { operacion: nombre, etapa: etapaActual() } }, extra);
http.setResponseCallback(http.expectedStatuses({ min: 200, max: 399 }));

export function ciudadano() {
    http.cookieJar().clear(BASE);
    const caso = azar(datos[0].busquedas);
    const inicio = http.get(`${BASE}/tramite`, op('portada'));
    if (!check(inicio, { 'portada 200': (r) => r.status === 200 })) { pausa(3, 8); return; }
    pausa(3, 8); // reads the terms and fills in the search form
    const busqueda = http.post(`${BASE}/tramite/buscar`, {
        _token: token(inicio.body), terminos: '1', tipo: caso.tipo, titular: caso.titular, fecha: caso.fecha,
    }, op('busqueda_publica', { redirects: 0 }));
    check(busqueda, { 'búsqueda encuentra el acta': (r) => r.status === 302 && (r.headers.Location || '').includes('/tramite/datos') });
    pausa(3, 8);

    const pagina = http.get(`${BASE}/tramite/seguimiento`, op('seguimiento'));
    if (!check(pagina, { 'seguimiento 200': (r) => r.status === 200 })) { pausa(3, 8); return; }
    pausa(3, 8);
    const persona = azar(datos[0].seguimiento);
    const recuperar = http.post(`${BASE}/tramite/seguimiento`, { _token: token(pagina.body), dni: persona.dni, email: persona.email },
        op('seguimiento', { redirects: 0 }));
    check(recuperar, { 'seguimiento pide código': (r) => r.status === 302 && (r.headers.Location || '').includes('/tramite/codigo') });
    pausa(3, 8);
}

export function personal() {
    if (exec.vu.iterationInScenario === 0) {
        const login = http.get(`${BASE}/login`, op('login'));
        const r = http.post(`${BASE}/login`, { _token: token(login.body), username: 'asistente', password: 'Asistente123!' }, op('login'));
        check(r, { 'login correcto': (res) => res.status === 200 && res.url.includes('/dashboard') });
    }
    const caso = azar(datos[0].busquedas);
    const porNombre = http.get(`${BASE}/actas?nombre=${encodeURIComponent(caso.nombre)}`, op('busqueda_archivo'));
    check(porNombre, { 'archivo por nombre 200': (r) => r.status === 200 });
    pausa(2, 5);
    const porNumero = http.get(`${BASE}/actas?numero_acta=${encodeURIComponent(caso.numero)}`, op('busqueda_archivo'));
    check(porNumero, { 'archivo encuentra el número': (r) => r.status === 200 && r.body.includes(caso.numero) });
    pausa(2, 5);
    const bandeja = http.get(`${BASE}/atencion-ciudadana`, op('bandeja'));
    check(bandeja, { 'bandeja 200': (r) => r.status === 200 && r.body.includes('Solicitudes ciudadanas') });
    pausa(2, 5);
    const porDni = http.get(`${BASE}/atencion-ciudadana?dni=${azar(datos[0].seguimiento).dni}`, op('bandeja'));
    check(porDni, { 'bandeja por DNI 200': (r) => r.status === 200 });
    pausa(2, 5);
}

// Compact per-level table on screen and a JSON file for the report.
export function handleSummary(data) {
    const m = data.metrics;
    const v = (nombre, campo) => (m[nombre] && m[nombre].values ? m[nombre].values[campo] : undefined);
    const filas = ventanas.map(([, , e]) => {
        const fila = { nivel: parseInt(e.slice(1), 10), peticiones: v(`http_reqs{etapa:${e}}`, 'count') || 0,
            por_segundo: Math.round(((v(`http_reqs{etapa:${e}}`, 'count') || 0) / MANTENER) * 10) / 10,
            p95_ms: Math.round(v(`http_req_duration{etapa:${e}}`, 'p(95)') || 0),
            mediana_ms: Math.round(v(`http_req_duration{etapa:${e}}`, 'med') || 0),
            fallidas: v(`http_req_failed{etapa:${e}}`, 'rate') || 0 };
        for (const o of OPERACIONES) fila[`p95_${o}`] = Math.round(v(`http_req_duration{etapa:${e},operacion:${o}}`, 'p(95)') || 0);
        fila.cumple = fila.p95_ms <= META_P95_MS && fila.fallidas < 0.01;
        return fila;
    });
    const resumen = { meta_p95_ms: META_P95_MS, subida_s: SUBIDA, meseta_s: MANTENER, personal: PERSONAL, niveles: filas,
        total: { peticiones: v('http_reqs', 'count'), fallidas: v('http_req_failed', 'rate'), verificaciones_ok: v('checks', 'rate'),
            vus_max: v('vus_max', 'max') } };
    const pct = (x) => `${(100 * x).toFixed(2)} %`;
    let texto = `\nNivel | Pet./s | p95 total | Búsqueda | Seguim. | Archivo | Bandeja | Fallidas | Meta p95 ≤ ${META_P95_MS} ms\n`;
    for (const f of filas) {
        texto += `${String(f.nivel).padStart(5)} | ${String(f.por_segundo).padStart(6)} | ${String(f.p95_ms).padStart(6)} ms | ${String(f.p95_busqueda_publica).padStart(8)} | ${String(f.p95_seguimiento).padStart(7)} | ${String(f.p95_busqueda_archivo).padStart(7)} | ${String(f.p95_bandeja).padStart(7)} | ${pct(f.fallidas).padStart(8)} | ${f.cumple ? 'cumple' : 'NO cumple'}\n`;
    }
    const verificaciones = [];
    const recorrer = (g) => { (g.checks || []).forEach((c) => verificaciones.push({ nombre: c.name, ok: c.passes, fallos: c.fails })); (g.groups || []).forEach(recorrer); };
    if (data.root_group) recorrer(data.root_group);
    resumen.verificaciones = verificaciones;
    for (const c of verificaciones) texto += `  ${c.fallos ? '✗' : '✓'} ${c.nombre}: ${c.ok} correctas, ${c.fallos} fallidas\n`;
    texto += `Total: ${resumen.total.peticiones} peticiones · fallidas ${pct(resumen.total.fallidas || 0)} · verificaciones OK ${pct(resumen.total.verificaciones_ok || 0)} · VUs máx ${resumen.total.vus_max}\n`;
    return { stdout: texto, [__ENV.SALIDA || 'eq-progresiva-resumen.json']: JSON.stringify(resumen, null, 2) };
}
