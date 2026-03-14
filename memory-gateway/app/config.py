"""Configuration from environment."""
import os

QDRANT_URL = os.getenv("QDRANT_URL", "http://qdrant:6333")
OLLAMA_URL = os.getenv("OLLAMA_URL", "http://ollama:11434")
EMBEDDING_MODEL = os.getenv("EMBEDDING_MODEL", "nomic-embed-text")
EMBEDDING_DIMS = int(os.getenv("EMBEDDING_DIMS", "768"))

TRUAI_TOKENS = [t.strip() for t in os.getenv("TRUAI_TOKENS", "").split(",") if t.strip()]
PHANTOM_TOKENS = [t.strip() for t in os.getenv("PHANTOM_TOKENS", "").split(",") if t.strip()]
GEMINI_TOKENS = [t.strip() for t in os.getenv("GEMINI_TOKENS", "").split(",") if t.strip()]

ALL_TOKENS = set(TRUAI_TOKENS + PHANTOM_TOKENS + GEMINI_TOKENS)

DATA_DIR = os.getenv("GATEWAY_DATA_DIR", "/data")
QUEUE_DB_PATH = os.path.join(DATA_DIR, "queue.db")

MAX_TEXT_CHARS = int(os.getenv("MAX_TEXT_CHARS", "20000"))
MAX_PAYLOAD_CHARS = int(os.getenv("MAX_PAYLOAD_CHARS", "20000"))

# Option B: ROMA + CIDR zone classification
ROMA_URL = os.getenv("ROMA_URL", "http://127.0.0.1:8001/TruAi/api/v1/security/roma")
LOCAL_CIDRS = [s.strip() for s in os.getenv("LOCAL_CIDRS", "127.0.0.1/32,::1/128,192.168.0.0/16,10.0.0.0/8,172.16.0.0/12").split(",") if s.strip()]
WG_CIDRS = [s.strip() for s in os.getenv("WG_CIDRS", "10.100.0.0/24").split(",") if s.strip()]
