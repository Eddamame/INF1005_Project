<?php
/**
 * admin/users.php
 * Phase 3: User management — list, search, edit role, deactivate.
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
    $targetId = inputInt('user_id');

    if ($targetId && $targetId !== (int) $_SESSION['user_id']) {
        if ($action === 'change_role') {
            $newRole = inputString('role');
            if (in_array($newRole, ['customer', 'admin'], true)) {
                $stmt = $db->prepare('UPDATE users SET role = :role WHERE id = :id');
                $stmt->execute([':role' => $newRole, ':id' => $targetId]);
                $success = "User #{$targetId} role changed to {$newRole}.";
            }
        } elseif ($action === 'deactivate') {
            $stmt = $db->prepare('UPDATE users SET email_verified = 0 WHERE id = :id');
            $stmt->execute([':id' => $targetId]);
            $success = "User #{$targetId} deactivated.";
        } elseif ($action === 'activate') {
            $stmt = $db->prepare('UPDATE users SET email_verified = 1 WHERE id = :id');
            $stmt->execute([':id' => $targetId]);
            $success = "User #{$targetId} activated.";
        }
    } elseif ($targetId === (int) $_SESSION['user_id']) {
        $error = 'You cannot modify your own account from this panel.';
    }
}

// ── Search & pagination ──────────────────────────────────────
$search      = inputString('q', 'GET') ?? '';
$perPage     = 20;
$currentPage = max(1, inputInt('page', 'GET') ?? 1);
$offset      = ($currentPage - 1) * $perPage;

$countSql  = 'SELECT COUNT(*) FROM users WHERE 1=1';
$fetchSql  = 'SELECT * FROM users WHERE 1=1';
$params    = [];

if ($search) {
    $like = '%' . $search . '%';
    $countSql .= ' AND (first_name LIKE :q OR last_name LIKE :q2 OR email LIKE :q3)';
    $fetchSql .= ' AND (first_name LIKE :q OR last_name LIKE :q2 OR email LIKE :q3)';
    $params[':q']  = $like;
    $params[':q2'] = $like;
    $params[':q3'] = $like;
}

$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalUsers = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalUsers / $perPage));

$fetchSql .= ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
$fetchStmt = $db->prepare($fetchSql);
foreach ($params as $k => $v) {
    $fetchStmt->bindValue($k, $v);
}
$fetchStmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$fetchStmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$fetchStmt->execute();
$users = $fetchStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users — Admin — PawShield</title>
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
        <h2><i class="bi bi-people me-2"></i>Manage Users</h2>
        <a href="<?= base_path() ?>/admin/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= esc($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= esc($error) ?></div>
    <?php endif; ?>

    <!-- Search -->
    <form method="GET" class="mb-4">
        <div class="input-group" style="max-width:400px;">
            <input type="text" name="q" class="form-control" placeholder="Search by name or email…"
                   value="<?= esc($search) ?>">
            <button class="btn btn-outline-primary" type="submit">
                <i class="bi bi-search"></i>
            </button>
            <?php if ($search): ?>
            <a href="<?= base_path() ?>/admin/users.php" class="btn btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <p class="text-muted small"><?= number_format($totalUsers) ?> user(s) found.</p>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= (int)$u['id'] ?></td>
                    <td><?= esc($u['first_name'] . ' ' . $u['last_name']) ?></td>
                    <td><?= esc($u['email']) ?></td>
                    <td>
                        <span class="badge <?= $u['role'] === 'admin' ? 'bg-warning text-dark' : 'bg-secondary' ?>">
                            <?= esc(ucfirst($u['role'])) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ((int)$u['email_verified']): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td><?= esc(date('d M Y', strtotime($u['created_at']))) ?></td>
                    <td>
                        <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                        <form method="POST" class="d-inline">
                            <?php csrfField(); ?>
                            <input type="hidden" name="action" value="change_role">
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="role" value="<?= $u['role'] === 'admin' ? 'customer' : 'admin' ?>">
                            <button class="btn btn-sm btn-outline-primary" title="Toggle role">
                                <i class="bi bi-arrow-repeat"></i>
                                <?= $u['role'] === 'admin' ? 'Demote' : 'Promote' ?>
                            </button>
                        </form>
                        <form method="POST" class="d-inline">
                            <?php csrfField(); ?>
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <?php if ((int)$u['email_verified']): ?>
                                <input type="hidden" name="action" value="deactivate">
                                <button class="btn btn-sm btn-outline-danger" title="Deactivate">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="activate">
                                <button class="btn btn-sm btn-outline-success" title="Activate">
                                    <i class="bi bi-check-circle"></i>
                                </button>
                            <?php endif; ?>
                        </form>
                        <?php else: ?>
                            <span class="text-muted small">You</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav aria-label="Users pagination">
        <ul class="pagination justify-content-center">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $p ?><?= $search ? '&q=' . urlencode($search) : '' ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>

</div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>