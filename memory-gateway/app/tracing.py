"""Trace ID + structured request logging."""
import json
import logging
import time
import uuid

from starlette.middleware.base import BaseHTTPMiddleware
from starlette.requests import Request
from starlette.responses import Response

from app.permissions import token_to_identity

logger = logging.getLogger("gateway.requests")


def get_trace_id(request: Request) -> str:
    """Use X-Request-ID if present, else generate uuid4."""
    rid = request.headers.get("X-Request-ID") or request.headers.get("X-Trace-ID")
    return rid.strip()[:64] if rid else str(uuid.uuid4())


class TracingMiddleware(BaseHTTPMiddleware):
    """Attach trace_id to request, add X-Trace-ID to response, log one JSON line per request."""

    async def dispatch(self, request: Request, call_next) -> Response:
        trace_id = get_trace_id(request)
        request.state.trace_id = trace_id
        start = time.perf_counter()

        response = await call_next(request)
        latency_ms = round((time.perf_counter() - start) * 1000)

        auth = request.headers.get("Authorization", "")
        token = auth[7:] if auth.startswith("Bearer ") else ""
        identity = token_to_identity(token) if token else "unknown"

        log_entry = {
            "timestamp": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
            "trace_id": trace_id,
            "identity": identity,
            "path": request.url.path,
            "method": request.method,
            "status_code": response.status_code,
            "zone": getattr(request.state, "zone", "unknown"),
            "roma_verified": getattr(request.state, "roma_verified", False),
            "fail_open_allowed": getattr(request.state, "fail_open_allowed", False),
            "degraded": getattr(request.state, "degraded", None),
            "queued": getattr(request.state, "queued", None),
            "latency_ms": latency_ms,
        }
        logger.info(json.dumps(log_entry))

        response.headers["X-Trace-ID"] = trace_id
        return response
