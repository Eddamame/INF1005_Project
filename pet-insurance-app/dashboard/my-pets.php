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

// Prefer POST so form submit (action=update) wins over URL (action=edit)
$action = inputString('action') ?? inputString('action', 'GET') ?? '';

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

$breadcrumb_items = [
    ['Dashboard', base_path() . '/dashboard/my-pets.php'],
    ['My Pets', ''],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Pets — PawShield Pet Insurance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/accessibility.css">
</head>
<body>

<a class="skip-link" href="#main-content">Skip to main content</a>

<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content">
    <!-- Hero -->
    <section class="hero-section hero-section--short" aria-labelledby="mypets-hero-heading">
        <div class="hero-bg-shapes" aria-hidden="true">
            <div class="hero-shape hero-shape-1"></div>
            <div class="hero-shape hero-shape-2"></div>
        </div>
        <div class="container hero-content">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <span class="hero-badge">
                        <i class="bi bi-heart-pulse me-1" aria-hidden="true"></i>Dashboard
                    </span>
                    <h1 id="mypets-hero-heading" class="hero-heading">My Pets</h1>
                    <p class="hero-subtitle mb-0 text-center mx-auto" style="max-width: 32rem;">
                        Manage your pets and get them covered with a plan that fits.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-6">
        <div class="container">
            <?php require_once __DIR__ . '/../includes/breadcrumb.php'; ?>
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center gap-3 mb-4 mt-3">
                <h2 class="h4 mb-0" style="color: var(--ps-navy);">Your pets</h2>
                <a href="?action=add" class="btn btn-success">
                    <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add Pet
                </a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= esc($success) ?></div>
            <?php endif; ?>

            <?php if ($action === 'add' || ($action === 'update' && $errors)): ?>
            <div class="card border-0 shadow-sm mb-4 my-pets-form-card">
                <div class="card-body p-4 p-lg-5">
                    <h3 class="h5 mb-4" style="color: var(--ps-navy);">Add a New Pet</h3>
                    <?= renderPetForm($errors, [], 'add', $SPECIES) ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($editPet || ($action === 'update' && $errors && $editPet)): ?>
            <div class="card border-0 shadow-sm mb-4 my-pets-form-card">
                <div class="card-body p-4 p-lg-5">
                    <h3 class="h5 mb-4" style="color: var(--ps-navy);">Edit Pet</h3>
                    <?= renderPetForm($errors, $editPet, 'update', $SPECIES) ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($pets)): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-5 text-center">
                        <i class="bi bi-heart text-muted d-block mb-3" style="font-size: 3rem;" aria-hidden="true"></i>
                        <p class="text-muted mb-4">You haven't added any pets yet.</p>
                        <a href="?action=add" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add your first pet
                        </a>
                    </div>
                </div>
            <?php else: ?>
            <div class="card border-0 shadow-sm my-pets-table-card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 my-pets-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Species</th>
                                    <th>Breed</th>
                                    <th>Date of Birth</th>
                                    <th>Microchip ID</th>
                                    <th>Plan</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($pets as $pet): ?>
                                <tr>
                                    <td>
                                        <span class="pet-species-icon me-2" aria-hidden="true" title="<?= esc(ucfirst($pet['species'])) ?>">
                                            <?php if (strtolower($pet['species']) === 'dog'): ?>
                                                <i class="bi bi-star-fill text-teal"></i>
                                            <?php elseif (strtolower($pet['species']) === 'cat'): ?>
                                                <i class="bi bi-heart-fill text-teal"></i>
                                            <?php else: ?>
                                                <i class="bi bi-circle-fill text-teal"></i>
                                            <?php endif; ?>
                                        </span>
                                        <strong><?= esc($pet['name']) ?></strong>
                                    </td>
                                    <td><?= esc(ucfirst($pet['species'])) ?></td>
                                    <td><?= esc($pet['breed'] ?? '—') ?></td>
                                    <td><?= esc($pet['date_of_birth'] ?? '—') ?></td>
                                    <td><?= esc($pet['microchip_id'] ?? '—') ?></td>
                                    <td><?= isset($planByPetId[(int)$pet['id']]) ? esc($planByPetId[(int)$pet['id']]) : '—' ?></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <a href="?action=edit&pet_id=<?= (int)$pet['id'] ?>"
                                               class="btn btn-sm btn-outline-primary">Edit</a>
                                            <form method="POST" class="d-inline"
                                                  onsubmit="return confirm('Delete <?= esc($pet['name']) ?>?')">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="pet_id" value="<?= (int)$pet['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                            <a href="<?= base_path() ?>/dashboard/subscriptions/review-and-pay.php?pet_id=<?= (int)$pet['id'] ?>"
                                               class="btn btn-sm btn-primary">
                                                Get coverage
                                            </a>
                                        </div>
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
    </section>
</main>

<script src="<?= base_path() ?>/assets/js/inline-validation.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

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
                <label class="form-label" for="pet_name">Name *</label>
                <input type="text" name="name" id="pet_name"
                       class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                       value="<?= esc($pet['name'] ?? '') ?>" required autofocus>
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
                       value="<?= esc($pet['date_of_birth'] ?? '') ?>"
                       data-inline-validate="dob">
                <div class="invalid-feedback" data-inline-feedback><?= esc($errors['dob'] ?? '') ?></div>
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
