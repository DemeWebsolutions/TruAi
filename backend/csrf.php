<?php
/**
 * CSRF Protection Service
 *
 * Generates and validates CSRF tokens for state-changing requests
 *
 * @package TruAi
 * @version 1.0.0
 * @copyright My Deme, LLC © 2026
 */

class CSRFProtection {
    // 32 bytes → 64 hexadecimal characters when encoded with bin2hex()
    private const TOKEN_LENGTH = 32;

    /**
     * Generate CSRF token (or return existing)
     */
    public static function generateToken(): string {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    /**
     * Validate CSRF token from request
     */
    public static function validateToken(string $token): bool {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            return false;
        }
        return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }

    /**
     * Regenerate CSRF token (call after sensitive operations)
     */
    public static function regenerateToken(): string {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    /**
     * Get token from request headers or POST data
     */
    public static function getTokenFromRequest(): ?string {
        // Check X-CSRF-Token header (preferred)
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return $_SERVER['HTTP_X_CSRF_TOKEN'];
        }

        // Check POST data (form fallback)
        if (isset($_POST['csrf_token'])) {
            return $_POST['csrf_token'];
        }

        // Check JSON body
        $json = json_decode(file_get_contents('php://input'), true);
        if (isset($json['csrf_token'])) {
            return $json['csrf_token'];
        }

        return null;
    }
}
