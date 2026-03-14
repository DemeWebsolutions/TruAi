"""In-process queue worker — exponential backoff, runs on startup."""
import logging
import os
import threading
import time

from app.embeddings_ollama import get_embedding
from app.qdrant_store import get_client, upsert_points
from app.queue_sqlite import dequeue, delete_from_queue, increment_attempts

logger = logging.getLogger(__name__)

_worker_thread: threading.Thread | None = None
_stop_event = threading.Event()


def _run_worker() -> None:
    """Process queue items with exponential backoff."""
    base_delay = 1.0
    max_delay = 60.0
    delay = base_delay
    empty_count = 0
    while not _stop_event.is_set():
        items = dequeue(batch_size=5)
        if not items:
            empty_count += 1
            if empty_count > 3:
                delay = min(delay * 1.5, max_delay)
            time.sleep(delay)
            continue
        empty_count = 0
        delay = base_delay
        client = get_client()
        if not client:
            time.sleep(delay)
            continue
        for row_id, collection, text, payload, dims in items:
            if _stop_event.is_set():
                break
            try:
                vec = get_embedding(text)
                if upsert_points(client, collection, [(text, vec, payload)], dims):
                    delete_from_queue(row_id)
                else:
                    increment_attempts(row_id, "Qdrant upsert failed")
            except Exception as e:
                increment_attempts(row_id, str(e))
                logger.warning("Queue item %s failed: %s", row_id, e)
        time.sleep(0.5)


def start_worker() -> None:
    """Start background worker thread."""
    global _worker_thread
    if _worker_thread and _worker_thread.is_alive():
        return
    _stop_event.clear()
    _worker_thread = threading.Thread(target=_run_worker, daemon=True)
    _worker_thread.start()
    logger.info("Queue worker started")


def stop_worker() -> None:
    """Signal worker to stop."""
    _stop_event.set()
    if _worker_thread:
        _worker_thread.join(timeout=5.0)
