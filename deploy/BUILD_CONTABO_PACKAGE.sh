#!/usr/bin/env bash
#
# Build Gemini.ai Contabo deployment package
# Creates gemini-deploy-contabo-YYYYMMDD.tar.gz in the project root
#
# Usage:
#   ./deploy/BUILD_CONTABO_PACKAGE.sh
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
TIMESTAMP=$(date +%Y%m%d)
OUTPUT_NAME="gemini-deploy-contabo-${TIMESTAMP}"
OUTPUT_DIR="$SOURCE_DIR/$OUTPUT_NAME"
ARCHIVE="$SOURCE_DIR/gemini-deploy-contabo-${TIMESTAMP}.tar.gz"

echo "Building Gemini.ai Contabo deployment package..."
echo "  Source: $SOURCE_DIR"
echo "  Output: $ARCHIVE"

# Clean previous build
rm -rf "$OUTPUT_DIR"
mkdir -p "$OUTPUT_DIR"

# Copy essential files
cp -r "$SOURCE_DIR"/router.php "$SOURCE_DIR"/index.php "$OUTPUT_DIR/"
cp -r "$SOURCE_DIR"/backend "$OUTPUT_DIR/"
cp -r "$SOURCE_DIR"/assets "$OUTPUT_DIR/"
cp -r "$SOURCE_DIR"/dev "$OUTPUT_DIR/"
cp -r "$SOURCE_DIR"/database "$OUTPUT_DIR/"
cp -r "$SOURCE_DIR"/deploy "$OUTPUT_DIR/"

# Copy static files
for f in login-portal.html welcome.html start.html loading.html access-granted.html access-denied.html change-password.php reset-admin-password.php access-granted.gif access-denied.gif; do
  [ -f "$SOURCE_DIR/$f" ] && cp "$SOURCE_DIR/$f" "$OUTPUT_DIR/" 2>/dev/null || true
done
[ -f "$SOURCE_DIR/TruAi Prototype.html" ] && cp "$SOURCE_DIR/TruAi Prototype.html" "$OUTPUT_DIR/" 2>/dev/null || true

# Create logs dir
mkdir -p "$OUTPUT_DIR/logs"

# Remove DB file (fresh DB created on deploy); keep schema if present
rm -f "$OUTPUT_DIR/database/truai.db" 2>/dev/null || true

# Create deployment package
cd "$SOURCE_DIR"
tar -czf "$ARCHIVE" "$OUTPUT_NAME"
rm -rf "$OUTPUT_DIR"

echo ""
echo "=========================================="
echo "  Package built: $ARCHIVE"
echo "=========================================="
echo ""
echo "Next steps:"
echo "  1. Upload $ARCHIVE to Contabo (SCP, SFTP, or Plesk File Manager)"
echo "  2. SSH/Plesk Terminal: tar -xzf gemini-deploy-contabo-${TIMESTAMP}.tar.gz"
echo "  3. cd gemini-deploy-contabo-${TIMESTAMP}"
echo "  4. chmod +x deploy/DEPLOY_CONTABO_PLESK.sh"
echo "  5. ./deploy/DEPLOY_CONTABO_PLESK.sh"
echo ""
