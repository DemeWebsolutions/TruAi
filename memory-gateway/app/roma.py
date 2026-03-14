"""ROMA HTTP trust check — Option B, with 1s cache."""
import time
import httpx

from app.config import ROMA_URL

_roma_cache: tuple[float, dict | None] = (0.0, None)
ROMA_CACHE_TTL = 1.0
ROMA_TIMEOUT = 2.0


def get_roma_status() -> dict | None:
    """
    Fetch ROMA trust status from TruAi endpoint.
    Cached for ~1s to reduce latency. Timeout 2s.
    Returns {"trust_state": "VERIFIED"} or None on failure.
    """
    global _roma_cache
    now = time.monotonic()
    if now - _roma_cache[0] < ROMA_CACHE_TTL:
        return _roma_cache[1]
    try:
        with httpx.Client(timeout=ROMA_TIMEOUT) as client:
            r = client.get(ROMA_URL)
            r.raise_for_status()
            data = r.json()
            result = data if isinstance(data, dict) else None
            _roma_cache = (now, result)
            return result
    except Exception:
        _roma_cache = (now, None)
        return None
