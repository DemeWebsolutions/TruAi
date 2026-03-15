#!/usr/bin/env bash
# Build Memory Gateway as macOS binary (PyInstaller)
# Run from memory-gateway/ or project root.
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GATEWAY_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$GATEWAY_DIR"

# Create venv if needed
if [ ! -d ".venv" ]; then
  python3 -m venv .venv
fi
source .venv/bin/activate

# Install deps + pyinstaller
pip install -q -r requirements.txt
pip install -q pyinstaller

# Build
pyinstaller gateway.spec

echo "Built: dist/gateway"
ls -la dist/gateway 2>/dev/null || true
