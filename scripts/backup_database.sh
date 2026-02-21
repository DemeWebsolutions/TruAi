#!/bin/bash
#
# TruAi Database Backup Script
#
# Backs up SQLite database with compression and retention policy
#
# Usage: ./scripts/backup_database.sh
# Or schedule via cron: 0 2 * * * /path/to/TruAi/scripts/backup_database.sh

set -e

# Configuration
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="$HOME/.truai_backups"
DB_PATH="$PROJECT_ROOT/database/truai.db"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/truai_${TIMESTAMP}.db"
RETENTION_DAYS=30

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Check database exists
if [ ! -f "$DB_PATH" ]; then
    echo "[ERR] Database not found: $DB_PATH"
    exit 1
fi

echo "Starting TruAi database backup..."
echo "Source: $DB_PATH"
echo "Destination: $BACKUP_FILE"

# SQLite online backup (handles locks gracefully)
sqlite3 "$DB_PATH" ".backup '$BACKUP_FILE'"

# Verify backup
if [ ! -f "$BACKUP_FILE" ]; then
    echo "[ERR] Backup failed"
    exit 1
fi

# Compress
gzip "$BACKUP_FILE"
echo "[OK] Backup compressed: ${BACKUP_FILE}.gz"

# Calculate size
BACKUP_SIZE=$(du -h "${BACKUP_FILE}.gz" | cut -f1)
echo "[OK] Backup size: $BACKUP_SIZE"

# Cleanup old backups (keep last 30 days)
find "$BACKUP_DIR" -name "truai_*.db.gz" -mtime +$RETENTION_DAYS -delete
OLD_COUNT=$(find "$BACKUP_DIR" -name "truai_*.db.gz" -mtime +$RETENTION_DAYS 2>/dev/null | wc -l)
if [ "$OLD_COUNT" -gt 0 ]; then
    echo "[OK] Cleaned up $OLD_COUNT old backups (>$RETENTION_DAYS days)"
fi

# List recent backups
echo ""
echo "Recent backups:"
ls -lh "$BACKUP_DIR" | tail -5

echo ""
echo "[OK] Backup complete: ${BACKUP_FILE}.gz"
