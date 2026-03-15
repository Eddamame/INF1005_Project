<?php
/**
 * admin/vet-clinics.php
 * Phase 3: Full CRUD for vet clinic database.
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../auth/middleware.php';

requireAdmin();

$success = '';
$errors  = [];
$action  = inputString('action', 'GET') ?? inputString('action') ?? '';

// ── DELETE ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    requireValidCsrf();
    $clinicId = inputInt('clinic_id');
    if ($clinicId) {
        $db->prepare('DELETE FROM vet_clinics WHERE id = :id')->execute([':id' => $clinicId]);
        $success = 'Clinic deleted.';
    }
    $action = '';
}

// ── ADD ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    requireValidCsrf();
    $code    = inputString('clinic_code');
    $name    = inputString('name');
    $address = inputString('address');
    $phone   = inputString('phone');
    $email   = inputEmail('email');
    $verified = inputInt('is_verified') ?? 1;

    if (!$code) $errors['clinic_code'] = 'Clinic code is required.';
    if (!$name) $errors['name']        = 'Clinic name is required.';

    if ($code && !$errors) {
        $chk = $db->prepare('SELECT id FROM vet_clinics WHERE clinic_code = :code');
        $chk->execute([':code' => $code]);
        if ($chk->fetch()) $errors['clinic_code'] = 'This clinic code already exists.';
    }

    if (!$errors) {
        $stmt = $db->prepare('
            INSERT INTO vet_clinics (clinic_code, name, address, phone, email, is_verified)
            VALUES (:code, :name, :address, :phone, :email, :verified)
        ');
        $stmt->execute([
            ':code'     => $code,
            ':name'     => $name,
            ':address'  => $address,
            ':phone'    => $phone,
            ':email'    => $email,
            ':verified' => $verified,
        ]);
        $success = 'Clinic added.';
        $action  = '';
    }
}

// ── EDIT ─────────────────────────────────────────────────────
$editClinic = null;
if ($action === 'edit') {
    $clinicId   = inputInt('clinic_id', 'GET') ?? inputInt('clinic_id');
    $stmt       = $db->prepare('SELECT * FROM vet_clinics WHERE id = :id');
    $stmt->execute([':id' => $clinicId]);
    $editClinic = $stmt->fetch();
    if (!$editClinic) $action = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
    requireValidCsrf();
    $clinicId = inputInt('clinic_id');
    $code     = inputString('clinic_code');
    $name     = inputString('name');
    $address  = inputString('address');
    $phone    = inputString('phone');
    $email    = inputEmail('email');
    $verified = inputInt('is_verified') ?? 1;

    if (!$code) $errors['clinic_code'] = 'Clinic code is required.';
    if (!$name) $errors['name']        = 'Clinic name is required.';

    if ($code && $clinicId && !$errors) {
        $chk = $db->prepare('SELECT id FROM vet_clinics WHERE clinic_code = :code AND id != :id');
        $chk->execute([':code' => $code, ':id' => $clinicId]);
        if ($chk->fetch()) $errors['clinic_code'] = 'This clinic code already exists.';
    }

    if (!$errors && $clinicId) {
        $stmt = $db->prepare('
            UPDATE vet_clinics
            SET clinic_code = :code, name = :name, address = :address,
                phone = :phone, email = :email, is_verified = :verified
            WHERE id = :id
        ');
        $stmt->execute([
            ':code'     => $code,
            ':name'     => $name,
            ':address'  => $address,
            ':phone'    => $phone,
            ':email'    => $email,
            ':verified' => $verified,
            ':id'       => $clinicId,
        ]);
        $success    = 'Clinic updated.';
        $action     = '';
        $editClinic = null;
    } else {
        $editClinic = [
            'id' => $clinicId, 'clinic_code' => $code, 'name' => $name,
            'address' => $address, 'phone' => $phone, 'email' => $email,
            'is_verified' => $verified,
        ];
        $action = 'edit';
    }
}

// ── List & search ────────────────────────────────────────────
$search  = inputString('q', 'GET') ?? '';
$perPage = 20;
$page    = max(1, inputInt('page', 'GET') ?? 1);
$offset  = ($page - 1) * $perPage;

$where  = '';
$params = [];
if ($search) {
    $like   = '%' . $search . '%';
    $where  = ' WHERE (clinic_code LIKE :q OR name LIKE :q2)';
    $params = [':q' => $like, ':q2' => $like];
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM vet_clinics{$where}");
$countStmt->execute($params);
$total      = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$fetchStmt = $db->prepare("SELECT * FROM vet_clinics{$where} ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) {
    $fetchStmt->bindValue($k, $v);
}
$fetchStmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$fetchStmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$fetchStmt->execute();
$clinics = $fetchStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vet Clinics — Admin — PawShield</title>
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
        <h2><i class="bi bi-hospital me-2"></i>Vet Clinics</h2>
        <div>
            <a href="?action=add" class="btn btn-success btn-sm me-2">
                <i class="bi bi-plus-circle me-1"></i> Add Clinic
            </a>
            <a href="<?= base_path() ?>/admin/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= esc($success) ?></div><?php endif; ?>

    <!-- Add / Edit form -->
    <?php if ($action === 'add' || $editClinic): ?>
    <div class="card mb-4">
        <div class="card-body">
            <h5><?= $editClinic ? 'Edit Clinic' : 'Add New Clinic' ?></h5>
            <form method="POST" novalidate>
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="<?= $editClinic ? 'update' : 'add' ?>">
                <?php if ($editClinic): ?>
                    <input type="hidden" name="clinic_id" value="<?= (int)$editClinic['id'] ?>">
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Clinic Code *</label>
                        <input type="text" name="clinic_code"
                               class="form-control <?= isset($errors['clinic_code']) ? 'is-invalid' : '' ?>"
                               value="<?= esc($editClinic['clinic_code'] ?? '') ?>" required>
                        <div class="invalid-feedback"><?= esc($errors['clinic_code'] ?? '') ?></div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name"
                               class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                               value="<?= esc($editClinic['name'] ?? '') ?>" required>
                        <div class="invalid-feedback"><?= esc($errors['name'] ?? '') ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-control"
                               value="<?= esc($editClinic['phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control"
                               value="<?= esc($editClinic['address'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= esc($editClinic['email'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Verified?</label>
                        <select name="is_verified" class="form-select">
                            <option value="1" <?= (($editClinic['is_verified'] ?? 1) == 1) ? 'selected' : '' ?>>Yes</option>
                            <option value="0" <?= (($editClinic['is_verified'] ?? 1) == 0) ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-primary"><?= $editClinic ? 'Save Changes' : 'Add Clinic' ?></button>
                    <a href="<?= base_path() ?>/admin/vet-clinics.php" class="btn btn-secondary ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Search -->
    <form method="GET" class="mb-3">
        <div class="input-group" style="max-width:400px;">
            <input type="text" name="q" class="form-control" placeholder="Search by code or name…"
                   value="<?= esc($search) ?>">
            <button class="btn btn-outline-primary"><i class="bi bi-search"></i></button>
            <?php if ($search): ?>
            <a href="<?= base_path() ?>/admin/vet-clinics.php" class="btn btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Verified</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($clinics as $cl): ?>
                <tr>
                    <td><code><?= esc($cl['clinic_code']) ?></code></td>
                    <td><?= esc($cl['name']) ?></td>
                    <td><?= esc($cl['phone'] ?? '—') ?></td>
                    <td><?= esc($cl['email'] ?? '—') ?></td>
                    <td>
                        <?= (int)$cl['is_verified']
                            ? '<span class="badge bg-success">Yes</span>'
                            : '<span class="badge bg-secondary">No</span>' ?>
                    </td>
                    <td>
                        <a href="?action=edit&clinic_id=<?= (int)$cl['id'] ?>"
                           class="btn btn-sm btn-outline-primary">Edit</a>
                        <form method="POST" class="d-inline"
                              onsubmit="return confirm('Delete this clinic?')">
                            <?php csrfField(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="clinic_id" value="<?= (int)$cl['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
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
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $p ?><?= $search ? '&q=' . urlencode($search) : '' ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>

</div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>