"""Ollama embeddings — nomic-embed-text, 768 dims."""
import httpx

from app.config import EMBEDDING_MODEL, OLLAMA_URL


def ollama_reachable() -> bool:
    """Quick check if Ollama is reachable."""
    try:
        r = httpx.get(f"{OLLAMA_URL.rstrip('/')}/api/tags", timeout=2.0)
        return r.status_code == 200
    except Exception:
        return False


def get_embedding(text: str) -> list[float]:
    """Fetch embedding from Ollama. Raises on error."""
    url = f"{OLLAMA_URL.rstrip('/')}/api/embeddings"
    with httpx.Client(timeout=60.0) as client:
        r = client.post(url, json={"model": EMBEDDING_MODEL, "prompt": text})
        r.raise_for_status()
        data = r.json()
    return data.get("embedding", [])
