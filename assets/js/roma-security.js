/**
 * TruAi · ROMA Security Manager
 *
 * Shared module for ROMA status checks, UBSAS biometric authentication,
 * autofill credential retrieval, and security settings persistence.
 *
 * Used by:
 *   - dashboard.html  (Security tab)
 *   - login-portal.html  (biometric sign-in button)
 *
 * API endpoints consumed:
 *   GET  /api/v1/security/roma          → ROMA trust state
 *   POST /api/v1/auth/biometric         → UBSAS biometric login (full auth)
 *   POST /api/v1/auth/autofill          → retrieve stored credentials for form fill
 */

(function (global) {
  'use strict';

  // ── Helpers ──────────────────────────────────────────────────────────────────
  function apiBase() {
    if (global.TRUAI_CONFIG && global.TRUAI_CONFIG.API_BASE) return global.TRUAI_CONFIG.API_BASE;
    return (global.location.origin + '/TruAi/api/v1').replace(/\/TruAi\/TruAi/, '/TruAi');
  }

  function csrfToken() {
    return (global.TRUAI_CONFIG && global.TRUAI_CONFIG.CSRF_TOKEN) || '';
  }

  function secHeaders() {
    return { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() };
  }

  // ── Settings storage ─────────────────────────────────────────────────────────
  const STORAGE_KEY = 'truai_security_settings';

  function getSettings() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); } catch { return {}; }
  }

  function saveSettings(patch) {
    const current = getSettings();
    const updated = Object.assign({}, current, patch);
    localStorage.setItem(STORAGE_KEY, JSON.stringify(updated));
    return updated;
  }

  // ── ROMA Status ──────────────────────────────────────────────────────────────

  /**
   * Fetch live ROMA trust state from the backend.
   * @returns {Promise<{state:'VERIFIED'|'UNVERIFIED'|'BLOCKED', raw: object}>}
   */
  async function fetchRomaStatus() {
    try {
      const r = await fetch(apiBase() + '/security/roma', { credentials: 'include' });
      if (!r.ok) throw new Error('HTTP ' + r.status);
      const d = await r.json();
      let state = d.trust_state || (d.roma ? 'VERIFIED' : 'UNVERIFIED');
      // Normalise: if roma=true AND portal_protected AND monitor=active → VERIFIED
      if (d.roma && d.portal_protected && d.monitor === 'active') state = 'VERIFIED';
      return { state, raw: d };
    } catch (err) {
      return { state: 'UNREACHABLE', raw: { error: err.message } };
    }
  }

  /**
   * Update a DOM element with the ROMA badge style.
   * @param {HTMLElement|string} el  Element or its id
   * @param {'VERIFIED'|'UNVERIFIED'|'BLOCKED'|'UNREACHABLE'} state
   */
  function applyRomaBadge(el, state) {
    if (typeof el === 'string') el = document.getElementById(el);
    if (!el) return;
    const map = {
      VERIFIED:    { cls: 'confirmed', text: 'ROMA • Portal Protected • Monitor Active' },
      UNVERIFIED:  { cls: '',          text: 'ROMA • Unverified' },
      BLOCKED:     { cls: '',          text: 'ROMA • Blocked' },
      UNREACHABLE: { cls: '',          text: 'ROMA • Unreachable (start TruAi server)' },
    };
    const info = map[state] || map.UNVERIFIED;
    el.textContent = info.text;
    el.className = ('roma-indicator ' + info.cls).trim();
  }

  // ── UBSAS Biometric Auth ─────────────────────────────────────────────────────

  /**
   * Request full biometric login via UBSAS.
   * On success the backend creates a session and returns csrf_token.
   * @param {string} [app='truai']
   * @returns {Promise<{success:boolean, username?:string, csrf_token?:string, error?:string}>}
   */
  async function biometricLogin(app) {
    app = app || 'truai';
    try {
      const r = await fetch(apiBase() + '/auth/biometric', {
        method:      'POST',
        credentials: 'include',
        headers:     secHeaders(),
        body:        JSON.stringify({ app }),
      });
      const d = await r.json();
      if (d.success && global.TRUAI_CONFIG) {
        global.TRUAI_CONFIG.CSRF_TOKEN      = d.csrf_token  || global.TRUAI_CONFIG.CSRF_TOKEN;
        global.TRUAI_CONFIG.IS_AUTHENTICATED = true;
        global.TRUAI_CONFIG.USERNAME         = d.username   || '';
      }
      return d;
    } catch (err) {
      return { success: false, error: err.message };
    }
  }

  /**
   * Fetch stored credentials for form auto-fill (Tier 2 / Keychain).
   * Does NOT create a session — the caller must still submit the login form.
   * @param {string} [app='truai']
   * @returns {Promise<{success:boolean, credentials?:{username,password}, error?:string}>}
   */
  async function autofillCredentials(app) {
    app = app || 'truai';
    try {
      const r = await fetch(apiBase() + '/auth/autofill', {
        method:      'POST',
        credentials: 'include',
        headers:     secHeaders(),
        body:        JSON.stringify({ app }),
      });
      return await r.json();
    } catch (err) {
      return { success: false, error: err.message };
    }
  }

  // ── Login Portal Integration ──────────────────────────────────────────────────

  /**
   * Inject a "Sign in with Biometric" button into the login portal form.
   * Only rendered when biometricEnabled is true in security settings.
   *
   * @param {object} opts
   *   formId          {string}   id of the form/wrapper element  (default: 'formWrapper')
   *   onCredentials   {Function} callback(username, password) when autofill succeeds
   *   onFullLogin     {Function} callback(data) when full biometric login succeeds
   *   onError         {Function} callback(msg) on failure
   */
  function injectBiometricButton(opts) {
    opts = opts || {};
    const settings = getSettings();
    // Only inject when biometric is enabled in settings
    if (!settings.biometricEnabled) return;

    const wrapper = document.getElementById(opts.formId || 'formWrapper');
    if (!wrapper || document.getElementById('biometric-login-btn')) return;

    const btn = document.createElement('button');
    btn.id        = 'biometric-login-btn';
    btn.type      = 'button';
    btn.innerHTML =
      '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" ' +
      'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ' +
      'style="vertical-align:middle;margin-right:6px;">' +
      '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/>' +
      '<circle cx="12" cy="12" r="2"/></svg>' +
      'Sign in with Biometric';
    btn.style.cssText = [
      'display:block', 'width:calc(100% - 32px)', 'margin:10px auto 0',
      'padding:11px 16px', 'background:rgba(0,142,214,0.10)',
      'border:1px solid rgba(0,142,214,0.3)', 'border-radius:10px',
      'color:#008ed6', 'font-size:13px', 'font-weight:600',
      'cursor:pointer', 'transition:background 0.2s, border-color 0.2s',
      'text-align:center',
    ].join(';');

    btn.addEventListener('mouseover',  () => { btn.style.background = 'rgba(0,142,214,0.18)'; btn.style.borderColor = 'rgba(0,142,214,0.5)'; });
    btn.addEventListener('mouseout',   () => { btn.style.background = 'rgba(0,142,214,0.10)'; btn.style.borderColor = 'rgba(0,142,214,0.3)'; });

    btn.addEventListener('click', async () => {
      btn.disabled    = true;
      btn.textContent = 'Verifying…';

      const autoLoginEnabled = settings.autoLogin !== false; // default true if not set

      if (autoLoginEnabled) {
        // Full biometric login (creates session server-side)
        const result = await biometricLogin('truai');
        if (result.success) {
          if (typeof opts.onFullLogin === 'function') opts.onFullLogin(result);
        } else {
          // Fall back to autofill (Tier 2 / Keychain)
          const fill = await autofillCredentials('truai');
          if (fill.success && fill.credentials) {
            const u = document.getElementById('username');
            const p = document.getElementById('password');
            if (u) u.value = fill.credentials.username;
            if (p) p.value = fill.credentials.password;
            if (typeof opts.onCredentials === 'function') opts.onCredentials(fill.credentials.username, fill.credentials.password);
            else {
              // Auto-submit the form if callback not provided
              const form = document.getElementById('verifyForm');
              if (form) form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            }
          } else {
            if (typeof opts.onError === 'function') opts.onError(result.error || 'Biometric authentication failed');
          }
        }
      } else {
        // Autofill only — user still clicks the icon to submit
        const fill = await autofillCredentials('truai');
        if (fill.success && fill.credentials) {
          const u = document.getElementById('username');
          const p = document.getElementById('password');
          if (u) u.value = fill.credentials.username;
          if (p) p.value = fill.credentials.password;
          if (typeof opts.onCredentials === 'function') opts.onCredentials(fill.credentials.username, fill.credentials.password);
        } else {
          if (typeof opts.onError === 'function') opts.onError(fill.error || 'No stored credentials found');
        }
      }

      btn.disabled    = false;
      btn.innerHTML =
        '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" ' +
        'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ' +
        'style="vertical-align:middle;margin-right:6px;">' +
        '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/>' +
        '<circle cx="12" cy="12" r="2"/></svg>' +
        'Sign in with Biometric';
    });

    wrapper.appendChild(btn);
  }

  // ── Public API ────────────────────────────────────────────────────────────────
  global.RomaSecurity = {
    getSettings,
    saveSettings,
    fetchRomaStatus,
    applyRomaBadge,
    biometricLogin,
    autofillCredentials,
    injectBiometricButton,
  };

})(window);
