# Memory Gateway Changelog

All notable changes to the TruAi Memory Gateway are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-03-12

### Added

- **Request logging and trace_id**
  - `TracingMiddleware` sets `request.state.trace_id` from `X-Request-ID` or generates UUID
  - Structured JSON logs per request: `trace_id`, `identity`, `path`, `method`, `status_code`, `zone`, `roma_verified`, `fail_open_allowed`, `degraded`, `queued`, `latency_ms`
  - Response header `X-Trace-ID` for correlation

- **Phase 1 — Platform hardening**
  - Embedding probe at startup: verifies `EMBEDDING_DIMS` matches Ollama model (fail-fast on mismatch)
  - Qdrant collections and payload indexes created on startup (`ensure_all_collections_and_indexes`)
  - Config summary logged at startup (no tokens)

- **Phase 2 — Observability**
  - Queue worker counters: `jobs_processed`, `jobs_failed`
  - Structured JSON logs on worker failures
  - `/health` returns `jobs_processed`, `jobs_failed`
  - Operator runbook (`docs/MEMORY_GATEWAY_RUNBOOK.md`)

- **Phase 3 — Per-token collection permissions**
  - `permissions.py`: `token_to_identity()`, `can_read()`, `can_write()`, `check_read()`, `check_write()`
  - TruAi: read/write all collections
  - Phantom: read `phantom_episodes`, `shared_knowledge`; write `phantom_episodes` only
  - Gemini: read `gemini_episodes`, `shared_knowledge`; write `gemini_episodes` only
  - `identity` in query/upsert responses and request logs

- **Shared knowledge governance**
  - Only TruAi can write to `shared_knowledge` (enforced by `check_write`)

### Changed

- `/health` response includes `trace_id`, `identity` in memory responses

### Security

- Per-token collection access control
- `shared_knowledge` write-restricted to TruAi

---

## Integration roadmap (Phases 4–8)

- **Phase 4**: Phantom.ai integration — Phantom app uses `PHANTOM_TOKENS` to query/upsert `phantom_episodes`; retry on 503
- **Phase 5**: WireGuard + Gemini.ai — Gemini app over WG; strict fail-closed for external zone
- **Phase 6**: Shared knowledge promotion — TruAi promotes from episodes to `shared_knowledge`; Phantom/Gemini read-only
- **Phase 7**: Load and safety benchmarks — locust/httpx scripts; document in runbook
- **Phase 8**: Versioning, tags, release automation
