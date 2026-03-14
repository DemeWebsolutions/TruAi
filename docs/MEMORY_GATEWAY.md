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

## ROMA policy (v1 Option A)

- **Localhost** (127.0.0.1, ::1): fail-open — degraded query returns empty results; upsert queues when Qdrant down
- **External**: fail-closed — returns 503 when degraded (no queue, no empty results)

## Failure-mode test (fail-open, localhost only)

When Qdrant is down, query from localhost should return 200 with `degraded: true` and empty results:

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

## Endpoints

| Path | Method | Auth |
|------|--------|------|
| `/health` | GET | Bearer |
| `/version` | GET | Bearer |
| `/memory/query` | POST | Bearer |
| `/memory/upsert` | POST | Bearer |
