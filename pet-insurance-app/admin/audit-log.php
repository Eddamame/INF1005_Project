<?php
/**
 * admin/audit-log.php
 * Phase 3: Paginated read-only audit log.
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../auth/middleware.php';

requireAdmin();

// ── Filter by action ─────────────────────────────────────────
$actionFilter = inputString('action_type', 'GET');

// ── Pagination ───────────────────────────────────────────────
$perPage     = 30;
$currentPage = max(1, inputInt('page', 'GET') ?? 1);
$offset      = ($currentPage - 1) * $perPage;

$where  = '';
$params = [];
if ($actionFilter) {
    $where  = ' WHERE al.action = :action';
    $params[':action'] = $actionFilter;
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM audit_log al{$where}");
$countStmt->execute($params);
$total      = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$sql = "
    SELECT al.*, u.first_name, u.last_name, u.email
    FROM audit_log al
    LEFT JOIN users u ON u.id = al.user_id
    {$where}
    ORDER BY al.created_at DESC
    LIMIT :limit OFFSET :offset
";
$fetchStmt = $db->prepare($sql);
foreach ($params as $k => $v) {
    $fetchStmt->bindValue($k, $v);
}
$fetchStmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$fetchStmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$fetchStmt->execute();
$logs = $fetchStmt->fetchAll();

// Get distinct actions for filter dropdown
$actions = $db->query("SELECT DISTINCT action FROM audit_log ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Log — Admin — PawShield</title>
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
        <h2><i class="bi bi-journal-text me-2"></i>Audit Log</h2>
        <a href="<?= base_path() ?>/admin/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    <!-- Filter -->
    <form method="GET" class="mb-3 d-flex gap-2 align-items-center">
        <select name="action_type" class="form-select form-select-sm" style="width:auto;">
            <option value="">All actions</option>
            <?php foreach ($actions as $a): ?>
            <option value="<?= esc($a) ?>" <?= $actionFilter === $a ? 'selected' : '' ?>>
                <?= esc($a) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-primary">Filter</button>
        <?php if ($actionFilter): ?>
        <a href="<?= base_path() ?>/admin/audit-log.php" class="btn btn-sm btn-outline-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <p class="text-muted small"><?= number_format($total) ?> log entries.</p>

    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>IP Address</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="7" class="text-center text-muted py-3">No audit log entries.</td></tr>
            <?php else: ?>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="text-muted"><?= (int)$log['id'] ?></td>
                    <td><?= esc(date('d M Y H:i:s', strtotime($log['created_at']))) ?></td>
                    <td>
                        <?php if ($log['user_id']): ?>
                            <?= esc($log['first_name'] . ' ' . $log['last_name']) ?>
                            <br><small class="text-muted"><?= esc($log['email'] ?? '') ?></small>
                        <?php else: ?>
                            <span class="text-muted">System</span>
                        <?php endif; ?>
                    </td>
                    <td><code><?= esc($log['action']) ?></code></td>
                    <td>
                        <?php if ($log['entity_type']): ?>
                            <?= esc($log['entity_type']) ?> #<?= (int)$log['entity_id'] ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><small><?= esc($log['ip_address'] ?? '—') ?></small></td>
                    <td>
                        <?php if ($log['details']): ?>
                            <button class="btn btn-sm btn-outline-secondary"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#detail-<?= (int)$log['id'] ?>">
                                <i class="bi bi-code-slash"></i>
                            </button>
                            <div class="collapse mt-1" id="detail-<?= (int)$log['id'] ?>">
                                <pre class="bg-light p-2 rounded small mb-0" style="max-width:400px; overflow-x:auto;"><?= esc($log['details']) ?></pre>
                            </div>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav>
        <ul class="pagination justify-content-center">
            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $currentPage - 1 ?><?= $actionFilter ? '&action_type=' . urlencode($actionFilter) : '' ?>">
                    &laquo;
                </a>
            </li>
            <?php
            $startP = max(1, $currentPage - 4);
            $endP   = min($totalPages, $startP + 9);
            for ($p = $startP; $p <= $endP; $p++): ?>
            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $p ?><?= $actionFilter ? '&action_type=' . urlencode($actionFilter) : '' ?>">
                    <?= $p ?>
                </a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $currentPage + 1 ?><?= $actionFilter ? '&action_type=' . urlencode($actionFilter) : '' ?>">
                    &raquo;
                </a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

</div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>