<?php
/**
 * admin/claims-review.php
 * Unified claims & quotes review page.
 *
 * Shows all claims with their auto-approve/reject status, quote details,
 * and confidence scores. Admin can flag or suspend claims.
 *
 * Workflow:
 *   User creates claim → uploads receipt → generates quote →
 *   Auto-approved/rejected by confidence → Admin reviews here if needed.
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

// ── Handle POST actions (flag/suspend/restore) ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $action   = inputString('action');
    $claimId  = inputInt('claim_id');
    $notes    = inputString('admin_notes');

    if ($claimId && in_array($action, ['flag', 'suspend', 'restore'], true)) {
        if ($action === 'flag') {
            $newClaimStatus = 'flagged';
            $newQuoteStatus = 'flagged';
        } elseif ($action === 'suspend') {
            $newClaimStatus = 'suspended';
            $newQuoteStatus = 'suspended';
        } else {
            // Restore: revert to the auto-decision status
            // Look up the original auto-decision from audit log
            $auditStmt = $db->prepare("
                SELECT details FROM audit_log
                WHERE entity_type = 'claim' AND entity_id = :cid
                  AND action IN ('auto_approve', 'auto_reject')
                ORDER BY created_at DESC LIMIT 1
            ");
            $auditStmt->execute([':cid' => $claimId]);
            $auditRow = $auditStmt->fetch();
            $originalDecision = 'approved'; // default fallback
            if ($auditRow) {
                $auditDetails = json_decode($auditRow['details'], true);
                $originalDecision = $auditDetails['decision'] ?? 'approved';
            }
            $newClaimStatus = $originalDecision;
            $newQuoteStatus = $originalDecision;
        }

        // Update claim
        $stmt = $db->prepare('UPDATE claims SET status = :status, admin_notes = :notes WHERE id = :id');
        $stmt->execute([
            ':status' => $newClaimStatus,
            ':notes'  => ($action !== 'restore') ? $notes : null,
            ':id'     => $claimId,
        ]);

        // Update associated quotes
        $db->prepare('UPDATE quotes SET status = :status, admin_notes = :notes WHERE claim_id = :cid')
           ->execute([
               ':status' => $newQuoteStatus,
               ':notes'  => ($action !== 'restore') ? $notes : null,
               ':cid'    => $claimId,
           ]);

        // Audit log
        $logStmt = $db->prepare("
            INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address)
            VALUES (:uid, :action, 'claim', :eid, :details, :ip)
        ");
        $logStmt->execute([
            ':uid'     => $_SESSION['user_id'],
            ':action'  => 'admin_' . $action,
            ':eid'     => $claimId,
            ':details' => json_encode([
                'new_status' => $newClaimStatus,
                'admin_notes' => $notes,
            ]),
            ':ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        $actionLabel = ucfirst($action) . ($action === 'restore' ? 'd' : ($action === 'flag' ? 'ged' : 'ed'));
        $success = "Claim #{$claimId} has been {$actionLabel}.";
    }
}

// ── Filter & pagination ──────────────────────────────────────
$statusFilter  = inputString('status', 'GET');
$validStatuses = ['approved', 'rejected', 'flagged', 'suspended', 'draft', 'scanning', 'scanned', 'quoted'];
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
           ip.name AS plan_name,
           q.id AS quote_id, q.reference_id AS quote_ref,
           q.total_vet_cost, q.covered_amount, q.customer_pays,
           q.coverage_pct AS quote_coverage_pct,
           q.deductible AS quote_deductible,
           q.status AS quote_status, q.clinic_verified,
           q.line_items AS quote_line_items,
           cd.ai_confidence
    FROM claims c
    JOIN users u ON u.id = c.user_id
    JOIN pets p ON p.id = c.pet_id
    JOIN subscriptions s ON s.id = c.subscription_id
    JOIN insurance_plans ip ON ip.id = s.plan_id
    LEFT JOIN quotes q ON q.claim_id = c.id
    LEFT JOIN (
        SELECT claim_id, MAX(ai_confidence) AS ai_confidence
        FROM claim_documents
        WHERE scan_status = \'completed\'
        GROUP BY claim_id
    ) cd ON cd.claim_id = c.id
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

// Stats for quick overview
$statsSql = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
        SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) AS flagged,
        SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) AS suspended,
        SUM(CASE WHEN status NOT IN ('approved','rejected','flagged','suspended') THEN 1 ELSE 0 END) AS pending
    FROM claims
";
$stats = $db->query($statsSql)->fetch();

function claimBadge(string $status): string {
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
    if ($status === 'flagged') $icon = '<i class="bi bi-flag-fill me-1"></i>';
    if ($status === 'suspended') $icon = '<i class="bi bi-pause-circle-fill me-1"></i>';
    if ($status === 'approved') $icon = '<i class="bi bi-check-circle-fill me-1"></i>';
    if ($status === 'rejected') $icon = '<i class="bi bi-x-circle-fill me-1"></i>';
    return '<span class="badge ' . $cls . '">' . $icon . htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') . '</span>';
}

function confidenceBadge(?float $confidence): string {
    if ($confidence === null) return '<span class="text-muted">—</span>';
    $pct = round($confidence * 100, 1);
    $threshold = defined('AUTO_APPROVE_CONFIDENCE_THRESHOLD') ? AUTO_APPROVE_CONFIDENCE_THRESHOLD * 100 : 80;
    if ($pct >= $threshold) {
        return '<span class="badge bg-success">' . $pct . '%</span>';
    } elseif ($pct >= 50) {
        return '<span class="badge bg-warning text-dark">' . $pct . '%</span>';
    } else {
        return '<span class="badge bg-danger">' . $pct . '%</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Claims Review — Admin — PawShield</title>
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
        <h2><i class="bi bi-clipboard2-check me-2"></i>Claims Review</h2>
        <a href="<?= base_path() ?>/admin/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= esc($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= esc($error) ?></div>
    <?php endif; ?>

    <!-- Quick stats -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="fw-bold fs-4"><?= number_format($stats['total'] ?? 0) ?></div>
                    <div class="text-muted small">Total</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <div class="card border-0 shadow-sm h-100 border-start border-success border-3">
                <div class="card-body text-center py-3">
                    <div class="fw-bold fs-4 text-success"><?= number_format($stats['approved'] ?? 0) ?></div>
                    <div class="text-muted small">Auto-Approved</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <div class="card border-0 shadow-sm h-100 border-start border-danger border-3">
                <div class="card-body text-center py-3">
                    <div class="fw-bold fs-4 text-danger"><?= number_format($stats['rejected'] ?? 0) ?></div>
                    <div class="text-muted small">Auto-Rejected</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <div class="card border-0 shadow-sm h-100 border-start border-warning border-3">
                <div class="card-body text-center py-3">
                    <div class="fw-bold fs-4 text-warning"><?= number_format($stats['flagged'] ?? 0) ?></div>
                    <div class="text-muted small">Flagged</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <div class="card border-0 shadow-sm h-100 border-start border-dark border-3">
                <div class="card-body text-center py-3">
                    <div class="fw-bold fs-4"><?= number_format($stats['suspended'] ?? 0) ?></div>
                    <div class="text-muted small">Suspended</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <div class="card border-0 shadow-sm h-100 border-start border-info border-3">
                <div class="card-body text-center py-3">
                    <div class="fw-bold fs-4 text-info"><?= number_format($stats['pending'] ?? 0) ?></div>
                    <div class="text-muted small">In Progress</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Info banner about auto-approval -->
    <div class="alert alert-info small mb-4">
        <i class="bi bi-info-circle me-1"></i>
        Claims are automatically approved when AI confidence is <strong>&ge; <?= round(AUTO_APPROVE_CONFIDENCE_THRESHOLD * 100) ?>%</strong>
        and auto-rejected when below. Use Flag or Suspend for claims that need manual review.
    </div>

    <!-- Status filter -->
    <div class="mb-3 d-flex flex-wrap gap-2">
        <a href="?page=1" class="btn btn-sm <?= !$statusFilter ? 'btn-dark' : 'btn-outline-secondary' ?>">All</a>
        <a href="?status=approved&page=1" class="btn btn-sm <?= $statusFilter === 'approved' ? 'btn-dark' : 'btn-outline-secondary' ?>">Approved</a>
        <a href="?status=rejected&page=1" class="btn btn-sm <?= $statusFilter === 'rejected' ? 'btn-dark' : 'btn-outline-secondary' ?>">Rejected</a>
        <a href="?status=flagged&page=1" class="btn btn-sm <?= $statusFilter === 'flagged' ? 'btn-dark' : 'btn-outline-secondary' ?>">
            <i class="bi bi-flag-fill me-1"></i>Flagged
        </a>
        <a href="?status=suspended&page=1" class="btn btn-sm <?= $statusFilter === 'suspended' ? 'btn-dark' : 'btn-outline-secondary' ?>">
            <i class="bi bi-pause-circle me-1"></i>Suspended
        </a>
        <a href="?status=scanned&page=1" class="btn btn-sm <?= $statusFilter === 'scanned' ? 'btn-dark' : 'btn-outline-secondary' ?>">Scanned</a>
        <a href="?status=quoted&page=1" class="btn btn-sm <?= $statusFilter === 'quoted' ? 'btn-dark' : 'btn-outline-secondary' ?>">Quoted</a>
    </div>

    <p class="text-muted small"><?= number_format($totalClaims) ?> claim(s).</p>

    <!-- Claims list -->
    <?php if (empty($claims)): ?>
        <div class="alert alert-light text-center py-4">No claims found.</div>
    <?php endif; ?>

    <?php foreach ($claims as $c): ?>
    <div class="card mb-3 <?= in_array($c['status'], ['flagged', 'suspended']) ? 'border-warning' : '' ?>">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <code class="fw-bold"><?= esc($c['reference_id']) ?></code>
                <?= claimBadge($c['status']) ?>
                <?php if ($c['clinic_verified']): ?>
                    <span class="badge bg-success ms-1"><i class="bi bi-patch-check me-1"></i>Clinic Verified</span>
                <?php endif; ?>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted">Confidence:</span>
                <?= confidenceBadge($c['ai_confidence'] !== null ? (float)$c['ai_confidence'] : null) ?>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <!-- Claim info -->
                <div class="col-md-4">
                    <h6 class="small fw-bold text-muted mb-2">CLAIM DETAILS</h6>
                    <div class="small">
                        <div class="mb-1">
                            <strong>Submitted by:</strong> <?= esc($c['first_name'] . ' ' . $c['last_name']) ?>
                            <br><span class="text-muted"><?= esc($c['email']) ?></span>
                        </div>
                        <div class="mb-1"><strong>Pet:</strong> <?= esc($c['pet_name']) ?> (<?= esc(ucfirst($c['species'])) ?>)</div>
                        <div class="mb-1"><strong>Plan:</strong> <?= esc($c['plan_name']) ?></div>
                        <div class="mb-1"><strong>Created:</strong> <?= esc(date('d M Y H:i', strtotime($c['created_at']))) ?></div>
                        <?php if ($c['visit_date']): ?>
                            <div class="mb-1"><strong>Visit:</strong> <?= esc(date('d M Y', strtotime($c['visit_date']))) ?></div>
                        <?php endif; ?>
                        <?php if ($c['clinic_code']): ?>
                            <div class="mb-1"><strong>Clinic:</strong> <code><?= esc($c['clinic_code']) ?></code></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quote info -->
                <div class="col-md-4">
                    <h6 class="small fw-bold text-muted mb-2">QUOTE DETAILS</h6>
                    <?php if ($c['quote_id']): ?>
                        <div class="small">
                            <div class="mb-1"><strong>Quote Ref:</strong> <code><?= esc($c['quote_ref']) ?></code></div>
                            <div class="mb-1"><strong>Vet Cost:</strong> $<?= number_format((float)$c['total_vet_cost'], 2) ?></div>
                            <div class="mb-1"><strong>Deductible:</strong> $<?= number_format((float)$c['quote_deductible'], 2) ?></div>
                            <div class="mb-1"><strong>Coverage:</strong> <?= number_format((float)$c['quote_coverage_pct'], 1) ?>%</div>
                            <div class="mb-1">
                                <strong>We Pay:</strong>
                                <span class="text-success fw-bold">$<?= number_format((float)$c['covered_amount'], 2) ?></span>
                            </div>
                            <div class="mb-1">
                                <strong>Customer Pays:</strong>
                                <span class="text-danger fw-bold">$<?= number_format((float)$c['customer_pays'], 2) ?></span>
                            </div>
                        </div>
                        <?php
                        $lineItems = json_decode($c['quote_line_items'] ?? '[]', true) ?: [];
                        if (!empty($lineItems)): ?>
                            <details class="mt-2">
                                <summary class="small text-muted" style="cursor:pointer;">View line items (<?= count($lineItems) ?>)</summary>
                                <div class="table-responsive mt-1">
                                    <table class="table table-sm mb-0">
                                        <thead><tr><th class="small">Description</th><th class="small text-end">Amount</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($lineItems as $item): ?>
                                            <tr>
                                                <td class="small"><?= esc($item['description'] ?? '') ?></td>
                                                <td class="small text-end">$<?= number_format((float)($item['amount'] ?? 0), 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-muted small mb-0">No quote generated yet.</p>
                    <?php endif; ?>
                </div>

                <!-- Admin actions -->
                <div class="col-md-4">
                    <h6 class="small fw-bold text-muted mb-2">ADMIN ACTIONS</h6>

                    <?php if (!empty($c['admin_notes'])): ?>
                        <div class="alert alert-warning small py-2 px-3 mb-2">
                            <i class="bi bi-sticky me-1"></i>
                            <strong>Admin note:</strong> <?= esc($c['admin_notes']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!in_array($c['status'], ['flagged', 'suspended'], true)): ?>
                        <!-- Flag button -->
                        <button class="btn btn-sm btn-outline-warning me-1 mb-1" type="button"
                                data-bs-toggle="modal"
                                data-bs-target="#flagModal<?= (int)$c['id'] ?>">
                            <i class="bi bi-flag me-1"></i>Flag
                        </button>
                        <!-- Suspend button -->
                        <button class="btn btn-sm btn-outline-dark me-1 mb-1" type="button"
                                data-bs-toggle="modal"
                                data-bs-target="#suspendModal<?= (int)$c['id'] ?>">
                            <i class="bi bi-pause-circle me-1"></i>Suspend
                        </button>
                    <?php else: ?>
                        <!-- Restore button -->
                        <form method="POST" class="d-inline">
                            <?php csrfField(); ?>
                            <input type="hidden" name="claim_id" value="<?= (int)$c['id'] ?>">
                            <input type="hidden" name="action" value="restore">
                            <button class="btn btn-sm btn-outline-success mb-1"
                                    onclick="return confirm('Restore this claim to its original auto-decision status?')">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Restore
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Flag Modal -->
                    <div class="modal fade" id="flagModal<?= (int)$c['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="claim_id" value="<?= (int)$c['id'] ?>">
                                    <input type="hidden" name="action" value="flag">
                                    <div class="modal-header">
                                        <h5 class="modal-title">
                                            <i class="bi bi-flag-fill text-warning me-2"></i>
                                            Flag Claim <?= esc($c['reference_id']) ?>
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="text-muted small">
                                            Flag this claim for review. The claim will be marked as flagged and the customer will see a "under review" status.
                                        </p>
                                        <label for="flag-reason-<?= (int)$c['id'] ?>" class="form-label">
                                            Reason <span class="text-danger">*</span>
                                        </label>
                                        <textarea id="flag-reason-<?= (int)$c['id'] ?>" name="admin_notes"
                                                  class="form-control" rows="3"
                                                  placeholder="Why are you flagging this claim?" required></textarea>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-warning">
                                            <i class="bi bi-flag me-1"></i>Flag Claim
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Suspend Modal -->
                    <div class="modal fade" id="suspendModal<?= (int)$c['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="claim_id" value="<?= (int)$c['id'] ?>">
                                    <input type="hidden" name="action" value="suspend">
                                    <div class="modal-header">
                                        <h5 class="modal-title">
                                            <i class="bi bi-pause-circle-fill me-2"></i>
                                            Suspend Claim <?= esc($c['reference_id']) ?>
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="text-muted small">
                                            Suspending a claim freezes processing entirely. Use this for suspected fraud or policy violations.
                                        </p>
                                        <label for="suspend-reason-<?= (int)$c['id'] ?>" class="form-label">
                                            Reason <span class="text-danger">*</span>
                                        </label>
                                        <textarea id="suspend-reason-<?= (int)$c['id'] ?>" name="admin_notes"
                                                  class="form-control" rows="3"
                                                  placeholder="Why are you suspending this claim?" required></textarea>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-dark">
                                            <i class="bi bi-pause-circle me-1"></i>Suspend Claim
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if ($totalPages > 1): ?>
    <nav>
        <ul class="pagination justify-content-center">
            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $currentPage - 1 ?><?= $statusFilter ? '&status=' . $statusFilter : '' ?>">&laquo;</a>
            </li>
            <?php
            $startP = max(1, $currentPage - 4);
            $endP   = min($totalPages, $startP + 9);
            for ($p = $startP; $p <= $endP; $p++): ?>
            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $p ?><?= $statusFilter ? '&status=' . $statusFilter : '' ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $currentPage + 1 ?><?= $statusFilter ? '&status=' . $statusFilter : '' ?>">&raquo;</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

</div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>