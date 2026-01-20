<?php
/**
 * TruAi AI Exception Classes
 * 
 * Custom exceptions for AI service error handling
 * 
 * @package TruAi
 * @version 1.0.0
 */

/**
 * Base exception for all AI-related errors
 */
class AIException extends Exception {
    protected $errorCode;
    protected $retryable = false;
    
    public function __construct($message, $errorCode = null, $retryable = false, $previous = null) {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
        $this->retryable = $retryable;
    }
    
    public function getErrorCode() {
        return $this->errorCode;
    }
    
    public function isRetryable() {
        return $this->retryable;
    }
}

/**
 * Exception for AI service configuration errors
 * Not retryable - requires user action to fix
 */
class AIConfigurationException extends AIException {
    public function __construct($message, $errorCode = null, $previous = null) {
        parent::__construct($message, $errorCode, false, $previous);
    }
}

/**
 * Exception for AI service rate limit errors
 * Retryable after delay
 */
class AIRateLimitException extends AIException {
    private $retryAfter;
    
    public function __construct($message, $retryAfter = null, $errorCode = null, $previous = null) {
        parent::__construct($message, $errorCode, true, $previous);
        $this->retryAfter = $retryAfter;
    }
    
    public function getRetryAfter() {
        return $this->retryAfter;
    }
}

/**
 * Exception for AI service timeout errors
 * Retryable
 */
class AITimeoutException extends AIException {
    public function __construct($message, $errorCode = null, $previous = null) {
        parent::__construct($message, $errorCode, true, $previous);
    }
}

/**
 * Exception for transient network/API errors
 * Retryable
 */
class AITransientException extends AIException {
    public function __construct($message, $errorCode = null, $previous = null) {
        parent::__construct($message, $errorCode, true, $previous);
    }
}

/**
 * Exception for invalid API responses
 * Not retryable
 */
class AIResponseException extends AIException {
    public function __construct($message, $errorCode = null, $previous = null) {
        parent::__construct($message, $errorCode, false, $previous);
    }
}
