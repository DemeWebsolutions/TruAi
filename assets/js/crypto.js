/**
 * TruAi Encryption Utilities
 * 
 * Client-side encryption for secure authentication
 * Uses AES-256-GCM for credential encryption (not hybrid RSA+AES in this implementation)
 * 
 * @package TruAi
 * @version 1.0.0
 */

class TruAiCrypto {
    constructor() {
        this.publicKey = null;
        this.sessionKey = null;
    }

    /**
     * Initialize encryption - get server public key
     */
    async initialize() {
        try {
            const response = await fetch(window.TRUAI_CONFIG.API_BASE + '/auth/publickey');
            const data = await response.json();
            this.publicKey = data.public_key;
            this.sessionKey = this.generateSessionKey();
            return true;
        } catch (error) {
            console.error('Crypto initialization failed:', error);
            return false;
        }
    }

    /**
     * Generate random session key
     */
    generateSessionKey() {
        const array = new Uint8Array(32);
        window.crypto.getRandomValues(array);
        return Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
    }

    /**
     * Hash password with SHA-256
     */
    async hashPassword(password) {
        const encoder = new TextEncoder();
        const data = encoder.encode(password);
        const hashBuffer = await crypto.subtle.digest('SHA-256', data);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    }

    /**
     * Encrypt credentials for transmission
     * Uses AES-256-GCM encryption with generated session key
     */
    async encryptCredentials(username, password) {
        // Hash password first
        const passwordHash = await this.hashPassword(password);
        
        // Create payload
        const payload = JSON.stringify({
            username: username,
            password_hash: passwordHash,
            timestamp: Date.now(),
            session_key: this.sessionKey
        });

        // Encrypt with session key using AES-256-GCM
        const encrypted = await this.encryptAES(payload, this.sessionKey);
        
        return {
            encrypted_data: encrypted,
            session_id: this.generateSessionId()
        };
    }

    /**
     * AES-GCM encryption (256-bit)
     */
    async encryptAES(data, keyHex) {
        try {
            // Convert hex key to bytes (use full 64 hex chars = 256 bits = 32 bytes)
            const keyBytes = this.hexToBytes(keyHex.substring(0, 64));
            
            // Import key
            const key = await crypto.subtle.importKey(
                'raw',
                keyBytes,
                { name: 'AES-GCM' },
                false,
                ['encrypt']
            );

            // Generate IV
            const iv = window.crypto.getRandomValues(new Uint8Array(12));
            
            // Encrypt
            const encoder = new TextEncoder();
            const encodedData = encoder.encode(data);
            
            const encrypted = await crypto.subtle.encrypt(
                {
                    name: 'AES-GCM',
                    iv: iv
                },
                key,
                encodedData
            );

            // Combine IV + encrypted data
            const combined = new Uint8Array(iv.length + encrypted.byteLength);
            combined.set(iv, 0);
            combined.set(new Uint8Array(encrypted), iv.length);

            // Return as base64
            return this.arrayBufferToBase64(combined);
        } catch (error) {
            console.error('Encryption error:', error);
            // Fallback to base64 encoding (not secure, but prevents plaintext)
            return btoa(data);
        }
    }

    /**
     * Generate session ID
     */
    generateSessionId() {
        const array = new Uint8Array(16);
        window.crypto.getRandomValues(array);
        return Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
    }

    /**
     * Utility: Hex to bytes
     */
    hexToBytes(hex) {
        const bytes = new Uint8Array(hex.length / 2);
        for (let i = 0; i < hex.length; i += 2) {
            bytes[i / 2] = parseInt(hex.substr(i, 2), 16);
        }
        return bytes;
    }

    /**
     * Utility: ArrayBuffer to Base64
     */
    arrayBufferToBase64(buffer) {
        let binary = '';
        const bytes = new Uint8Array(buffer);
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary);
    }

    /**
     * Utility: Base64 to ArrayBuffer
     */
    base64ToArrayBuffer(base64) {
        const binary = atob(base64);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes.buffer;
    }

    /**
     * Secure random string generator
     */
    generateSecureRandom(length = 32) {
        const array = new Uint8Array(length);
        window.crypto.getRandomValues(array);
        return Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
    }

    /**
     * Verify encryption capability
     */
    static isSupported() {
        return !!(window.crypto && window.crypto.subtle);
    }
}

// Export for use in other scripts
window.TruAiCrypto = TruAiCrypto;
