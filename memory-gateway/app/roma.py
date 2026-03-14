"""ROMA HTTP trust check — Option B."""
import httpx

from app.config import ROMA_URL


def get_roma_status() -> dict | None:
    """
    Fetch ROMA trust status from TruAi endpoint.
    Returns {"trust_state": "VERIFIED"} or None on failure.
    """
    try:
        with httpx.Client(timeout=5.0) as client:
            r = client.get(ROMA_URL)
            r.raise_for_status()
            data = r.json()
            return data if isinstance(data, dict) else None
    except Exception:
        return None
