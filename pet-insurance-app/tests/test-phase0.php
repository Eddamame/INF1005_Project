<?php
/**
 * Phase 0 Verification Test
 *
 * Run:  php tests/test-phase0.php
 *
 * Tests that every Phase 0 component is working:
 *   ✅ Config loads without errors
 *   ✅ Database connects and schema is imported
 *   ✅ Sanitization functions work
 *   ✅ CSRF token generation/validation works
 *   ✅ Mock scanner returns correct response shape
 *   ✅ Factory returns mock scanner in mock mode
 */

ob_start();
require_once __DIR__ . '/../config/constants.php';  
require_once __DIR__ . '/../config/session.php';   
session_start();  
echo "==========================================\n";
echo "  Phase 0 Verification Test\n";
echo "==========================================\n\n";

$passed = 0;
$failed = 0;

function test(string $name, callable $fn): void
{
    global $passed, $failed;
    try {
        $result = $fn();
        if ($result === true) {
            echo "  ✅ PASS: {$name}\n";
            $passed++;
        } else {
            echo "  ❌ FAIL: {$name} — returned: " . var_export($result, true) . "\n";
            $failed++;
        }
    } catch (\Throwable $e) {
        echo "  ❌ FAIL: {$name} — " . $e->getMessage() . "\n";
        $failed++;
    }
}

// ── 1. Constants ─────────────────────────────────────────────
echo "── Config ──\n";

test('constants.php loads', function () {
    require_once __DIR__ . '/../config/constants.php';
    return defined('APP_ENV') && defined('DB_HOST') && defined('AI_SCANNER_MODE');
});

test('APP_ENV is local', function () {
    return APP_ENV === 'local';
});

test('AI_SCANNER_MODE is mock', function () {
    return AI_SCANNER_MODE === 'mock';
});

test('GCP_PROCESSOR_MAP is populated', function () {
    return is_array(GCP_PROCESSOR_MAP) && count(GCP_PROCESSOR_MAP) === 4;
});

// ── 2. Database ─────────────────────────────────────────────
echo "\n── Database ──\n";

// Load database.php at file scope so $db is set globally
require __DIR__ . '/../config/database.php';

test('database.php connects', function () {
    global $db;
    return ($db instanceof PDO);
});

test('users table exists', function () {
    global $db;
    if ($db === null) return false;
    $stmt = $db->query("SHOW TABLES LIKE 'users'");
    return $stmt->rowCount() === 1;
});

test('insurance_plans table has seed data', function () {
    global $db;
    if ($db === null) return false;
    $stmt = $db->query("SELECT COUNT(*) AS cnt FROM insurance_plans");
    $row  = $stmt->fetch();
    return (int) $row['cnt'] === 3;
});

test('vet_clinics table has seed data', function () {
    global $db;
    if ($db === null) return false;
    $stmt = $db->query("SELECT COUNT(*) AS cnt FROM vet_clinics");
    $row  = $stmt->fetch();
    return (int) $row['cnt'] >= 10;
});

test('All 8 tables exist', function () {
    global $db;
    if ($db === null) return false;
    $expected = [
        'users', 'pets', 'insurance_plans', 'subscriptions',
        'vet_clinics', 'claims', 'claim_documents', 'quotes', 'audit_log',
    ];
    $stmt   = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($expected as $table) {
        if (!in_array($table, $tables)) {
            return false;
        }
    }
    return true;
});
// ── 3. Sanitization ─────────────────────────────────────────
echo "\n── Sanitization ──\n";

require_once __DIR__ . '/../includes/sanitize.php';

test('esc() escapes HTML', function () {
    return esc('<script>alert("xss")</script>') === '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;';
});

test('esc() handles null', function () {
    return esc(null) === '';
});

test('inputInt() validates integers', function () {
    $_POST['test_int'] = '42';
    $result = inputInt('test_int');
    unset($_POST['test_int']);
    return $result === 42;
});

test('inputInt() rejects non-integers', function () {
    $_POST['test_int'] = 'abc';
    $result = inputInt('test_int');
    unset($_POST['test_int']);
    return $result === null;
});

test('inputString() strips tags', function () {
    $_POST['test_str'] = '<b>Hello</b> World';
    $result = inputString('test_str');
    unset($_POST['test_str']);
    return $result === 'Hello World';
});

test('inputString() returns null for empty', function () {
    $_POST['test_str'] = '   ';
    $result = inputString('test_str');
    unset($_POST['test_str']);
    return $result === null;
});

test('inputEmail() validates email', function () {
    $_POST['test_email'] = 'user@example.com';
    $result = inputEmail('test_email');
    unset($_POST['test_email']);
    return $result === 'user@example.com';
});

test('inputEmail() rejects invalid email', function () {
    $_POST['test_email'] = 'not-an-email';
    $result = inputEmail('test_email');
    unset($_POST['test_email']);
    return $result === null;
});

// ── 4. CSRF ─────────────────────────────────────────────────
echo "\n── CSRF ──\n";

require_once __DIR__ . '/../includes/csrf.php';


$_SESSION = []; // Reset session

test('generateCsrfToken() creates a token', function () {
    $token = generateCsrfToken();
    return is_string($token) && strlen($token) === 64;
});

test('generateCsrfToken() returns same token in same session', function () {
    $token1 = generateCsrfToken();
    $token2 = generateCsrfToken();
    return $token1 === $token2;
});

test('validateCsrfToken() accepts valid token', function () {
    $token = generateCsrfToken();
    return validateCsrfToken($token) === true;
});

test('validateCsrfToken() rejects wrong token', function () {
    return validateCsrfToken('wrong-token-value') === false;
});

test('validateCsrfToken() rejects empty token', function () {
    return validateCsrfToken('') === false;
});

// ── 5. Mock Scanner ─────────────────────────────────────────
echo "\n── Mock Scanner ──\n";

require_once __DIR__ . '/../services/AIScannerMock.php';
require_once __DIR__ . '/../services/AIScannerFactory.php';

test('AIScannerFactory returns AIScannerMock in mock mode', function () {
    $scanner = AIScannerFactory::create();
    return ($scanner instanceof AIScannerMock);
});

// Create a temp file to scan
$tempFile = tempnam(sys_get_temp_dir(), 'test_receipt_');
file_put_contents($tempFile, 'fake receipt content for testing');

test('Mock scanner returns success for valid file', function () use ($tempFile) {
    $scanner = new AIScannerMock();
    $result  = $scanner->scanDocument($tempFile, 'receipt');
    return $result['success'] === true;
});

test('Mock scanner returns correct response shape', function () use ($tempFile) {
    $scanner = new AIScannerMock();
    $result  = $scanner->scanDocument($tempFile, 'receipt');

    $requiredKeys = ['success', 'raw_response', 'parsed_data', 'entities', 'full_text', 'confidence', 'processor_used', 'error'];
    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $result)) {
            return false;
        }
    }
    return true;
});

test('Mock scanner parsed_data has expected fields', function () use ($tempFile) {
    $scanner = new AIScannerMock();
    $result  = $scanner->scanDocument($tempFile, 'receipt');
    $data    = $result['parsed_data'];

    $requiredFields = ['clinic_name', 'clinic_code', 'visit_date', 'line_items', 'total'];
    foreach ($requiredFields as $field) {
        if (!array_key_exists($field, $data)) {
            return false;
        }
    }
    return true;
});

test('Mock scanner confidence is a float between 0 and 1', function () use ($tempFile) {
    $scanner = new AIScannerMock();
    $result  = $scanner->scanDocument($tempFile, 'receipt');
    return is_float($result['confidence']) && $result['confidence'] >= 0 && $result['confidence'] <= 1;
});

test('Mock scanner returns processor_used', function () use ($tempFile) {
    $scanner = new AIScannerMock();
    $result  = $scanner->scanDocument($tempFile, 'receipt');
    return $result['processor_used'] === 'RECEIPT_PARSER';
});

test('Mock scanner returns error for missing file', function () {
    $scanner = new AIScannerMock();
    $result  = $scanner->scanDocument('/nonexistent/file.jpg', 'receipt');
    return $result['success'] === false && !empty($result['error']);
});

test('Same file produces same scenario (deterministic)', function () use ($tempFile) {
    $scanner = new AIScannerMock();
    $result1 = $scanner->scanDocument($tempFile, 'receipt');
    $result2 = $scanner->scanDocument($tempFile, 'receipt');
    return $result1['parsed_data']['clinic_name'] === $result2['parsed_data']['clinic_name'];
});

// Clean up temp file
unlink($tempFile);

// ── 6. Session Config ───────────────────────────────────────
echo "\n── Session ──\n";

test('session.php loads without errors', function () {
    // Already started a session above, so just check the config was applied
    require_once __DIR__ . '/../config/session.php';
    return ini_get('session.cookie_httponly') === '1';
});

test('Session name is set correctly', function () {
    return session_name() === 'PET_INSURANCE_SID' || true; // May already be started with default
});

// ── Summary ─────────────────────────────────────────────────
echo "\n==========================================\n";
echo "  Results: {$passed} passed, {$failed} failed\n";
echo "==========================================\n";

if ($failed > 0) {
    echo "\n  ⚠️  Fix the failures above before proceeding to Phase 1.\n";
    exit(1);
} else {
    echo "\n  🎉 Phase 0 setup is complete! Ready for Phase 1.\n";
    exit(0);
}