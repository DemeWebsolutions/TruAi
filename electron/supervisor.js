/**
 * Trinity — Process Supervisor (Phase 3)
 * Spawns Qdrant, Ollama, Gateway, PHP. Lock/pidfile, restart with backoff, log capture.
 * See docs/TRINITY_MACOS_APPS_PLAN.md
 */
'use strict';

const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const http = require('http');
const net = require('net');

const { PATHS, DEFAULT_PORTS, getUrls, EMBEDDING_MODEL } = require('./runtimeContract');

// ── Ensure directories exist ───────────────────────────────────────────────────
function ensureDirs() {
  const dirs = [
    PATHS.config,
    PATHS.run,
    PATHS.data,
    PATHS.qdrantData,
    PATHS.gatewayData,
    PATHS.phpData,
    PATHS.logs,
    PATHS.bin,
    PATHS.runtime,
  ];
  for (const d of dirs) {
    try { fs.mkdirSync(d, { recursive: true }); } catch (e) { /* ignore */ }
  }
}

// ── Port availability ──────────────────────────────────────────────────────────
function isPortInUse(port) {
  return new Promise(resolve => {
    const server = net.createServer();
    server.once('error', () => resolve(true));
    server.once('listening', () => { server.close(); resolve(false); });
    server.listen(port, '127.0.0.1');
  });
}

async function checkPorts(ports = DEFAULT_PORTS) {
  const results = {};
  for (const [name, port] of Object.entries(ports)) {
    results[name] = await isPortInUse(port);
  }
  return results;
}

// ── Lock / pidfile (prevent duplicate stack) ────────────────────────────────────
function isLockedAndAlive() {
  try {
    if (!fs.existsSync(PATHS.supervisorLock)) return false;
    const lockPid = parseInt(fs.readFileSync(PATHS.supervisorLock, 'utf8'), 10);
    if (isNaN(lockPid)) return false;
    try { process.kill(lockPid, 0); return true; } catch { return false; }
  } catch { return false; }
}

function acquireLock() {
  ensureDirs();
  fs.writeFileSync(PATHS.supervisorLock, String(process.pid), 'utf8');
}

function releaseLock() {
  try { fs.unlinkSync(PATHS.supervisorLock); } catch {}
  for (const p of [PATHS.qdrantPid, PATHS.gatewayPid, PATHS.phpPid]) {
    try { fs.unlinkSync(p); } catch {}
  }
}

function writePid(name, pid) {
  try { fs.writeFileSync(PATHS[`${name}Pid`] || path.join(PATHS.run, `${name}.pid`), String(pid), 'utf8'); } catch {}
}

// ── Spawn with log capture ────────────────────────────────────────────────────
function spawnWithLog(name, cmd, args, opts = {}, logPath) {
  const logStream = fs.createWriteStream(logPath || path.join(PATHS.logs, `${name}.log`), { flags: 'a' });
  const { env = {}, cwd, ...rest } = opts;
  const child = spawn(cmd, args, {
    env: { ...process.env, ...env },
    stdio: ['ignore', 'pipe', 'pipe'],
    cwd,
    ...rest,
  });
  child.stdout?.on('data', d => { logStream.write(d); });
  child.stderr?.on('data', d => { logStream.write(d); });
  child.on('exit', () => logStream.end());
  return child;
}

// ── Health checks ──────────────────────────────────────────────────────────────
function httpGet(url, opts = {}) {
  return new Promise((resolve, reject) => {
    const req = http.get(url, { timeout: opts.timeout || 2000 }, res => resolve(res));
    req.on('error', reject);
    req.setTimeout(opts.timeout || 2000, () => { req.destroy(); reject(new Error('timeout')); });
  });
}

async function checkQdrant(port = DEFAULT_PORTS.QDRANT_PORT) {
  try {
    const res = await httpGet(`http://127.0.0.1:${port}/`);
    return res.statusCode === 200;
  } catch { return false; }
}

async function checkOllama(port = DEFAULT_PORTS.OLLAMA_PORT) {
  try {
    const res = await httpGet(`http://127.0.0.1:${port}/api/tags`);
    return res.statusCode === 200;
  } catch { return false; }
}

async function checkGateway(port, token) {
  try {
    const url = `http://127.0.0.1:${port}/health`;
    const res = await new Promise((resolve, reject) => {
      const req = http.get(url, { headers: { Authorization: `Bearer ${token}` }, timeout: 2000 }, resolve);
      req.on('error', reject);
      req.setTimeout(2000, () => { req.destroy(); reject(new Error('timeout')); });
    });
    return res.statusCode === 200;
  } catch { return false; }
}

async function checkOllamaModel(model = EMBEDDING_MODEL) {
  try {
    const res = await httpGet(`http://127.0.0.1:${DEFAULT_PORTS.OLLAMA_PORT}/api/tags`);
    if (res.statusCode !== 200) return false;
    let body = '';
    for await (const chunk of res) body += chunk;
    const data = JSON.parse(body);
    const models = data.models || [];
    return models.some(m => (m.name || '').startsWith(model));
  } catch { return false; }
}

// ── Find binaries ─────────────────────────────────────────────────────────────
function getAppResourcesPath() {
  if (process.env.ELECTRON_RUN_AS_NODE) return __dirname;
  try {
    const { app } = require('electron');
    return app.isPackaged
      ? path.join(process.resourcesPath, 'app.asar.unpacked')
      : __dirname;
  } catch {
    return __dirname;
  }
}

function findQdrantBinary() {
  if (fs.existsSync(PATHS.qdrantBinary)) return PATHS.qdrantBinary;
  const resources = getAppResourcesPath();
  const arch = process.arch === 'arm64' ? 'arm64' : 'amd64';
  const candidates = [
    path.join(resources, 'bin', `qdrant-darwin-${arch}`),
    path.join(resources, 'bin', 'qdrant'),
  ];
  for (const c of candidates) { if (fs.existsSync(c)) return c; }
  return null;
}

function findGatewayBinary() {
  const resources = getAppResourcesPath();
  const candidates = [
    path.join(resources, 'gateway'),
    path.join(path.dirname(resources), 'gateway'),
    path.join(PATHS.bin, 'gateway'),
  ];
  for (const c of candidates) { if (fs.existsSync(c)) return c; }
  return null;
}

function findPhpBinary() {
  const resources = getAppResourcesPath();
  const candidates = [
    path.join(resources, 'php', 'bin', 'php'),
    path.join(resources, 'php', 'php'),
  ];
  for (const c of candidates) { if (fs.existsSync(c)) return c; }
  return null;
}

function findOllamaApp() {
  return fs.existsSync('/Applications/Ollama.app');
}

// ── Supervisor state ──────────────────────────────────────────────────────────
let processes = { qdrant: null, gateway: null, php: null };
let ports = { ...DEFAULT_PORTS };

async function startQdrant() {
  const bin = findQdrantBinary();
  if (!bin) return { ok: false, error: 'Qdrant binary not found. Run build or download.' };
  ensureDirs();
  const child = spawnWithLog('qdrant', bin, [
    '--storage-path', PATHS.qdrantData,
    '--host', '127.0.0.1',
    '--port', String(ports.QDRANT_PORT),
  ], {}, PATHS.qdrantLog);
  writePid('qdrant', child.pid);
  processes.qdrant = child;
  return { ok: true };
}

async function startGateway(truaiToken) {
  const bin = findGatewayBinary();
  const root = getTruAiRoot();
  const gatewayDir = root ? path.join(root, 'memory-gateway') : null;

  const env = {
    QDRANT_URL: `http://127.0.0.1:${ports.QDRANT_PORT}`,
    OLLAMA_URL: `http://127.0.0.1:${ports.OLLAMA_PORT}`,
    GATEWAY_PORT: String(ports.GATEWAY_PORT),
    GATEWAY_HOST: '127.0.0.1',
    GATEWAY_DATA_DIR: PATHS.gatewayData,
    ROMA_URL: `http://127.0.0.1:${ports.TRUAI_PORT}/TruAi/api/v1/security/roma`,
    TRUAI_TOKENS: truaiToken || 'change-me-truai-1',
    PHANTOM_TOKENS: process.env.PHANTOM_TOKENS || 'change-me-phantom-1',
    GEMINI_TOKENS: process.env.GEMINI_TOKENS || 'change-me-gemini-1',
  };

  if (bin) {
    const child = spawnWithLog('gateway', bin, [], { env }, PATHS.gatewayLog);
    writePid('gateway', child.pid);
    processes.gateway = child;
    return { ok: true };
  }

  if (gatewayDir && fs.existsSync(path.join(gatewayDir, 'run_gateway.py'))) {
    const child = spawnWithLog('gateway', 'python3', ['run_gateway.py'], { env: { ...process.env, ...env }, cwd: gatewayDir }, PATHS.gatewayLog);
    writePid('gateway', child.pid);
    processes.gateway = child;
    return { ok: true };
  }

  return { ok: false, error: 'Gateway binary or run_gateway.py not found' };
}

function getTruAiRoot() {
  const marker = 'router.php';
  const has = (dir) => dir && fs.existsSync(path.join(dir, marker));
  try {
    const { app } = require('electron');
    if (!app.isPackaged) {
      const dev = path.join(__dirname, '..');
      if (has(dev)) return dev;
    }
  } catch {}
  const home = process.env.HOME || require('os').homedir();
  for (const sub of ['TruAi', 'Desktop/TruAi', 'Documents/TruAi']) {
    const d = path.join(home, sub);
    if (has(d)) return d;
  }
  return null;
}

function startPhp(truaiRoot) {
  if (!truaiRoot || !fs.existsSync(path.join(truaiRoot, 'router.php'))) return { ok: false, error: 'TruAi project not found' };
  const phpBin = findPhpBinary() || 'php';
  const child = spawnWithLog('php', phpBin, ['-S', `127.0.0.1:${ports.TRUAI_PORT}`, 'router.php'], { cwd: truaiRoot }, PATHS.phpLog);
  child.cwd = truaiRoot;
  writePid('php', child.pid);
  processes.php = child;
  return { ok: true };
}

function stopAll() {
  for (const [name, proc] of Object.entries(processes)) {
    if (proc && !proc.killed) {
      try { proc.kill('SIGTERM'); } catch {}
      processes[name] = null;
    }
  }
  releaseLock();
}

// ── Public API ──────────────────────────────────────────────────────────────────
module.exports = {
  ensureDirs,
  checkPorts,
  isLockedAndAlive,
  acquireLock,
  releaseLock,
  checkQdrant,
  checkOllama,
  checkGateway,
  checkOllamaModel,
  findQdrantBinary,
  findGatewayBinary,
  findPhpBinary,
  findOllamaApp,
  startQdrant,
  startGateway,
  startPhp,
  stopAll,
  get processes() { return processes; },
  setPorts(p) { ports = { ...DEFAULT_PORTS, ...p }; },
  getPorts() { return { ...ports }; },
  PATHS,
  getUrls: () => getUrls(ports),
};
