#!/usr/bin/env php
<?php
/**
 * TruAi Password Change Utility
 * 
 * Change the password for any user in the database
 * 
 * Usage: php change-password.php [username] [new-password]
 * 
 * @package TruAi
 * @version 1.0.0
 */

// Get the directory where this script is located
$scriptDir = __DIR__;
chdir($scriptDir);

// Load configuration
require_once $scriptDir . '/backend/config.php';
require_once $scriptDir . '/backend/database.php';

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔐 TruAi Password Change Utility\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";

// Get username and password from command line or prompt
$username = $argv[1] ?? null;
$newPassword = $argv[2] ?? null;

// If not provided via command line, prompt interactively
if (!$username) {
    echo "Enter username (default: admin): ";
    $input = trim(fgets(STDIN));
    $username = $input ?: 'admin';
}

if (!$newPassword) {
    echo "Enter new password: ";
    system('stty -echo'); // Hide password input
    $newPassword = trim(fgets(STDIN));
    system('stty echo'); // Restore echo
    echo "\n";
    
    if (empty($newPassword)) {
        echo "❌ Password cannot be empty!\n";
        exit(1);
    }
    
    echo "Confirm new password: ";
    system('stty -echo');
    $confirmPassword = trim(fgets(STDIN));
    system('stty echo');
    echo "\n";
    
    if ($newPassword !== $confirmPassword) {
        echo "❌ Passwords do not match!\n";
        exit(1);
    }
}

// Validate password strength (optional but recommended)
if (strlen($newPassword) < 8) {
    echo "⚠️  Warning: Password is less than 8 characters. Consider using a stronger password.\n";
    echo "Continue anyway? (y/N): ";
    $confirm = trim(fgets(STDIN));
    if (strtolower($confirm) !== 'y') {
        echo "Password change cancelled.\n";
        exit(0);
    }
}

try {
    $db = Database::getInstance();
    
    // Check if user exists
    $users = $db->query(
        "SELECT id, username, role FROM users WHERE username = :username",
        [':username' => $username]
    );
    
    if (empty($users)) {
        echo "❌ User '$username' not found in database.\n";
        echo "\nAvailable users:\n";
        $allUsers = $db->query("SELECT username, role FROM users");
        foreach ($allUsers as $user) {
            echo "  - {$user['username']} ({$user['role']})\n";
        }
        exit(1);
    }
    
    $user = $users[0];
    echo "📋 User found: {$user['username']} ({$user['role']})\n";
    echo "🔐 Changing password...\n";
    
    // Hash the new password
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password in database
    $db->execute(
        "UPDATE users SET password_hash = :hash WHERE username = :username",
        [
            ':hash' => $passwordHash,
            ':username' => $username
        ]
    );
    
    echo "✅ Password changed successfully!\n";
    echo "\n";
    echo "📝 Updated credentials:\n";
    echo "   Username: $username\n";
    echo "   Password: [hidden]\n";
    echo "\n";
    echo "💡 You can now log in with these credentials at http://localhost:8080\n";
    echo "\n";
    
} catch (Exception $e) {
    echo "❌ Error changing password: " . $e->getMessage() . "\n";
    echo "\nTroubleshooting:\n";
    echo "1. Check that database exists: ls -la database/truai.db\n";
    echo "2. Verify database is writable: chmod 644 database/truai.db\n";
    echo "3. Check PHP error logs: tail -f logs/error.log\n";
    exit(1);
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Password Change Complete\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";
