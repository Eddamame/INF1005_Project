<?php
/**
 * api/scan-receipt.php
 *
 * Orchestrates a full "scan receipt" flow:
 *   - Auth + CSRF validation
 *   - Ownership check on the claim
 *   - Secure file upload to /uploads using FileUploadHandler
 *   - AI scan via AIScannerFactory (mock or live)
 *   - Persists claim_documents row with AI metadata
 *   - Checks confidence against AUTO_APPROVE_CONFIDENCE_THRESHOLD:
 *       >= threshold → claim status = 'scanned' (user can generate quote)
 *       <  threshold → claim status = 'rejected' (user must resubmit)
 *   - Returns a JSON payload for the dashboard UI
 *
 * Expects a multipart/form-data POST with:
 *   - csrf_token     (hidden field or X-CSRF-Token header)
 *   - claim_id       (int, existing claim owned by current user)
 *   - file_type      (optional, defaults to 'receipt')
 *   - document       (the uploaded file input name)
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../services/FileUploadHandler.php';
require_once __DIR__ . '/../services/AIScannerFactory.php';

header('Content-Type: application/json; charset=utf-8');

// Helpers ------------------------------------------------------
function json_error(string $message, int $status = 400, array $extra = []): void
{
    http_response_code($status);
    echo json_encode(array_merge([
        'success' => false,
        'error'   => $message,
    ], $extra));
    exit;
}

function json_success(array $payload = []): void
{
    http_response_code(200);
    echo json_encode(array_merge([
        'success' => true,
    ], $payload));
    exit;
}

// Ensure authenticated user
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

// CSRF protection (supports header for AJAX)
requireValidCsrf();

// Validate input ------------------------------------------------
$claimId  = inputInt('claim_id') ?? null;
$fileType = inputString('file_type') ?? 'receipt';

if (!$claimId) {
    json_error('Missing or invalid claim_id.');
}

if (!isset($_FILES['document'])) {
    json_error('No document uploaded.');
}

// Verify claim ownership and status -----------------------------
try {
    $stmt = $db->prepare('SELECT * FROM claims WHERE id = :id AND user_id = :uid LIMIT 1');
    $stmt->execute([
        ':id'  => $claimId,
        ':uid' => (int) ($_SESSION['user_id'] ?? 0),
    ]);
    $claim = $stmt->fetch();

    if (!$claim) {
        json_error('Claim not found.', 404);
    }

    // Allow scanning on draft, scanning, scanned, quoted, AND rejected
    // (rejected claims can be rescanned with a better document)
    $allowedStatuses = ['draft', 'scanning', 'scanned', 'quoted', 'rejected'];
    if (!in_array($claim['status'], $allowedStatuses, true)) {
        json_error('This claim cannot accept new documents in its current status.', 409, [
            'current_status' => $claim['status'],
        ]);
    }

    // Mark claim as "scanning"
    $update = $db->prepare('UPDATE claims SET status = :status WHERE id = :id');
    $update->execute([
        ':status' => 'scanning',
        ':id'     => $claimId,
    ]);
} catch (Throwable $e) {
    error_log('scan-receipt: claim lookup failed: ' . $e->getMessage());
    json_error('Unable to load claim. Please try again later.', 500);
}

// Handle upload -------------------------------------------------
$uploadHandler = new FileUploadHandler();
$uploadResult  = $uploadHandler->handle($_FILES['document'], $claimId, $fileType);

if (!$uploadResult['success']) {
    // Reset claim status back to draft if upload failed
    $db->prepare('UPDATE claims SET status = :status WHERE id = :id')
       ->execute([':status' => 'draft', ':id' => $claimId]);
    json_error($uploadResult['error'] ?? 'File upload failed.');
}

// Run AI scan ---------------------------------------------------
$scanner = AIScannerFactory::create();

try {
    $scanResult = $scanner->scanDocument(
        $uploadResult['file_path'],
        $uploadResult['file_type'] ?? $fileType
    );
} catch (Throwable $e) {
    error_log('scan-receipt: AI scan exception: ' . $e->getMessage());

    // Persist a failed document record for auditing
    $docStmt = $db->prepare('
        INSERT INTO claim_documents (
            claim_id, file_name, file_path, file_type, mime_type, file_size,
            processor_used, ai_raw_response, ai_parsed_data, ai_confidence, scan_status
        ) VALUES (
            :claim_id, :file_name, :file_path, :file_type, :mime_type, :file_size,
            :processor_used, :ai_raw_response, :ai_parsed_data, :ai_confidence, :scan_status
        )
    ');
    $docStmt->execute([
        ':claim_id'      => $claimId,
        ':file_name'     => $uploadResult['file_name'],
        ':file_path'     => $uploadResult['relative_path'],
        ':file_type'     => $uploadResult['file_type'],
        ':mime_type'     => $uploadResult['mime_type'],
        ':file_size'     => $uploadResult['file_size'],
        ':processor_used'=> null,
        ':ai_raw_response' => json_encode([]),
        ':ai_parsed_data'  => json_encode([]),
        ':ai_confidence'   => 0.0,
        ':scan_status'     => 'failed',
    ]);

    // Set claim back to draft
    $db->prepare('UPDATE claims SET status = :status WHERE id = :id')
       ->execute([':status' => 'draft', ':id' => $claimId]);

    json_error('The AI scanner failed to process this document. Please try again or contact support.', 502);
}

// Normalize scan result -----------------------------------------
$success      = (bool) ($scanResult['success'] ?? false);
$parsed       = $scanResult['parsed_data'] ?? [];
$entities     = $scanResult['entities'] ?? [];
$fullText     = $scanResult['full_text'] ?? '';
$confidence   = $scanResult['confidence'] ?? 0.0;
$processor    = $scanResult['processor_used'] ?? null;
$scanErrorMsg = $scanResult['error'] ?? null;

// Persist claim_documents row -----------------------------------
try {
    $docStmt = $db->prepare('
        INSERT INTO claim_documents (
            claim_id, file_name, file_path, file_type, mime_type, file_size,
            processor_used, ai_raw_response, ai_parsed_data, ai_confidence, scan_status
        ) VALUES (
            :claim_id, :file_name, :file_path, :file_type, :mime_type, :file_size,
            :processor_used, :ai_raw_response, :ai_parsed_data, :ai_confidence, :scan_status
        )
    ');

    $docStmt->execute([
        ':claim_id'       => $claimId,
        ':file_name'      => $uploadResult['file_name'],
        ':file_path'      => $uploadResult['relative_path'],
        ':file_type'      => $uploadResult['file_type'],
        ':mime_type'      => $uploadResult['mime_type'],
        ':file_size'      => $uploadResult['file_size'],
        ':processor_used' => $processor,
        ':ai_raw_response'=> json_encode($scanResult['raw_response'] ?? []),
        ':ai_parsed_data' => json_encode($parsed),
        ':ai_confidence'  => $confidence,
        ':scan_status'    => $success ? 'completed' : 'failed',
    ]);

    $documentId = (int) $db->lastInsertId();
} catch (Throwable $e) {
    error_log('scan-receipt: failed saving claim_documents: ' . $e->getMessage());
    json_error('Failed to record scan results. Please try again later.', 500);
}

// ── Confidence gate ────────────────────────────────────────────
// Check whether the scan confidence meets the auto-approval threshold.
// If it does: claim moves to 'scanned' and user can generate a quote.
// If not: claim is set to 'rejected' and user is told to resubmit.
$threshold     = defined('AUTO_APPROVE_CONFIDENCE_THRESHOLD') ? AUTO_APPROVE_CONFIDENCE_THRESHOLD : 0.80;
$confidenceMet = $success && ($confidence >= $threshold);

// Update parent claim with extracted fields ---------------------
try {
    $clinicCode = $parsed['clinic_code'] ?? null;
    $visitDate  = $parsed['visit_date'] ?? null;

    if ($confidenceMet) {
        $newStatus = 'scanned';
    } elseif ($success) {
        // Scan succeeded but confidence too low
        $newStatus = 'rejected';
    } else {
        // Scan itself failed
        $newStatus = 'rejected';
    }

    $params = [
        ':id'     => $claimId,
        ':status' => $newStatus,
    ];

    $setSql = 'status = :status';

    if ($clinicCode) {
        $setSql .= ', clinic_code = :clinic_code';
        $params[':clinic_code'] = $clinicCode;
    }

    if ($visitDate) {
        $setSql .= ', visit_date = :visit_date';
        $params[':visit_date'] = $visitDate;
    }

    $sql = 'UPDATE claims SET ' . $setSql . ' WHERE id = :id';
    $upd = $db->prepare($sql);
    $upd->execute($params);
} catch (Throwable $e) {
    error_log('scan-receipt: failed updating claim: ' . $e->getMessage());
    // Non-fatal for client; continue
}

// Audit log for low-confidence rejection
if ($success && !$confidenceMet) {
    try {
        $db->prepare("
            INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address)
            VALUES (:uid, 'scan_confidence_rejected', 'claim', :eid, :details, :ip)
        ")->execute([
            ':uid'     => (int) ($_SESSION['user_id'] ?? 0),
            ':eid'     => $claimId,
            ':details' => json_encode([
                'confidence' => round($confidence, 4),
                'threshold'  => $threshold,
                'document_id'=> $documentId,
            ]),
            ':ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
        error_log('scan-receipt: audit log failed: ' . $e->getMessage());
    }
}

// Final JSON response -------------------------------------------
json_success([
    'claim_id'       => $claimId,
    'document_id'    => $documentId,
    'file'           => [
        'name'         => $uploadResult['file_name'],
        'original'     => $uploadResult['original_name'],
        'relativePath' => $uploadResult['relative_path'],
        'mime_type'    => $uploadResult['mime_type'],
        'size'         => $uploadResult['file_size'],
        'file_type'    => $uploadResult['file_type'],
    ],
    'scan'           => [
        'success'          => $success,
        'parsed_data'      => $parsed,
        'entities'         => $entities,
        'full_text'        => $fullText,
        'confidence'       => $confidence,
        'processor_used'   => $processor,
        'error'            => $scanErrorMsg,
        'confidence_met'   => $confidenceMet,
        'threshold'        => $threshold,
    ],
]);