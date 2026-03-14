"""ROMA policy: local (VERIFIED) = fail-open; WireGuard = fail-closed."""
from fastapi import Request

from app.cidr import is_private_or_localhost


def is_local_verified(request: Request) -> bool:
    """
    True if request is from localhost/private IP (treated as VERIFIED).
    WireGuard IPs are not private → fail-closed.
    """
    # X-Forwarded-For can be spoofed; for v1 we trust direct client IP
    client = request.client
    if not client:
        return False
    return is_private_or_localhost(client.host)
