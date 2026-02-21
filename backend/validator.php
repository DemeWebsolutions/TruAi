<?php
/**
 * Input Validation Service
 *
 * Validates and sanitizes user input to prevent injection attacks
 *
 * @package TruAi
 * @version 1.0.0
 * @copyright My Deme, LLC © 2026
 */

class Validator {
    /**
     * Validate username
     *
     * Rules:
     * - 3-32 characters
     * - Alphanumeric, hyphens, underscores only
     * - No spaces
     */
    public static function username(string $username): array {
        $username = trim($username);
        $errors = [];

        if (strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters';
        }

        if (strlen($username) > 32) {
            $errors[] = 'Username must be less than 32 characters';
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            $errors[] = 'Username can only contain letters, numbers, hyphens, and underscores';
        }

        return [
            'valid' => empty($errors),
            'value' => $username,
            'errors' => $errors
        ];
    }

    /**
     * Validate password strength
     *
     * Rules:
     * - 8+ characters
     * - At least 1 uppercase letter
     * - At least 1 lowercase letter
     * - At least 1 number
     * - At least 1 special character
     */
    public static function password(string $password): array {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }

        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate email address
     */
    public static function email(string $email): array {
        $email = trim(strtolower($email));
        $valid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;

        return [
            'valid' => $valid,
            'value' => $email,
            'errors' => $valid ? [] : ['Invalid email address format']
        ];
    }

    /**
     * Sanitize file path (prevent directory traversal)
     */
    public static function sanitizeFilePath(string $path): string {
        // Remove directory traversal attempts
        $path = str_replace(['..', '~'], '', $path);

        // Allow only alphanumeric, forward slash, hyphen, underscore, dot
        $path = preg_replace('/[^a-zA-Z0-9\/_.-]/', '', $path);

        // Remove multiple slashes
        $path = preg_replace('#/+#', '/', $path);

        // Remove leading/trailing slashes
        return trim($path, '/');
    }

    /**
     * Sanitize HTML output (prevent XSS)
     */
    public static function sanitizeHTML(string $html): string {
        return htmlspecialchars($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Sanitize for SQL LIKE queries (prevent LIKE injection)
     */
    public static function sanitizeLike(string $input): string {
        return str_replace(['%', '_'], ['\\%', '\\_'], $input);
    }

    /**
     * Validate conversation ID (numeric only)
     */
    public static function conversationId($id): array {
        $valid = is_numeric($id) && $id > 0;

        return [
            'valid' => $valid,
            'value' => $valid ? (int)$id : null,
            'errors' => $valid ? [] : ['Invalid conversation ID']
        ];
    }

    /**
     * Validate JSON string
     */
    public static function json(string $json): array {
        json_decode($json);
        $valid = json_last_error() === JSON_ERROR_NONE;

        return [
            'valid' => $valid,
            'value' => $valid ? json_decode($json, true) : null,
            'errors' => $valid ? [] : ['Invalid JSON: ' . json_last_error_msg()]
        ];
    }
}
