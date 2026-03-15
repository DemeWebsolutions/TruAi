# Trinity macOS Apps — Proposed Plan & Detailed Solutions

**Version:** 3.3  
**Date:** 2026-03-12  
**Last reviewed:** 2026-03-15  
**Status:** Implemented — No Docker; single-purpose embedded runtime

---

## Reality check (macOS constraints & mitigations)

This section captures risks, constraints, and design choices so the plan stays reliable and updatable.

| Area | Reality | Mitigation |
|------|---------|------------|
| **Ollama** | Bundling Ollama causes install friction, notarization risk, model bloat, CPU/RAM surprises. It is the hardest component to embed. | **Mode A default:** Require Ollama.app. TruAi manages config + health only. Mode B (bundle) optional, with careful notarization. |
| **PyInstaller** | Can be fragile on macOS upgrades; large bundle; dynamic libs; code signing needs care. | Pinned deps; strict runtime contract (config/logs/ports); no system Python. Long-term: consider Go/Rust rewrite. |
| **System PHP** | macOS may not ship PHP in 2026. Relying on it is risky. | **Bundle PHP.** Minimal runtime + extensions inside TruAi.app. |
| **Qdrant** | Bundled binary can fail (Gatekeeper, quarantine, arch mismatch). | Bundle correct arch; add download-on-first-run fallback to `.../bin/qdrant` with checksum verification. |
| **Process management** | Starting processes in order is not enough; they can die; diagnosis is hard. | **Supervisor:** restart with backoff; health gating; logs to files; UI tails logs. |
| **Port binding** | Exposing services beyond localhost increases attack surface. | Bind everything to `127.0.0.1` by default. WireGuard exposure only when explicitly enabled. |
| **Updates** | "Version aligned" can become manual and brittle. | **2-layer model:** app update + runtime component update. Bundled for major; downloaded for patches. |
| **Parity** | Docker (CI/dev) and embedded (macOS) can drift. | **Parity contract:** same env vars, endpoints, `/health` fields, permissions. Integration tests target both. |
| **Signing/notarization** | All binaries must be signed; DMGs notarized; PyInstaller output needs entitlements care. | Developer ID cert; sign all binaries; staple ticket; verify PyInstaller output. |

---

## Executive summary

Ship three macOS apps—**TruAi**, **Phantom.ai**, and **Gemini.ai**—as dockable, installable apps. TruAi is the **local platform** (owns and runs all services). Phantom and Gemini are **pure clients** (no servers). All use Electron, one-click run, full-window UI, updatable and scalable.

---

## 1. Architectural contract: platform vs clients

| Role | App | Responsibility |
|------|-----|----------------|
| **Platform** | TruAi.app | Owns and runs: Qdrant, Ollama, Memory Gateway, PHP backend/ROMA. Spawns, supervises, restarts. |
| **Client** | Phantom.ai.app | Never runs servers. Only calls `http://127.0.0.1:8010` with Phantom token. |
| **Client** | Gemini.ai.app | Only calls TruAi via WireGuard (or remote endpoint). Strict fail-closed. |

**Benefit:** Phantom and Gemini stay lightweight; only TruAi ships native binaries.

---

## 2. Runtime contract (constants)

### Port binding (localhost only by default)

| Service | Bind address | Port |
|---------|--------------|------|
| Qdrant | `127.0.0.1` | 6333 |
| Ollama | `127.0.0.1` | 11434 |
| Memory Gateway | `127.0.0.1` | 8010 |
| PHP backend | `127.0.0.1` | 8001 |

**WireGuard exposure:** Only if explicitly enabled via setting; requires user confirmation.

### Access modes (versatility)

| Mode | Use case |
|------|----------|
| **TruAi.app (Electron)** | One-click start; Status, Logs, Config, Go-live checklist |
| **Localhost HTTP** | Browser, scripts, automation: `http://127.0.0.1:8001` (TruAi), `http://127.0.0.1:8010` (gateway) |

Both modes available when services run. Phantom/Gemini can use native app or any HTTP client.

**Security note:** Localhost HTTP is supported, but **never expose these ports publicly**. Endpoints require Bearer tokens. Use WireGuard or explicit proxying with firewall rules for remote access.

### Config format (single source of truth)

- **Canonical:** `config/config.json` — structured config (ports, URLs, CIDRs). Tokens **never** stored here.
- **Generated:** `.env` — generated from `config.json` for Gateway/PHP subprocesses. Tokens injected at runtime from Keychain.
- **Why:** `.env` can drift and leak secrets; Keychain + JSON makes upgrades safer.

### Port configuration (collision strategy)

| Env var | Default | Purpose |
|---------|---------|---------|
| `TRUAI_PORT` | 8001 | PHP backend |
| `GATEWAY_PORT` | 8010 | Memory Gateway |
| `QDRANT_PORT` | 6333 | Qdrant |
| `OLLAMA_PORT` | 11434 | Ollama |

**On launch:** Supervisor checks port availability. If occupied:
- **Fail with clear UI error** + "Open troubleshooting" link, **or**
- **"Auto-select ports"** (advanced mode) — find next available ports and persist choice.

### Environment variables (parity with Docker Compose)

- `QDRANT_URL`, `OLLAMA_URL`, `EMBEDDING_MODEL`, `EMBEDDING_DIMS`
- `TRUAI_TOKENS`, `PHANTOM_TOKENS`, `GEMINI_TOKENS` (from Keychain at runtime)
- `ROMA_URL`, `LOCAL_CIDRS`, `WG_CIDRS`
- `GATEWAY_DATA_DIR`

Same names and semantics as `docker-compose` for parity.

### Filesystem layout (Application Support = source of truth)

```
~/Library/Application Support/TruAi/
├── config/
│   ├── config.json           # canonical (no tokens)
│   ├── .env                  # generated from config.json
│   └── schema_version.txt
├── run/
│   ├── supervisor.lock       # prevents duplicate stack
│   ├── qdrant.pid
│   ├── gateway.pid
│   ├── php.pid
│   └── ollama.pid            # only if Mode B
├── data/
│   ├── qdrant/
│   │   └── schema_version.txt
│   ├── gateway/
│   │   ├── queue.db
│   │   └── schema_version.txt
│   ├── php/
│   │   ├── sqlite.db         # if applicable
│   │   └── schema_version.txt
│   └── ollama/               # only if Mode B
│       └── models/
├── models/                   # optional: managed model cache
├── logs/
│   ├── qdrant.log
│   ├── gateway.log
│   ├── php.log
│   └── supervisor.log
├── bin/                      # fallback downloaded binaries
│   └── qdrant
└── runtime/
    └── manifest.json         # pinned versions, sha256, download URLs
```

**Schema versioning:** Each `schema_version.txt` holds a version string. Migrations run on startup.

**Process identity:** On launch, if `supervisor.lock` exists and processes are alive, attach UI to running stack (do not start a second copy). Eliminates "two Qdrants bound to 6333" failure.

---

## 3. Embedded components (refined)

### Qdrant

- **Bundle:** Binary for `darwin-arm64` (Apple Silicon) and `darwin-amd64` (Intel) at build time.
- **Fallback:** If bundled binary fails (Gatekeeper, quarantine, OS mismatch), download pinned version from `runtime/manifest.json` into `.../bin/qdrant`; verify sha256 before run.
- **Manifest:** `runtime/manifest.json` (shipped with app): component name, version, sha256, download URL. Supervisor only downloads components listed in manifest (supply-chain safety).
- **Args:** `--storage-path .../data/qdrant`; bind `127.0.0.1:6333`.

### Ollama — two modes

| Mode | Default | Behavior |
|------|---------|----------|
| **A) Bring-your-own Ollama.app** | ✅ Yes | TruAi detects `/Applications/Ollama.app` or running `ollama serve`. Manages config + health only. No installation. |
| **B) Embedded Ollama CLI** | Optional | Bundle `ollama` binary; run `ollama serve`; store models under `.../data/ollama/`. Requires notarization/entitlements planning. |

**Recommendation:** Mode A for v1. Keeps app smaller; Ollama.app is native (not Docker).

**Model readiness (Mode A):** Health check alone is not enough. Supervisor checks:
1. Ollama reachable
2. `nomic-embed-text` available (or triggers guided "Install model" step)

If missing: UI shows "Model missing" with one-click instruction or one-click `ollama pull` if permitted. Avoids "gateway runs but all queries fail due to missing model".

### Memory Gateway

- **Packaging:** PyInstaller `onefile` or `onedir` with pinned dependencies.
- **Contract:** No reliance on system `python`. Config dir, logs dir, ports fixed.
- **Logging:** Structured JSON logs to `.../logs/gateway.log`.
- **Future:** Consider Go/Rust rewrite for smaller binary and easier signing.

### PHP backend

- **Do not rely on system PHP.** macOS may not ship it in 2026.
- **Bundle:** Minimal PHP runtime + required extensions inside TruAi.app.
- **Run:** Internal process bound to `127.0.0.1:8001` only.

---

## 4. Process supervisor (inside TruAi.app)

### Behavior

1. **Start order:** Qdrant → Ollama → Gateway → PHP
2. **Stop order:** Reverse (graceful shutdown)
3. **Watch:** If any process dies, restart with exponential backoff
4. **Health gating:** Show "Ready" only when all health checks pass
5. **Logs:** Capture stdout/stderr to `.../logs/<service>.log`; UI "Logs" tab tails these files

### Health checks

- Qdrant: `GET http://127.0.0.1:6333/`
- Ollama: `GET http://127.0.0.1:11434/api/tags` (or equivalent)
- Gateway: `GET http://127.0.0.1:8010/health` (with TRUAI token)
- PHP: `GET http://127.0.0.1:8001/`

---

## 5. Update strategy (2-layer)

| Layer | What | How |
|-------|------|-----|
| **App update** | TruAi.app, Phantom.app, Gemini.app | DMG replace; optional Electron auto-updater |
| **Runtime update** | Qdrant, Gateway, PHP | Bundled in major releases; downloaded for patch updates (smaller) |

**Recommendation:** Integrate "Check for updates" (or signed download + replace). Version strings aligned across apps and gateway.

---

## 6. Parity contract (Docker vs embedded)

Docker Compose remains for CI, dev, and Linux/server. macOS uses embedded runtime.

**Parity requirements:**

- Same env vars
- Same endpoints and response formats
- Same `/health` output fields
- Same permissions enforcement

**Integration tests:** Single suite that can target:
- `docker-compose` environment
- macOS embedded environment (running locally)

---

## 7. Keychain (first-class requirement)

Tokens stored/retrieved via macOS Keychain. **Never** in `.env` or `config.json`.

| App | Keychain service name |
|-----|------------------------|
| TruAi | `com.trinity.truai.token.truai` |
| Phantom | `com.trinity.truai.token.phantom` |
| Gemini | `com.trinity.truai.token.gemini` |

**UI:** "Reset token" and "Reveal token" only with explicit user confirmation. Required for production-grade macOS apps.

---

## 8. Signing & notarization

- Developer ID Application certificate
- Sign all binaries (Qdrant, Gateway, PHP, Electron app)
- Notarize each DMG
- Staple notarization ticket
- PyInstaller: ensure dynamic libs and entitlements are correct for macOS

---

## 9. Decisions summary

| # | Decision | Choice |
|---|----------|--------|
| 1 | TruAi runtime | **Single-purpose embedded** (no Docker) |
| 2 | UI framework | **Electron** (hold true to current UI/UX) |
| 3 | Ollama default | **Mode A** (require Ollama.app) |
| 4 | Repo strategy | **Separate repos** per app; Trinity as meta-repo |
| 5 | Installers | **Three separate DMGs** (optional Suite later) |
| 6 | UI style | **Full window** for all three; one-click run |
| 7 | Memory client | **Copy + local npm-like** (file/workspace, no registry) |
| 8 | WireGuard (Gemini) | **User installs WireGuard app** + imports config |

---

## 10. Phased execution (refined)

| Phase | Deliverable | Acceptance |
|-------|-------------|------------|
| 1 | **Runtime contract + layout + ports** | Doc + `electron/runtimeContract.js` + `config.template.json` + `runtime/manifest.json` |
| 2 | **Memory Gateway PyInstaller** | Signed runnable; `/health` parity with Docker |
| 3 | **Qdrant + Supervisor** | Bundle Qdrant; start/stop/restart; logs tab; download fallback |
| 4 | **Ollama Mode A** | Detect Ollama.app; health check; model readiness |
| 5 | **Bundle PHP** | PHP runtime in app; no system dependency |
| 6 | **TruAi.app full UI** | Tabs: Status, Logs, Config, Go-live checklist |
| 7 | **`@truai/memory-client`** | Shared client for Phantom/Gemini |
| 8 | **Phantom.ai.app** | Client; Keychain; retry on 503 |
| 9 | **Gemini.ai.app** | WG checks; strict fail-closed |
| 10 | **Suite DMG + notarization pipeline** | Optional |

---

## 11. App-by-app summary

### TruAi.app (platform)

- Supervisor: Qdrant → Ollama → Gateway → PHP
- Logs to files; UI tails them
- Config in Application Support
- Bundle: Qdrant, Gateway (PyInstaller), PHP
- Ollama: Mode A (detect); Mode B (optional)

### Phantom.ai.app (client)

- Gateway URL + Phantom token (Keychain)
- Memory client with retry on 503
- Episode writer to `phantom_episodes`
- No servers

### Gemini.ai.app (client)

- WireGuard check; gateway over WG
- Memory client strict fail-closed
- Gemini token in Keychain
- No servers

---

## 12. Implementation summary (v3.3)

| Path | Purpose |
|------|---------|
| `electron/runtimeContract.js` | Paths, ports, Keychain names |
| `electron/supervisor.js` | Process spawn, lock, health checks |
| `electron/dashboard.html` | Tabs: Status, TruAi, Logs, Config, Go-live |
| `electron/config.template.json` | Default config |
| `electron/runtime/manifest.json` | Qdrant download manifest |
| `packages/memory-client/` | `@truai/memory-client` package |
| `apps/phantom/` | Phantom.ai scaffold |
| `apps/gemini/` | Gemini.ai scaffold |
| `memory-gateway/run_gateway.py` | PyInstaller entry point |
| `memory-gateway/gateway.spec` | PyInstaller spec |
| `scripts/build_macos.sh` | Gateway build |
| `scripts/build-suite.sh` | Build all three apps |
| `scripts/download-php-macos.sh` | PHP bundle (manual) |

---

## 13. Document history


| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-03-12 | Initial draft (Docker-based) |
| 2.0 | 2026-03-12 | Eliminate Docker; embedded runtime |
| 3.0 | 2026-03-12 | Refinements: platform vs clients; Ollama Mode A/B; Supervisor; bundled PHP; Qdrant fallback; parity contract; phased execution; Electron + Mode A confirmed |
| 3.1 | 2026-03-12 | Reality check section (macOS constraints, risks, mitigations); access modes (app vs localhost HTTP) |
| 3.2 | 2026-03-15 | Config.json canonical; Keychain service names; run/ lock + pidfiles; port collision strategy; localhost HTTP security note; runtime manifest; Ollama model readiness; Last reviewed date |
| 3.3 | 2026-03-15 | Implementation complete: supervisor, dashboard, memory-client, Phantom/Gemini scaffolds, build scripts |
| 3.4 | 2026-03-15 | Project completion: spawn cwd fix, UX preservation, completion checklist |
