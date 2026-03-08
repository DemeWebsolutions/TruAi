<?php
/**
 * ROMA Trust Validation
 *
 * Validates runtime trust state per ROMA v2 Contract (§4.2).
 * ROMA must never report "active" or "protected" unless all checks pass.
 *
 * @package TruAi
 * @version 1.0.0
 */

class RomaTrust {
    private static $suspicionWindow = 300;   // 5 minutes
    private static $suspicionThreshold = 5;
    private static $suspicionScores = [];   // key => [count, windowStart]
    /**
     * Validate ROMA trust state. Returns ['verified' => bool, 'reason' => string, 'checks' => array].
     */
    public static function validate($encryption, $workspaceRoot = null) {
        $checks = [];
        $reason = '';

        // 1. Encryption keys present and valid
        try {
            $pubKey = $encryption->getPublicKey();
            $checks['encryption_keys'] = !empty($pubKey) && strlen($pubKey) > 100;
        } catch (Exception $e) {
            $checks['encryption_keys'] = false;
        }
        if (!$checks['encryption_keys']) {
            $reason = 'Encryption keys not ready';
            return self::result(false, $reason, $checks);
        }

        // 2. Session started (PHP session exists)
        $checks['session'] = session_status() === PHP_SESSION_ACTIVE;
        if (!$checks['session']) {
            $reason = 'Session not initialized';
            return self::result(false, $reason, $checks);
        }

        // 3. Workspace mounted and readable
        $root = $workspaceRoot ?? (defined('WORKSPACE_ROOT') && WORKSPACE_ROOT ? WORKSPACE_ROOT : (__DIR__ . '/..'));
        $root = realpath($root);
        $checks['workspace'] = $root && is_dir($root) && is_readable($root);
        if (!$checks['workspace']) {
            $reason = 'Workspace not available';
            return self::result(false, $reason, $checks);
        }

        // 4. Workspace writable (for file write operations)
        $checks['workspace_writable'] = is_writable($root);
        if (!$checks['workspace_writable']) {
            $reason = 'Workspace not writable';
            return self::result(false, $reason, $checks);
        }

        return self::result(true, '', $checks);
    }

    /**
     * Get ROMA status for API response. Returns verified state and appropriate monitor value.
     */
    public static function getStatus($encryption, $workspaceRoot = null) {
        $validation = self::validate($encryption, $workspaceRoot);
        $verified = $validation['verified'];

        $suspicionBlocked = self::isSuspicionBlocked();
        $effectiveVerified = $verified && !$suspicionBlocked;
        return [
            'roma' => $effectiveVerified,
            'portal_protected' => $effectiveVerified,
            'monitor' => $effectiveVerified ? 'active' : ($suspicionBlocked ? 'blocked' : 'unverified'),
            'encryption' => 'RSA-2048 + AES-256-GCM',
            'local_only' => true,
            'timestamp' => time(),
            'trust_state' => $suspicionBlocked ? 'BLOCKED' : ($verified ? 'VERIFIED' : 'UNVERIFIED'),
            'reason' => $validation['reason'] ?: ($suspicionBlocked ? 'suspicion_threshold' : null),
            'checks' => $validation['checks'] ?? [],
            'suspicion_blocked' => $suspicionBlocked
        ];
    }

    /**
     * Increment suspicion score for a key (default: client IP).
     * Called on failed auth, validation failure, decryption failure.
     */
    public static function incrementSuspicion($key = null) {
        $key = $key ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $now = time();
        if (!isset(self::$suspicionScores[$key])) {
            self::$suspicionScores[$key] = ['count' => 0, 'windowStart' => $now];
        }
        $data = &self::$suspicionScores[$key];
        if ($now - $data['windowStart'] > self::$suspicionWindow) {
            $data = ['count' => 0, 'windowStart' => $now];
        }
        $data['count']++;
    }

    /**
     * Get current suspicion score for a key.
     */
    public static function getSuspicionScore($key = null) {
        $key = $key ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!isset(self::$suspicionScores[$key])) return 0;
        $data = self::$suspicionScores[$key];
        if (time() - $data['windowStart'] > self::$suspicionWindow) return 0;
        return $data['count'];
    }

    /**
     * Check if suspicion threshold exceeded (block/throttle).
     */
    public static function isSuspicionBlocked($key = null) {
        return self::getSuspicionScore($key) >= self::$suspicionThreshold;
    }

    /**
     * Emit security event to audit log.
     */
    public static function emitSecurityEvent($event, $details = []) {
        try {
            $db = Database::getInstance();
            $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
            $db->execute(
                "INSERT INTO audit_logs (user_id, event, actor, details) VALUES (:user_id, :event, 'ROMA', :details)",
                [
                    ':user_id' => $userId,
                    ':event' => $event,
                    ':details' => json_encode($details)
                ]
            );
        } catch (Exception $e) {
            error_log('ROMA security event failed: ' . $e->getMessage());
        }
    }

    private static function result($verified, $reason, $checks) {
        return [
            'verified' => $verified,
            'reason' => $reason,
            'checks' => $checks
        ];
    }
}
