#!/usr/bin/env php
<?php
/**
 * TruAi Test Runner
 *
 * Executes unit tests for core functionality
 *
 * Usage: php tests/run_tests.php
 *
 * @package TruAi
 * @version 1.0.0
 * @copyright My Deme, LLC © 2026
 */

// Suppress session warnings when running CLI
if (PHP_SAPI === 'cli') {
    ini_set('session.use_cookies', '0');
    ini_set('session.use_only_cookies', '0');
}

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/database.php';
require_once __DIR__ . '/../backend/auth.php';
require_once __DIR__ . '/../backend/validator.php';
require_once __DIR__ . '/../backend/csrf.php';

class TestRunner {
    private $passed = 0;
    private $failed = 0;

    public function assert(bool $condition, string $message): void {
        if ($condition) {
            echo "  [PASS] $message\n";
            $this->passed++;
        } else {
            echo "  [FAIL] $message\n";
            $this->failed++;
        }
    }

    public function assertEqual($expected, $actual, string $message): void {
        $this->assert($expected === $actual, $message . " (expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")");
    }

    public function testValidator(): void {
        echo "\n=== Validator Tests ===\n";

        // Valid username
        $result = Validator::username('admin');
        $this->assert($result['valid'] === true, 'Valid username accepted');
        $this->assertEqual('admin', $result['value'], 'Username value preserved');

        // Short username
        $result = Validator::username('ab');
        $this->assert($result['valid'] === false, 'Short username rejected');
        $this->assert(count($result['errors']) > 0, 'Error message provided for short username');

        // Invalid characters
        $result = Validator::username('admin@test');
        $this->assert($result['valid'] === false, 'Username with @ symbol rejected');

        // Long username
        $result = Validator::username(str_repeat('a', 33));
        $this->assert($result['valid'] === false, 'Username over 32 chars rejected');

        // Valid password
        $result = Validator::password('Password123!');
        $this->assert($result['valid'] === true, 'Strong password accepted');

        // Weak passwords
        $result = Validator::password('weak');
        $this->assert($result['valid'] === false, 'Weak password rejected');

        $result = Validator::password('NoNumbersOrSymbols');
        $this->assert($result['valid'] === false, 'Password without numbers rejected');

        $result = Validator::password('nonumbers123!');
        $this->assert($result['valid'] === false, 'Password without uppercase rejected');

        // File path sanitization
        $clean = Validator::sanitizeFilePath('../../../etc/passwd');
        $this->assert(strpos($clean, '..') === false, 'Directory traversal removed');

        $clean = Validator::sanitizeFilePath('path/to/file.txt');
        $this->assertEqual('path/to/file.txt', $clean, 'Valid path preserved');

        // HTML sanitization
        $clean = Validator::sanitizeHTML('<script>alert("xss")</script>');
        $this->assert(strpos($clean, '<script>') === false, 'Script tags escaped');
        $this->assert(strpos($clean, '&lt;') !== false, 'HTML entities used');
    }

    public function testCSRF(): void {
        echo "\n=== CSRF Protection Tests ===\n";

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Clear any existing token
        unset($_SESSION[CSRF_TOKEN_NAME]);

        // Generate token
        $token1 = CSRFProtection::generateToken();
        $this->assert(!empty($token1), 'CSRF token generated');
        $this->assert(strlen($token1) === 64, 'Token is 64 characters (32 bytes hex)');

        // Same token returned on subsequent calls
        $token2 = CSRFProtection::generateToken();
        $this->assertEqual($token1, $token2, 'Same token returned without regeneration');

        // Validate correct token
        $valid = CSRFProtection::validateToken($token1);
        $this->assert($valid === true, 'Correct token validates');

        // Validate incorrect token
        $valid = CSRFProtection::validateToken('invalid_token');
        $this->assert($valid === false, 'Incorrect token rejected');

        // Regenerate token
        $token3 = CSRFProtection::regenerateToken();
        $this->assert($token3 !== $token1, 'New token generated on regeneration');

        // Old token no longer valid
        $valid = CSRFProtection::validateToken($token1);
        $this->assert($valid === false, 'Old token invalidated after regeneration');

        // New token valid
        $valid = CSRFProtection::validateToken($token3);
        $this->assert($valid === true, 'New token validates');
    }

    public function testDatabase(): void {
        echo "\n=== Database Tests ===\n";

        $db = Database::getInstance();

        // Check database file exists
        $this->assert(file_exists(DB_PATH), 'Database file exists');
        $this->assert(is_writable(DB_PATH), 'Database file is writable');

        // Check required tables exist
        $tables = [
            'users', 'audit_logs', 'recovery_attempts', 'master_recovery_keys',
            'biometric_logins', 'conversations', 'messages', 'settings'
        ];

        foreach ($tables as $table) {
            $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name=:table", [':table' => $table]);
            $this->assert(!empty($result), "Table '$table' exists");
        }

        // Test basic CRUD - use audit_logs which allows NULL user_id
        $testData = 'test_' . time();
        $db->execute(
            "INSERT INTO audit_logs (user_id, event, actor, details) VALUES (NULL, :event, :actor, NULL)",
            [':event' => $testData, ':actor' => 'test_runner']
        );

        $result = $db->query(
            "SELECT actor FROM audit_logs WHERE event = :event",
            [':event' => $testData]
        );

        $this->assert(!empty($result), 'INSERT and SELECT work');
        $this->assertEqual('test_runner', $result[0]['actor'], 'Data retrieved correctly');

        // Cleanup
        $db->execute("DELETE FROM audit_logs WHERE event = :event", [':event' => $testData]);
    }

    public function testEncryption(): void {
        echo "\n=== Encryption Tests ===\n";

        // Check encryption keys exist
        $keyDir = DATABASE_PATH . '/keys';
        $this->assert(file_exists($keyDir . '/private_key.pem'), 'Private key exists');
        $this->assert(file_exists($keyDir . '/public_key.pem'), 'Public key exists');

        // Test encryption service
        require_once __DIR__ . '/../backend/encryption.php';
        $enc = new EncryptionService();

        $publicKey = $enc->getPublicKey();
        $this->assert(!empty($publicKey), 'Public key retrieved');
        $this->assert(strlen($publicKey) > 100, 'Public key has reasonable length');

        // Test ROMA trust (basic check)
        require_once __DIR__ . '/../backend/roma_trust.php';
        $status = RomaTrust::getStatus($enc);

        $this->assert(isset($status['roma']), 'ROMA status includes "roma" key');
        $this->assert(isset($status['trust_state']), 'ROMA status includes "trust_state"');
        $this->assert(in_array($status['trust_state'], ['VERIFIED', 'UNVERIFIED', 'BLOCKED']), 'Valid trust state');
    }

    public function testAuth(): void {
        echo "\n=== Authentication Tests ===\n";

        $db = Database::getInstance();
        $testPassword = 'TestPass123!';
        $hash = password_hash($testPassword, PASSWORD_ARGON2ID, ARGON2ID_OPTIONS);

        $db->execute(
            "INSERT OR REPLACE INTO users (id, username, password_hash, role) VALUES (999, 'testuser', :hash, 'user')",
            [':hash' => $hash]
        );

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $auth = new Auth();

        // Test valid login
        session_unset();
        $result = $auth->login('testuser', $testPassword);
        $this->assert($result === true, 'Valid login successful');
        $this->assert(isset($_SESSION['user_id']), 'Session user_id set after login');
        $this->assert(isset($_SESSION['login_time']), 'Session login_time set');

        // Test invalid password
        session_unset();
        $result = $auth->login('testuser', 'WrongPassword123!');
        $this->assert($result === false, 'Invalid password rejected');

        // Test non-existent user
        $result = $auth->login('nonexistent', $testPassword);
        $this->assert($result === false, 'Non-existent user rejected');

        // Test input validation integration
        $result = $auth->login('ab', $testPassword); // Too short
        $this->assert($result === false, 'Short username rejected by auth');

        $result = $auth->login('admin@test', $testPassword); // Invalid chars
        $this->assert($result === false, 'Invalid characters rejected by auth');

        // Cleanup
        $db->execute("DELETE FROM users WHERE id = 999");
    }

    public function run(): void {
        echo "+===========================================+\n";
        echo "|       TruAi Unit Test Suite              |\n";
        echo "+===========================================+\n";

        $this->testValidator();
        $this->testCSRF();
        $this->testDatabase();
        $this->testEncryption();
        $this->testAuth();

        echo "\n+===========================================+\n";
        echo "|            Test Summary                  |\n";
        echo "+===========================================+\n";
        echo "\n";
        printf("  Passed: %d\n", $this->passed);
        printf("  Failed: %d\n", $this->failed);
        printf("  Total:  %d\n", $this->passed + $this->failed);
        echo "\n";

        if ($this->failed > 0) {
            echo "[FAIL] Tests failed\n";
            exit(1);
        } else {
            echo "[PASS] All tests passed\n";
            exit(0);
        }
    }
}

// Run tests
$runner = new TestRunner();
$runner->run();
