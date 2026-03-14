"""Memory routes — query, upsert; auth, ROMA, redaction, fail-open."""
from fastapi import APIRouter, Depends, Request
from pydantic import BaseModel

from app.auth import verify_bearer
from app.embeddings_ollama import get_embedding
from app.policies import apply_roma, check_private_key_block
from app.qdrant_store import get_client, search
from app.queue_sqlite import enqueue
from app.redact import redact_top_level
from app.config import EMBEDDING_DIMS

router = APIRouter(dependencies=[Depends(verify_bearer)])


class QueryRequest(BaseModel):
    collection: str
    text: str
    top_k: int = 10
    filters: dict | None = None


class UpsertRequest(BaseModel):
    collection: str
    text: str
    payload: dict | None = None


@router.post("/query")
async def memory_query(req: QueryRequest, request: Request):
    """Query memory — fail-open: returns degraded:true + [] if Qdrant down."""
    apply_roma(request)
    check_private_key_block(req.text, req.filters or {})

    client = get_client()
    if not client:
        return {"degraded": True, "results": []}

    try:
        vec = get_embedding(req.text)
        results = search(client, req.collection, vec, top_k=req.top_k, filters=req.filters)
        return {"degraded": False, "results": results}
    except Exception:
        return {"degraded": True, "results": []}


@router.post("/upsert")
async def memory_upsert(req: UpsertRequest, request: Request):
    """Upsert memory — queue if Qdrant down; block private keys."""
    apply_roma(request)
    check_private_key_block(req.text, req.payload or {})

    payload = req.payload or {}
    redacted_text, redacted_payload = redact_top_level(req.text, payload)
    # Store redacted for persistence
    payload_to_store = redacted_payload or {}
    if redacted_text is not None:
        payload_to_store["text"] = redacted_text

    client = get_client()
    if client:
        try:
            vec = get_embedding(req.text)
            from app.qdrant_store import upsert_points

            if upsert_points(client, req.collection, [(req.text, vec, payload_to_store)], EMBEDDING_DIMS):
                return {"status": "ok", "queued": False}
        except Exception:
            pass

    # Fail-open: queue for later
    enqueue(req.collection, req.text, payload_to_store, EMBEDDING_DIMS)
    return {"status": "ok", "queued": True}
