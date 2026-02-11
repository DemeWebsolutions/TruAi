<?php
/**
 * ROMA Internal Trust Channel (ITC v1)
 *
 * Authenticated, encrypted, revocable communication between My Deme internal systems.
 * Per ROMA_ITC_V1.md.
 *
 * @package TruAi
 * @version 1.0.0
 */

class RomaItc {
    private static $sessionKeys = [];  // session_id => ['key' => hex, 'system_id' => id]
    private static $sessionTtl = 3600; // 1 hour

    /**
     * Register a system in the trust registry (admin bootstrap).
     */
    public static function registerSystem($systemId, $publicKeyPem) {
        $db = Database::getInstance();
        $systemId = trim($systemId);
        $publicKeyPem = trim($publicKeyPem);
        if (empty($systemId) || empty($publicKeyPem)) {
            throw new Exception('system_id and public_key required');
        }
        if (!preg_match('/^[a-z0-9._-]+\.(local|cloud)$/i', $systemId)) {
            throw new Exception('system_id must match pattern: name.local or name.cloud');
        }
        $key = openssl_pkey_get_public($publicKeyPem);
        if (!$key) {
            throw new Exception('Invalid public key');
        }
        $db->execute(
            "INSERT OR REPLACE INTO itc_systems (system_id, public_key, trust_status, revoked_at, updated_at) VALUES (:sid, :pk, 'active', NULL, datetime('now'))",
            [':sid' => $systemId, ':pk' => $publicKeyPem]
        );
        return true;
    }

    /**
     * Revoke system trust.
     */
    public static function revokeSystem($systemId) {
        $db = Database::getInstance();
        $db->execute(
            "UPDATE itc_systems SET trust_status = 'revoked', revoked_at = datetime('now'), updated_at = datetime('now') WHERE system_id = :sid",
            [':sid' => $systemId]
        );
        foreach (self::$sessionKeys as $sid => $data) {
            if ($data['system_id'] === $systemId) {
                unset(self::$sessionKeys[$sid]);
            }
        }
        return true;
    }

    /**
     * Perform handshake: verify system identity, issue session.
     * Returns ['session_id' => ..., 'encrypted_session_key' => base64] or throws.
     */
    public static function handshake($systemId, $nonce, $timestamp, $signatureB64) {
        $db = Database::getInstance();
        $systemId = trim($systemId);
        $row = $db->query("SELECT * FROM itc_systems WHERE system_id = :sid AND trust_status = 'active'", [':sid' => $systemId]);
        if (empty($row)) {
            throw new Exception('System not registered or revoked');
        }
        $pubKey = openssl_pkey_get_public($row[0]['public_key']);
        if (!$pubKey) {
            throw new Exception('Invalid system public key');
        }
        $payload = $systemId . '|' . $nonce . '|' . $timestamp;
        $signature = base64_decode($signatureB64);
        if ($signature === false || !openssl_verify($payload, $signature, $pubKey, OPENSSL_ALGO_SHA256)) {
            throw new Exception('Signature verification failed');
        }
        $age = abs(time() - (int) $timestamp);
        if ($age > 60) {
            throw new Exception('Timestamp too old');
        }
        $sessionKey = random_bytes(32);
        $sessionId = bin2hex(random_bytes(16));
        $encryptedWithPub = '';
        if (!openssl_public_encrypt($sessionKey, $encryptedWithPub, $pubKey, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new Exception('Key encryption failed');
        }
        $expiresAt = date('Y-m-d H:i:s', time() + self::$sessionTtl);
        $db->execute(
            "INSERT INTO itc_sessions (session_id, system_id, expires_at) VALUES (:sid, :sys, :exp)",
            [':sid' => $sessionId, ':sys' => $systemId, ':exp' => $expiresAt]
        );
        self::$sessionKeys[$sessionId] = ['key' => bin2hex($sessionKey), 'system_id' => $systemId, 'created' => time()];
        return [
            'session_id' => $sessionId,
            'encrypted_session_key' => base64_encode($encryptedWithPub),
            'expires_in' => self::$sessionTtl
        ];
    }

    /**
     * Verify session and return system_id. Throws if invalid.
     */
    public static function verifySession($sessionId) {
        self::cleanExpiredSessions();
        if (isset(self::$sessionKeys[$sessionId])) {
            return self::$sessionKeys[$sessionId]['system_id'];
        }
        $db = Database::getInstance();
        $row = $db->query("SELECT * FROM itc_sessions WHERE session_id = :sid AND expires_at > datetime('now')", [':sid' => $sessionId]);
        if (empty($row)) {
            throw new Exception('Session expired or invalid');
        }
        $sysRow = $db->query("SELECT * FROM itc_systems WHERE system_id = :sid AND trust_status = 'active'", [':sid' => $row[0]['system_id']]);
        if (empty($sysRow)) {
            throw new Exception('System revoked');
        }
        return $row[0]['system_id'];
    }

    /**
     * Get session key for decryption (internal use).
     */
    public static function getSessionKey($sessionId) {
        if (isset(self::$sessionKeys[$sessionId])) {
            return hex2bin(self::$sessionKeys[$sessionId]['key']);
        }
        return null;
    }

    /**
     * Store session key (after client completes key exchange).
     */
    public static function storeSessionKey($sessionId, $keyHex, $systemId) {
        self::$sessionKeys[$sessionId] = ['key' => $keyHex, 'system_id' => $systemId];
    }

    private static function cleanExpiredSessions() {
        $db = Database::getInstance();
        $db->getConnection()->exec("DELETE FROM itc_sessions WHERE expires_at < datetime('now')");
        $now = time();
        foreach (self::$sessionKeys as $sid => $data) {
            $row = $db->query("SELECT expires_at FROM itc_sessions WHERE session_id = :sid", [':sid' => $sid]);
            if (empty($row) || strtotime($row[0]['expires_at']) < $now) {
                unset(self::$sessionKeys[$sid]);
            }
        }
    }

    /**
     * List registered systems (for admin).
     */
    public static function listSystems() {
        $db = Database::getInstance();
        return $db->query("SELECT system_id, trust_status, created_at, revoked_at FROM itc_systems ORDER BY system_id");
    }
}
