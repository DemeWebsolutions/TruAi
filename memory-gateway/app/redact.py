"""Top-level redaction: text + top-level payload strings only."""
import re

# Block scope: reject private key blocks
PRIVATE_KEY_PATTERN = re.compile(
    r"-----BEGIN\s+(?:RSA\s+)?(?:EC\s+)?PRIVATE\s+KEY-----"
)


def has_private_key_block(s: str) -> bool:
    """True if string contains a PEM private key block."""
    return bool(PRIVATE_KEY_PATTERN.search(s)) if s else False


def redact_top_level(text: str | None, payload: dict | None) -> tuple[str | None, dict | None]:
    """
    Redact top-level text and payload string values.
    Returns (redacted_text, redacted_payload).
    """
    out_text = None
    if text is not None and isinstance(text, str):
        out_text = "[REDACTED]" if text.strip() else text

    out_payload = None
    if payload is not None and isinstance(payload, dict):
        out_payload = {}
        for k, v in payload.items():
            if isinstance(v, str):
                out_payload[k] = "[REDACTED]" if v.strip() else v
            else:
                out_payload[k] = v

    return out_text, out_payload
