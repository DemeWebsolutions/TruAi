<?php
/**
 * TruAi Encryption Service
 * 
 * Server-side encryption/decryption utilities
 * 
 * @package TruAi
 * @version 1.0.0
 */

class EncryptionService {
    private $privateKey;
    private $publicKey;
    private $sessionKeys = [];

    public function __construct() {
        $this->initializeKeys();
    }

    /**
     * Initialize RSA key pair
     */
    private function initializeKeys() {
        $keyFile = DATABASE_PATH . '/encryption_keys.json';
        
        if (file_exists($keyFile)) {
            $keys = json_decode(file_get_contents($keyFile), true);
            $this->privateKey = $keys['private'];
            $this->publicKey = $keys['public'];
        } else {
            // Generate new key pair
            $config = [
                "digest_alg" => "sha512",
                "private_key_bits" => 2048,
                "private_key_type" => OPENSSL_KEYTYPE_RSA,
            ];
            
            $res = openssl_pkey_new($config);
            openssl_pkey_export($res, $this->privateKey);
            
            $pubKey = openssl_pkey_get_details($res);
            $this->publicKey = $pubKey['key'];
            
            // Save keys
            file_put_contents($keyFile, json_encode([
                'private' => $this->privateKey,
                'public' => $this->publicKey,
                'created' => date('Y-m-d H:i:s')
            ]));
            
            chmod($keyFile, 0600);
        }
    }

    /**
     * Get public key for client
     */
    public function getPublicKey() {
        return base64_encode($this->publicKey);
    }

    /**
     * Decrypt login credentials
     */
    public function decryptCredentials($encryptedData, $sessionId) {
        try {
            // Decode base64
            $encrypted = base64_decode($encryptedData);
            
            // Extract IV (first 12 bytes) and ciphertext
            $iv = substr($encrypted, 0, 12);
            $ciphertext = substr($encrypted, 12);
            
            // Get session key (in production, this would be securely exchanged)
            $sessionKey = $this->getSessionKey($sessionId);
            
            if (!$sessionKey) {
                // Fallback: try to decode as base64
                $decoded = base64_decode($encryptedData);
                if ($decoded) {
                    $payload = json_decode($decoded, true);
                    if ($payload && isset($payload['username']) && isset($payload['password_hash'])) {
                        return $payload;
                    }
                }
                throw new Exception('Invalid session key');
            }
            
            // Decrypt using AES-256-GCM
            $key = hex2bin(substr($sessionKey, 0, 64)); // Use 256 bits
            
            $decrypted = openssl_decrypt(
                $ciphertext,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag = ''
            );
            
            if ($decrypted === false) {
                // Fallback decryption
                $decrypted = $this->fallbackDecrypt($encryptedData);
            }
            
            $payload = json_decode($decrypted, true);
            
            if (!$payload) {
                throw new Exception('Invalid payload');
            }
            
            // Verify timestamp (prevent replay attacks)
            if (isset($payload['timestamp'])) {
                $age = time() * 1000 - $payload['timestamp'];
                if ($age > 60000) { // 60 seconds
                    throw new Exception('Credentials expired');
                }
            }
            
            return $payload;
            
        } catch (Exception $e) {
            error_log('Decryption error: ' . $e->getMessage());
            throw new Exception('Decryption failed');
        }
    }

    /**
     * Fallback decryption for compatibility
     */
    private function fallbackDecrypt($data) {
        $decoded = base64_decode($data);
        if ($decoded) {
            return $decoded;
        }
        return $data;
    }

    /**
     * Store session key temporarily
     */
    public function storeSessionKey($sessionId, $key) {
        $this->sessionKeys[$sessionId] = [
            'key' => $key,
            'created' => time()
        ];
        
        // Clean old keys
        $this->cleanOldKeys();
    }

    /**
     * Get session key
     */
    private function getSessionKey($sessionId) {
        if (isset($this->sessionKeys[$sessionId])) {
            return $this->sessionKeys[$sessionId]['key'];
        }
        
        // Generate temporary key for this session
        $key = bin2hex(random_bytes(32));
        $this->storeSessionKey($sessionId, $key);
        return $key;
    }

    /**
     * Clean expired session keys
     */
    private function cleanOldKeys() {
        $now = time();
        foreach ($this->sessionKeys as $id => $data) {
            if ($now - $data['created'] > 300) { // 5 minutes
                unset($this->sessionKeys[$id]);
            }
        }
    }

    /**
     * Enhanced password hashing with salt
     */
    public static function hashPassword($password, $salt = null) {
        if ($salt === null) {
            $salt = bin2hex(random_bytes(16));
        }
        
        // Use PBKDF2 with high iteration count
        $hash = hash_pbkdf2('sha256', $password, $salt, 100000, 64);
        
        return [
            'hash' => $hash,
            'salt' => $salt
        ];
    }

    /**
     * Verify password against stored hash
     */
    public static function verifyPassword($password, $storedHash, $salt) {
        $computed = self::hashPassword($password, $salt);
        return hash_equals($storedHash, $computed['hash']);
    }

    /**
     * Encrypt data for storage
     */
    public function encryptForStorage($data, $userKey = null) {
        $key = $userKey ?? hash('sha256', TRUAI_API_KEY . session_id());
        $iv = random_bytes(16);
        
        $encrypted = openssl_encrypt(
            $data,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt data from storage
     */
    public function decryptFromStorage($encryptedData, $userKey = null) {
        $key = $userKey ?? hash('sha256', TRUAI_API_KEY . session_id());
        $data = base64_decode($encryptedData);
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt(
            $encrypted,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
    }
}
