"""SQLite queue for memory upserts — in-process worker with exponential backoff."""
import json
import sqlite3
import time
from pathlib import Path

from app.config import QUEUE_DB_PATH


def init_db() -> None:
    """Create queue table if missing."""
    Path(QUEUE_DB_PATH).parent.mkdir(parents=True, exist_ok=True)
    with sqlite3.connect(QUEUE_DB_PATH) as conn:
        conn.execute("""
            CREATE TABLE IF NOT EXISTS memory_queue (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                collection TEXT NOT NULL,
                text TEXT NOT NULL,
                payload TEXT NOT NULL,
                dims INTEGER NOT NULL,
                created_at REAL NOT NULL,
                attempts INTEGER DEFAULT 0,
                last_error TEXT
            )
        """)
        conn.commit()


def enqueue(collection: str, text: str, payload: dict, dims: int) -> None:
    """Add item to queue."""
    init_db()
    with sqlite3.connect(QUEUE_DB_PATH) as conn:
        conn.execute(
            "INSERT INTO memory_queue (collection, text, payload, dims, created_at) VALUES (?, ?, ?, ?, ?)",
            (collection, text, json.dumps(payload), dims, time.time()),
        )
        conn.commit()


def dequeue(batch_size: int = 10) -> list[tuple[int, str, str, dict, int]]:
    """Dequeue up to batch_size items. Returns [(id, collection, text, payload, dims), ...]."""
    init_db()
    with sqlite3.connect(QUEUE_DB_PATH) as conn:
        conn.row_factory = sqlite3.Row
        rows = conn.execute(
            "SELECT id, collection, text, payload, dims, attempts FROM memory_queue ORDER BY id LIMIT ?",
            (batch_size,),
        ).fetchall()
        items = []
        for r in rows:
            items.append((r["id"], r["collection"], r["text"], json.loads(r["payload"]), r["dims"]))
        return items


def delete_from_queue(row_id: int) -> None:
    """Remove successfully processed item."""
    with sqlite3.connect(QUEUE_DB_PATH) as conn:
        conn.execute("DELETE FROM memory_queue WHERE id = ?", (row_id,))
        conn.commit()


def queue_depth() -> int:
    """Return number of pending items in queue."""
    init_db()
    with sqlite3.connect(QUEUE_DB_PATH) as conn:
        return conn.execute("SELECT COUNT(*) FROM memory_queue").fetchone()[0]


def increment_attempts(row_id: int, error: str) -> None:
    """Increment attempts and store last error."""
    with sqlite3.connect(QUEUE_DB_PATH) as conn:
        conn.execute(
            "UPDATE memory_queue SET attempts = attempts + 1, last_error = ? WHERE id = ?",
            (error[:500], row_id),
        )
        conn.commit()
