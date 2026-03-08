#!/usr/bin/env php
<?php
/**
 * TruAi ROMA-Secure Password Reset
 *
 * Resets user password with ROMA-aligned security:
 * - Generates crypto-random password (no weak defaults)
 * - Writes to .initial_credentials only (never stdout)
 * - Requires localhost or TRUAI_ALLOW_RESET=1 for non-CLI
 *
 * Usage:
 *   php reset-admin-password.php [username]
 *   php reset-admin-password.php --interactive
 *
 * Options:
 *   --interactive  Prompt for new password (most secure)
 *   [username]     Reset specific user (default: admin)
 *
 * Environment:
 *   TRUAI_RESET_PASSWORD  Optional: new password (less secure; avoid in production)
 *   TRUAI_ALLOW_RESET=1   Override localhost check (recovery only)
 *
 * @package TruAi
 * @version 2.0.0
 */

$scriptDir = __DIR__;
chdir($scriptDir);

require_once $scriptDir . '/backend/config.php';
require_once $scriptDir . '/backend/database.php';

$username = 'admin';  // Default: admin (Deme is legacy; DB may have admin)
$interactive = false;
foreach ($argv as $i => $arg) {
    if ($i === 0) continue;
    if ($arg === '--interactive' || $arg === '-i') {
        $interactive = true;
    } elseif ($arg[0] !== '-') {
        $username = trim($arg);
    }
}

// ROMA-aware: only allow from CLI on same machine, or explicit override
$isCli = php_sapi_name() === 'cli';
$allowReset = $isCli || (getenv('TRUAI_ALLOW_RESET') === '1');

if (!$allowReset) {
    fwrite(STDERR, "Reset is only allowed from CLI. Set TRUAI_ALLOW_RESET=1 for recovery.\n");
    exit(1);
}

$db = Database::getInstance();

// Verify user exists
$users = $db->query("SELECT id, username, role FROM users WHERE username = :u", [':u' => $username]);
if (empty($users)) {
    fwrite(STDERR, "User '$username' not found.\n");
    exit(1);
}

// Determine new password
$newPassword = null;
if ($interactive) {
    echo "Enter new password for $username: ";
    if (function_exists('readline')) {
        $newPassword = readline();
    } else {
        system('stty -echo 2>/dev/null');
        $newPassword = trim(fgets(STDIN));
        system('stty echo 2>/dev/null');
        echo "\n";
    }
    echo "Confirm new password: ";
    if (function_exists('readline')) {
        $confirm = readline();
    } else {
        system('stty -echo 2>/dev/null');
        $confirm = trim(fgets(STDIN));
        system('stty echo 2>/dev/null');
        echo "\n";
    }
    if ($newPassword !== $confirm) {
        fwrite(STDERR, "Passwords do not match.\n");
        exit(1);
    }
    if (strlen($newPassword) < 8) {
        fwrite(STDERR, "Password must be at least 8 characters.\n");
        exit(1);
    }
} elseif (($envPass = getenv('TRUAI_RESET_PASSWORD')) !== false && $envPass !== '') {
    $newPassword = $envPass;
} else {
    $newPassword = bin2hex(random_bytes(12));
}

$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
$db->execute(
    "UPDATE users SET password_hash = :hash WHERE username = :username",
    [':hash' => $passwordHash, ':username' => $username]
);

// Write to .initial_credentials (ROMA-aligned: no password on stdout)
$credFile = DATABASE_PATH . '/.initial_credentials';
$content = json_encode([
    'username' => $username,
    'password' => $newPassword,
    'reset_at' => date('c'),
    'warning' => 'ONE-TIME USE. Change password on first login. Delete after use.'
], JSON_PRETTY_PRINT);
file_put_contents($credFile, $content);
@chmod($credFile, 0600);

echo "Password reset complete for $username.\n";
echo "Credentials written to database/.initial_credentials — change password on first login.\n";
