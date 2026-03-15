<?php
/**
 * Application-wide constants.
 *
 * Environment detection:
 *   - On GCE VM (production): APP_ENV is set to 'production' in /etc/apache2/envvars
 *   - On local dev:           APP_ENV is absent, defaults to 'local'
 *
 * Sensitive values (DB_PASS, Stripe keys, etc.) come from environment variables.
 * If a .env file exists in the project root, it is loaded here so getenv() works.
 */

// ── Load .env (project root) so getenv() / $_ENV are populated ─
$envFile = __DIR__ . '/../.env';
if (is_file($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name  = trim($name);
                $value = trim($value, " \t\"'");
                if ($name !== '') {
                    putenv($name . '=' . $value);
                    $_ENV[$name] = $value;
                }
            }
        }
    }
}

// ── Environment ─────────────────────────────────────────────
define('APP_ENV',       getenv('APP_ENV') ?: 'local');
define('IS_PRODUCTION', APP_ENV === 'production');

// ── Base path (for subfolder installs, e.g. localhost/pet-insurance-app/) ─
if (php_sapi_name() === 'cli') {
    define('BASE_PATH', '');
} else {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $parts = array_filter(explode('/', trim($script, '/')));
    define('BASE_PATH', count($parts) > 1 ? '/' . $parts[0] : '');
}

/** Safe base path for HTML output (use in href/src). */
function base_path() {
    return htmlspecialchars(BASE_PATH, ENT_QUOTES, 'UTF-8');
}

// ── Database ─────────────────────────────────────────────────
// TCP connection in both environments — identical behaviour.
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'pet_insurance');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// ── File Uploads ────────────────────────────────────────────
// Local filesystem in both environments (VM has persistent disk).
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB
define('ALLOWED_MIME_TYPES', [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/tiff',        // Document AI supports TIFF natively
    'application/pdf',
]);

// ── Google Cloud Document AI ────────────────────────────────
define('GCP_PROJECT_ID', getenv('GCP_PROJECT_ID') ?: 'your-gcp-project-id');
define('GCP_LOCATION',   getenv('GCP_LOCATION')   ?: 'us');

// Credentials: on VM stored outside webroot; locally in config/
define('GCP_CREDENTIALS_PATH', IS_PRODUCTION
    ? '/var/www/gcp-credentials.json'
    : __DIR__ . '/gcp-credentials.json'
);

// Processor IDs — copy from GCP Console → Document AI → My Processors
define('GCP_RECEIPT_PROCESSOR_ID', getenv('GCP_RECEIPT_PROCESSOR_ID') ?: 'PLACEHOLDER_RECEIPT');
define('GCP_INVOICE_PROCESSOR_ID', getenv('GCP_INVOICE_PROCESSOR_ID') ?: 'PLACEHOLDER_INVOICE');
define('GCP_EXPENSE_PROCESSOR_ID', getenv('GCP_EXPENSE_PROCESSOR_ID') ?: 'PLACEHOLDER_EXPENSE');

// Map file_type → processor ID
define('GCP_PROCESSOR_MAP', [
    'receipt'    => GCP_RECEIPT_PROCESSOR_ID,
    'invoice'    => GCP_INVOICE_PROCESSOR_ID,
    'vet_report' => GCP_EXPENSE_PROCESSOR_ID,
    'other'      => GCP_RECEIPT_PROCESSOR_ID,   // Fallback
]);

// Map file_type → human-readable processor name (stored in DB for auditing)
define('GCP_PROCESSOR_NAMES', [
    'receipt'    => 'RECEIPT_PARSER',
    'invoice'    => 'INVOICE_PARSER',
    'vet_report' => 'EXPENSE_PARSER',
    'other'      => 'RECEIPT_PARSER',
]);

// ── AI Scanner Mode ─────────────────────────────────────────
// 'mock'  = AIScannerMock (local dev, Phases 0-3, no GCP needed)
// 'live'  = AIScanner (real Document AI, Phase 4+)
define('AI_SCANNER_MODE', getenv('AI_SCANNER_MODE') ?: 'mock');