"""Qdrant vector store — auto-detect vector size, fail-open on errors."""
from qdrant_client import QdrantClient
from qdrant_client.http import models

from app.config import DEFAULT_COLLECTIONS, EMBEDDING_DIMS, PAYLOAD_INDEX_KEYS, QDRANT_URL


def get_client() -> QdrantClient | None:
    """Return Qdrant client or None if unreachable (fail-open)."""
    try:
        return QdrantClient(url=QDRANT_URL, timeout=10.0)
    except Exception:
        return None


def ensure_collection(client: QdrantClient, collection: str, dims: int) -> None:
    """Create collection if missing, with given vector size."""
    try:
        collections = client.get_collections().collections
        names = [c.name for c in collections]
        if collection not in names:
            client.create_collection(
                collection_name=collection,
                vectors_config=models.VectorParams(size=dims, distance=models.Distance.COSINE),
            )
    except Exception:
        pass


def ensure_payload_index(client: QdrantClient, collection: str, key: str) -> None:
    """Create payload index for filter field if missing."""
    try:
        client.create_payload_index(
            collection_name=collection,
            field_name=key,
            field_schema=models.PayloadSchemaType.KEYWORD,
        )
    except Exception:
        pass


def ensure_all_collections_and_indexes(client: QdrantClient) -> None:
    """Create default collections and payload indexes on startup."""
    for coll in DEFAULT_COLLECTIONS:
        ensure_collection(client, coll, EMBEDDING_DIMS)
        for key in PAYLOAD_INDEX_KEYS:
            ensure_payload_index(client, coll, key)


def upsert_points(
    client: QdrantClient,
    collection: str,
    points: list[tuple[str, list[float], dict]],
    dims: int,
) -> bool:
    """Upsert points. Returns True on success."""
    try:
        ensure_collection(client, collection, dims)
        ids = [hash(p[0]) % (2**63) for p in points]
        vectors = [p[1] for p in points]
        payloads = [p[2] for p in points]
        client.upsert(
            collection_name=collection,
            points=[
                models.PointStruct(id=ids[i], vector=vectors[i], payload=payloads[i])
                for i in range(len(points))
            ],
        )
        return True
    except Exception:
        return False


def search(
    client: QdrantClient,
    collection: str,
    vector: list[float],
    top_k: int = 10,
    filters: dict | None = None,
) -> list[dict]:
    """Search by vector. Returns list of payloads."""
    try:
        q = None
        if filters:
            q = models.Filter(
                must=[
                    models.FieldCondition(
                        key=k,
                        match=models.MatchValue(value=v),
                    )
                    for k, v in filters.items()
                ]
            )
        results = client.search(
            collection_name=collection,
            query_vector=vector,
            limit=top_k,
            query_filter=q,
        )
        return [r.payload or {} for r in results]
    except Exception:
        return []
