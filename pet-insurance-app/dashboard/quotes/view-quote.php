<?php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../auth/middleware.php';

requireLogin();

$userId  = (int) ($_SESSION['user_id'] ?? 0);
$quoteId = inputInt('id', 'GET') ?? 0;

if (!$quoteId) {
    http_response_code(400);
    die('Missing quote id.');
}

// Load quote, claim, pet, plan
$quote = null;
$lineItems = [];

try {
    $stmt = $db->prepare('
        SELECT q.*, c.reference_id AS claim_ref, c.status AS claim_status,
               p.name AS pet_name, p.species,
               ip.name AS plan_name, ip.coverage_pct AS plan_coverage_pct, ip.deductible AS plan_deductible
        FROM quotes q
        JOIN claims c ON c.id = q.claim_id
        JOIN pets p   ON p.id = c.pet_id
        JOIN subscriptions s ON s.id = c.subscription_id
        JOIN insurance_plans ip ON ip.id = s.plan_id
        WHERE q.id = :id AND c.user_id = :uid
        LIMIT 1
    ');
    $stmt->execute([':id' => $quoteId, ':uid' => $userId]);
    $quote = $stmt->fetch();

    if (!$quote) {
        http_response_code(404);
        die('Quote not found.');
    }

    if (!empty($quote['line_items'])) {
        $lineItems = json_decode($quote['line_items'], true) ?: [];
    }
} catch (Throwable $e) {
    error_log('view-quote: failed loading data: ' . $e->getMessage());
    http_response_code(500);
    die('Unable to load quote.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quote <?= esc($quote['reference_id']) ?> — Pet Insurance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
        <h2>Quote <?= esc($quote['reference_id']) ?></h2>
        <span class="badge bg-secondary text-uppercase"><?= esc($quote['status']) ?></span>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">Overview</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Pet</dt>
                        <dd class="col-sm-7"><?= esc($quote['pet_name']) ?> (<?= esc(ucfirst($quote['species'])) ?>)</dd>

                        <dt class="col-sm-5">Plan</dt>
                        <dd class="col-sm-7"><?= esc($quote['plan_name']) ?></dd>

                        <dt class="col-sm-5">Claim</dt>
                        <dd class="col-sm-7">
                            <a href="<?= base_path() ?>/dashboard/claims/view-claim.php?id=<?= (int)$quote['claim_id'] ?>">
                                <?= esc($quote['claim_ref']) ?>
                            </a>
                        </dd>

                        <dt class="col-sm-5">Clinic Verified</dt>
                        <dd class="col-sm-7">
                            <?php if ($quote['clinic_verified']): ?>
                                <span class="badge bg-success">Yes</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Not yet</span>
                            <?php endif; ?>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header">Payout Breakdown</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-sm-6">Total vet cost</dt>
                                <dd class="col-sm-6">
                                    <span data-money="<?= esc((string)$quote['total_vet_cost']) ?>"></span>
                                </dd>

                                <dt class="col-sm-6">Deductible</dt>
                                <dd class="col-sm-6">
                                    <span data-money="<?= esc((string)$quote['deductible']) ?>"></span>
                                </dd>

                                <dt class="col-sm-6">Coverage</dt>
                                <dd class="col-sm-6">
                                    <?= number_format((float)$quote['coverage_pct'], 1) ?>%
                                </dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-sm-6">We pay</dt>
                                <dd class="col-sm-6 fw-bold text-success">
                                    <span data-money="<?= esc((string)$quote['covered_amount']) ?>"></span>
                                </dd>

                                <dt class="col-sm-6">You pay</dt>
                                <dd class="col-sm-6 fw-bold text-danger">
                                    <span data-money="<?= esc((string)$quote['customer_pays']) ?>"></span>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($lineItems)): ?>
            <div class="card">
                <div class="card-header">Itemized costs</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($lineItems as $item): ?>
                                <tr>
                                    <td><?= esc($item['description'] ?? '') ?></td>
                                    <td class="text-end">
                                        <?php if (isset($item['amount'])): ?>
                                            <span data-money="<?= esc((string)$item['amount']) ?>"></span>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_path() ?>/assets/js/quote-dashboard.js"></script>
</body>
</html>

