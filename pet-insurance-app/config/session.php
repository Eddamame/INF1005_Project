<?php
/**
 * Session configuration.
 *
 * Standard PHP file-based sessions — works on both localhost and GCE VM
 * (the VM has a persistent disk, so file sessions are fine).
 *
 * Usage: require this file BEFORE session_start() in any entry point.
 *
 *   require_once __DIR__ . '/../config/session.php';
 *   session_start();
 */

// Only configure if a session hasn't already been started
if (session_status() === PHP_SESSION_NONE) {

    // Prevent JavaScript access to the session cookie (XSS mitigation)
    ini_set('session.cookie_httponly', '1');

    // Only send cookie over HTTPS (disable if serving over plain HTTP)
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');

    // SameSite=Lax prevents the cookie being sent on cross-site subrequests
    ini_set('session.cookie_samesite', 'Lax');

    // Reject uninitialized session IDs (session fixation protection)
    ini_set('session.use_strict_mode', '1');

    // Sessions expire after 1 hour of inactivity
    ini_set('session.gc_maxlifetime', '3600');

    // Only use cookies for session IDs (never URL parameters)
    ini_set('session.use_only_cookies', '1');

    // Set a meaningful session name (helps with debugging multiple projects)
    session_name('PET_INSURANCE_SID');

    session_start();
}