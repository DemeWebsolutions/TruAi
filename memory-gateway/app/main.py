"""FastAPI app — memory gateway with /version, auth, ROMA, queue worker."""
from contextlib import asynccontextmanager

from fastapi import Depends, FastAPI, Request

from app.auth import verify_bearer
from app.embeddings_ollama import ollama_reachable
from app.limits import apply_limits_middleware
from app.policies import apply_roma
from app.qdrant_store import get_client
from app.queue_sqlite import queue_depth
from app.queue_worker import start_worker, stop_worker
from app.routes_memory import router as memory_router

VERSION = "1.0.0"


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Start queue worker on startup, stop on shutdown."""
    start_worker()
    yield
    stop_worker()


app = FastAPI(
    title="TruAi Memory Gateway",
    version=VERSION,
    lifespan=lifespan,
)

apply_limits_middleware(app)

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
    return {
        "status": "ok",
        "zone": getattr(request.state, "zone", "unknown"),
        "roma_verified": getattr(request.state, "roma_verified", False),
        "fail_open_allowed": getattr(request.state, "fail_open_allowed", False),
        "queue_depth": queue_depth(),
        "qdrant_reachable": qdrant_ok,
        "ollama_reachable": ollama_ok,
    }


@app.get("/version", dependencies=[Depends(verify_bearer)])
async def version():
    return {"version": VERSION}
