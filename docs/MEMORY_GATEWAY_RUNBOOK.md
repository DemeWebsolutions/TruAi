# TruAi Memory Gateway — Operator Runbook

## Quick verification (< 15 min)

```bash
cd /Users/mydemellc./Desktop/TruAi
export TRUAI_TOKEN=your-token-from-env

# 1. Start stack
docker compose up -d --build

# 2. Pull embedding model (once)
docker exec -it truai-ollama ollama pull nomic-embed-text

# 3. Health
curl -s -H "Authorization: Bearer $TRUAI_TOKEN" http://127.0.0.1:8010/health | jq

# 4. Upsert + query
curl -s -X POST -H "Authorization: Bearer $TRUAI_TOKEN" -H "Content-Type: application/json" \
  http://127.0.0.1:8010/memory/upsert \
  -d '{"collection":"truai_episodes","text":"test","payload":{"project_id":"demo"}}' | jq

curl -s -X POST -H "Authorization: Bearer $TRUAI_TOKEN" -H "Content-Type: application/json" \
  http://127.0.0.1:8010/memory/query \
  -d '{"collection":"truai_episodes","text":"test","top_k":5}' | jq

# 5. Failure-mode test (ROMA must be reachable for fail-open)
docker stop truai-qdrant
curl -s -X POST -H "Authorization: Bearer $TRUAI_TOKEN" -H "Content-Type: application/json" \
  http://127.0.0.1:8010/memory/query \
  -d '{"collection":"truai_episodes","text":"test","top_k":5}' | jq
# Expect: 200 + degraded:true (if local+ROMA) or 503 (if ROMA down)
docker start truai-qdrant
```

---

## Token rotation

1. Update `.env` with new comma-separated tokens:
   ```
   TRUAI_TOKENS=new-token-1,new-token-2
   PHANTOM_TOKENS=new-phantom-token
   GEMINI_TOKENS=new-gemini-token
   ```
2. Restart gateway: `docker compose up -d`
3. Old tokens stop working immediately.

---

## Changing LOCAL_CIDRS / WG_CIDRS

1. Edit `.env`:
   ```
   LOCAL_CIDRS=127.0.0.1/32,::1/128,192.168.0.0/16,10.0.0.0/8,172.16.0.0/12
   WG_CIDRS=10.100.0.0/24
   ```
2. Restart: `docker compose up -d`

---

## Inspecting Qdrant

- Dashboard: http://127.0.0.1:6333/dashboard
- Collections, points, and payloads are visible there.

---

## Verifying ROMA from inside container

```bash
docker exec -it truai-gateway python -c "
import httpx
r = httpx.get('http://host.docker.internal:8001/TruAi/api/v1/security/roma', timeout=3)
print(r.status_code, r.json())
"
```

---

## Reset volumes (dev only)

```bash
docker compose down -v
# Removes qdrant data, ollama models, gateway queue. Use with caution.
```

---

## Logs

```bash
docker compose logs -f truai-gateway
```

Structured JSON lines: `trace_id`, `identity`, `path`, `method`, `status_code`, `zone`, `roma_verified`, `fail_open_allowed`, `degraded`, `queued`, `latency_ms`.

---

## Phase 4–8 integration notes

### Phase 4: Phantom.ai integration

- Phantom app: use `PHANTOM_TOKENS` Bearer token for `/memory/query` and `/memory/upsert`
- Collections: read `phantom_episodes`, `shared_knowledge`; write `phantom_episodes` only
- On 503: retry with backoff; queue locally if gateway unavailable

### Phase 5: WireGuard + Gemini.ai

- Gemini app: connect over WireGuard (`WG_CIDRS`); use `GEMINI_TOKENS`
- Zone `wg` and `public`: strict fail-closed (503 when Qdrant/Ollama down)
- See `deploy/WIREGUARD_HQ_CONTABO.md` for WG setup

### Phase 6: Shared knowledge promotion

- Only TruAi can write to `shared_knowledge` (enforced)
- Phantom/Gemini read `shared_knowledge` for cross-system context
- Promotion workflow: TruAi extracts from episodes → upserts to `shared_knowledge`

### Phase 7: Load and safety benchmarks

```bash
# Run load test script
cd memory-gateway
export TRUAI_TOKEN=your-token
python scripts/load_test.py

# Or inline (requires httpx)
pip install httpx
LOAD_TEST_N=100 python scripts/load_test.py
```

### Phase 8: Versioning and release

- Version in `app/main.py` (`VERSION`)
- `memory-gateway/CHANGELOG.md` for gateway-specific changes
- Tag releases: `memory-gateway-v1.0.0`
