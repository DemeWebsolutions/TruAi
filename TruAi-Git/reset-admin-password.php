<?php
/**
 * Reset Admin Password Script
 * Run this if you need to reset the admin password
 */

require_once 'backend/config.php';
require_once 'backend/database.php';

$db = Database::getInstance();

// Reset admin password to 'admin123'
$newPassword = 'admin123';
$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

$db->execute(
    "UPDATE users SET password_hash = :hash WHERE username = 'admin'",
    [':hash' => $passwordHash]
);

echo "✅ Admin password reset successfully!\n";
echo "Username: admin\n";
echo "Password: admin123\n";
