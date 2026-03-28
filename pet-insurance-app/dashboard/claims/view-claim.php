<?php
/**
 * dashboard/claims/view-claim.php
 * View a claim's details, uploaded documents, AI scan results, and quotes.
 * Updated: shows auto-approve/reject decision and flagged/suspended states.
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_once __DIR__ . '/../../services/ClinicVerifier.php';

requireLogin();

/** @var PDO $db */

$userId  = (int) ($_SESSION['user_id'] ?? 0);
$claimId = inputInt('id', 'GET') ?? 0;

if (!$claimId) {
    header('Location: ' . BASE_PATH . '/dashboard/claims/index.php');
    exit;
}

$flashMessage = $_SESSION['flash_message'] ?? '';
$flashError   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

// Load claim
$claim = null;
$documents = [];
$quotes = [];

try {
    $stmt = $db->prepare('
        SELECT c.*, p.name AS pet_name, p.species, p.breed,
               s.id AS subscription_id, ip.name AS plan_name,
               ip.coverage_pct AS plan_coverage, ip.deductible AS plan_deductible,
               ip.annual_limit AS plan_limit
        FROM claims c
        JOIN pets p ON p.id = c.pet_id
        JOIN subscriptions s ON s.id = c.subscription_id
        JOIN insurance_plans ip ON ip.id = s.plan_id
        WHERE c.id = :id AND c.user_id = :uid
        LIMIT 1
    ');
    $stmt->execute([':id' => $claimId, ':uid' => $userId]);
    $claim = $stmt->fetch();

    if (!$claim) {
        header('Location: ' . BASE_PATH . '/dashboard/claims/index.php');
        exit;
    }

    // Documents
    $stmt = $db->prepare('SELECT * FROM claim_documents WHERE claim_id = :cid ORDER BY uploaded_at DESC');
    $stmt->execute([':cid' => $claimId]);
    $documents = $stmt->fetchAll();

    // Quotes
    $stmt = $db->prepare('SELECT * FROM quotes WHERE claim_id = :cid ORDER BY generated_at DESC');
    $stmt->execute([':cid' => $claimId]);
    $quotes = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('view-claim: ' . $e->getMessage());
    header('Location: ' . BASE_PATH . '/dashboard/claims/index.php');
    exit;
}

// Latest completed scan
$latestCompleted = null;
$parsed = [];
foreach ($documents as $doc) {
    if ($doc['scan_status'] === 'completed') {
        $latestCompleted = $doc;
        $parsed = json_decode($doc['ai_parsed_data'] ?? '[]', true) ?: [];
        break;
    }
}

// Clinic verification
$latestQuote = !empty($quotes) ? $quotes[0] : null;
$clinicVerified = $latestQuote ? ((int) ($latestQuote['clinic_verified'] ?? 0) === 1) : null;

// Auto re-check clinic verification on page load
if ($latestQuote && $clinicVerified === false && $latestCompleted) {
    $latestQuoteId = (int) ($latestQuote['id'] ?? 0);
    if ($latestQuoteId > 0 && (!empty($parsed['clinic_code']) || !empty($claim['clinic_code']))) {
        if (empty($parsed['clinic_code']) && !empty($claim['clinic_code'])) {
            $parsed['clinic_code'] = $claim['clinic_code'];
        }
        try {
            $verifier = new ClinicVerifier($db);
            $verification = $verifier->verifyForQuote($latestQuoteId, $parsed);
            $clinicVerified = (bool) ($verification['verified'] ?? false);
        } catch (Throwable $e) {
            error_log('view-claim: clinic auto-reverify failed: ' . $e->getMessage());
        }
    }
}

function claimStatusBadge(string $status): string {
    $map = [
        'draft'     => 'bg-secondary',
        'scanning'  => 'bg-info text-dark',
        'scanned'   => 'bg-primary',
        'quoted'    => 'bg-warning text-dark',
        'verified'  => 'bg-success',
        'approved'  => 'bg-success',
        'rejected'  => 'bg-danger',
        'flagged'   => 'bg-warning text-dark',
        'suspended' => 'bg-dark',
    ];
    $cls = $map[$status] ?? 'bg-secondary';
    $icon = '';
    if ($status === 'approved') $icon = '<i class="bi bi-check-circle-fill me-1"></i>';
    if ($status === 'rejected') $icon = '<i class="bi bi-x-circle-fill me-1"></i>';
    if ($status === 'flagged') $icon = '<i class="bi bi-flag-fill me-1"></i>';
    if ($status === 'suspended') $icon = '<i class="bi bi-pause-circle-fill me-1"></i>';
    return '<span class="badge ' . $cls . '">' . $icon . htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Claim <?= esc($claim['reference_id']) ?> — PawShield</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= esc(generateCsrfToken()); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/accessibility.css">
</head>
<body>

<?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

<main id="main-content">
<div class="container py-5">

    <a href="<?= base_path() ?>/dashboard/claims/index.php" class="btn btn-outline-secondary btn-sm mb-4">
        <i class="bi bi-arrow-left me-1"></i> Back to Claims
    </a>

    <?php if ($flashMessage): ?>
        <div class="alert alert-success"><?= esc($flashMessage) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="alert alert-danger"><?= esc($flashError) ?></div>
    <?php endif; ?>

    <!-- Flagged/Suspended banner -->
    <?php if (in_array($claim['status'], ['flagged', 'suspended'])): ?>
        <div class="alert <?= $claim['status'] === 'flagged' ? 'alert-warning' : 'alert-dark text-white' ?> mb-4">
            <i class="bi bi-<?= $claim['status'] === 'flagged' ? 'flag-fill' : 'pause-circle-fill' ?> me-2"></i>
            <strong>This claim has been <?= esc($claim['status']) ?> by an administrator.</strong>
            <?php if (!empty($claim['admin_notes'])): ?>
                <br><span class="small">Reason: <?= esc($claim['admin_notes']) ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Claim <?= esc($claim['reference_id']) ?></h2>
            <p class="text-muted mb-0">
                <?= esc($claim['pet_name']) ?> (<?= esc(ucfirst($claim['species'])) ?>)
                · <?= esc($claim['plan_name']) ?> plan
            </p>
        </div>
        <?= claimStatusBadge($claim['status']) ?>
    </div>

    <div class="row g-4">
        <!-- Left column -->
        <div class="col-lg-5">
            <!-- Pet & Policy Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-heart-pulse me-2"></i>Pet & Policy</h6>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-5">Pet</dt>
                        <dd class="col-sm-7"><?= esc($claim['pet_name']) ?> (<?= esc(ucfirst($claim['species'])) ?>)</dd>
                        <dt class="col-sm-5">Breed</dt>
                        <dd class="col-sm-7"><?= esc($claim['breed'] ?? '—') ?></dd>
                        <dt class="col-sm-5">Plan</dt>
                        <dd class="col-sm-7"><?= esc($claim['plan_name']) ?></dd>
                        <dt class="col-sm-5">Coverage</dt>
                        <dd class="col-sm-7"><?= number_format((float)$claim['plan_coverage']) ?>%</dd>
                        <dt class="col-sm-5">Deductible</dt>
                        <dd class="col-sm-7">$<?= number_format((float)$claim['plan_deductible'], 2) ?></dd>
                        <dt class="col-sm-5">Annual Limit</dt>
                        <dd class="col-sm-7">$<?= number_format((float)$claim['plan_limit']) ?></dd>
                        <dt class="col-sm-5">Created</dt>
                        <dd class="col-sm-7"><?= esc(date('d M Y H:i', strtotime($claim['created_at']))) ?></dd>
                    </dl>
                </div>
            </div>

            <!-- Visit & Clinic Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-hospital me-2"></i>Visit & Clinic</h6>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-5">Clinic</dt>
                        <dd class="col-sm-7"><?= esc($parsed['clinic_name'] ?? $claim['clinic_code'] ?? '—') ?></dd>
                        <dt class="col-sm-5">Clinic Code</dt>
                        <dd class="col-sm-7"><code><?= esc($claim['clinic_code'] ?? ($parsed['clinic_code'] ?? '—')) ?></code></dd>
                        <dt class="col-sm-5">Clinic Verification</dt>
                        <dd class="col-sm-7">
                            <?php if ($clinicVerified === null): ?>
                                <span class="badge bg-secondary">Not evaluated</span>
                            <?php elseif ($clinicVerified): ?>
                                <span class="badge bg-success">Clinic Verified</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Clinic Not Verified</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-5">Visit Date</dt>
                        <dd class="col-sm-7"><?= esc($claim['visit_date'] ?? ($parsed['visit_date'] ?? '—')) ?></dd>
                        <dt class="col-sm-5">Description</dt>
                        <dd class="col-sm-7"><?= esc($claim['description'] ?? '—') ?></dd>
                    </dl>
                </div>
            </div>

            <!-- Generate Quote (if scanned and confidence met, no quote yet) -->
            <?php if (in_array($claim['status'], ['scanned', 'verified']) && empty($quotes)): ?>
            <div class="card border-0 shadow-sm mb-4 border-success">
                <div class="card-body text-center">
                    <div class="alert alert-success small py-2 mb-3">
                        <i class="bi bi-check-circle-fill me-1"></i>
                        Receipt scanned with sufficient confidence. Ready to generate your quote.
                    </div>
                    <form method="POST" action="<?= base_path() ?>/dashboard/claims/generate-quote.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="claim_id" value="<?= (int)$claimId ?>">
                        <button class="btn btn-success">
                            <i class="bi bi-calculator me-1"></i>Generate Reimbursement Quote
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Rejected due to low confidence — prompt resubmit -->
            <?php if ($claim['status'] === 'rejected' && empty($quotes)): ?>
            <?php
                // Get the confidence from the latest scan to show user
                $latestConfidence = null;
                foreach ($documents as $d) {
                    if ($d['scan_status'] === 'completed' && $d['ai_confidence'] !== null) {
                        $latestConfidence = (float) $d['ai_confidence'];
                        break;
                    }
                }
            ?>
            <div class="card border-0 shadow-sm mb-4 border-danger">
                <div class="card-body">
                    <div class="alert alert-danger small py-2 mb-3">
                        <i class="bi bi-x-circle-fill me-1"></i>
                        <strong>Receipt scan did not meet the confidence threshold.</strong>
                        <?php if ($latestConfidence !== null): ?>
                            AI confidence was <?= round($latestConfidence * 100, 1) ?>%
                            (minimum required: <?= round(AUTO_APPROVE_CONFIDENCE_THRESHOLD * 100) ?>%).
                        <?php endif; ?>
                    </div>
                    <p class="small text-muted mb-2">
                        This usually means the receipt image was blurry, too dark, or partially cut off. Please upload a clearer version.
                    </p>
                    <div class="card bg-light border-0 p-3 mb-3">
                        <h6 class="small fw-bold mb-2"><i class="bi bi-lightbulb me-1"></i>Tips for a better scan:</h6>
                        <ul class="small mb-0 ps-3">
                            <li>Use good lighting — avoid shadows on the receipt</li>
                            <li>Make sure the entire receipt is visible and not cut off</li>
                            <li>Keep the camera steady to avoid blur</li>
                            <li>If you have a PDF version from the vet, upload that instead</li>
                        </ul>
                    </div>
                    <a href="<?= base_path() ?>/dashboard/claims/new-claim.php?continue=<?= (int)$claimId ?>"
                       class="btn btn-warning w-100">
                        <i class="bi bi-arrow-repeat me-1"></i>Resubmit with Better Receipt
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right column -->
        <div class="col-lg-7">
            <!-- Documents -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-paperclip me-2"></i>Documents</h6>
                    <span class="badge bg-secondary"><?= count($documents) ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($documents)): ?>
                        <p class="text-muted mb-0 small">No documents uploaded yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>File</th>
                                        <th>Type</th>
                                        <th>Scan Status</th>
                                        <th>Confidence</th>
                                        <th>Uploaded</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($documents as $doc): ?>
                                    <tr>
                                        <td class="small"><?= esc($doc['file_name']) ?></td>
                                        <td><span class="badge bg-light text-dark"><?= esc($doc['file_type']) ?></span></td>
                                        <td>
                                            <?php
                                            $scanBadge = [
                                                'completed' => 'bg-success',
                                                'failed'    => 'bg-danger',
                                                'processing'=> 'bg-info',
                                                'pending'   => 'bg-secondary',
                                            ];
                                            ?>
                                            <span class="badge <?= $scanBadge[$doc['scan_status']] ?? 'bg-secondary' ?>">
                                                <?= esc(ucfirst($doc['scan_status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($doc['ai_confidence'] !== null):
                                                $pct = round((float)$doc['ai_confidence'] * 100, 1);
                                                $threshold = AUTO_APPROVE_CONFIDENCE_THRESHOLD * 100;
                                                $cls = $pct >= $threshold ? 'text-success' : ($pct >= 50 ? 'text-warning' : 'text-danger');
                                            ?>
                                                <span class="fw-bold <?= $cls ?>"><?= $pct ?>%</span>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted"><?= esc(date('d M Y H:i', strtotime($doc['uploaded_at']))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- AI Scan Summary -->
            <?php if ($latestCompleted): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-cpu me-2"></i>AI Scan Summary</h6>
                </div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-sm-4">Clinic</dt>
                        <dd class="col-sm-8"><?= esc($parsed['clinic_name'] ?? '—') ?></dd>
                        <dt class="col-sm-4">Total</dt>
                        <dd class="col-sm-8 fw-bold">
                            <?= isset($parsed['total']) ? '$' . number_format((float)$parsed['total'], 2) : '—' ?>
                        </dd>
                        <dt class="col-sm-4">Currency</dt>
                        <dd class="col-sm-8"><?= esc($parsed['currency'] ?? '—') ?></dd>
                        <dt class="col-sm-4">Processor</dt>
                        <dd class="col-sm-8"><code><?= esc($latestCompleted['processor_used'] ?? '—') ?></code></dd>
                    </dl>

                    <?php if (!empty($parsed['line_items']) && is_array($parsed['line_items'])): ?>
                        <hr>
                        <h6 class="small fw-bold">Line Items</h6>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Description</th><th class="text-end">Amount</th></tr></thead>
                                <tbody>
                                <?php foreach ($parsed['line_items'] as $item): ?>
                                    <tr>
                                        <td class="small"><?= esc($item['description'] ?? '') ?></td>
                                        <td class="text-end small">
                                            <?= isset($item['amount']) ? '$' . number_format((float)$item['amount'], 2) : '—' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quotes -->
            <?php if (!empty($quotes)): ?>
            <?php foreach ($quotes as $q): ?>
            <div class="card border-0 shadow-sm mb-4 <?= $q['status'] === 'approved' ? 'border-success' : ($q['status'] === 'rejected' ? 'border-danger' : '') ?>">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="bi bi-receipt me-2"></i>Quote <?= esc($q['reference_id']) ?>
                    </h6>
                    <div class="d-flex gap-2 align-items-center">
                        <?php if ($q['clinic_verified']): ?>
                            <span class="badge bg-success"><i class="bi bi-patch-check me-1"></i>Clinic Verified</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Clinic Not Verified</span>
                        <?php endif; ?>
                        <?php
                        $qStatusMap = [
                            'approved'  => 'bg-success',
                            'rejected'  => 'bg-danger',
                            'flagged'   => 'bg-warning text-dark',
                            'suspended' => 'bg-dark',
                        ];
                        $qBadgeCls = $qStatusMap[$q['status']] ?? 'bg-secondary';
                        ?>
                        <span class="badge <?= $qBadgeCls ?>">
                            <?= esc(ucfirst(str_replace('_', ' ', $q['status']))) ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Auto-decision explanation -->
                    <?php if (in_array($q['status'], ['approved', 'rejected'])): ?>
                    <div class="alert <?= $q['status'] === 'approved' ? 'alert-success' : 'alert-danger' ?> small py-2 mb-3">
                        <i class="bi bi-<?= $q['status'] === 'approved' ? 'check-circle' : 'x-circle' ?> me-1"></i>
                        This quote was <strong>auto-<?= esc($q['status']) ?></strong> based on AI scan confidence
                        (threshold: <?= round(AUTO_APPROVE_CONFIDENCE_THRESHOLD * 100) ?>%).
                    </div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <dl class="row small mb-0">
                                <dt class="col-6">Vet Cost</dt>
                                <dd class="col-6">$<?= number_format((float)$q['total_vet_cost'], 2) ?></dd>
                                <dt class="col-6">Deductible</dt>
                                <dd class="col-6">$<?= number_format((float)$q['deductible'], 2) ?></dd>
                                <dt class="col-6">Coverage</dt>
                                <dd class="col-6"><?= number_format((float)$q['coverage_pct'], 1) ?>%</dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="row small mb-0">
                                <dt class="col-6">We Pay</dt>
                                <dd class="col-6 fw-bold text-success">$<?= number_format((float)$q['covered_amount'], 2) ?></dd>
                                <dt class="col-6">You Pay</dt>
                                <dd class="col-6 fw-bold text-danger">$<?= number_format((float)$q['customer_pays'], 2) ?></dd>
                            </dl>
                        </div>
                    </div>

                    <?php
                    $lineItems = json_decode($q['line_items'] ?? '[]', true) ?: [];
                    if (!empty($lineItems)): ?>
                        <hr>
                        <h6 class="small fw-bold">Itemized Costs</h6>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Description</th><th class="text-end">Amount</th></tr></thead>
                                <tbody>
                                <?php foreach ($lineItems as $item): ?>
                                    <tr>
                                        <td class="small"><?= esc($item['description'] ?? '') ?></td>
                                        <td class="text-end small">$<?= number_format((float)($item['amount'] ?? 0), 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>