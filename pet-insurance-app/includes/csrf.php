<?php
/**
 * CSRF (Cross-Site Request Forgery) protection.
 *
 * HOW IT WORKS:
 *   1. A unique token is generated per session.
 *   2. Every HTML form includes the token as a hidden field.
 *   3. Every POST handler validates the token before processing.
 *   4. If the token doesn't match, the request is rejected.
 */

/**
 * Generate (or retrieve) the CSRF token for the current session.
 * Creates a new token if one doesn't exist yet.
 *
 * @return string  The 64-character hex token.
 */
function generateCsrfToken(): string
{
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Validate a CSRF token against the session token.
 * Uses hash_equals() to prevent timing attacks.
 *
 * @param  string $token  The token submitted by the user.
 * @return bool           True if valid, false otherwise.
 */
function validateCsrfToken(string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Output a hidden form field containing the CSRF token.
 * Call this inside every <form> tag.
 */
function csrfField(): void
{
    $token = generateCsrfToken();
    echo '<input type="hidden" name="csrf_token" value="' . esc($token) . '">';
}

/**
 * Require a valid CSRF token on the current request.
 * Call this at the top of any POST handler.
 * Sends a 403 Forbidden response and exits if the token is invalid.
 */
function requireValidCsrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (!validateCsrfToken($token)) {
        http_response_code(419);
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';
        if (!headers_sent()) {
            header('Location: ' . $basePath . '/pages/419.php');
            exit;
        }
        die('Session expired. Please refresh the page and try again.');
    }
}