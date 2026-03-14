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
        header('Location: /auth/login.php');
        exit;
    }
}

function isLoggedIn(): void {
    if (!empty($_SESSION['user_id'])) {
        header('Location: /dashboard/my-pets.php');
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
