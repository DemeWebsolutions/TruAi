/**
 * @truai/memory-client — Memory Gateway client
 * Query and upsert with configurable retry and fail-closed behavior.
 */
'use strict';

const http = require('http');
const https = require('https');

class MemoryClient {
  /**
   * @param {object} opts
   * @param {string} opts.baseUrl - e.g. http://127.0.0.1:8010
   * @param {string} opts.token - Bearer token
   * @param {boolean} [opts.strictFailClosed=false] - Phantom: false; Gemini: true
   * @param {number} [opts.retries=3] - Phantom: 3; Gemini: 0
   */
  constructor(opts = {}) {
    this.baseUrl = opts.baseUrl || 'http://127.0.0.1:8010';
    this.token = opts.token || '';
    this.strictFailClosed = opts.strictFailClosed ?? false;
    this.retries = opts.retries ?? (this.strictFailClosed ? 0 : 3);
  }

  _request(method, path, body = null) {
    return new Promise((resolve, reject) => {
      const url = new URL(path, this.baseUrl);
      const isHttps = url.protocol === 'https:';
      const lib = isHttps ? https : http;
      const opts = {
        hostname: url.hostname,
        port: url.port || (isHttps ? 443 : 80),
        path: url.pathname + url.search,
        method,
        headers: {
          'Authorization': `Bearer ${this.token}`,
          'Content-Type': 'application/json',
        },
        timeout: 10000,
      };
      if (body) opts.headers['Content-Length'] = Buffer.byteLength(body);
      const req = lib.request(opts, res => {
        let data = '';
        res.on('data', chunk => data += chunk);
        res.on('end', () => {
          try {
            const parsed = data ? JSON.parse(data) : {};
            if (res.statusCode >= 400) reject(new Error(parsed.detail || `HTTP ${res.statusCode}`));
            else resolve({ ...parsed, statusCode: res.statusCode, headers: res.headers });
          } catch (e) { reject(e); }
        });
      });
      req.on('error', reject);
      req.setTimeout(10000, () => { req.destroy(); reject(new Error('timeout')); });
      if (body) req.write(body);
      req.end();
    });
  }

  async _withRetry(fn) {
    if (this.strictFailClosed || this.retries <= 0) return fn();
    let lastErr;
    for (let i = 0; i <= this.retries; i++) {
      try {
        return await fn();
      } catch (e) {
        lastErr = e;
        if (e.message?.includes('503') || e.message?.includes('ECONNREFUSED')) {
          await new Promise(r => setTimeout(r, Math.pow(2, i) * 500));
        } else throw e;
      }
    }
    throw lastErr;
  }

  /**
   * Query memory
   * @param {object} opts - { collection, text, top_k?, filters? }
   * @returns {Promise<{results, trace_id, identity, degraded}>}
   */
  async query(opts) {
    const { collection, text, top_k = 10, filters = {} } = opts;
    const body = JSON.stringify({ collection, text, top_k, filters });
    const doRequest = () => this._request('POST', '/memory/query', body);
    const res = await this._withRetry(doRequest);
    return {
      results: res.results || [],
      trace_id: res.trace_id || res.headers?.['x-trace-id'] || '',
      identity: res.identity || '',
      degraded: res.degraded ?? false,
    };
  }

  /**
   * Upsert memory
   * @param {object} opts - { collection, text, payload? }
   * @returns {Promise<{status, queued, trace_id}>}
   */
  async upsert(opts) {
    const { collection, text, payload = {} } = opts;
    const body = JSON.stringify({ collection, text, payload });
    const doRequest = () => this._request('POST', '/memory/upsert', body);
    const res = await this._withRetry(doRequest);
    return {
      status: res.status || 'ok',
      queued: res.queued ?? false,
      trace_id: res.trace_id || res.headers?.['x-trace-id'] || '',
    };
  }
}

module.exports = { MemoryClient };
