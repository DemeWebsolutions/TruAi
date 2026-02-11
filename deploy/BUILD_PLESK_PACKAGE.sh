#!/usr/bin/env bash
#
# Build Gemini.ai Plesk deployment package
# Creates gemini-deploy-plesk-YYYYMMDD.tar.gz in project root
#
# Usage:
#   ./deploy/BUILD_PLESK_PACKAGE.sh
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
TIMESTAMP=$(date +%Y%m%d)
OUTPUT_NAME="gemini-deploy-plesk-${TIMESTAMP}"
OUTPUT_DIR="$SOURCE_DIR/$OUTPUT_NAME"
ARCHIVE="$SOURCE_DIR/gemini-deploy-plesk-${TIMESTAMP}.tar.gz"

echo "Building Gemini.ai Plesk deployment package..."
echo "  Source: $SOURCE_DIR"
echo "  Output: $ARCHIVE"

# Clean previous build
rm -rf "$OUTPUT_DIR"
mkdir -p "$OUTPUT_DIR"

# Copy essential files
cp -r "$SOURCE_DIR"/backend "$OUTPUT_DIR/"
cp -r "$SOURCE_DIR"/assets "$OUTPUT_DIR/"
cp -r "$SOURCE_DIR"/dev "$OUTPUT_DIR/"
cp -r "$SOURCE_DIR"/database "$OUTPUT_DIR/"
cp -r "$SOURCE_DIR"/deploy "$OUTPUT_DIR/"
cp "$SOURCE_DIR"/router.php "$SOURCE_DIR"/index.php "$OUTPUT_DIR/"

# Copy static files
for f in login-portal.html welcome.html start.html loading.html access-granted.html access-denied.html change-password.php reset-admin-password.php access-granted.gif access-denied.gif; do
  [ -f "$SOURCE_DIR/$f" ] && cp "$SOURCE_DIR/$f" "$OUTPUT_DIR/" 2>/dev/null || true
done
[ -f "$SOURCE_DIR/TruAi Prototype.html" ] && cp "$SOURCE_DIR/TruAi Prototype.html" "$OUTPUT_DIR/" 2>/dev/null || true

# Remove DB file, .DS_Store, and macOS resource forks
rm -f "$OUTPUT_DIR/database/truai.db" 2>/dev/null || true
find "$OUTPUT_DIR" -name '.DS_Store' -delete 2>/dev/null || true
find "$OUTPUT_DIR" -name '._*' -delete 2>/dev/null || true

# Create archive (COPYFILE_DISABLE=1 avoids macOS xattrs for Linux extraction)
cd "$SOURCE_DIR"
COPYFILE_DISABLE=1 tar -czf "$ARCHIVE" \
  --exclude='.DS_Store' \
  --exclude='._*' \
  "$OUTPUT_NAME"
rm -rf "$OUTPUT_DIR"

echo ""
echo "=========================================="
echo "  Package built: $ARCHIVE"
echo "=========================================="
echo ""
echo "Next steps (on Contabo via Plesk Terminal):"
echo "  1. Upload $ARCHIVE to /tmp"
echo "  2. cd /tmp && tar -xzf gemini-deploy-plesk-${TIMESTAMP}.tar.gz"
echo "  3. cd gemini-deploy-plesk-${TIMESTAMP}"
echo "  4. chmod +x deploy/DEPLOY_PLESK_GEMINI_AI.sh"
echo "  5. ./deploy/DEPLOY_PLESK_GEMINI_AI.sh"
echo ""
