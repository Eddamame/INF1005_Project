<?php
/**
 * Auth middleware functions.
 * Include this file in any page that requires authentication.
 *
 * Usage:
 *   require_once __DIR__ . '/../../includes/session.php';
 *   require_once __DIR__ . '/../../auth/middleware.php';
 *   requireLogin();
 */

/**
 * Redirect to login if the user is not authenticated.
 */
function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/auth/login.php');
        exit;
    }
}

function isLoggedIn(): void {
    if (!empty($_SESSION['user_id'])) {
        header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/dashboard/my-pets.php');
        exit;
    }
}

/**
 * Redirect to email verification if the user hasn't verified yet.
 * Call after requireLogin() on pages that need verified users.
 */
function requireVerified(): void {
    if (isset($_SESSION['email_verified']) && $_SESSION['email_verified'] === false) {
        header('Location: ' . BASE_PATH . '/auth/verify-email.php');
        exit;
    }
}

/**
 * Redirect to dashboard if the user is not an admin.
 * Always call requireLogin() first.
 */
function requireAdmin(): void {
    requireLogin();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        http_response_code(403);
        // Swap for a proper 403 view if you have one
        die('Access denied.');
    }
}
