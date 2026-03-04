<?php
/**
 * Input sanitization & output escaping helpers.
 *
 * RULES FOR THE TEAM:
 *   1. EVERY user input goes through inputString() or inputInt() before use.
 *   2. EVERY variable echoed into HTML goes through esc().
 *   3. NO exceptions. Ever.
 *
 * Usage:
 *   require_once __DIR__ . '/../includes/sanitize.php';
 *
 *   // Reading input
 *   $email = inputString('email');          // from $_POST
 *   $page  = inputInt('page', 'GET');       // from $_GET
 *
 *   // Output in HTML
 *   echo '<p>Hello, ' . esc($firstName) . '</p>';
 */

/**
 * Escape a string for safe HTML output.
 * Prevents XSS by converting special characters to HTML entities.
 *
 * @param  string|null $value  The raw value to escape.
 * @return string              HTML-safe string.
 */
function esc(?string $value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Get a sanitized string from user input ($_POST or $_GET).
 * Strips HTML tags and trims whitespace.
 *
 * @param  string $key     The form field name.
 * @param  string $method  'POST' (default) or 'GET'.
 * @return string|null     Sanitized string, or null if not present.
 */
function inputString(string $key, string $method = 'POST'): ?string
{
    $source = ($method === 'GET') ? $_GET : $_POST;

    if (!isset($source[$key]) || $source[$key] === '') {
        return null;
    }

    // Strip tags, trim whitespace
    $value = trim(strip_tags($source[$key]));

    // Return null for empty-after-cleaning strings
    return ($value !== '') ? $value : null;
}

/**
 * Get a validated integer from user input.
 *
 * @param  string $key     The form field name.
 * @param  string $method  'POST' (default) or 'GET'.
 * @return int|null        Validated integer, or null if not present/invalid.
 */
function inputInt(string $key, string $method = 'POST'): ?int
{
    $source = ($method === 'GET') ? $_GET : $_POST;

    if (!isset($source[$key])) {
        return null;
    }

    $filtered = filter_var($source[$key], FILTER_VALIDATE_INT);

    return ($filtered !== false) ? $filtered : null;
}

/**
 * Get a validated email from user input.
 *
 * @param  string $key     The form field name.
 * @param  string $method  'POST' (default) or 'GET'.
 * @return string|null     Valid email, or null if invalid/not present.
 */
function inputEmail(string $key, string $method = 'POST'): ?string
{
    $source = ($method === 'GET') ? $_GET : $_POST;

    if (!isset($source[$key])) {
        return null;
    }

    $filtered = filter_var(trim($source[$key]), FILTER_VALIDATE_EMAIL);

    return ($filtered !== false) ? $filtered : null;
}

/**
 * Get a sanitized float from user input (for monetary values).
 *
 * @param  string $key     The form field name.
 * @param  string $method  'POST' (default) or 'GET'.
 * @return float|null      Validated float, or null if not present/invalid.
 */
function inputFloat(string $key, string $method = 'POST'): ?float
{
    $source = ($method === 'GET') ? $_GET : $_POST;

    if (!isset($source[$key])) {
        return null;
    }

    $filtered = filter_var($source[$key], FILTER_VALIDATE_FLOAT);

    return ($filtered !== false) ? $filtered : null;
}