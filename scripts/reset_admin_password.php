#!/usr/bin/env php
<?php
/**
 * Reset Admin Password Script
 *
 * Emergency tool to reset a user's password
 *
 * Usage: php scripts/reset_admin_password.php <username>
 *
 * @package TruAi
 * @version 1.0.0
 * @copyright My Deme, LLC © 2026
 */

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/database.php';

if ($argc < 2) {
    echo "Usage: php scripts/reset_admin_password.php <username>\n";
    echo "Example: php scripts/reset_admin_password.php admin\n";
    exit(1);
}

$username = $argv[1];

$db = Database::getInstance();

// Check user exists
$result = $db->query(
    "SELECT id FROM users WHERE username = :username LIMIT 1",
    [':username' => $username]
);

if (empty($result)) {
    echo "[ERR] User not found: $username\n";
    exit(1);
}

$userId = $result[0]['id'];

// Generate new password
$newPassword = bin2hex(random_bytes(8)); // 16 chars

// Hash with Argon2id
$passwordHash = password_hash($newPassword, PASSWORD_ARGON2ID, ARGON2ID_OPTIONS);

// Update database
$db->execute(
    "UPDATE users SET password_hash = :hash, requires_password_change = 1 WHERE id = :id",
    [
        ':hash' => $passwordHash,
        ':id'   => $userId
    ]
);

// Write to credentials file
$credentialsFile = DATABASE_PATH . '/.initial_credentials';
$credentials = json_encode([
    'username'  => $username,
    'password'  => $newPassword,
    'reset_at'  => date('c'),
    'note'      => 'Password reset by admin. Change immediately after login.'
], JSON_PRETTY_PRINT);

file_put_contents($credentialsFile, $credentials);
chmod($credentialsFile, 0600);

echo "[OK] Password reset for user: $username\n";
echo "[OK] New credentials written: $credentialsFile\n";
echo "\n";
echo "Temporary password: $newPassword\n";
echo "\n";
echo "[WARN] IMPORTANT:\n";
echo "  1. Login immediately: http://127.0.0.1:8001/TruAi/login-portal.html\n";
echo "  2. Change this password in Settings -> Security\n";
echo "  3. Delete $credentialsFile after login\n";
echo "\n";

exit(0);
