<?php
/**
 * CSRF Protection
 * Library Management System
 */

/**
 * Generate CSRF token
 * @return string
 */
function generateCsrfToken(): string {
    if (!isset($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }
    
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_tokens'][$token] = time() + CSRF_TTL;
    
    return $token;
}

/**
 * Get CSRF token field
 * @return string
 */
function csrfField(): string {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Validate CSRF token
 * @param string $token
 * @return bool
 */
function validateCsrfToken(string $token): bool {
    if (!isset($_SESSION['csrf_tokens']) || !isset($_SESSION['csrf_tokens'][$token])) {
        return false;
    }
    
    // Check if token has expired
    if ($_SESSION['csrf_tokens'][$token] < time()) {
        unset($_SESSION['csrf_tokens'][$token]);
        return false;
    }
    
    return true;
}

/**
 * Verify CSRF token from request
 * @param string|null $token
 * @return bool
 */
function verifyCsrfToken(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }
    
    if ($token === null) {
        return false;
    }
    
    $valid = validateCsrfToken($token);
    
    // Clean up expired tokens
    if ($valid) {
        unset($_SESSION['csrf_tokens'][$token]);
    }
    
    return $valid;
}

/**
 * Require valid CSRF token
 * @param string|null $token
 */
function requireCsrfToken(?string $token = null): void {
    if (!verifyCsrfToken($token)) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
}

/**
 * Clean expired CSRF tokens
 */
function cleanExpiredCsrfTokens(): void {
    if (isset($_SESSION['csrf_tokens'])) {
        $now = time();
        foreach ($_SESSION['csrf_tokens'] as $token => $expiry) {
            if ($expiry < $now) {
                unset($_SESSION['csrf_tokens'][$token]);
            }
        }
    }
}