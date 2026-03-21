<?php
/**
 * admin/claims.php
 * Phase 3: Admin claim management — filter by status, approve/reject.
 * - Shows which user submitted each claim
 * - Prompts admin for a reason when rejecting a claim
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../auth/middleware.php';

requireAdmin();

$success = '';

// ── Handle POST actions (approve/reject) ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $action  = inputString('action');
    $claimId = inputInt('claim_id');

    if ($claimId && in_array($action, ['approve', 'reject'], true)) {
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';

        // For rejections, capture the reason
        $rejectReason = null;
        if ($action === 'reject') {
            $rejectReason = inputString('reject_reason');
            if (!$rejectReason) {
                $rejectReason = 'No reason provided.';
            }
        }

        $stmt = $db->prepare('UPDATE claims SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $newStatus, ':id' => $claimId]);

        // Log the action (include rejection reason in details)
        $logDetails = ['new_status' => $newStatus];
        if ($rejectReason) {
            $logDetails['reject_reason'] = $rejectReason;
        }

        $logStmt = $db->prepare("
            INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address)
            VALUES (:uid, :action, 'claim', :eid, :details, :ip)
        ");
        $logStmt->execute([
            ':uid'     => $_SESSION['user_id'],
            ':action'  => 'claim_' . $action,
            ':eid'     => $claimId,
            ':details' => json_encode($logDetails),
            ':ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        $success = "Claim #{$claimId} has been {$newStatus}.";
        if ($rejectReason && $action === 'reject') {
            $success .= " Reason: " . htmlspecialchars($rejectReason, ENT_QUOTES, 'UTF-8');
        }
    }
}

// ── Filter & pagination ──────────────────────────────────────
$statusFilter  = inputString('status', 'GET');
$validStatuses = ['draft','scanning','scanned','quoted','verified','approved','rejected'];
if ($statusFilter && !in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = null;
}

$perPage     = 20;
$currentPage = max(1, inputInt('page', 'GET') ?? 1);
$offset      = ($currentPage - 1) * $perPage;

$countSql = '
    SELECT COUNT(*)
    FROM claims c
    JOIN users u ON u.id = c.user_id
';
$fetchSql = '
    SELECT c.*, u.first_name, u.last_name, u.email,
           p.name AS pet_name, p.species,
           ip.name AS plan_name
    FROM claims c
    JOIN users u ON u.id = c.user_id
    JOIN pets p ON p.id = c.pet_id
    JOIN subscriptions s ON s.id = c.subscription_id
    JOIN insurance_plans ip ON ip.id = s.plan_id
';

$params = [];
if ($statusFilter) {
    $countSql .= ' WHERE c.status = :status';
    $fetchSql .= ' WHERE c.status = :status';
    $params[':status'] = $statusFilter;
}

$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalClaims = (int) $countStmt->fetchColumn();
$totalPages  = max(1, (int) ceil($totalClaims / $perPage));

$fetchSql .= ' ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset';
$fetchStmt = $db->prepare($fetchSql);
foreach ($params as $k => $v) {
    $fetchStmt->bindValue($k, $v);
}
$fetchStmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$fetchStmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$fetchStmt->execute();
$claims = $fetchStmt->fetchAll();

function adminClaimBadge(string $status): string {
    $map = [
        'draft'    => 'bg-secondary',
        'scanning' => 'bg-info text-dark',
        'scanned'  => 'bg-primary',
        'quoted'   => 'bg-warning text-dark',
        'verified' => 'bg-success',
        'approved' => 'bg-success',
        'rejected' => 'bg-danger',
    ];
    $cls = $map[$status] ?? 'bg-secondary';
    return '<span class="badge ' . $cls . '">' . htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Claims — Admin — PawShield</title>
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
        <h2><i class="bi bi-file-earmark-check me-2"></i>Manage Claims</h2>
        <a href="<?= base_path() ?>/admin/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= esc($success) ?></div>
    <?php endif; ?>

    <!-- Status filter -->
    <div class="mb-3 d-flex flex-wrap gap-2">
        <a href="?page=1" class="btn btn-sm <?= !$statusFilter ? 'btn-dark' : 'btn-outline-secondary' ?>">All</a>
        <?php foreach ($validStatuses as $s): ?>
        <a href="?status=<?= $s ?>&page=1"
           class="btn btn-sm <?= $statusFilter === $s ? 'btn-dark' : 'btn-outline-secondary' ?>"><?= ucfirst($s) ?></a>
        <?php endforeach; ?>
    </div>

    <p class="text-muted small"><?= number_format($totalClaims) ?> claim(s).</p>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Reference</th>
                    <th>Submitted By</th>
                    <th>Pet</th>
                    <th>Plan</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($claims as $c): ?>
                <tr>
                    <td><code><?= esc($c['reference_id']) ?></code></td>
                    <td>
                        <?= esc($c['first_name'] . ' ' . $c['last_name']) ?>
                        <br><small class="text-muted"><?= esc($c['email']) ?></small>
                    </td>
                    <td><?= esc($c['pet_name']) ?> <small>(<?= esc(ucfirst($c['species'])) ?>)</small></td>
                    <td><?= esc($c['plan_name']) ?></td>
                    <td><?= adminClaimBadge($c['status']) ?></td>
                    <td><?= esc(date('d M Y', strtotime($c['created_at']))) ?></td>
                    <td>
                        <?php if (!in_array($c['status'], ['approved', 'rejected'], true)): ?>
                        <form method="POST" class="d-inline">
                            <?php csrfField(); ?>
                            <input type="hidden" name="claim_id" value="<?= (int)$c['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button class="btn btn-sm btn-success" title="Approve"
                                    onclick="return confirm('Approve this claim?')">
                                <i class="bi bi-check-lg"></i>
                            </button>
                        </form>
                        <button class="btn btn-sm btn-danger" title="Reject"
                                data-bs-toggle="modal"
                                data-bs-target="#rejectModal<?= (int)$c['id'] ?>">
                            <i class="bi bi-x-lg"></i>
                        </button>

                        <!-- Reject Modal -->
                        <div class="modal fade" id="rejectModal<?= (int)$c['id'] ?>" tabindex="-1"
                             aria-labelledby="rejectModalLabel<?= (int)$c['id'] ?>" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST">
                                        <?php csrfField(); ?>
                                        <input type="hidden" name="claim_id" value="<?= (int)$c['id'] ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="rejectModalLabel<?= (int)$c['id'] ?>">
                                                Reject Claim <?= esc($c['reference_id']) ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="text-muted small mb-2">
                                                Submitted by <strong><?= esc($c['first_name'] . ' ' . $c['last_name']) ?></strong>
                                                for <strong><?= esc($c['pet_name']) ?></strong>.
                                            </p>
                                            <label for="reject-reason-<?= (int)$c['id'] ?>" class="form-label">
                                                Reason for rejection <span class="text-danger">*</span>
                                            </label>
                                            <textarea
                                                id="reject-reason-<?= (int)$c['id'] ?>"
                                                name="reject_reason"
                                                class="form-control"
                                                rows="3"
                                                placeholder="Please provide a reason for rejecting this claim…"
                                                required></textarea>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-danger">
                                                <i class="bi bi-x-lg me-1"></i>Reject Claim
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                            <span class="text-muted small">Finalized</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

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