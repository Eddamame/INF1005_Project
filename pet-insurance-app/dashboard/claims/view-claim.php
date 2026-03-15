<?php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../auth/middleware.php';

requireLogin();

$userId  = (int) ($_SESSION['user_id'] ?? 0);
$claimId = inputInt('id', 'GET') ?? 0;

if (!$claimId) {
    http_response_code(400);
    die('Missing claim id.');
}

// Load claim with pet, plan, and latest document
$claim = null;
$documents = [];

try {
    $stmt = $db->prepare('
        SELECT c.*, p.name AS pet_name, p.species, p.breed,
               s.id AS subscription_id, ip.name AS plan_name
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
        http_response_code(404);
        die('Claim not found.');
    }

    $stmt = $db->prepare('
        SELECT *
        FROM claim_documents
        WHERE claim_id = :cid
        ORDER BY uploaded_at DESC
    ');
    $stmt->execute([':cid' => $claimId]);
    $documents = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('view-claim: failed loading data: ' . $e->getMessage());
    http_response_code(500);
    die('Unable to load claim.');
}

// Pick latest completed document for preview
$latestCompleted = null;
foreach ($documents as $doc) {
    if ($doc['scan_status'] === 'completed') {
        $latestCompleted = $doc;
        break;
    }
}

$parsed = [];
if ($latestCompleted && !empty($latestCompleted['ai_parsed_data'])) {
    $parsed = json_decode($latestCompleted['ai_parsed_data'], true) ?: [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Claim <?= esc($claim['reference_id']) ?> — Pet Insurance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= esc(generateCsrfToken()); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= base_path() ?>/index.php">🐾 PawShield</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?= base_path() ?>/dashboard/my-pets.php">My Pets</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <span class="nav-link text-light"><?= esc($_SESSION['user_name'] ?? 'User') ?></span>
                </li>
                <li class="nav-item">
                    <a class="nav-link btn btn-outline-light btn-sm px-3 ms-2"
                       href="<?= base_path() ?>/auth/logout.php">Log Out</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Claim <?= esc($claim['reference_id']) ?></h2>
        <span class="badge bg-secondary text-uppercase"><?= esc($claim['status']) ?></span>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header">Pet &amp; Policy</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Pet</dt>
                        <dd class="col-sm-8"><?= esc($claim['pet_name']) ?> (<?= esc(ucfirst($claim['species'])) ?>)</dd>

                        <dt class="col-sm-4">Breed</dt>
                        <dd class="col-sm-8"><?= esc($claim['breed'] ?? '—') ?></dd>

                        <dt class="col-sm-4">Policy</dt>
                        <dd class="col-sm-8"><?= esc($claim['plan_name']) ?></dd>

                        <dt class="col-sm-4">Created</dt>
                        <dd class="col-sm-8"><?= esc($claim['created_at']) ?></dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Visit &amp; Clinic</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Clinic Code</dt>
                        <dd class="col-sm-8"><?= esc($claim['clinic_code'] ?? ($parsed['clinic_code'] ?? '—')) ?></dd>

                        <dt class="col-sm-4">Visit Date</dt>
                        <dd class="col-sm-8"><?= esc($claim['visit_date'] ?? ($parsed['visit_date'] ?? '—')) ?></dd>

                        <dt class="col-sm-4">Description</dt>
                        <dd class="col-sm-8"><?= esc($claim['description'] ?? '—') ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Uploaded Documents</span>
                    <a href="<?= base_path() ?>/dashboard/claims/new-claim.php" class="btn btn-sm btn-outline-primary">
                        + New Claim
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($documents)): ?>
                        <p class="text-muted mb-0">No documents uploaded yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>File</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Uploaded</th>
                                        <th>Confidence</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($documents as $doc): ?>
                                    <tr>
                                        <td><?= esc($doc['file_name']) ?></td>
                                        <td><?= esc($doc['file_type']) ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark"><?= esc($doc['scan_status']) ?></span>
                                        </td>
                                        <td><?= esc($doc['uploaded_at']) ?></td>
                                        <td>
                                            <?php if ($doc['ai_confidence'] !== null): ?>
                                                <?= number_format((float)$doc['ai_confidence'] * 100, 1) ?>%
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">AI Scan Summary</div>
                <div class="card-body">
                    <?php if (!$latestCompleted): ?>
                        <p class="text-muted mb-0">No completed scan yet. Upload a receipt to get an instant summary.</p>
                    <?php else: ?>
                        <dl class="row">
                            <dt class="col-sm-4">Clinic</dt>
                            <dd class="col-sm-8"><?= esc($parsed['clinic_name'] ?? '—') ?></dd>

                            <dt class="col-sm-4">Total</dt>
                            <dd class="col-sm-8">
                                <?php if (isset($parsed['total'])): ?>
                                    <?= number_format((float)$parsed['total'], 2) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </dd>

                            <dt class="col-sm-4">Currency</dt>
                            <dd class="col-sm-8"><?= esc($parsed['currency'] ?? '—') ?></dd>
                        </dl>

                        <?php if (!empty($parsed['line_items']) && is_array($parsed['line_items'])): ?>
                            <h6>Line Items</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Description</th>
                                            <th class="text-end">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($parsed['line_items'] as $item): ?>
                                        <tr>
                                            <td><?= esc($item['description'] ?? '') ?></td>
                                            <td class="text-end">
                                                <?php if (isset($item['amount'])): ?>
                                                    <?= number_format((float)$item['amount'], 2) ?>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

