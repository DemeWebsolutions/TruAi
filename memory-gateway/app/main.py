"""FastAPI app — memory gateway with /version, auth, ROMA, queue worker."""
import logging
from contextlib import asynccontextmanager

from fastapi import Depends, FastAPI, Request

logging.basicConfig(
    level=logging.INFO,
    format="%(message)s",
)

from app.auth import verify_bearer
from app.embeddings_ollama import ollama_reachable
from app.limits import apply_limits_middleware
from app.policies import apply_roma
from app.tracing import TracingMiddleware
from app.qdrant_store import get_client
from app.queue_sqlite import queue_depth
from app.queue_worker import get_worker_metrics, start_worker, stop_worker
from app.routes_memory import router as memory_router

VERSION = "1.0.0"


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Startup: config summary, embedding probe, Qdrant schema; then queue worker."""
    from app.config import (
        EMBEDDING_DIMS,
        EMBEDDING_MODEL,
        LOCAL_CIDRS,
        QDRANT_URL,
        WG_CIDRS,
    )
    from app.embeddings_ollama import probe_embedding_dims
    from app.qdrant_store import ensure_all_collections_and_indexes, get_client

    logging.info(
        "gateway startup: EMBEDDING_MODEL=%s EMBEDDING_DIMS=%s QDRANT_URL=%s "
        "LOCAL_CIDRS=%s WG_CIDRS=%s",
        EMBEDDING_MODEL,
        EMBEDDING_DIMS,
        QDRANT_URL,
        len(LOCAL_CIDRS),
        len(WG_CIDRS),
    )
    probe_embedding_dims()
    client = get_client()
    if client:
        ensure_all_collections_and_indexes(client)
        logging.info("gateway startup: Qdrant collections and indexes ensured")
    else:
        logging.warning("gateway startup: Qdrant unreachable, collections will be created on first upsert")
    start_worker()
    yield
    stop_worker()


app = FastAPI(
    title="TruAi Memory Gateway",
    version=VERSION,
    lifespan=lifespan,
)

apply_limits_middleware(app)
app.add_middleware(TracingMiddleware)

app.include_router(memory_router, prefix="/memory", tags=["memory"])


@app.get("/")
async def root():
    return {"service": "TruAi Memory Gateway", "version": VERSION}


@app.get("/health", dependencies=[Depends(verify_bearer)])
async def health(request: Request):
    """Detailed health: zone, roma_verified, fail_open_allowed, queue_depth, qdrant, ollama."""
    apply_roma(request)
    qdrant_ok = get_client() is not None
    ollama_ok = ollama_reachable()
    metrics = get_worker_metrics()
    return {
        "status": "ok",
        "trace_id": getattr(request.state, "trace_id", ""),
        "zone": getattr(request.state, "zone", "unknown"),
        "roma_verified": getattr(request.state, "roma_verified", False),
        "fail_open_allowed": getattr(request.state, "fail_open_allowed", False),
        "queue_depth": queue_depth(),
        "jobs_processed": metrics["jobs_processed"],
        "jobs_failed": metrics["jobs_failed"],
        "qdrant_reachable": qdrant_ok,
        "ollama_reachable": ollama_ok,
    }


@app.get("/version", dependencies=[Depends(verify_bearer)])
async def version():
    return {"version": VERSION}
