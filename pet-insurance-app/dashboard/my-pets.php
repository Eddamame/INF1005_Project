<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../auth/middleware.php';
requireLogin();

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    $_SESSION = [];
    header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/auth/login.php');
    exit;
}

// Ensure the user still exists in DB (e.g. not deleted)
if ($db instanceof PDO) {
    $stmt = $db->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    if (!$stmt->fetch()) {
        $_SESSION = [];
        header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/auth/login.php');
        exit;
    }
}

$errors  = [];
$success = '';

$SPECIES = ['dog', 'cat', 'bird', 'rabbit', 'reptile', 'other'];

// ── Helper: ownership check ──────────────────────────────────
function getPetForUser(PDO $db, int $petId, int $userId): array|false {
    $stmt = $db->prepare('SELECT * FROM pets WHERE id = :pid AND user_id = :uid LIMIT 1');
    $stmt->execute([':pid' => $petId, ':uid' => $userId]);
    return $stmt->fetch();
}

$action = inputString('action', 'GET') ?? inputString('action') ?? '';

// ── DELETE ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    requireValidCsrf();
    $petId = inputInt('pet_id');
    if ($petId && getPetForUser($db, $petId, $userId)) {
        $stmt = $db->prepare('DELETE FROM pets WHERE id = :id AND user_id = :uid');
        $stmt->execute([':id' => $petId, ':uid' => $userId]);
        $success = 'Pet removed.';
    }
    $action = ''; // fall through to list view
}

// ── ADD ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    requireValidCsrf();

    $name    = inputString('name');
    $species = inputString('species');
    $breed   = inputString('breed');
    $dob     = inputString('date_of_birth');
    $chip    = inputString('microchip_id');

    if (!$name)                              $errors['name']    = 'Pet name is required.';
    if (!$species || !in_array($species, $SPECIES, true))
                                             $errors['species'] = 'Please select a valid species.';
    if ($dob && !strtotime($dob))            $errors['dob']     = 'Invalid date of birth.';

    if (!$errors && $userId > 0) {
        $stmt = $db->prepare('
            INSERT INTO pets (user_id, name, species, breed, date_of_birth, microchip_id)
            VALUES (:uid, :name, :species, :breed, :dob, :chip)
        ');
        $stmt->execute([
            ':uid'     => $userId,
            ':name'    => $name,
            ':species' => $species,
            ':breed'   => $breed ?: null,
            ':dob'     => $dob   ?: null,
            ':chip'    => $chip  ?: null,
        ]);
        $success = esc($name) . ' has been added.';
        $action  = '';
    }
}

// ── EDIT ─────────────────────────────────────────────────────
$editPet = null;
if ($action === 'edit') {
    $petId   = inputInt('pet_id', 'GET') ?? inputInt('pet_id');
    $editPet = $petId ? getPetForUser($db, $petId, $userId) : null;
    if (!$editPet) { $action = ''; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
    requireValidCsrf();

    $petId   = inputInt('pet_id');
    $editPet = $petId ? getPetForUser($db, $petId, $userId) : null;

    if (!$editPet) {
        $action = '';
    } else {
        $name    = inputString('name');
        $species = inputString('species');
        $breed   = inputString('breed');
        $dob     = inputString('date_of_birth');
        $chip    = inputString('microchip_id');

        if (!$name)                             $errors['name']    = 'Pet name is required.';
        if (!$species || !in_array($species, $SPECIES, true))
                                                $errors['species'] = 'Please select a valid species.';
        if ($dob && !strtotime($dob))           $errors['dob']     = 'Invalid date of birth.';

        if (!$errors) {
            $stmt = $db->prepare('
                UPDATE pets SET name=:name, species=:species, breed=:breed,
                                date_of_birth=:dob, microchip_id=:chip
                WHERE id=:id AND user_id=:uid
            ');
            $stmt->execute([
                ':name'    => $name,
                ':species' => $species,
                ':breed'   => $breed ?: null,
                ':dob'     => $dob   ?: null,
                ':chip'    => $chip  ?: null,
                ':id'      => $petId,
                ':uid'     => $userId,
            ]);
            $success = 'Pet updated.';
            $action  = '';
            $editPet = null;
        }
    }
}

// ── Fetch all pets for listing ────────────────────────────────
$stmt = $db->prepare('SELECT * FROM pets WHERE user_id = :uid ORDER BY created_at DESC');
$stmt->execute([':uid' => $userId]);
$pets = $stmt->fetchAll();

// Active subscriptions per pet (pet_id => plan name; one plan per pet for display)
$planByPetId = [];
if ($db instanceof PDO && !empty($pets)) {
    $stmt = $db->prepare('
        SELECT s.pet_id, ip.name AS plan_name
        FROM subscriptions s
        JOIN insurance_plans ip ON ip.id = s.plan_id
        WHERE s.user_id = :uid AND s.status = :status
    ');
    $stmt->execute([':uid' => $userId, ':status' => 'active']);
    while ($row = $stmt->fetch()) {
        $planByPetId[(int) $row['pet_id']] = $row['plan_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Pets — Pet Insurance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/accessibility.css">
</head>
<body>
    
<!-- ── Navbar (was a TODO comment — now using our include) ── -->
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>


<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>My Pets</h2>
        <a href="?action=add" class="btn btn-success">+ Add Pet</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= esc($success) ?></div>
    <?php endif; ?>

    <?php if ($action === 'add' || ($action === 'update' && $errors)): ?>
    <!-- ADD FORM -->
    <div class="card mb-4">
        <div class="card-body">
            <h5>Add a New Pet</h5>
            <?= renderPetForm($errors, [], 'add', $SPECIES) ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($editPet || ($action === 'update' && $errors && $editPet)): ?>
    <!-- EDIT FORM -->
    <div class="card mb-4">
        <div class="card-body">
            <h5>Edit Pet</h5>
            <?= renderPetForm($errors, $editPet, 'update', $SPECIES) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- PET LIST -->
    <?php if (empty($pets)): ?>
        <p class="text-muted">You haven't added any pets yet.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>Name</th><th>Species</th><th>Breed</th>
                    <th>Date of Birth</th><th>Microchip ID</th><th>Plan</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pets as $pet): ?>
                <tr>
                    <td><?= esc($pet['name']) ?></td>
                    <td><?= esc(ucfirst($pet['species'])) ?></td>
                    <td><?= esc($pet['breed'] ?? '—') ?></td>
                    <td><?= esc($pet['date_of_birth'] ?? '—') ?></td>
                    <td><?= esc($pet['microchip_id'] ?? '—') ?></td>
                    <td><?= isset($planByPetId[(int)$pet['id']]) ? esc($planByPetId[(int)$pet['id']]) : '—' ?></td>
                    <td>
                        <a href="?action=edit&pet_id=<?= (int)$pet['id'] ?>"
                           class="btn btn-sm btn-outline-primary me-1">Edit</a>
                        <form method="POST" class="d-inline me-1"
                              onsubmit="return confirm('Delete <?= esc($pet['name']) ?>?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="pet_id" value="<?= (int)$pet['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                        <a href="<?= base_path() ?>/dashboard/subscriptions/review-and-pay.php?pet_id=<?= (int)$pet['id'] ?>"
                           class="btn btn-sm btn-success">
                            Get coverage
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php
function renderPetForm(array $errors, array $pet, string $action, array $species): string {
    $hiddenId = ($action === 'update')
        ? '<input type="hidden" name="pet_id" value="' . (int)($pet['id'] ?? 0) . '">'
        : '';
    $speciesOptions = '';
    foreach ($species as $s) {
        $sel = (($pet['species'] ?? '') === $s) ? 'selected' : '';
        $speciesOptions .= "<option value=\"{$s}\" {$sel}>" . ucfirst($s) . "</option>";
    }
    ob_start(); ?>
    <form method="POST" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="<?= esc($action) ?>">
        <?= $hiddenId ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Name *</label>
                <input type="text" name="name"
                       class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                       value="<?= esc($pet['name'] ?? '') ?>" required>
                <div class="invalid-feedback"><?= esc($errors['name'] ?? '') ?></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Species *</label>
                <select name="species" class="form-select <?= isset($errors['species']) ? 'is-invalid' : '' ?>" required>
                    <option value="">— Select —</option>
                    <?= $speciesOptions ?>
                </select>
                <div class="invalid-feedback"><?= esc($errors['species'] ?? '') ?></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Breed</label>
                <input type="text" name="breed" class="form-control"
                       value="<?= esc($pet['breed'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Date of Birth</label>
                <input type="date" name="date_of_birth"
                       class="form-control <?= isset($errors['dob']) ? 'is-invalid' : '' ?>"
                       value="<?= esc($pet['date_of_birth'] ?? '') ?>">
                <div class="invalid-feedback"><?= esc($errors['dob'] ?? '') ?></div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Microchip ID</label>
                <input type="text" name="microchip_id" class="form-control"
                       value="<?= esc($pet['microchip_id'] ?? '') ?>">
            </div>
        </div>
        <div class="mt-3">
            <button class="btn btn-primary">
                <?= $action === 'update' ? 'Save Changes' : 'Add Pet' ?>
            </button>
            <a href="<?= base_path() ?>/dashboard/my-pets.php" class="btn btn-secondary ms-2">Cancel</a>
        </div>
    </form>
    <?php return ob_get_clean();
}
?>
</body>
</html>
