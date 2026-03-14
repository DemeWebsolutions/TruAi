"""Optional payload limits — 413 if exceeded."""
import json

from fastapi import FastAPI, Request, Response
from starlette.middleware.base import BaseHTTPMiddleware

from app.config import MAX_PAYLOAD_CHARS, MAX_TEXT_CHARS


class LimitsMiddleware(BaseHTTPMiddleware):
    """Reject oversized text/payload with 413. Re-injects body for downstream."""

    async def dispatch(self, request: Request, call_next):
        if request.method not in ("POST", "PUT", "PATCH"):
            return await call_next(request)
        body = await request.body()
        if len(body) > MAX_PAYLOAD_CHARS:
            return Response(
                content='{"detail":"Payload too large"}',
                status_code=413,
                media_type="application/json",
            )
        try:
            data = json.loads(body) if body else {}
            text = data.get("text") or data.get("query") or ""
            if isinstance(text, str) and len(text) > MAX_TEXT_CHARS:
                return Response(
                    content='{"detail":"Text too large"}',
                    status_code=413,
                    media_type="application/json",
                )
        except json.JSONDecodeError:
            pass

        # Re-inject body so route handlers can read it
        async def receive():
            return {"type": "http.request", "body": body}

        request._receive = receive
        return await call_next(request)


def apply_limits_middleware(app: FastAPI) -> None:
    """Add limits middleware to app."""
    app.add_middleware(LimitsMiddleware)
