#!/usr/bin/env python3
"""
Memory Gateway — entry point for PyInstaller / embedded runtime.
Runs uvicorn with host/port from env (default 127.0.0.1:8010).
"""
import os
import sys

# Ensure app is on path when frozen
if getattr(sys, "frozen", False):
    sys.path.insert(0, os.path.dirname(sys.executable))
else:
    sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import uvicorn

HOST = os.getenv("GATEWAY_HOST", "127.0.0.1")
PORT = int(os.getenv("GATEWAY_PORT", "8010"))

if __name__ == "__main__":
    uvicorn.run(
        "app.main:app",
        host=HOST,
        port=PORT,
        log_level="info",
    )
