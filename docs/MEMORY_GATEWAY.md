# TruAi Memory Gateway v1

## Quick start

```bash
cd /Users/mydemellc./Desktop/TruAi

# Build and start
docker compose up -d --build

# Pull embedding model (required once)
docker exec -it truai-ollama ollama pull nomic-embed-text
```

## Verification commands

```bash
# Set your token
export TRUAI_TOKEN=your-token-here

# Health check
curl -H "Authorization: Bearer $TRUAI_TOKEN" http://127.0.0.1:8010/health

# Version
curl -H "Authorization: Bearer $TRUAI_TOKEN" http://127.0.0.1:8010/version

# Compose config validation
docker compose config
```

## ROMA policy (Option B)

- **zone=local** + ROMA trust_state=VERIFIED: fail-open — degraded query returns empty results; upsert queues when Qdrant down
- **zone=wg** (WireGuard): always fail-closed — 503 when degraded
- **zone=public**: always fail-closed — 503 when degraded

CIDRs: `LOCAL_CIDRS` (default: loopback + RFC1918), `WG_CIDRS` (default: 10.100.0.0/24). ROMA URL: `ROMA_URL` (default: `http://host.docker.internal:8001/TruAi/api/v1/security/roma` when in Docker).

## Failure-mode test (fail-open, zone=local + ROMA VERIFIED)

When Qdrant is down and request is from LOCAL_CIDRS with ROMA VERIFIED, query should return 200 with `degraded: true` and empty results:

```bash
docker stop truai-qdrant

curl -H "Authorization: Bearer $TRUAI_TOKEN" -H "Content-Type: application/json" \
  http://127.0.0.1:8010/memory/query \
  -d '{"collection":"truai_episodes","text":"hello","top_k":5,"filters":{"project_id":"demo"}}'

# Expect: 200 + degraded:true + results:[]

docker start truai-qdrant
```

## Environment

- `TRUAI_TOKENS`, `PHANTOM_TOKENS`, `GEMINI_TOKENS` — comma-separated Bearer tokens (required)
- `QDRANT_URL`, `OLLAMA_URL` — set by docker-compose for containers
- `MAX_TEXT_CHARS`, `MAX_PAYLOAD_CHARS` — optional limits (default 20000)
- `ROMA_URL` — TruAi ROMA endpoint (default: host.docker.internal:8001 in Docker)
- `LOCAL_CIDRS`, `WG_CIDRS` — comma-separated CIDRs for zone classification

## Endpoints

| Path | Method | Auth |
|------|--------|------|
| `/health` | GET | Bearer |
| `/version` | GET | Bearer |
| `/memory/query` | POST | Bearer |
| `/memory/upsert` | POST | Bearer |
