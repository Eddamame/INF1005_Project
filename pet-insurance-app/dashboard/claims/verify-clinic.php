<?php
/**
 * dashboard/claims/verify-clinic.php
 *
 * Re-runs ClinicVerifier for the latest quote on a claim using the latest
 * completed AI scan data.
 *
 * IMPORTANT:
 *  - This does NOT modify claims.status (admin lifecycle only).
 *  - It only updates quotes.clinic_verified.
 */

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_once __DIR__ . '/../../services/ClinicVerifier.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . '/dashboard/claims/index.php');
    exit;
}

requireValidCsrf();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$claimId = inputInt('claim_id');
$quoteId = inputInt('quote_id');

if (!$claimId) {
    $_SESSION['flash_error'] = 'Missing claim ID.';
    header('Location: ' . BASE_PATH . '/dashboard/claims/index.php');
    exit;
}

// Verify claim belongs to user and load latest completed parsed data
$stmt = $db->prepare('SELECT id, clinic_code FROM claims WHERE id = :id AND user_id = :uid LIMIT 1');
$stmt->execute([':id' => $claimId, ':uid' => $userId]);
$claim = $stmt->fetch();

if (!$claim) {
    $_SESSION['flash_error'] = 'Claim not found.';
    header('Location: ' . BASE_PATH . '/dashboard/claims/index.php');
    exit;
}

// Latest completed parsed data
$docStmt = $db->prepare('
    SELECT ai_parsed_data
    FROM claim_documents
    WHERE claim_id = :cid AND scan_status = :status
    ORDER BY uploaded_at DESC
    LIMIT 1
');
$docStmt->execute([':cid' => $claimId, ':status' => 'completed']);
$docRow = $docStmt->fetch();
$parsedData = $docRow ? (json_decode($docRow['ai_parsed_data'], true) ?: []) : [];

// Fallback: if parsed data has no clinic_code, use the claim's clinic_code
if (!isset($parsedData['clinic_code']) || trim((string) ($parsedData['clinic_code'] ?? '')) === '') {
    if (!empty($claim['clinic_code'])) {
        $parsedData['clinic_code'] = $claim['clinic_code'];
    }
}

// Determine which quote to verify
if (!$quoteId) {
    $qStmt = $db->prepare('
        SELECT id
        FROM quotes
        WHERE claim_id = :cid
        ORDER BY generated_at DESC
        LIMIT 1
    ');
    $qStmt->execute([':cid' => $claimId]);
    $qRow = $qStmt->fetch();
    $quoteId = $qRow ? (int) $qRow['id'] : 0;
}

if (!$quoteId) {
    $_SESSION['flash_error'] = 'No quote found to verify.';
    header('Location: ' . BASE_PATH . '/dashboard/claims/view-claim.php?id=' . (int) $claimId);
    exit;
}

// Ownership check for quote -> claim
$qStmt2 = $db->prepare('
    SELECT q.id
    FROM quotes q
    JOIN claims c ON c.id = q.claim_id
    WHERE q.id = :qid AND c.id = :cid AND c.user_id = :uid
    LIMIT 1
');
$qStmt2->execute([':qid' => $quoteId, ':cid' => $claimId, ':uid' => $userId]);
if (!$qStmt2->fetch()) {
    $_SESSION['flash_error'] = 'Quote not found.';
    header('Location: ' . BASE_PATH . '/dashboard/claims/view-claim.php?id=' . (int) $claimId);
    exit;
}

$verifier = new ClinicVerifier($db);
$verification = $verifier->verifyForQuote((int) $quoteId, $parsedData);
$isVerified = (bool) ($verification['verified'] ?? false);

$_SESSION['flash_message'] = 'Clinic re-verified: ' . ($isVerified ? 'Clinic Verified' : 'Clinic Not Verified') . '.';

header('Location: ' . BASE_PATH . '/dashboard/claims/view-claim.php?id=' . (int) $claimId);
exit;

