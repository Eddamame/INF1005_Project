<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../auth/middleware.php';
requireLogin();
requireVerified();

/** @var PDO $db */

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    $_SESSION = [];
    header('Location: ' . BASE_PATH . '/auth/login.php');
    exit;
}

if ($db instanceof PDO) {
    $stmt = $db->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    if (!$stmt->fetch()) {
        $_SESSION = [];
        header('Location: ' . BASE_PATH . '/auth/login.php');
        exit;
    }
}

$errors  = [];
$success = '';

if (!empty($_SESSION['flash_message'])) {
    $success = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

$SPECIES = ['dog', 'cat', 'bird', 'rabbit', 'reptile', 'other'];

function getPetForUser(PDO $db, int $petId, int $userId): array|false {
    $stmt = $db->prepare('SELECT * FROM pets WHERE id = :pid AND user_id = :uid LIMIT 1');
    $stmt->execute([':pid' => $petId, ':uid' => $userId]);
    return $stmt->fetch();
}

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
    $action = '';
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

// ── Fetch all pets ───────────────────────────────────────────
$stmt = $db->prepare('SELECT * FROM pets WHERE user_id = :uid ORDER BY created_at DESC');
$stmt->execute([':uid' => $userId]);
$pets = $stmt->fetchAll();

// Active subscriptions per pet with full plan details
$subByPetId = [];
if (!empty($pets)) {
    $stmt = $db->prepare('
        SELECT s.pet_id, s.start_date, s.end_date, s.status,
               ip.name AS plan_name, ip.monthly_premium, ip.annual_limit,
               ip.deductible, ip.coverage_pct, ip.description AS plan_description
        FROM subscriptions s
        JOIN insurance_plans ip ON ip.id = s.plan_id
        WHERE s.user_id = :uid AND s.status = :status
    ');
    $stmt->execute([':uid' => $userId, ':status' => 'active']);
    while ($row = $stmt->fetch()) {
        $subByPetId[(int) $row['pet_id']] = $row;
    }
}

function timeRemaining(string $endDate): array {
    $now = new DateTime();
    $end = new DateTime($endDate);

    if ($end <= $now) {
        return ['text' => 'Expired', 'days' => 0, 'expired' => true];
    }

    $diff = $now->diff($end);
    $totalDays = (int) $now->diff($end)->format('%a');

    $parts = [];
    if ($diff->y > 0) $parts[] = $diff->y . ($diff->y === 1 ? ' year' : ' years');
    if ($diff->m > 0) $parts[] = $diff->m . ($diff->m === 1 ? ' month' : ' months');
    if ($diff->d > 0 && $diff->y === 0) $parts[] = $diff->d . ($diff->d === 1 ? ' day' : ' days');

    return [
        'text'    => implode(', ', $parts) . ' remaining',
        'days'    => $totalDays,
        'expired' => false,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Pets — PawShield</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/accessibility.css">
</head>
<body>

<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<div class="container py-5 my-pets-page">
    <div class="d-flex justify-content-between align-items-center mb-4 my-pets-header">
        <h2 class="mb-0">My Pets</h2>
        <a href="?action=add" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Pet</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= esc($success) ?></div>
    <?php endif; ?>

    <?php if ($action === 'add' || ($action === 'update' && $errors)): ?>
    <div class="card mb-4 my-pets-form-card border-0 shadow-sm">
        <div class="card-body">
            <h5>Add a New Pet</h5>
            <?= renderPetForm($errors, [], 'add', $SPECIES) ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($editPet || ($action === 'update' && $errors && $editPet)): ?>
    <div class="card mb-4 my-pets-form-card border-0 shadow-sm">
        <div class="card-body">
            <h5>Edit Pet</h5>
            <?= renderPetForm($errors, $editPet, 'update', $SPECIES) ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($pets)): ?>
        <p class="text-muted">You haven't added any pets yet.</p>
    <?php else: ?>
        <?php foreach ($pets as $pet): ?>
            <?php
            $sub = $subByPetId[(int)$pet['id']] ?? null;
            $remaining = ($sub && !empty($sub['end_date'])) ? timeRemaining($sub['end_date']) : null;
            ?>
            <div class="card mb-3 pet-summary-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <?php
                            $species = strtolower((string)($pet['species'] ?? ''));
                            $speciesIcon = match ($species) {
                                'dog' => '🐶',
                                'cat' => '🐱',
                                'bird' => '🐦',
                                'rabbit' => '🐰',
                                'reptile' => '🦎',
                                default => '🐾',
                            };
                            ?>
                            <h5 class="mb-1 d-flex align-items-center gap-2">
                                <span class="pet-species-badge" aria-hidden="true"><?= esc($speciesIcon) ?></span>
                                <?= esc($pet['name']) ?>
                            </h5>
                            <p class="text-muted small mb-0">
                                <?= esc(ucfirst($pet['species'])) ?>
                                <?= $pet['breed'] ? ' · ' . esc($pet['breed']) : '' ?>
                                <?= $pet['date_of_birth'] ? ' · Born ' . esc($pet['date_of_birth']) : '' ?>
                                <?= $pet['microchip_id'] ? ' · Chip: ' . esc($pet['microchip_id']) : '' ?>
                            </p>
                        </div>
                        <div class="d-flex gap-1">
                            <a href="?action=edit&pet_id=<?= (int)$pet['id'] ?>"
                               class="btn btn-sm btn-outline-primary">Edit</a>
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('Delete <?= esc($pet['name']) ?>?')">
                                <?php csrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="pet_id" value="<?= (int)$pet['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </div>
                    </div>

                    <?php if ($sub): ?>
                        <!-- Policy details -->
                        <hr class="my-3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="badge bg-success"><?= esc($sub['plan_name']) ?> Plan</span>
                                    <?php if ($remaining && !$remaining['expired']): ?>
                                        <?php if ($remaining['days'] <= 30): ?>
                                            <span class="badge bg-warning text-dark">Expiring soon</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">Active</span>
                                        <?php endif; ?>
                                    <?php elseif ($remaining && $remaining['expired']): ?>
                                        <span class="badge bg-danger">Expired</span>
                                    <?php endif; ?>
                                </div>
                                <div class="small">
                                    <div class="mb-1">
                                        <i class="bi bi-clock me-1 text-muted"></i>
                                        <strong><?= esc($remaining['text'] ?? 'N/A') ?></strong>
                                    </div>
                                    <div class="text-muted">
                                        <i class="bi bi-calendar-range me-1"></i>
                                        <?= esc(date('d M Y', strtotime($sub['start_date']))) ?>
                                        — <?= esc(date('d M Y', strtotime($sub['end_date']))) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="small">
                                    <div class="mb-1">
                                        <i class="bi bi-cash me-1 text-muted"></i>
                                        <strong>$<?= number_format((float)$sub['monthly_premium'], 2) ?></strong>/month
                                    </div>
                                    <div class="mb-1">
                                        <i class="bi bi-shield-check me-1 text-muted"></i>
                                        <?= number_format((float)$sub['coverage_pct']) ?>% coverage
                                        · $<?= number_format((float)$sub['deductible']) ?> deductible
                                    </div>
                                    <div>
                                        <i class="bi bi-graph-up me-1 text-muted"></i>
                                        Up to $<?= number_format((float)$sub['annual_limit']) ?> annual limit
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($remaining && $remaining['days'] <= 30): ?>
                            <div class="mt-3">
                                <a href="<?= base_path() ?>/dashboard/subscriptions/review-and-pay.php?pet_id=<?= (int)$pet['id'] ?>"
                                   class="btn btn-sm btn-warning">
                                    <i class="bi bi-arrow-repeat me-1"></i>Renew Coverage
                                </a>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <!-- No policy -->
                        <hr class="my-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="text-muted small"><i class="bi bi-shield-x me-1"></i>No active policy</span>
                            <a href="<?= base_path() ?>/dashboard/subscriptions/review-and-pay.php?pet_id=<?= (int)$pet['id'] ?>"
                               class="btn btn-sm btn-success">
                                <i class="bi bi-shield-check me-1"></i>Get Coverage
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
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
    <form method="POST" action="<?= base_path() ?>/dashboard/my-pets.php" novalidate data-validate="pet">
        <?php csrfField(); ?>
        <input type="hidden" name="action" value="<?= esc($action) ?>">
        <?= $hiddenId ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="pet-form-name">Name *</label>
                <input type="text" id="pet-form-name" name="name"
                       class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                       value="<?= esc($pet['name'] ?? '') ?>" required autocomplete="off">
                <div class="invalid-feedback"><?= esc($errors['name'] ?? '') ?></div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="pet-form-species">Species *</label>
                <select id="pet-form-species" name="species" class="form-select <?= isset($errors['species']) ? 'is-invalid' : '' ?>" required>
                    <option value="">— Select —</option>
                    <?= $speciesOptions ?>
                </select>
                <div class="invalid-feedback"><?= esc($errors['species'] ?? '') ?></div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="pet-form-breed">Breed</label>
                <input type="text" id="pet-form-breed" name="breed" class="form-control"
                       value="<?= esc($pet['breed'] ?? '') ?>" autocomplete="off">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="pet-form-dob">Date of Birth</label>
                <input type="date" id="pet-form-dob" name="date_of_birth"
                       class="form-control <?= isset($errors['dob']) ? 'is-invalid' : '' ?>"
                       value="<?= esc($pet['date_of_birth'] ?? '') ?>">
                <div class="invalid-feedback"><?= esc($errors['dob'] ?? '') ?></div>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="pet-form-chip">Microchip ID</label>
                <input type="text" id="pet-form-chip" name="microchip_id" class="form-control"
                       value="<?= esc($pet['microchip_id'] ?? '') ?>" autocomplete="off">
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>