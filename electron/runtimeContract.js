/**
 * Trinity macOS Apps — Runtime Contract (Phase 1)
 * Single source of truth for paths, ports, Keychain, and layout.
 * See docs/TRINITY_MACOS_APPS_PLAN.md
 */
'use strict';

const path = require('path');
const os = require('os');

// ── Application Support base (macOS)
const APP_SUPPORT = process.platform === 'darwin'
  ? path.join(process.env.HOME || os.homedir(), 'Library', 'Application Support', 'TruAi')
  : path.join(process.env.APPDATA || process.env.HOME, 'TruAi');

// ── Default ports (configurable via config.json)
const DEFAULT_PORTS = {
  TRUAI_PORT: 8001,
  GATEWAY_PORT: 8010,
  QDRANT_PORT: 6333,
  OLLAMA_PORT: 11434,
};

// ── Bind address (localhost only by default)
const BIND_ADDRESS = '127.0.0.1';

// ── Filesystem layout
const PATHS = {
  base: APP_SUPPORT,
  config: path.join(APP_SUPPORT, 'config'),
  configJson: path.join(APP_SUPPORT, 'config', 'config.json'),
  env: path.join(APP_SUPPORT, 'config', '.env'),
  schemaVersion: path.join(APP_SUPPORT, 'config', 'schema_version.txt'),
  run: path.join(APP_SUPPORT, 'run'),
  supervisorLock: path.join(APP_SUPPORT, 'run', 'supervisor.lock'),
  qdrantPid: path.join(APP_SUPPORT, 'run', 'qdrant.pid'),
  gatewayPid: path.join(APP_SUPPORT, 'run', 'gateway.pid'),
  phpPid: path.join(APP_SUPPORT, 'run', 'php.pid'),
  ollamaPid: path.join(APP_SUPPORT, 'run', 'ollama.pid'),
  data: path.join(APP_SUPPORT, 'data'),
  qdrantData: path.join(APP_SUPPORT, 'data', 'qdrant'),
  gatewayData: path.join(APP_SUPPORT, 'data', 'gateway'),
  phpData: path.join(APP_SUPPORT, 'data', 'php'),
  ollamaData: path.join(APP_SUPPORT, 'data', 'ollama'),
  models: path.join(APP_SUPPORT, 'models'),
  logs: path.join(APP_SUPPORT, 'logs'),
  qdrantLog: path.join(APP_SUPPORT, 'logs', 'qdrant.log'),
  gatewayLog: path.join(APP_SUPPORT, 'logs', 'gateway.log'),
  phpLog: path.join(APP_SUPPORT, 'logs', 'php.log'),
  supervisorLog: path.join(APP_SUPPORT, 'logs', 'supervisor.log'),
  bin: path.join(APP_SUPPORT, 'bin'),
  qdrantBinary: path.join(APP_SUPPORT, 'bin', 'qdrant'),
  runtime: path.join(APP_SUPPORT, 'runtime'),
  manifest: path.join(APP_SUPPORT, 'runtime', 'manifest.json'),
};

// ── Keychain service names (tokens never in config)
const KEYCHAIN_SERVICES = {
  truai: 'com.trinity.truai.token.truai',
  phantom: 'com.trinity.truai.token.phantom',
  gemini: 'com.trinity.truai.token.gemini',
};

// ── Embedding model (Ollama Mode A readiness)
const EMBEDDING_MODEL = 'nomic-embed-text';

// ── URLs (from ports)
function getUrls(ports = DEFAULT_PORTS) {
  return {
    truai: `http://${BIND_ADDRESS}:${ports.TRUAI_PORT || DEFAULT_PORTS.TRUAI_PORT}`,
    gateway: `http://${BIND_ADDRESS}:${ports.GATEWAY_PORT || DEFAULT_PORTS.GATEWAY_PORT}`,
    qdrant: `http://${BIND_ADDRESS}:${ports.QDRANT_PORT || DEFAULT_PORTS.QDRANT_PORT}`,
    ollama: `http://${BIND_ADDRESS}:${ports.OLLAMA_PORT || DEFAULT_PORTS.OLLAMA_PORT}`,
  };
}

module.exports = {
  APP_SUPPORT,
  DEFAULT_PORTS,
  BIND_ADDRESS,
  PATHS,
  KEYCHAIN_SERVICES,
  EMBEDDING_MODEL,
  getUrls,
};
