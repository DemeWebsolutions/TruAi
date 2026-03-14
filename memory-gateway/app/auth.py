"""Bearer token auth — requires valid token from TRUAI/PHANTOM/GEMINI."""
from fastapi import HTTPException, Security
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

from app.config import ALL_TOKENS

security = HTTPBearer(auto_error=True)


def verify_bearer(credentials: HTTPAuthorizationCredentials = Security(security)) -> str:
    """Validate Bearer token; raise 401 if invalid."""
    token = credentials.credentials
    if not token or token not in ALL_TOKENS:
        raise HTTPException(status_code=401, detail="Invalid or missing token")
    return token
