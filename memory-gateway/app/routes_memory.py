"""Memory routes — query, upsert; auth, ROMA, redaction, fail-open."""
from fastapi import APIRouter, Depends, HTTPException, Request
from pydantic import BaseModel

from app.auth import verify_bearer
from app.embeddings_ollama import get_embedding
from app.permissions import check_read, check_write, token_to_identity
from app.policies import apply_roma, check_private_key_block
from app.qdrant_store import get_client, search
from app.queue_sqlite import enqueue
from app.redact import redact_top_level
from app.config import EMBEDDING_DIMS

router = APIRouter(dependencies=[Depends(verify_bearer)])


def get_identity(request: Request) -> str:
    """Get identity from Authorization header (token mapped to truai/phantom/gemini)."""
    auth = request.headers.get("Authorization", "")
    if auth.startswith("Bearer "):
        token = auth[7:]
        return token_to_identity(token)
    return "unknown"


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
    """Query memory — fail-open only for localhost when ROMA VERIFIED."""
    identity = get_identity(request)
    check_read(identity, req.collection)
    apply_roma(request)
    check_private_key_block(req.text, req.filters or {})

    base = {
        "trace_id": getattr(request.state, "trace_id", ""),
        "zone": request.state.zone,
        "identity": identity,
    }

    client = get_client()
    if not client:
        if not request.state.fail_open_allowed:
            raise HTTPException(status_code=503, detail="Memory unavailable (external fail-closed)")
        request.state.degraded = True
        return {**base, "degraded": True, "results": []}

    try:
        vec = get_embedding(req.text)
        results = search(client, req.collection, vec, top_k=req.top_k, filters=req.filters)
        request.state.degraded = False
        return {**base, "degraded": False, "results": results}
    except Exception:
        if not request.state.fail_open_allowed:
            raise HTTPException(status_code=503, detail="Memory query failed (external fail-closed)")
        request.state.degraded = True
        return {**base, "degraded": True, "results": []}


@router.post("/upsert")
async def memory_upsert(req: UpsertRequest, request: Request):
    """Upsert memory — queue only for localhost when ROMA VERIFIED."""
    identity = get_identity(request)
    check_write(identity, req.collection)
    apply_roma(request)
    check_private_key_block(req.text, req.payload or {})

    base = {
        "trace_id": getattr(request.state, "trace_id", ""),
        "zone": request.state.zone,
        "identity": identity,
    }

    payload = req.payload or {}
    redacted_text, redacted_payload = redact_top_level(req.text, payload)
    payload_to_store = redacted_payload or {}
    if redacted_text is not None:
        payload_to_store["text"] = redacted_text

    client = get_client()
    if client:
        try:
            vec = get_embedding(req.text)
            from app.qdrant_store import upsert_points

            if upsert_points(client, req.collection, [(req.text, vec, payload_to_store)], EMBEDDING_DIMS):
                request.state.queued = False
                return {**base, "status": "ok", "queued": False}
        except Exception:
            pass

    if not request.state.fail_open_allowed:
        raise HTTPException(status_code=503, detail="Memory upsert unavailable (external fail-closed)")

    enqueue(req.collection, req.text, payload_to_store, EMBEDDING_DIMS)
    request.state.queued = True
    return {**base, "status": "ok", "queued": True}
