"""Per-token collection permissions — Phase 3."""
from fastapi import HTTPException

from app.config import GEMINI_TOKENS, PHANTOM_TOKENS, TRUAI_TOKENS

READABLE = {
    "truai": ["truai_episodes", "phantom_episodes", "gemini_episodes", "shared_knowledge"],
    "phantom": ["phantom_episodes", "shared_knowledge"],
    "gemini": ["gemini_episodes", "shared_knowledge"],
}

WRITABLE = {
    "truai": ["truai_episodes", "phantom_episodes", "gemini_episodes", "shared_knowledge"],
    "phantom": ["phantom_episodes"],
    "gemini": ["gemini_episodes"],
}


def token_to_identity(token: str) -> str:
    """Map token to identity (truai, phantom, gemini). TruAi checked first for overlap."""
    truai_set = set(TRUAI_TOKENS)
    phantom_set = set(PHANTOM_TOKENS)
    gemini_set = set(GEMINI_TOKENS)
    if token in truai_set:
        return "truai"
    if token in phantom_set:
        return "phantom"
    if token in gemini_set:
        return "gemini"
    return "unknown"


def can_read(identity: str, collection: str) -> bool:
    return collection in READABLE.get(identity, [])


def can_write(identity: str, collection: str) -> bool:
    return collection in WRITABLE.get(identity, [])


def check_read(identity: str, collection: str) -> None:
    if not can_read(identity, collection):
        raise HTTPException(status_code=403, detail=f"Identity {identity} cannot read collection {collection}")


def check_write(identity: str, collection: str) -> None:
    if not can_write(identity, collection):
        raise HTTPException(status_code=403, detail=f"Identity {identity} cannot write collection {collection}")
