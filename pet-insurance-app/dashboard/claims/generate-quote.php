<?php
/**
 * dashboard/claims/generate-quote.php
 * Generates a reimbursement quote from the AI scan data using QuoteEngine.
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_once __DIR__ . '/../../services/QuoteEngine.php';
require_once __DIR__ . '/../../services/ClinicVerifier.php';

requireLogin();

/** @var PDO $db */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . '/dashboard/claims/index.php');
    exit;
}

requireValidCsrf();

$userId  = (int) ($_SESSION['user_id'] ?? 0);
$claimId = inputInt('claim_id');

if (!$claimId) {
    $_SESSION['flash_error'] = 'Missing claim ID.';
    header('Location: ' . BASE_PATH . '/dashboard/claims/index.php');
    exit;
}

// Verify claim belongs to user
$stmt = $db->prepare('SELECT * FROM claims WHERE id = :id AND user_id = :uid LIMIT 1');
$stmt->execute([':id' => $claimId, ':uid' => $userId]);
$claim = $stmt->fetch();

if (!$claim) {
    $_SESSION['flash_error'] = 'Claim not found.';
    header('Location: ' . BASE_PATH . '/dashboard/claims/index.php');
    exit;
}

// Generate quote using QuoteEngine
$engine = new QuoteEngine($db);
$result = $engine->generateForClaim($claimId);

if ($result['success']) {
    // Run clinic verification
    $verifier = new ClinicVerifier($db);

    // Get parsed data from latest document
    $docStmt = $db->prepare('
        SELECT ai_parsed_data FROM claim_documents
        WHERE claim_id = :cid AND scan_status = :status
        ORDER BY uploaded_at DESC LIMIT 1
    ');
    $docStmt->execute([':cid' => $claimId, ':status' => 'completed']);
    $docRow = $docStmt->fetch();
    $parsedData = $docRow ? (json_decode($docRow['ai_parsed_data'], true) ?: []) : [];

    // Robustness: if AI parsed payload is missing clinic_code, fall back to
    // the value we already stored on the claim during scan-receipt.php.
    if (!isset($parsedData['clinic_code']) || trim((string) ($parsedData['clinic_code'] ?? '')) === '') {
        if (!empty($claim['clinic_code'])) {
            $parsedData['clinic_code'] = $claim['clinic_code'];
        }
    }

    $verification = $verifier->verifyForQuote($result['quote_id'], $parsedData);
    $isVerified = (bool) ($verification['verified'] ?? false);

    // Do NOT adjust claims.status here; admin remains responsible for lifecycle changes.
    // We only display verification result in the UI via quotes.clinic_verified.
    $_SESSION['flash_message'] = 'Quote generated successfully! Reference: ' . $result['reference_id']
        . ' — Clinic ' . ($isVerified ? 'Verified' : 'Not Verified') . '.';
} else {
    $_SESSION['flash_error'] = $result['error'] ?? 'Could not generate quote.';
}

header('Location: ' . BASE_PATH . '/dashboard/claims/view-claim.php?id=' . $claimId);
exit;