<?php
/**
 * dashboard/claims/new-claim.php
 * Create a new claim, then upload a receipt for AI scanning.
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_once __DIR__ . '/../../services/ReferenceGenerator.php';

requireLogin();

/** @var PDO $db */

$userId  = (int) ($_SESSION['user_id'] ?? 0);
$errors  = [];
$success = '';
$claimId = null;

// Check if continuing a draft claim
$continueDraft = inputInt('continue', 'GET');
if ($continueDraft) {
    $stmt = $db->prepare('
        SELECT c.*, p.name AS pet_name, p.species, ip.name AS plan_name
        FROM claims c
        JOIN pets p ON p.id = c.pet_id
        JOIN subscriptions s ON s.id = c.subscription_id
        JOIN insurance_plans ip ON ip.id = s.plan_id
        WHERE c.id = :id AND c.user_id = :uid AND c.status = :status
        LIMIT 1
    ');
    $stmt->execute([':id' => $continueDraft, ':uid' => $userId, ':status' => 'draft']);
    $draftClaim = $stmt->fetch();
    if ($draftClaim) {
        $claimId = (int) $draftClaim['id'];
        $success = 'Continuing draft claim ' . esc($draftClaim['reference_id']) . '. Upload your receipt below.';
    }
}

// Load user's pets with active subscriptions (only needed if no claim yet)
$petsWithPolicies = [];
if (!$claimId) {
    try {
        $stmt = $db->prepare('
            SELECT p.id AS pet_id, p.name AS pet_name, p.species,
                   s.id AS subscription_id, ip.name AS plan_name
            FROM pets p
            JOIN subscriptions s ON s.pet_id = p.id AND s.user_id = p.user_id AND s.status = :status
            JOIN insurance_plans ip ON ip.id = s.plan_id
            WHERE p.user_id = :uid
            ORDER BY p.name ASC
        ');
        $stmt->execute([':uid' => $userId, ':status' => 'active']);
        $petsWithPolicies = $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('new-claim: failed loading pets/subscriptions: ' . $e->getMessage());
    }
}

// Handle form submission (only if not continuing a draft)
if (!$claimId && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $subscriptionId = inputInt('subscription_id');
    $desc = inputString('description');

    if (!$subscriptionId) {
        $errors['subscription_id'] = 'Please select a pet and policy.';
    }

    $selectedSub = null;
    if ($subscriptionId) {
        foreach ($petsWithPolicies as $pp) {
            if ((int)$pp['subscription_id'] === $subscriptionId) {
                $selectedSub = $pp;
                break;
            }
        }
        if (!$selectedSub) {
            $errors['subscription_id'] = 'Invalid policy selected.';
        }
    }

    if (!$errors && $selectedSub) {
        try {
            $ref = ReferenceGenerator::uniqueClaim($db);
            $stmt = $db->prepare('
                INSERT INTO claims (reference_id, user_id, pet_id, subscription_id, description, status)
                VALUES (:ref, :uid, :pet_id, :sub_id, :description, :status)
            ');
            $stmt->execute([
                ':ref'         => $ref,
                ':uid'         => $userId,
                ':pet_id'      => (int)$selectedSub['pet_id'],
                ':sub_id'      => $subscriptionId,
                ':description' => $desc ?: null,
                ':status'      => 'draft',
            ]);
            $claimId = (int) $db->lastInsertId();
            header('Location: ' . BASE_PATH . '/dashboard/claims/new-claim.php?continue=' . $claimId);
            exit;
        } catch (Throwable $e) {
            error_log('new-claim: failed creating claim: ' . $e->getMessage());
            $errors['general'] = 'Could not create claim. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Claim — PawShield</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= esc(generateCsrfToken()); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/accessibility.css">
    <style>
        .upload-dropzone {
            border: 2px dashed var(--ps-gray-200);
            border-radius: var(--radius-lg);
            padding: 2.5rem;
            text-align: center;
            transition: border-color 0.2s, background 0.2s;
            cursor: pointer;
        }
        .upload-dropzone.is-dragover {
            border-color: var(--ps-teal);
            background: var(--ps-teal-light);
        }
        .upload-dropzone.is-uploading {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

<main id="main-content">
<div class="container py-5 claims-new-page" style="max-width: 960px;">

    <a href="<?= base_path() ?>/dashboard/claims/index.php" class="btn btn-outline-secondary btn-sm mb-4">
        <i class="bi bi-arrow-left me-1"></i> Back to Claims
    </a>

    <h2 class="mb-4 claims-new-title"><i class="bi bi-file-earmark-plus me-2"></i>Start a New Claim</h2>

    <div class="row g-4">
        <!-- Left: Create Claim Form -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm claim-step-card claim-step-card--details">
                <div class="card-body">
                    <h5 class="card-title mb-3">1. Claim Details</h5>

                    <?php if (!empty($errors['general'])): ?>
                        <div class="alert alert-danger"><?= esc($errors['general']) ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= esc($success) ?></div>
                    <?php endif; ?>

                    <?php if (!$claimId): ?>
                        <?php if (empty($petsWithPolicies)): ?>
                            <div class="alert alert-warning claim-policy-alert">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                You need an active policy before you can submit a claim.
                                <a href="<?= base_path() ?>/dashboard/my-pets.php">Go to My Pets</a> to purchase coverage.
                            </div>
                        <?php else: ?>
                            <form method="POST" novalidate>
                                <?= csrfField() ?>

                                <div class="mb-3">
                                    <label class="form-label">Pet & Policy</label>
                                    <select name="subscription_id"
                                            class="form-select <?= isset($errors['subscription_id']) ? 'is-invalid' : '' ?>" required>
                                        <option value="">— Select a pet with an active policy —</option>
                                        <?php foreach ($petsWithPolicies as $pp): ?>
                                            <option value="<?= (int)$pp['subscription_id'] ?>">
                                                <?= esc($pp['pet_name']) ?> (<?= esc(ucfirst($pp['species'])) ?>)
                                                — <?= esc($pp['plan_name']) ?> plan
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback"><?= esc($errors['subscription_id'] ?? '') ?></div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Description <span class="text-muted">(optional)</span></label>
                                    <textarea name="description" class="form-control" rows="3"
                                              placeholder="E.g. Emergency visit for Bruno's leg injury"></textarea>
                                </div>

                                <button class="btn btn-primary w-100">
                                    <i class="bi bi-plus-circle me-1"></i>Create Claim
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 2rem;"></i>
                            <p class="mt-2 mb-0">Claim created. Upload your receipt on the right.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Upload Receipt -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm claim-step-card claim-step-card--upload">
                <div class="card-body">
                    <h5 class="card-title mb-3">2. Upload Vet Receipt</h5>

                    <?php if ($claimId): ?>
                        <p class="text-muted small">
                            Upload a photo or PDF of your vet receipt. Our AI will scan it and extract the details automatically.
                        </p>
                        <div class="upload-dropzone claim-upload-dropzone"
                             id="receipt-dropzone"
                             data-receipt-dropzone
                             data-claim-id="<?= (int) $claimId ?>"
                             data-file-type="receipt">
                            <input type="file" id="receipt-file-input" name="document"
                                   accept="image/*,.pdf" hidden>
                            <i class="bi bi-cloud-arrow-up" style="font-size: 2.5rem; color: var(--ps-teal);"></i>
                            <p class="mt-2 mb-1">
                                Drag & drop your receipt here, or
                                <button type="button" class="btn btn-sm btn-outline-primary" data-upload-trigger>browse files</button>
                            </p>
                            <p class="small text-muted mb-0">JPEG, PNG, WebP, TIFF, or PDF. Max 10 MB.</p>
                            <div class="mt-2 small fw-semibold" data-upload-status></div>
                        </div>

                        <!-- Scan results preview -->
                        <div class="mt-4" id="scan-preview" hidden>
                            <h6><i class="bi bi-cpu me-1"></i>AI Scan Results</h6>
                            <div class="border rounded p-3">
                                <dl class="row mb-0 small">
                                    <dt class="col-sm-4">Clinic</dt>
                                    <dd class="col-sm-8" id="scan-clinic-name">—</dd>
                                    <dt class="col-sm-4">Clinic Code</dt>
                                    <dd class="col-sm-8" id="scan-clinic-code">—</dd>
                                    <dt class="col-sm-4">Visit Date</dt>
                                    <dd class="col-sm-8" id="scan-visit-date">—</dd>
                                    <dt class="col-sm-4">Total</dt>
                                    <dd class="col-sm-8 fw-bold" id="scan-total">—</dd>
                                    <dt class="col-sm-4">Confidence</dt>
                                    <dd class="col-sm-8" id="scan-confidence">—</dd>
                                </dl>

                                <div id="scan-line-items" class="mt-2" hidden>
                                    <h6 class="small fw-bold">Line Items</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0" id="scan-items-table">
                                            <thead><tr><th>Description</th><th class="text-end">Amount</th></tr></thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 d-flex gap-2">
                                <a href="<?= base_path() ?>/dashboard/claims/view-claim.php?id=<?= (int)$claimId ?>"
                                   class="btn btn-primary btn-sm">
                                    <i class="bi bi-eye me-1"></i>View Full Claim
                                </a>
                                <a href="<?= base_path() ?>/dashboard/claims/index.php"
                                   class="btn btn-outline-secondary btn-sm">
                                    Back to Claims
                                </a>
                            </div>
                        </div>

                        <!-- Generate Quote button (shown after successful scan) -->
                        <div class="mt-3" id="generate-quote-section" hidden>
                            <form method="POST" action="<?= base_path() ?>/dashboard/claims/generate-quote.php">
                                <?= csrfField() ?>
                                <input type="hidden" name="claim_id" value="<?= (int)$claimId ?>">
                                <button class="btn btn-success w-100">
                                    <i class="bi bi-calculator me-1"></i>Generate Reimbursement Quote
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 claim-upload-empty">
                            <i class="bi bi-arrow-left-circle" style="font-size: 2rem; color: var(--ps-gray-400);"></i>
                            <p class="text-muted mt-2 mb-0">Create a claim first, then upload your receipt here.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script src="<?= base_path() ?>/assets/js/file-upload.js"></script>
<?php if ($claimId): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var zone = document.getElementById('receipt-dropzone');
    if (!zone) return;

    zone.addEventListener('receipt:upload:success', function (event) {
        var data = event.detail || {};
        var scan = data.scan || {};
        var parsed = scan.parsed_data || {};

        var preview = document.getElementById('scan-preview');
        if (!preview) return;

        document.getElementById('scan-clinic-name').textContent = parsed.clinic_name || '—';
        document.getElementById('scan-clinic-code').textContent = parsed.clinic_code || '—';
        document.getElementById('scan-visit-date').textContent = parsed.visit_date || '—';
        document.getElementById('scan-total').textContent = parsed.total != null ? '$' + parseFloat(parsed.total).toFixed(2) : '—';
        document.getElementById('scan-confidence').textContent = scan.confidence != null ? (scan.confidence * 100).toFixed(1) + '%' : '—';

        // Line items
        var items = parsed.line_items || [];
        var itemsSection = document.getElementById('scan-line-items');
        var tbody = document.querySelector('#scan-items-table tbody');
        tbody.innerHTML = '';

        if (items.length > 0) {
            items.forEach(function(item) {
                var row = document.createElement('tr');
                row.innerHTML = '<td>' + (item.description || '—') + '</td>'
                    + '<td class="text-end">$' + (item.amount != null ? parseFloat(item.amount).toFixed(2) : '—') + '</td>';
                tbody.appendChild(row);
            });
            itemsSection.hidden = false;
        }

        preview.hidden = false;

        // Show generate quote button if scan was successful
        if (scan.success) {
            var quoteSection = document.getElementById('generate-quote-section');
            if (quoteSection) quoteSection.hidden = false;
        }
    });
});
</script>
<?php endif; ?>