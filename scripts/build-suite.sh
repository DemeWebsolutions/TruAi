#!/usr/bin/env bash
# Build Trinity Suite — TruAi, Phantom, Gemini (Phase 10)
# Run from TruAi root.
set -e

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "Building TruAi.app..."
cd electron
npm install
npm run dist
cd ..

echo "Building Phantom.ai.app..."
cd apps/phantom
npm install
npm run build
cd ../..

echo "Building Gemini.ai.app..."
cd apps/gemini
npm install
npm run build
cd ../..

echo "Done. Outputs:"
echo "  TruAi:    electron/dist/"
echo "  Phantom:  apps/phantom/dist/"
echo "  Gemini:   apps/gemini/dist/"
echo ""
echo "Optional: Create combined Suite DMG with all three apps."
