"""Policy checks — block private keys, ROMA fail-open/fail-closed."""
from fastapi import HTTPException, Request

from app.redact import has_private_key_block


def is_localhost(request: Request) -> bool:
    """True if request is from localhost only (127.0.0.1, ::1). Option A."""
    ip = request.client.host if request.client else ""
    return ip in ("127.0.0.1", "::1")


def apply_roma(request: Request) -> None:
    """
    v1 Option A: localhost = VERIFIED (fail-open permitted).
    External = fail-closed on degradation (routes enforce 503).
    Sets request.state.is_local, request.state.is_external.
    """
    is_local = is_localhost(request)
    request.state.is_local = is_local
    request.state.is_external = not is_local


def check_private_key_block(text: str | None, payload: dict | None) -> None:
    """Raise 400 if private key block detected. Do not store or queue."""
    if text and has_private_key_block(text):
        raise HTTPException(status_code=400, detail="Private key blocks are not allowed")
    if payload:
        for v in payload.values():
            if isinstance(v, str) and has_private_key_block(v):
                raise HTTPException(status_code=400, detail="Private key blocks are not allowed")
