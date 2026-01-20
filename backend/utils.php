<?php
/**
 * TruAi Utility Functions
 * 
 * Common utility functions used across the application
 * 
 * @package TruAi
 * @version 1.0.0
 */

class TruAiUtils {
    /**
     * Generate a unique forensic ID for tracking
     * 
     * @param string $prefix Prefix for the ID (e.g., 'task', 'chat')
     * @param string $context Additional context to include in hash
     * @return string Forensic ID in format TRUAI_YYYYMMDD_HHMMSS_hash
     */
    public static function generateForensicId($prefix = '', $context = '') {
        $timestamp = date('Ymd_His');
        $randomData = $prefix . $context . microtime() . random_bytes(8);
        $hash = substr(hash('sha256', $randomData), 0, 12);
        return 'TRUAI_' . $timestamp . '_' . $hash;
    }
    
    /**
     * Generate a task ID
     * 
     * @return string Task ID in format task_YYYYMMDD_HHMMSS_hash
     */
    public static function generateTaskId() {
        $timestamp = date('Ymd_His');
        $hash = substr(hash('sha256', uniqid() . random_bytes(8)), 0, 8);
        return 'task_' . $timestamp . '_' . $hash;
    }
    
    /**
     * Generate an execution ID
     * 
     * @return string Execution ID in format exec_timestamp_hash
     */
    public static function generateExecutionId() {
        $timestamp = time();
        $hash = substr(hash('sha256', uniqid() . random_bytes(8)), 0, 6);
        return 'exec_' . $timestamp . '_' . $hash;
    }
    
    /**
     * Generate an artifact ID
     * 
     * @return string Artifact ID in format artifact_YYYYMMDD_HHMMSS
     */
    public static function generateArtifactId() {
        return 'artifact_' . date('Ymd_His');
    }
    
    /**
     * Sanitize user input
     * 
     * @param string $input User input to sanitize
     * @param int $maxLength Maximum allowed length
     * @return string Sanitized input
     */
    public static function sanitizeInput($input, $maxLength = 10000) {
        $input = trim($input);
        if (strlen($input) > $maxLength) {
            $input = substr($input, 0, $maxLength);
        }
        return $input;
    }
}
