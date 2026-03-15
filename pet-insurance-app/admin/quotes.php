<?php
/**
 * admin/quotes.php
 * Phase 3: Admin quote management — adjust amounts, override coverage.
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../auth/middleware.php';

requireAdmin();

$success = '';
$error   = '';

// ── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $action  = inputString('action');
    $quoteId = inputInt('quote_id');

    if ($quoteId) {
        if ($action === 'update_amounts') {
            $coveredAmount = inputFloat('covered_amount');
            $customerPays  = inputFloat('customer_pays');
            $coveragePct   = inputFloat('coverage_pct');

            if ($coveredAmount !== null && $customerPays !== null) {
                $stmt = $db->prepare('
                    UPDATE quotes
                    SET covered_amount = :covered, customer_pays = :pays,
                        coverage_pct = :pct, reviewed_at = NOW()
                    WHERE id = :id
                ');
                $stmt->execute([
                    ':covered' => $coveredAmount,
                    ':pays'    => $customerPays,
                    ':pct'     => $coveragePct ?? 0,
                    ':id'      => $quoteId,
                ]);

                // Audit
                $db->prepare("
                    INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address)
                    VALUES (:uid, 'quote_adjusted', 'quote', :eid, :details, :ip)
                ")->execute([
                    ':uid'     => $_SESSION['user_id'],
                    ':eid'     => $quoteId,
                    ':details' => json_encode(['covered_amount' => $coveredAmount, 'customer_pays' => $customerPays]),
                    ':ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);

                $success = "Quote #{$quoteId} amounts updated.";
            } else {
                $error = 'Please provide valid amounts.';
            }
        } elseif ($action === 'change_status') {
            $newStatus = inputString('status');
            $validStatuses = ['draft','pending_review','approved','rejected','paid'];
            if (in_array($newStatus, $validStatuses, true)) {
                $stmt = $db->prepare('UPDATE quotes SET status = :status, reviewed_at = NOW() WHERE id = :id');
                $stmt->execute([':status' => $newStatus, ':id' => $quoteId]);
                $success = "Quote #{$quoteId} status changed to {$newStatus}.";
            }
        }
    }
}

// ── Filter & pagination ──────────────────────────────────────
$statusFilter  = inputString('status', 'GET');
$validStatuses = ['draft','pending_review','approved','rejected','paid'];
if ($statusFilter && !in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = null;
}

$perPage     = 20;
$currentPage = max(1, inputInt('page', 'GET') ?? 1);
$offset      = ($currentPage - 1) * $perPage;

$where  = $statusFilter ? 'WHERE q.status = :status' : '';
$params = $statusFilter ? [':status' => $statusFilter] : [];

// Count
$countStmt = $db->prepare("SELECT COUNT(*) FROM quotes q JOIN claims c ON c.id = q.claim_id {$where}");
$countStmt->execute($params);
$totalQuotes = (int) $countStmt->fetchColumn();
$totalPages  = max(1, (int) ceil($totalQuotes / $perPage));

$sql = "
    SELECT q.*, c.reference_id AS claim_ref, u.first_name, u.last_name,
           p.name AS pet_name, ip.name AS plan_name
    FROM quotes q
    JOIN claims c ON c.id = q.claim_id
    JOIN users u ON u.id = c.user_id
    JOIN pets p ON p.id = c.pet_id
    JOIN subscriptions s ON s.id = c.subscription_id
    JOIN insurance_plans ip ON ip.id = s.plan_id
    {$where}
    ORDER BY q.generated_at DESC
    LIMIT :limit OFFSET :offset
";
$fetchStmt = $db->prepare($sql);
foreach ($params as $k => $v) {
    $fetchStmt->bindValue($k, $v);
}
$fetchStmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$fetchStmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$fetchStmt->execute();
$quotes = $fetchStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Quotes — Admin — PawShield</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/accessibility.css">
</head>
<body>

<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content">
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-receipt me-2"></i>Manage Quotes</h2>
        <a href="<?= base_path() ?>/admin/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= esc($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= esc($error) ?></div><?php endif; ?>

    <!-- Status filter -->
    <div class="mb-3 d-flex flex-wrap gap-2">
        <a href="?page=1" class="btn btn-sm <?= !$statusFilter ? 'btn-dark' : 'btn-outline-secondary' ?>">All</a>
        <?php foreach ($validStatuses as $s): ?>
        <a href="?status=<?= $s ?>&page=1"
           class="btn btn-sm <?= $statusFilter === $s ? 'btn-dark' : 'btn-outline-secondary' ?>">
            <?= ucfirst(str_replace('_', ' ', $s)) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php foreach ($quotes as $q): ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>
                <code><?= esc($q['reference_id']) ?></code>
                — <?= esc($q['first_name'] . ' ' . $q['last_name']) ?>
                — <?= esc($q['pet_name']) ?> (<?= esc($q['plan_name']) ?>)
            </span>
            <span class="badge <?= $q['status'] === 'approved' ? 'bg-success' : ($q['status'] === 'rejected' ? 'bg-danger' : 'bg-warning text-dark') ?>">
                <?= esc(ucfirst(str_replace('_', ' ', $q['status']))) ?>
            </span>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3 align-items-end">
                <?php csrfField(); ?>
                <input type="hidden" name="quote_id" value="<?= (int)$q['id'] ?>">
                <input type="hidden" name="action" value="update_amounts">

                <div class="col-md-2">
                    <label class="form-label small">Vet Cost</label>
                    <input type="text" class="form-control form-control-sm" value="$<?= number_format((float)$q['total_vet_cost'], 2) ?>" disabled>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Coverage %</label>
                    <input type="number" name="coverage_pct" class="form-control form-control-sm"
                           value="<?= number_format((float)$q['coverage_pct'], 2) ?>" step="0.01" min="0" max="100">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">We Pay (override)</label>
                    <input type="number" name="covered_amount" class="form-control form-control-sm"
                           value="<?= number_format((float)$q['covered_amount'], 2) ?>" step="0.01" min="0">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Customer Pays (override)</label>
                    <input type="number" name="customer_pays" class="form-control form-control-sm"
                           value="<?= number_format((float)$q['customer_pays'], 2) ?>" step="0.01" min="0">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-save me-1"></i> Save
                    </button>
                </div>
            </form>
            <hr>
            <form method="POST" class="d-flex gap-2 align-items-center">
                <?php csrfField(); ?>
                <input type="hidden" name="quote_id" value="<?= (int)$q['id'] ?>">
                <input type="hidden" name="action" value="change_status">
                <label class="form-label mb-0 small me-2">Change status:</label>
                <select name="status" class="form-select form-select-sm" style="width:auto;">
                    <?php foreach ($validStatuses as $vs): ?>
                    <option value="<?= $vs ?>" <?= $q['status'] === $vs ? 'selected' : '' ?>>
                        <?= ucfirst(str_replace('_', ' ', $vs)) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-primary">Update</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($quotes)): ?>
        <div class="alert alert-light text-center py-4">No quotes found.</div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
    <nav>
        <ul class="pagination justify-content-center">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $p ?><?= $statusFilter ? '&status=' . $statusFilter : '' ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>

</div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>