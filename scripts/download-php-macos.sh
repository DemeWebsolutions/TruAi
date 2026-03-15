#!/usr/bin/env bash
# Download PHP for macOS (Phase 5 — Bundle PHP)
# Run from TruAi root. Output: electron/php/
# Requires: curl, tar
set -e

PHP_VERSION="${PHP_VERSION:-8.3.0}"
ARCH=$(uname -m)
OUT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/electron/php"

echo "Downloading PHP ${PHP_VERSION} for ${ARCH}..."
mkdir -p "$OUT_DIR"
cd "$OUT_DIR"

# Use php.net builds or a known macOS PHP distro
# Example: https://github.com/nicoverbruggen/phpmon - or build from source
# For now, document the manual step:
echo ""
echo "Manual step: Download PHP for macOS from:"
echo "  https://www.php.net/downloads"
echo "  Or: brew install php (then copy from /opt/homebrew/opt/php)"
echo ""
echo "Place PHP in: $OUT_DIR"
echo "  Expected structure: $OUT_DIR/bin/php"
echo ""
