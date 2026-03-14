"""Policy checks — block private keys, ROMA fail-open/fail-closed."""
from fastapi import HTTPException, Request

from app.cidr import get_zone
from app.redact import has_private_key_block
from app.roma import get_roma_status


def apply_roma(request: Request) -> None:
    """
    Option B: CIDR zone + ROMA HTTP trust check.
    fail-open only if zone=local AND ROMA trust_state=VERIFIED.
    zone=wg and zone=public always fail-closed.
    Sets request.state.zone, request.state.roma_verified, request.state.fail_open_allowed.
    """
    ip = request.client.host if request.client else ""
    zone = get_zone(ip)
    request.state.zone = zone

    roma_verified = False
    if zone == "local":
        roma = get_roma_status()
        trust = (roma or {}).get("trust_state")
        roma_verified = trust == "VERIFIED"

    request.state.roma_verified = roma_verified
    request.state.fail_open_allowed = zone == "local" and roma_verified


def check_private_key_block(text: str | None, payload: dict | None) -> None:
    """Raise 400 if private key block detected. Do not store or queue."""
    if text and has_private_key_block(text):
        raise HTTPException(status_code=400, detail="Private key blocks are not allowed")
    if payload:
        for v in payload.values():
            if isinstance(v, str) and has_private_key_block(v):
                raise HTTPException(status_code=400, detail="Private key blocks are not allowed")
