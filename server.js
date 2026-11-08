/**
 * Crezcamos - Consulta de Créditos + Pago PSE
 *
 * Estrategia OPTIMIZADA:
 *   - 1-2 tokens por consulta (en vez de 5-15)
 *   - Pool pequeño (1 token listo)
 *   - Sesión pre-creada con reuso inteligente
 *   - Resultado en ~3-8 segundos
 *   - Ahorro ~80% en saldo CapSolver
 */

const http = require('http');
const https = require('https');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

const PORT = process.env.PORT || 3000;
const CAPSOLVER_API_KEY = process.env.CAPSOLVER_API_KEY || 'CAP-10B9BFCD4F61A52B1DE0DAAF7A3AD27DB4633AE03ACABDD94E83822C5EA0310F';
const API_BASE = 'https://analizamos.crezcamos.com:8440/pagos/api/public';
const CREZCAMOS_URL = 'https://transacciones.crezcamos.com/';
const RECAPTCHA_SITEKEY = '6LcN3IYfAAAAAHN_LIcvvuewvEcrUwoGGr7hVuf8';

// ─── Proxy residencial Geonode (Colombia) ───────────────────
const GEONODE_USER = process.env.GEONODE_USER || 'geonode_t6USfXsYi6-country-co';
const GEONODE_PASS = process.env.GEONODE_PASS || 'fbc9c047-7c88-416e-a80c-8d2c83a5c3fa';
const GEONODE_HOST = '92.204.171.83';
const GEONODE_PORT = 9000;

function obtenerProxy() {
  return {
    host: GEONODE_HOST,
    port: GEONODE_PORT,
    user: GEONODE_USER,
    pass: GEONODE_PASS,
  };
}

// ─── Tipos de documento (UUIDs del backend) ──────────────────
const TIPO_DOC_IDS = {
  CC:  'F18AFF61-F19C-42B7-8F24-3024C44BF911',
  CE:  'A13891A5-E949-4A9B-8F67-5B0E4C3CFC8E',
  NIT: '60C721B6-D691-48B0-B712-E54B4E7D03B2',
  PEP: '3E4F52A0-7C13-4E12-B8D5-C45F2B1A9D7F',
  PPT: 'D82A1F3B-9E56-4C01-A2E7-8F3B6D0C5E91',
};

// ─── RappiPay PSE Config ────────────────────────────────────
const RAPPIPAY_API = 'https://services.prod-k8s.rappipay.com/integration/pse/api';
const RAPPIPAY_LINK_CODE = 's5sqzI';
const FIREBASE_API_KEY = 'AIzaSyBXJ_5dpN6uTDr41m72dcS_ZKJtGc_dVHw';
const FIREBASE_DB_URL = 'https://rpp-platform-mobile-app-prod-default-rtdb.firebaseio.com';

// ─── API POST helper (Crezcamos) ────────────────────────────
function apiPost(ruta, body, headers = {}) {
  return new Promise((resolve, reject) => {
    const url = new URL(`${API_BASE}${ruta}`);
    const data = JSON.stringify(body);
    const opts = {
      hostname: url.hostname,
      port: url.port || 443,
      path: url.pathname,
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json, text/plain, */*',
        'Content-Length': Buffer.byteLength(data),
        ...headers,
      },
      timeout: 8000,
    };
    const req = https.request(opts, (res) => {
      let body = '';
      res.on('data', (c) => (body += c));
      res.on('end', () => {
        try { resolve({ status: res.statusCode, data: JSON.parse(body) }); }
        catch { resolve({ status: res.statusCode, data: body }); }
      });
    });
    req.on('timeout', () => { req.destroy(); reject(new Error('Timeout API')); });
    req.on('error', reject);
    req.write(data);
    req.end();
  });
}

// ─── HTTPS GET helper ──────────────────────────────────────
function httpsGet(urlStr, extraHeaders = {}) {
  return new Promise((resolve, reject) => {
    const url = new URL(urlStr);
    const opts = {
      hostname: url.hostname,
      port: url.port || 443,
      path: url.pathname + url.search,
      method: 'GET',
      headers: { 'Accept': 'application/json', ...extraHeaders },
    };
    const req = https.request(opts, (res) => {
      let body = '';
      res.on('data', (c) => (body += c));
      res.on('end', () => {
        try { resolve(JSON.parse(body)); }
        catch { resolve(body); }
      });
    });
    req.on('error', reject);
    req.end();
  });
}

// ─── HTTPS POST helper ────────────────────────────────────
function httpsPost(urlStr, data, extraHeaders = {}) {
  return new Promise((resolve, reject) => {
    const url = new URL(urlStr);
    const payload = JSON.stringify(data);
    const opts = {
      hostname: url.hostname,
      port: url.port || 443,
      path: url.pathname + url.search,
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Content-Length': Buffer.byteLength(payload),
        ...extraHeaders,
      },
    };
    const req = https.request(opts, (res) => {
      let body = '';
      res.on('data', (c) => (body += c));
      res.on('end', () => {
        try { resolve(JSON.parse(body)); }
        catch { resolve(body); }
      });
    });
    req.on('error', reject);
    req.write(payload);
    req.end();
  });
}

// ═══════════════════════════════════════════════════════════════
// CAPSOLVER OPTIMIZADO
// Pool mínimo: solo 1 token listo. Máximo 2 tokens por consulta.
// ═══════════════════════════════════════════════════════════════

const TOKEN_MAX_AGE = 110000;
const POOL_SIZE = 1;          // ← Solo 1 token pre-cacheado (ahorro de saldo)
let tokenPool = [];
let resolviendoCount = 0;

// Estadísticas para elegir el mejor taskType
let stats = {
  'ReCaptchaV3EnterpriseTask': { ok: 0, fail: 0, lowScore: 0 },
  'ReCaptchaV3M1Task': { ok: 0, fail: 0, lowScore: 0 },
};

function mejorTaskType() {
  const enterprise = stats['ReCaptchaV3EnterpriseTask'];
  const m1 = stats['ReCaptchaV3M1Task'];
  const eRate = enterprise.ok / (enterprise.ok + enterprise.fail + enterprise.lowScore + 1);
  const mRate = m1.ok / (m1.ok + m1.fail + m1.lowScore + 1);
  // Priorizar el que tenga mejor tasa de éxito
  if (eRate >= mRate) {
    return ['ReCaptchaV3EnterpriseTask', 'ReCaptchaV3M1Task'];
  }
  return ['ReCaptchaV3M1Task', 'ReCaptchaV3EnterpriseTask'];
}

async function resolverTokenCapSolver() {
  if (!CAPSOLVER_API_KEY) {
    throw new Error('CAPSOLVER_API_KEY no configurado.');
  }

  const t0 = Date.now();
  const proxy = obtenerProxy();
  const taskTypes = mejorTaskType();

  let lastErr;

  for (const taskType of taskTypes) {
    try {
      const task = {
        type: taskType,
        websiteURL: CREZCAMOS_URL,
        websiteKey: RECAPTCHA_SITEKEY,
        pageAction: 'sesion',
        minScore: 0.7,  // ← Bajado de 0.9 a 0.7 para menos LOW_SCORE
      };

      if (proxy) {
        task.proxy = `http:${proxy.host}:${proxy.port}:${proxy.user}:${proxy.pass}`;
      }

      const createResp = await httpsPost('https://api.capsolver.com/createTask', {
        clientKey: CAPSOLVER_API_KEY,
        task,
      });

      if (createResp.errorId && createResp.errorId !== 0) {
        const errMsg = createResp.errorDescription || createResp.errorCode;
        lastErr = new Error(`CapSolver ${taskType}: ${errMsg}`);
        stats[taskType].fail++;

        // Si es error de saldo, no seguir intentando
        if (errMsg?.includes('balance') || errMsg?.includes('insufficient')) {
          throw new Error(`CapSolver: Saldo insuficiente. Recarga en dashboard.capsolver.com`);
        }

        console.log(`[captcha] ${taskType} falló: ${errMsg}`);
        continue;
      }

      // Token directo
      if (createResp.solution?.gRecaptchaResponse) {
        stats[taskType].ok++;
        console.log(`[captcha] Token directo (${taskType}) ✓ ${Date.now() - t0}ms`);
        return createResp.solution.gRecaptchaResponse;
      }

      const taskId = createResp.taskId;
      if (!taskId) { lastErr = new Error('Sin taskId'); continue; }

      // Poll más agresivo: intervalos cortos, máximo 8 intentos (≈10s)
      for (let i = 0; i < 8; i++) {
        await new Promise(r => setTimeout(r, i < 2 ? 800 : 1200));
        const result = await httpsPost('https://api.capsolver.com/getTaskResult', {
          clientKey: CAPSOLVER_API_KEY,
          taskId,
        });

        if (result.status === 'ready' && result.solution?.gRecaptchaResponse) {
          stats[taskType].ok++;
          console.log(`[captcha] Token (${taskType}) ✓ ${Date.now() - t0}ms`);
          return result.solution.gRecaptchaResponse;
        }
        if (result.errorId && result.errorId !== 0) {
          const errMsg = result.errorDescription || result.errorCode;
          if (errMsg?.includes('LOW_SCORE')) {
            stats[taskType].lowScore++;
          } else {
            stats[taskType].fail++;
          }
          lastErr = new Error(errMsg);
          break;
        }
      }
    } catch (e) {
      lastErr = e;
      // Propagar errores de saldo inmediatamente
      if (e.message?.includes('Saldo insuficiente')) throw e;
    }
  }

  throw lastErr || new Error('CapSolver: no se pudo obtener token');
}

function llenarPool() {
  tokenPool = tokenPool.filter(t => (Date.now() - t.timestamp) < TOKEN_MAX_AGE);
  const faltantes = POOL_SIZE - tokenPool.length - resolviendoCount;
  for (let i = 0; i < faltantes; i++) {
    resolviendoCount++;
    resolverTokenCapSolver()
      .then(token => {
        tokenPool.push({ token, timestamp: Date.now() });
        resolviendoCount--;
        console.log(`[pool] Token listo ✓ (pool: ${tokenPool.length})`);
      })
      .catch(err => {
        resolviendoCount--;
        console.log(`[pool] Error: ${err.message}`);
        // Si es error de saldo, no reintentar
        if (!err.message?.includes('Saldo insuficiente')) {
          setTimeout(llenarPool, 5000);
        }
      });
  }
}

function tomarTokenDelPool() {
  tokenPool = tokenPool.filter(t => (Date.now() - t.timestamp) < TOKEN_MAX_AGE);
  if (tokenPool.length > 0) {
    const entry = tokenPool.shift();
    console.log(`[pool] Token consumido ✓ (edad: ${((Date.now() - entry.timestamp) / 1000).toFixed(0)}s)`);
    llenarPool(); // Reponer
    return entry.token;
  }
  return null;
}

// Refrescar pool cada 90s (no cada 60s para no desperdiciar)
setInterval(() => {
  tokenPool = tokenPool.filter(t => (Date.now() - t.timestamp) < TOKEN_MAX_AGE);
  if (tokenPool.length < POOL_SIZE && resolviendoCount === 0) {
    llenarPool();
  }
}, 90000);

// ═══════════════════════════════════════════════════════════════
// POOL DE SESIONES PRE-CREADAS
// ═══════════════════════════════════════════════════════════════
const SESSION_MAX_AGE = 105_000;
const SESSION_POOL_SIZE = 1;      // ← Solo 1 sesión pre-creada (ahorro)
let sessionPool = [];
let sessionResolving = 0;

function limpiarSessionPool() {
  sessionPool = sessionPool.filter(s => (Date.now() - s.timestamp) < SESSION_MAX_AGE);
}

function tomarSesionDelPool() {
  limpiarSessionPool();
  if (sessionPool.length > 0) {
    const entry = sessionPool.shift();
    console.log(`[session-pool] Sesión consumida ✓ (edad: ${((Date.now() - entry.timestamp) / 1000).toFixed(0)}s)`);
    llenarSessionPool();
    return { sessionId: entry.sessionId, aesKey: entry.aesKey };
  }
  return null;
}

function llenarSessionPool() {
  limpiarSessionPool();
  const faltantes = SESSION_POOL_SIZE - sessionPool.length - sessionResolving;
  for (let i = 0; i < faltantes; i++) {
    sessionResolving++;
    crearSesion()
      .then(sesion => {
        sessionPool.push({ ...sesion, timestamp: Date.now() });
        sessionResolving--;
        console.log(`[session-pool] Sesión lista ✓ (pool: ${sessionPool.length})`);
      })
      .catch(err => {
        sessionResolving--;
        console.log(`[session-pool] Error: ${err.message}`);
        if (!err.message?.includes('Saldo insuficiente')) {
          setTimeout(llenarSessionPool, 8000);
        }
      });
  }
}

setInterval(() => {
  limpiarSessionPool();
  if (sessionPool.length < SESSION_POOL_SIZE && sessionResolving === 0) {
    llenarSessionPool();
  }
}, 20000);

// ─── AES-256-GCM cifrar/descifrar ───────────────────────────
function cifrarAES(aesKey, datos) {
  const textoPlano = JSON.stringify(datos);
  const nonce = crypto.randomBytes(12);
  const cipher = crypto.createCipheriv('aes-256-gcm', aesKey, nonce);
  const encrypted = Buffer.concat([cipher.update(textoPlano, 'utf8'), cipher.final()]);
  const tag = cipher.getAuthTag();
  return Buffer.concat([nonce, encrypted, tag]).toString('base64');
}

function descifrarAES(aesKey, payloadBase64) {
  const buf = Buffer.from(payloadBase64, 'base64');
  const nonce = buf.subarray(0, 12);
  const tag = buf.subarray(buf.length - 16);
  const ciphertext = buf.subarray(12, buf.length - 16);
  const decipher = crypto.createDecipheriv('aes-256-gcm', aesKey, nonce);
  decipher.setAuthTag(tag);
  const decrypted = Buffer.concat([decipher.update(ciphertext), decipher.final()]);
  return JSON.parse(decrypted.toString('utf8'));
}

// ─── Crear sesión — SECUENCIAL INTELIGENTE ──────────────────
// Máximo 2 tokens por intento (en vez de 5×3=15)

async function intentarSesionConToken(captchaToken) {
  const initRes = await apiPost('/sesion/iniciar', { captchaToken });

  if (initRes.status !== 200 || !initRes.data?.sessionId) {
    throw new Error(`Fallo sesión (${initRes.status}): ${JSON.stringify(initRes.data)}`);
  }

  const { sessionId, clavePublicaRSA } = initRes.data;

  const aesKey = crypto.randomBytes(32);
  const rsaPubDer = Buffer.from(clavePublicaRSA, 'base64');
  const rsaPubPem =
    '-----BEGIN PUBLIC KEY-----\n' +
    rsaPubDer.toString('base64').match(/.{1,64}/g).join('\n') +
    '\n-----END PUBLIC KEY-----';

  const claveAESCifrada = crypto.publicEncrypt(
    { key: rsaPubPem, padding: crypto.constants.RSA_PKCS1_OAEP_PADDING, oaepHash: 'sha256' },
    aesKey
  );

  const exchRes = await apiPost(
    '/sesion/intercambio-clave',
    { claveAESCifrada: claveAESCifrada.toString('base64') },
    { 'X-Sesion-Pagos': sessionId }
  );

  if (exchRes.status !== 200) {
    throw new Error(`Fallo intercambio (${exchRes.status}): ${JSON.stringify(exchRes.data)}`);
  }

  return { sessionId, aesKey };
}

async function crearSesion() {
  const MAX_INTENTOS = 3;  // ← Máximo 3 tokens totales (en vez de 15)

  for (let intento = 1; intento <= MAX_INTENTOS; intento++) {
    const t0 = Date.now();

    // Intentar tomar del pool primero
    let token = tomarTokenDelPool();

    if (!token) {
      console.log(`[sesion] Intento ${intento}/${MAX_INTENTOS}: resolviendo token...`);
      try {
        token = await resolverTokenCapSolver();
      } catch (err) {
        // Error de saldo → parar inmediatamente
        if (err.message?.includes('Saldo insuficiente')) throw err;
        console.log(`[sesion] Intento ${intento}: no se obtuvo token: ${err.message}`);
        continue;
      }
    } else {
      console.log(`[sesion] Intento ${intento}/${MAX_INTENTOS}: usando token del pool`);
    }

    try {
      const sesion = await intentarSesionConToken(token);
      const elapsed = Date.now() - t0;
      console.log(`[sesion] ✓ Sesión lista en intento ${intento} (${elapsed}ms)`);
      llenarPool();
      return sesion;
    } catch (err) {
      const elapsed = Date.now() - t0;
      const isLowScore = err.message?.includes('LOW_SCORE') || err.message?.includes('score');
      console.log(`[sesion] Intento ${intento} falló (${elapsed}ms): ${isLowScore ? 'LOW_SCORE' : err.message}`);

      // Si quedan intentos, vaciar pool viejo
      if (intento < MAX_INTENTOS) {
        tokenPool = [];
      }
    }
  }

  throw new Error('No se pudo crear sesión después de 3 intentos. Posible problema con captcha o la API de Crezcamos.');
}

// ─── Consultar créditos ──────────────────────────────────────
async function consultarCreditos(tipoDoc, numDoc) {
  const t0 = Date.now();

  const cached = tomarSesionDelPool();
  let sessionId, aesKey;
  if (cached) {
    sessionId = cached.sessionId;
    aesKey = cached.aesKey;
    console.log(`[consulta] Usando sesión pre-creada ✓`);
  } else {
    console.log(`[consulta] Creando sesión nueva...`);
    const sesion = await crearSesion();
    sessionId = sesion.sessionId;
    aesKey = sesion.aesKey;
  }

  const hdrs = { 'X-Sesion-Pagos': sessionId };
  const idTipoDoc = TIPO_DOC_IDS[tipoDoc.toUpperCase()] || TIPO_DOC_IDS.CC;

  const [valRes, conRes] = await Promise.all([
    apiPost('/pse/validar', {
      payload: cifrarAES(aesKey, {
        numeroDocumento: numDoc,
        idTipoDocumento: idTipoDoc,
      })
    }, hdrs),
    apiPost('/consultar-creditos', {
      payload: cifrarAES(aesKey, {
        idDocument: numDoc,
        creditNumber: '',
        app: '',
        flagConsolidated: '',
        codigo: '',
      })
    }, hdrs),
  ]);

  console.log(`[api] /pse/validar → ${valRes.status}, /consultar-creditos → ${conRes.status}`);

  let creditosData = null;
  if (conRes.status === 200 && conRes.data?.payload) {
    try { creditosData = descifrarAES(aesKey, conRes.data.payload); } catch (e) {
      console.log('[api] Error descifrando créditos:', e.message);
    }
  }

  let creditos = [];
  if (creditosData) {
    if (Array.isArray(creditosData)) {
      creditos = creditosData;
    } else if (typeof creditosData === 'object') {
      for (const key of Object.keys(creditosData)) {
        if (Array.isArray(creditosData[key]) && creditosData[key].length > 0) {
          creditos = creditosData[key];
          break;
        }
      }
      if (creditos.length === 0 && Object.keys(creditosData).length > 0) {
        creditos = [creditosData];
      }
    }
  }

  const elapsed = Date.now() - t0;
  console.log(`[api] ${creditos.length} créditos en ${elapsed}ms`);

  return {
    ok: creditos.length > 0,
    error: creditos.length === 0 ? 'No se encontraron créditos para este documento' : undefined,
    tiempoMs: elapsed,
    consulta: { tipoDocumento: tipoDoc, numeroDocumento: numDoc },
    creditos,
  };
}

// ─── HTTP Server ─────────────────────────────────────────────
function sendJSON(res, status, obj) {
  res.writeHead(status, {
    'Content-Type': 'application/json; charset=utf-8',
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type',
  });
  res.end(JSON.stringify(obj, null, 2));
}

const MIME_TYPES = {
  '.html': 'text/html; charset=utf-8', '.css': 'text/css',
  '.js': 'application/javascript', '.png': 'image/png',
  '.jpg': 'image/jpeg', '.svg': 'image/svg+xml', '.ico': 'image/x-icon',
};

const server = http.createServer(async (req, res) => {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
  if (req.method === 'OPTIONS') { res.writeHead(204); return res.end(); }

  const url = new URL(req.url, `http://localhost:${PORT}`);

  // ─── REDIRIGIR PHP al servidor PHP built-in (puerto 8080) ───
  if (req.url.startsWith('/api/') || req.url.endsWith('.php') || req.url.includes('.php?')) {
    const options = {
      hostname: '127.0.0.1',
      port: 8080,
      path: req.url,
      method: req.method,
      headers: req.headers,
    };
    const proxyReq = http.request(options, (proxyRes) => {
      res.writeHead(proxyRes.statusCode, proxyRes.headers);
      proxyRes.pipe(res);
    });
    proxyReq.on('error', (err) => {
      console.error('[php-proxy] Error conectando a PHP:', err.message);
      sendJSON(res, 502, { error: 'PHP no disponible: ' + err.message });
    });
    req.pipe(proxyReq);
    return;
  }

  // ─── Servir index.html ───
  if (url.pathname === '/' && req.method === 'GET') {
    try {
      const html = fs.readFileSync(path.join(__dirname, 'index.html'), 'utf8');
      res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
      return res.end(html);
    } catch { return sendJSON(res, 500, { error: 'index.html no encontrado' }); }
  }

  // ─── Servir archivos estáticos ───
  if (req.method === 'GET' && !url.pathname.startsWith('/consultar') && !url.pathname.startsWith('/pse/')) {
    const safePath = path.normalize(url.pathname).replace(/^(\.\.(\/|\\|$))+/, '');
    const filePath = path.join(__dirname, safePath);
    const ext = path.extname(filePath).toLowerCase();
    const mime = MIME_TYPES[ext];
    if (mime && fs.existsSync(filePath)) {
      res.writeHead(200, { 'Content-Type': mime });
      return res.end(fs.readFileSync(filePath));
    }
  }

  // ─── Consultar créditos ───
  if (url.pathname === '/consultar') {
    let tipo, doc;
    if (req.method === 'GET') {
      tipo = url.searchParams.get('tipo') || 'CC';
      doc = url.searchParams.get('doc');
    } else if (req.method === 'POST') {
      const raw = await new Promise((r) => { let d = ''; req.on('data', (c) => (d += c)); req.on('end', () => r(d)); });
      try { ({ tipo = 'CC', doc } = JSON.parse(raw)); } catch { return sendJSON(res, 400, { error: 'JSON inválido' }); }
    } else {
      return sendJSON(res, 405, { error: 'Método no permitido' });
    }

    if (!doc) return sendJSON(res, 400, { error: 'Parámetro "doc" requerido' });

    console.log(`\n${'═'.repeat(50)}`);
    console.log(`Consulta: tipo=${tipo} doc=${doc}`);
    console.log('═'.repeat(50));

    try {
      const result = await consultarCreditos(tipo, doc);
      return sendJSON(res, 200, result);
    } catch (err) {
      console.error('[error]', err.message);
      return sendJSON(res, 500, { ok: false, error: err.message });
    }
  }

  // ═══════════════════════════════════════════════════════════
  // RAPPIPAY PSE
  // ═══════════════════════════════════════════════════════════

  if (url.pathname === '/pse/bancos' && req.method === 'GET') {
    try {
      const linkData = await httpsGet(`${RAPPIPAY_API}/transfer-link/code/${RAPPIPAY_LINK_CODE}`, { 'traceability-id': crypto.randomUUID() });
      const bancos = linkData.banks || [];
      console.log(`[pse] ${bancos.length} bancos`);
      return sendJSON(res, 200, { ok: true, bancos });
    } catch (err) {
      console.error('[pse] Error bancos:', err.message);
      return sendJSON(res, 500, { ok: false, error: 'Error obteniendo bancos: ' + err.message });
    }
  }

  if (url.pathname === '/pse/pagar' && req.method === 'POST') {
    const raw = await new Promise((r) => { let d = ''; req.on('data', (c) => (d += c)); req.on('end', () => r(d)); });
    let body;
    try { body = JSON.parse(raw); } catch { return sendJSON(res, 400, { ok: false, error: 'JSON inválido' }); }

    const { bank_code, bank_name, document_type, document_number, amount } = body;
    if (!bank_code || !document_number || !amount) {
      return sendJSON(res, 400, { ok: false, error: 'Faltan campos requeridos' });
    }

    try {
      console.log(`[pse] Pago: banco=${bank_code}, doc=${document_number}, monto=${amount}`);

      const linkData = await httpsGet(`${RAPPIPAY_API}/transfer-link/code/${RAPPIPAY_LINK_CODE}`, { 'traceability-id': crypto.randomUUID() });
      const transfer = linkData.transfer || linkData;
      if (transfer.status !== 'CREATED') {
        return sendJSON(res, 400, { ok: false, error: 'Link no disponible (status: ' + transfer.status + ')' });
      }

      const traceability_id = transfer.traceability_id;
      const minAmount = transfer.min_amount || 10000;
      const maxAmount = transfer.max_amount || 20000000;
      if (amount < minAmount || amount > maxAmount) {
        return sendJSON(res, 400, { ok: false, error: `Monto debe estar entre $${minAmount.toLocaleString()} y $${maxAmount.toLocaleString()}` });
      }

      const clientIp = req.headers['x-forwarded-for']?.split(',')[0]?.trim() || req.socket.remoteAddress || '127.0.0.1';

      const paymentPayload = {
        traceability_id,
        bank_code,
        bank_name: bank_name || '',
        document_type: document_type || 'CC',
        document_number: String(document_number),
        amount: Number(amount),
        user_agreement_check: true,
        client_ip: clientIp,
      };
      const headerTraceId = crypto.randomUUID();
      console.log('[pse] POST /payment-url payload:', JSON.stringify(paymentPayload));

      const paymentResp = await httpsPost(`${RAPPIPAY_API}/payment-url`, paymentPayload, {
        'traceability-id': headerTraceId,
      });
      console.log('[pse] payment-url response:', JSON.stringify(paymentResp).substring(0, 500));

      const firebaseToken = paymentResp.token || paymentResp.custom_token || paymentResp.firebase_token;
      const directUrl = paymentResp.bank_url || paymentResp.url || paymentResp.redirect_url || paymentResp.payment_url;

      if (directUrl) {
        console.log('[pse] URL directa del banco recibida ✓');
        return sendJSON(res, 200, { ok: true, bank_url: directUrl, traceability_id });
      }

      if (!firebaseToken) {
        return sendJSON(res, 500, { ok: false, error: 'No se recibió token de pago', debug: paymentResp });
      }

      const txTraceId = paymentResp.traceability_id || traceability_id;

      const firebaseAuth = await httpsPost(
        `https://identitytoolkit.googleapis.com/v1/accounts:signInWithCustomToken?key=${FIREBASE_API_KEY}`,
        { token: firebaseToken, returnSecureToken: true }
      );

      if (!firebaseAuth.idToken) {
        return sendJSON(res, 500, { ok: false, error: 'Error autenticación Firebase' });
      }

      console.log('[pse] Firebase auth OK');
      const firebasePath = `cash_flow_cash_in_pse_link_transaction/${txTraceId}.json?auth=${firebaseAuth.idToken}`;
      let bankUrl = null;

      for (let i = 0; i < 15; i++) {
        await new Promise(r => setTimeout(r, i === 0 ? 3000 : 2000));
        try {
          const fbData = await httpsGet(`${FIREBASE_DB_URL}/${firebasePath}`);
          console.log(`[pse] Firebase poll ${i+1}/15:`, JSON.stringify(fbData)?.substring(0, 400));
          const link = fbData?.data?.params?.link || fbData?.params?.link || fbData?.link;
          if (link) { bankUrl = link; break; }
        } catch (fbErr) {
          console.error(`[pse] Firebase poll ${i+1} error:`, fbErr.message);
        }
      }

      if (!bankUrl) {
        return sendJSON(res, 500, { ok: false, error: 'No se pudo obtener la URL del banco.' });
      }

      return sendJSON(res, 200, { ok: true, bank_url: bankUrl, traceability_id });

    } catch (err) {
      console.error('[pse] Error:', err.message);
      return sendJSON(res, 500, { ok: false, error: 'Error procesando pago: ' + err.message });
    }
  }

  // ─── Servir pago.html ───
  if (url.pathname === '/pago.html' && req.method === 'GET') {
    try {
      const html = fs.readFileSync(path.join(__dirname, 'pago.html'), 'utf8');
      res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
      return res.end(html);
    } catch { return sendJSON(res, 500, { error: 'pago.html no encontrado' }); }
  }

  // ─── Endpoint de estadísticas ───
  if (url.pathname === '/stats' && req.method === 'GET') {
    return sendJSON(res, 200, {
      captcha: stats,
      pool: { tokens: tokenPool.length, resolviendo: resolviendoCount },
      sesiones: { listas: sessionPool.length, creando: sessionResolving },
    });
  }

  sendJSON(res, 404, { error: 'Ruta no encontrada' });
});

// ─── Arranque ───────────────────────────────────────────────
server.listen(PORT, () => {
  console.log(`
╔═══════════════════════════════════════════════════════════╗
║  Crezcamos - Consulta + Pago PSE (OPTIMIZADO)            ║
║  http://localhost:${PORT}                                    ║
║                                                           ║
║  ★ Pool mínimo: 1 token + 1 sesión (ahorro ~80%)        ║
║  ★ Máximo 3 tokens por consulta (antes: hasta 15)        ║
║  ★ minScore 0.7 (menos LOW_SCORE)                        ║
║  ★ Stats: http://localhost:${PORT}/stats                     ║
╚═══════════════════════════════════════════════════════════╝
  `);

  if (!CAPSOLVER_API_KEY) {
    console.log('⚠️  CAPSOLVER_API_KEY no configurado!');
  } else {
    console.log(`[proxy] Geonode residencial CO ✓`);
    console.log(`[pool] Pre-cacheando 1 token...`);
    llenarPool();
    console.log(`[session-pool] Pre-creando sesión en 10s...`);
    setTimeout(llenarSessionPool, 10000);
  }
});
