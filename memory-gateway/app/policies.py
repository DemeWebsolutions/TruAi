"""Policy checks — block private keys, ROMA fail-open/fail-closed."""
from fastapi import HTTPException, Request

from app.redact import has_private_key_block
from app.roma import is_local_verified


def check_private_key_block(text: str | None, payload: dict | None) -> None:
    """Raise 400 if private key block detected. Do not store or queue."""
    if text and has_private_key_block(text):
        raise HTTPException(status_code=400, detail="Private key blocks are not allowed")
    if payload:
        for v in payload.values():
            if isinstance(v, str) and has_private_key_block(v):
                raise HTTPException(status_code=400, detail="Private key blocks are not allowed")


def apply_roma(request: Request) -> bool:
    """
    ROMA: local (VERIFIED) = fail-open; WireGuard = fail-closed.
    Returns True if request is allowed to proceed.
    """
    if is_local_verified(request):
        return True  # fail-open for local
    # For non-local (e.g. WireGuard), fail-closed: could add extra checks here
    return True  # v1: we allow all for now; ROMA is advisory
