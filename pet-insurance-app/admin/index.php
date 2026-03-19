<?php
/**
 * admin/index.php
 * Phase 3: Admin dashboard with summary stats.
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../auth/middleware.php';

requireAdmin();

/** @var PDO $db */

// ── Aggregate stats ──────────────────────────────────────────
$totalCustomers = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$totalPets    = (int) $db->query("SELECT COUNT(*) FROM pets")->fetchColumn();
$totalClaims  = (int) $db->query("SELECT COUNT(*) FROM claims")->fetchColumn();
$pendingClaims = (int) $db->query("SELECT COUNT(*) FROM claims WHERE status NOT IN ('approved','rejected')")->fetchColumn();
$totalQuotes  = (int) $db->query("SELECT COUNT(*) FROM quotes")->fetchColumn();
$totalClinics = (int) $db->query("SELECT COUNT(*) FROM vet_clinics")->fetchColumn();
$totalAudit   = (int) $db->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();

// Recent claims
$recentClaims = $db->query("
    SELECT c.reference_id, c.status, c.created_at,
           u.first_name, u.last_name,
           p.name AS pet_name
    FROM claims c
    JOIN users u ON u.id = c.user_id
    JOIN pets p ON p.id = c.pet_id
    ORDER BY c.created_at DESC
    LIMIT 5
")->fetchAll();

// Recent audit log
$recentLogs = $db->query("
    SELECT al.action, al.entity_type, al.entity_id, al.created_at,
           u.first_name, u.last_name
    FROM audit_log al
    LEFT JOIN users u ON u.id = al.user_id
    ORDER BY al.created_at DESC
    LIMIT 5
")->fetchAll();

function dashBadge(string $status): string {
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
    <title>Admin Dashboard — PawShield</title>
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
        <h2><i class="bi bi-shield-lock me-2"></i>Admin Dashboard</h2>
        <span class="text-muted small">
            Logged in as <?= esc($_SESSION['user_name'] ?? 'Admin') ?>
        </span>
    </div>

    <!-- ── Stat Cards ─────────────────────────────────────────── -->
    <div class="row g-3 mb-5">
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                        <i class="bi bi-people-fill text-primary fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-4"><?= number_format($totalCustomers) ?></div>
                        <div class="text-muted small">Customers</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3">
                        <i class="bi bi-heart-pulse-fill text-success fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-4"><?= number_format($totalPets) ?></div>
                        <div class="text-muted small">Pets Registered</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                        <i class="bi bi-file-earmark-check-fill text-warning fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-4"><?= number_format($totalClaims) ?></div>
                        <div class="text-muted small">Total Claims</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3">
                        <i class="bi bi-exclamation-triangle-fill text-danger fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-4"><?= number_format($pendingClaims) ?></div>
                        <div class="text-muted small">Pending Claims</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-info bg-opacity-10 p-3">
                        <i class="bi bi-receipt text-info fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-4"><?= number_format($totalQuotes) ?></div>
                        <div class="text-muted small">Quotes Generated</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-secondary bg-opacity-10 p-3">
                        <i class="bi bi-hospital text-secondary fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-4"><?= number_format($totalClinics) ?></div>
                        <div class="text-muted small">Vet Clinics</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-dark bg-opacity-10 p-3">
                        <i class="bi bi-journal-text text-dark fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-4"><?= number_format($totalAudit) ?></div>
                        <div class="text-muted small">Audit Entries</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Quick Links ────────────────────────────────────────── -->
    <h5 class="mb-3"><i class="bi bi-grid me-2"></i>Management</h5>
    <div class="row g-3 mb-5">
        <?php
        $links = [
            ['href' => base_path() . '/admin/users.php',       'icon' => 'bi-people',             'label' => 'Manage Users',       'color' => 'primary'],
            ['href' => base_path() . '/admin/claims.php',      'icon' => 'bi-file-earmark-check', 'label' => 'Manage Claims',      'color' => 'warning'],
            ['href' => base_path() . '/admin/quotes.php',      'icon' => 'bi-receipt',            'label' => 'Manage Quotes',      'color' => 'info'],
            ['href' => base_path() . '/admin/vet-clinics.php', 'icon' => 'bi-hospital',           'label' => 'Vet Clinics',        'color' => 'success'],
            ['href' => base_path() . '/admin/audit-log.php',   'icon' => 'bi-journal-text',       'label' => 'Audit Log',          'color' => 'secondary'],
        ];
        foreach ($links as $link): ?>
        <div class="col-6 col-md-4 col-lg">
            <a href="<?= $link['href'] ?>"
               class="card border-0 shadow-sm text-decoration-none text-center h-100">
                <div class="card-body py-4">
                    <i class="bi <?= $link['icon'] ?> fs-2 text-<?= $link['color'] ?> mb-2 d-block"></i>
                    <span class="fw-semibold text-dark"><?= $link['label'] ?></span>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Recent Claims ──────────────────────────────────────── -->
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Claims</h6>
                    <a href="<?= base_path() ?>/admin/claims.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Reference</th>
                                    <th>User</th>
                                    <th>Pet</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($recentClaims)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">No claims yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentClaims as $c): ?>
                                <tr>
                                    <td><code><?= esc($c['reference_id']) ?></code></td>
                                    <td><?= esc($c['first_name'] . ' ' . $c['last_name']) ?></td>
                                    <td><?= esc($c['pet_name']) ?></td>
                                    <td><?= dashBadge($c['status']) ?></td>
                                    <td class="text-muted small"><?= esc(date('d M Y', strtotime($c['created_at']))) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Recent Activity ────────────────────────────────── -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-activity me-2"></i>Recent Activity</h6>
                    <a href="<?= base_path() ?>/admin/audit-log.php" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if (empty($recentLogs)): ?>
                            <li class="list-group-item text-center text-muted py-3">No activity yet.</li>
                        <?php else: ?>
                            <?php foreach ($recentLogs as $log): ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <code class="me-1"><?= esc($log['action']) ?></code>
                                        <?php if ($log['entity_type']): ?>
                                            <small class="text-muted">
                                                on <?= esc($log['entity_type']) ?> #<?= (int)$log['entity_id'] ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted text-nowrap">
                                        <?= esc(date('d M H:i', strtotime($log['created_at']))) ?>
                                    </small>
                                </div>
                                <?php if ($log['first_name']): ?>
                                <small class="text-muted">
                                    by <?= esc($log['first_name'] . ' ' . $log['last_name']) ?>
                                </small>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>