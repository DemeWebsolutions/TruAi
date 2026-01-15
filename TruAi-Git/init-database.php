#!/usr/bin/env php
<?php
/**
 * TruAi Database Initialization Script
 * 
 * Run this script to initialize the database on fresh repository clones
 * 
 * Usage: php init-database.php
 * 
 * @package TruAi
 * @version 1.0.0
 */

// Get the directory where this script is located
$scriptDir = __DIR__;

// Change to script directory
chdir($scriptDir);

// Load configuration
require_once $scriptDir . '/backend/config.php';
require_once $scriptDir . '/backend/database.php';

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🗄️  TruAi Database Initialization\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";

// Ensure database directory exists
if (!is_dir(DATABASE_PATH)) {
    echo "📁 Creating database directory...\n";
    mkdir(DATABASE_PATH, 0755, true);
    echo "✅ Database directory created: " . DATABASE_PATH . "\n\n";
}

// Check if database already exists
if (file_exists(DB_PATH)) {
    echo "ℹ️  Database already exists: " . DB_PATH . "\n";
    echo "   Checking for admin user...\n";
    
    try {
        $db = Database::getInstance();
        $users = $db->query("SELECT username, role FROM users WHERE username = 'admin'");
        
        if (!empty($users)) {
            echo "✅ Admin user found: " . $users[0]['username'] . " (" . $users[0]['role'] . ")\n";
            echo "\n✅ Database is ready!\n";
        } else {
            echo "⚠️  Admin user not found. Database may need re-initialization.\n";
        }
    } catch (Exception $e) {
        echo "❌ Error checking database: " . $e->getMessage() . "\n";
        echo "   Attempting to re-initialize...\n\n";
        unlink(DB_PATH);
    }
}

// Initialize database
if (!file_exists(DB_PATH)) {
    echo "🔧 Initializing database...\n";
    
    try {
        $db = Database::getInstance();
        echo "✅ Database created: " . DB_PATH . "\n";
        
        // Verify admin user was created
        $users = $db->query("SELECT username, role FROM users WHERE username = 'admin'");
        if (!empty($users)) {
            echo "✅ Admin user created:\n";
            echo "   Username: admin\n";
            echo "   Password: admin123\n";
            echo "   Role: " . $users[0]['role'] . "\n";
        } else {
            echo "⚠️  Warning: Admin user not found after initialization\n";
        }
        
        echo "\n✅ Database initialization complete!\n";
    } catch (Exception $e) {
        echo "❌ Database initialization failed: " . $e->getMessage() . "\n";
        echo "\nTroubleshooting:\n";
        echo "1. Check that the database directory is writable\n";
        echo "2. Verify PHP has SQLite support: php -m | grep sqlite\n";
        echo "3. Check file permissions on the database directory\n";
        exit(1);
    }
} else {
    echo "\n✅ Database is ready!\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Initialization Complete\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";
echo "📝 Next Steps:\n";
echo "   1. Start the server: ./start.sh\n";
echo "   2. Open: http://localhost:8080\n";
echo "   3. Login with: admin / admin123\n";
echo "\n";
