#!/usr/bin/env php
<?php
/**
 * TruAi Migration Runner
 *
 * Applies pending SQL migrations from database/migrations/ in filename order.
 * Safe to run multiple times — already-applied migrations are skipped.
 *
 * Usage: php scripts/run_migrations.php
 *
 * @package TruAi
 * @version 1.0.0
 * @copyright My Deme, LLC © 2026
 */

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/database.php';

$db = Database::getInstance();

// Ensure the migrations tracking table exists
$db->getConnection()->exec("
    CREATE TABLE IF NOT EXISTS migrations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        filename TEXT NOT NULL UNIQUE,
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

// Collect already-applied migrations
$applied = array_column(
    $db->query("SELECT filename FROM migrations ORDER BY applied_at"),
    'filename'
);

// Discover available migration files
$migrationsDir = __DIR__ . '/../database/migrations';
$files = glob($migrationsDir . '/*.sql');

if (empty($files)) {
    echo "✓ No migration files found in $migrationsDir\n";
    exit(0);
}

sort($files); // Apply in filename order (001_, 002_, …)

$applied_count = 0;
$skipped_count = 0;

foreach ($files as $file) {
    $filename = basename($file);

    if (in_array($filename, $applied)) {
        echo "  ↷ Skipping (already applied): $filename\n";
        $skipped_count++;
        continue;
    }

    echo "  → Applying: $filename … ";

    $sql = file_get_contents($file);

    // Split on semicolons; ignore empty/comment-only lines (single-line -- and block /* */ comments)
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => !empty($s) && !preg_match('/^\s*--/', $s) && !preg_match('/^\s*\/\*.*\*\/\s*$/s', $s)
    );

    try {
        $db->getConnection()->beginTransaction();

        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $db->getConnection()->exec($statement);
            }
        }

        // Record that this migration was applied
        $db->execute(
            "INSERT INTO migrations (filename, applied_at) VALUES (:f, datetime('now'))",
            [':f' => $filename]
        );

        $db->getConnection()->commit();
        echo "✓\n";
        $applied_count++;

    } catch (Throwable $e) {
        $db->getConnection()->rollBack();
        echo "✗ FAILED\n";
        echo "  Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\n";
echo "✓ Migration complete — applied: $applied_count, skipped: $skipped_count\n";
exit(0);
