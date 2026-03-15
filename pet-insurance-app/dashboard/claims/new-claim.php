<?php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_once __DIR__ . '/../../services/ReferenceGenerator.php';

requireLogin();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$errors = [];
$success = '';

// Load user's pets and active subscriptions for dropdowns
$pets = [];
$subscriptions = [];

try {
    $stmt = $db->prepare('SELECT * FROM pets WHERE user_id = :uid ORDER BY created_at DESC');
    $stmt->execute([':uid' => $userId]);
    $pets = $stmt->fetchAll();

    $stmt = $db->prepare('
        SELECT s.*, p.name AS plan_name
        FROM subscriptions s
        JOIN insurance_plans p ON p.id = s.plan_id
        WHERE s.user_id = :uid AND s.status = \'active\'
        ORDER BY s.start_date DESC
    ');
    $stmt->execute([':uid' => $userId]);
    $subscriptions = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('new-claim: failed loading pets/subscriptions: ' . $e->getMessage());
}

// Handle form submission (create draft claim)
$claimId = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $petId  = inputInt('pet_id');
    $subId  = inputInt('subscription_id');
    $desc   = inputString('description');

    if (!$petId) {
        $errors['pet_id'] = 'Please select a pet.';
    }
    if (!$subId) {
        $errors['subscription_id'] = 'Please select a policy.';
    }

    if (!$errors) {
        try {
            $ref = ReferenceGenerator::uniqueClaim($db);
            $stmt = $db->prepare('
                INSERT INTO claims (reference_id, user_id, pet_id, subscription_id, description, status)
                VALUES (:ref, :uid, :pet_id, :sub_id, :description, :status)
            ');
            $stmt->execute([
                ':ref'         => $ref,
                ':uid'         => $userId,
                ':pet_id'      => $petId,
                ':sub_id'      => $subId,
                ':description' => $desc ?: null,
                ':status'      => 'draft',
            ]);
            $claimId = (int) $db->lastInsertId();
            $success = 'Claim created. You can now upload a receipt.';
        } catch (Throwable $e) {
            error_log('new-claim: failed creating claim: ' . $e->getMessage());
            $errors['general'] = 'Could not create claim. Please try again.';
        }
    }
}

// If a claim has just been created, keep it in a local variable so JS can use it
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Claim — Pet Insurance</title>
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
                    <a class="nav-link" href="<?= base_path() ?>/index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= base_path() ?>/dashboard/my-pets.php">My Pets</a>
                </li>
            </ul>

            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <span class="nav-link text-light">
                        <?= esc($_SESSION['user_name'] ?? 'User') ?>
                    </span>
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
    <div class="row">
        <div class="col-lg-6">
            <h2 class="mb-4">Start a New Claim</h2>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger"><?= esc($errors['general']) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= esc($success) ?></div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <?= csrfField() ?>

                <div class="mb-3">
                    <label class="form-label">Pet</label>
                    <select name="pet_id" class="form-select <?= isset($errors['pet_id']) ? 'is-invalid' : '' ?>" required>
                        <option value="">— Select a pet —</option>
                        <?php foreach ($pets as $pet): ?>
                            <option value="<?= (int) $pet['id'] ?>">
                                <?= esc($pet['name']) ?> (<?= esc(ucfirst($pet['species'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback"><?= esc($errors['pet_id'] ?? '') ?></div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Policy</label>
                    <select name="subscription_id" class="form-select <?= isset($errors['subscription_id']) ? 'is-invalid' : '' ?>" required>
                        <option value="">— Select a policy —</option>
                        <?php foreach ($subscriptions as $sub): ?>
                            <option value="<?= (int) $sub['id'] ?>">
                                <?= esc($sub['plan_name']) ?> — since <?= esc($sub['start_date']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback"><?= esc($errors['subscription_id'] ?? '') ?></div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Short description (optional)</label>
                    <textarea name="description" class="form-control" rows="3"
                              placeholder="E.g. Emergency visit for Bruno's leg injury"></textarea>
                </div>

                <button class="btn btn-primary">Create Claim</button>
            </form>
        </div>

        <div class="col-lg-6">
            <h2 class="mb-4">Upload Receipt</h2>
            <?php if ($claimId): ?>
                <p class="text-muted">Now upload a vet receipt to auto-fill your claim.</p>
                <div class="border rounded p-4 bg-white"
                     id="receipt-dropzone"
                     data-receipt-dropzone
                     data-claim-id="<?= (int) $claimId ?>"
                     data-file-type="receipt">
                    <input type="file" id="receipt-file-input" name="document" accept="image/*,.pdf" hidden>
                    <p class="mb-2">Drag &amp; drop your vet receipt here, or
                        <button type="button" class="btn btn-sm btn-outline-primary" data-upload-trigger>browse files</button>
                    </p>
                    <p class="small text-muted mb-2">Supported formats: JPEG, PNG, WebP, TIFF, PDF. Max 10 MB.</p>
                    <div class="small" data-upload-status></div>
                </div>

                <div class="mt-3" id="scan-preview" hidden>
                    <h5>Scan Summary</h5>
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Clinic</dt>
                        <dd class="col-sm-8" id="scan-clinic-name"></dd>
                        <dt class="col-sm-4">Visit Date</dt>
                        <dd class="col-sm-8" id="scan-visit-date"></dd>
                        <dt class="col-sm-4">Total</dt>
                        <dd class="col-sm-8" id="scan-total"></dd>
                    </dl>
                </div>
            <?php else: ?>
                <p class="text-muted">First create a claim on the left. After that, you’ll be able to upload a receipt here.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_path() ?>/assets/js/file-upload.js"></script>
<?php if ($claimId): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var zone = document.getElementById('receipt-dropzone');
    if (!zone) return;

    zone.addEventListener('receipt:upload:success', function (event) {
        var data = event.detail || {};
        var parsed = data.scan && data.scan.parsed_data ? data.scan.parsed_data : {};

        var preview = document.getElementById('scan-preview');
        if (!preview) return;

        document.getElementById('scan-clinic-name').textContent = parsed.clinic_name || '—';
        document.getElementById('scan-visit-date').textContent = parsed.visit_date || '—';
        document.getElementById('scan-total').textContent = (parsed.total != null ? parsed.total : '—');

        preview.hidden = false;
    });
});
</script>
<?php endif; ?>
</body>
</html>

