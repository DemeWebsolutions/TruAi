"""FastAPI app — memory gateway with /version, auth, ROMA, queue worker."""
from contextlib import asynccontextmanager

from fastapi import Depends, FastAPI

from app.auth import verify_bearer
from app.config import DATA_DIR
from app.limits import apply_limits_middleware
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
async def health():
    return {"status": "ok"}


@app.get("/version", dependencies=[Depends(verify_bearer)])
async def version():
    return {"version": VERSION}
