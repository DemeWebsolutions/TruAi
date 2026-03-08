<?php
/**
 * Settings Wiring Test
 * 
 * Tests the complete settings system wiring
 * 
 * @package TruAi
 * @version 1.0.0
 */

require_once __DIR__ . '/backend/config.php';
require_once __DIR__ . '/backend/database.php';
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/settings_service.php';

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔧 SETTINGS WIRING TEST\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Test 1: Database connection
echo "1. Testing Database Connection...\n";
try {
    $db = Database::getInstance();
    echo "   ✅ Database connection successful\n";
} catch (Exception $e) {
    echo "   ❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Settings table exists
echo "\n2. Testing Settings Table...\n";
try {
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
    if (count($tables) > 0) {
        echo "   ✅ Settings table exists\n";
    } else {
        echo "   ❌ Settings table missing - creating...\n";
        // Table should be created by initializeSchema
        $db->getConnection()->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                category TEXT NOT NULL,
                key TEXT NOT NULL,
                value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, category, key)
            );
        ");
        echo "   ✅ Settings table created\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error checking table: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: SettingsService instantiation
echo "\n3. Testing SettingsService...\n";
try {
    $service = new SettingsService();
    echo "   ✅ SettingsService instantiated\n";
} catch (Exception $e) {
    echo "   ❌ SettingsService failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 4: Get default user ID
echo "\n4. Testing User Authentication...\n";
try {
    $users = $db->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
    if (count($users) > 0) {
        $userId = $users[0]['id'];
        echo "   ✅ Found admin user (ID: $userId)\n";
    } else {
        echo "   ⚠️  No admin user found - creating...\n";
        $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $db->execute(
            "INSERT INTO users (username, password_hash, role) VALUES (:username, :password, :role)",
            [':username' => 'admin', ':password' => $defaultPassword, ':role' => 'SUPER_ADMIN']
        );
        $userId = $db->lastInsertId();
        echo "   ✅ Created admin user (ID: $userId)\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error with user: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 5: Get settings (should return defaults)
echo "\n5. Testing Get Settings...\n";
try {
    $settings = $service->getSettings($userId);
    if (isset($settings['editor']) && isset($settings['ai']) && isset($settings['appearance'])) {
        echo "   ✅ Get settings successful\n";
        echo "      Editor fontSize: " . $settings['editor']['fontSize'] . "\n";
        echo "      AI model: " . $settings['ai']['model'] . "\n";
        echo "      Theme: " . $settings['appearance']['theme'] . "\n";
    } else {
        echo "   ❌ Get settings returned incomplete data\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ❌ Get settings failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 6: Save settings
echo "\n6. Testing Save Settings...\n";
try {
    $testSettings = [
        'editor' => [
            'fontSize' => 16,
            'fontFamily' => 'Menlo',
            'tabSize' => 2,
            'wordWrap' => false,
            'minimapEnabled' => false
        ],
        'ai' => [
            'apiKey' => 'test-key-123',
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.5
        ]
    ];
    
    $service->saveSettings($userId, $testSettings);
    echo "   ✅ Save settings successful\n";
} catch (Exception $e) {
    echo "   ❌ Save settings failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 7: Verify saved settings
echo "\n7. Testing Saved Settings Retrieval...\n";
try {
    $savedSettings = $service->getSettings($userId);
    if ($savedSettings['editor']['fontSize'] == 16 && 
        $savedSettings['editor']['fontFamily'] == 'Menlo' &&
        $savedSettings['ai']['model'] == 'gpt-3.5-turbo') {
        echo "   ✅ Saved settings retrieved correctly\n";
        echo "      Editor fontSize: " . $savedSettings['editor']['fontSize'] . " (expected: 16)\n";
        echo "      Editor fontFamily: " . $savedSettings['editor']['fontFamily'] . " (expected: Menlo)\n";
        echo "      AI model: " . $savedSettings['ai']['model'] . " (expected: gpt-3.5-turbo)\n";
    } else {
        echo "   ❌ Saved settings don't match\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ❌ Retrieve saved settings failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 8: Reset settings
echo "\n8. Testing Reset Settings...\n";
try {
    $service->resetSettings($userId);
    $resetSettings = $service->getSettings($userId);
    if ($resetSettings['editor']['fontSize'] == 14 && 
        $resetSettings['editor']['fontFamily'] == 'Monaco') {
        echo "   ✅ Reset settings successful (back to defaults)\n";
    } else {
        echo "   ❌ Reset settings didn't restore defaults\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ❌ Reset settings failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 9: Router handler check
echo "\n9. Testing Router Integration...\n";
try {
    require_once __DIR__ . '/backend/router.php';
    $router = new Router();
    echo "   ✅ Router instantiated\n";
    
    // Check if routes are registered
    $reflection = new ReflectionClass($router);
    $routesProperty = $reflection->getProperty('routes');
    $routesProperty->setAccessible(true);
    $routes = $routesProperty->getValue($router);
    
    if (isset($routes['GET']['/api/v1/settings']) && 
        isset($routes['POST']['/api/v1/settings'])) {
        echo "   ✅ Settings routes registered\n";
    } else {
        echo "   ❌ Settings routes not registered\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ❌ Router test failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 10: File structure check
echo "\n10. Testing File Structure...\n";
$requiredFiles = [
    'backend/settings_service.php',
    'backend/router.php',
    'assets/js/api.js',
    'assets/js/dashboard.js'
];

$allExist = true;
foreach ($requiredFiles as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "   ✅ $file exists\n";
    } else {
        echo "   ❌ $file missing\n";
        $allExist = false;
    }
}

if (!$allExist) {
    exit(1);
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ ALL TESTS PASSED\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n📋 Summary:\n";
echo "   ✅ Database connection\n";
echo "   ✅ Settings table\n";
echo "   ✅ SettingsService class\n";
echo "   ✅ Get/Save/Reset operations\n";
echo "   ✅ Router integration\n";
echo "   ✅ File structure\n";
echo "\n🎯 Settings system is fully wired and functional!\n\n";
