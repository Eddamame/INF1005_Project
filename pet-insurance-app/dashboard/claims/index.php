<?php
/**
 * dashboard/claims/index.php
 * User's claims dashboard — list all claims with status and links.
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../auth/middleware.php';

requireLogin();

/** @var PDO $db */

$userId = (int) ($_SESSION['user_id'] ?? 0);
$success = '';

// Handle delete — MUST be before fetching claims
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_claim') {
    requireValidCsrf();
    $deleteId = inputInt('claim_id');
    if ($deleteId) {
        $stmt = $db->prepare('DELETE FROM claims WHERE id = :id AND user_id = :uid AND status = :status');
        $stmt->execute([':id' => $deleteId, ':uid' => $userId, ':status' => 'draft']);
        if ($stmt->rowCount() > 0) {
            $success = 'Draft claim deleted.';
        }
    }
}

// Fetch all claims AFTER any deletions
$stmt = $db->prepare('
    SELECT c.*, p.name AS pet_name, p.species,
           ip.name AS plan_name,
           (SELECT COUNT(*) FROM claim_documents cd WHERE cd.claim_id = c.id) AS doc_count,
           (SELECT COUNT(*) FROM quotes q WHERE q.claim_id = c.id) AS quote_count
    FROM claims c
    JOIN pets p ON p.id = c.pet_id
    JOIN subscriptions s ON s.id = c.subscription_id
    JOIN insurance_plans ip ON ip.id = s.plan_id
    WHERE c.user_id = :uid
    ORDER BY c.created_at DESC
');
$stmt->execute([':uid' => $userId]);
$claims = $stmt->fetchAll();

function claimStatusBadge(string $status): string {
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
    <title>My Claims — PawShield</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/accessibility.css">
</head>
<body>

<?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

<main id="main-content">
<div class="container py-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-file-earmark-text me-2"></i>My Claims</h2>
        <a href="<?= base_path() ?>/dashboard/claims/new-claim.php" class="btn btn-success">
            <i class="bi bi-plus-circle me-1"></i>New Claim
        </a>
    </div>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= esc($success) ?></div>
    <?php endif; ?>

    <?php if (empty($claims)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-file-earmark-x" style="font-size: 3rem; color: var(--ps-gray-400);"></i>
                <h5 class="mt-3" style="color: var(--ps-navy);">No claims yet</h5>
                <p class="text-muted mb-4">Submit your first claim by uploading a vet receipt.</p>
                <a href="<?= base_path() ?>/dashboard/claims/new-claim.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>Start a Claim
                </a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($claims as $c): ?>
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <code class="fw-bold"><?= esc($c['reference_id']) ?></code>
                            <?= claimStatusBadge($c['status']) ?>
                        </div>
                        <p class="mb-1">
                            <strong><?= esc($c['pet_name']) ?></strong>
                            (<?= esc(ucfirst($c['species'])) ?>)
                            · <?= esc($c['plan_name']) ?> plan
                        </p>
                        <div class="small text-muted">
                            <i class="bi bi-calendar me-1"></i>Created <?= esc(date('d M Y', strtotime($c['created_at']))) ?>
                            <?php if ($c['visit_date']): ?>
                                · <i class="bi bi-hospital me-1"></i>Visit <?= esc(date('d M Y', strtotime($c['visit_date']))) ?>
                            <?php endif; ?>
                            · <i class="bi bi-paperclip me-1"></i><?= (int)$c['doc_count'] ?> document(s)
                            <?php if ((int)$c['quote_count'] > 0): ?>
                                · <i class="bi bi-receipt me-1"></i><?= (int)$c['quote_count'] ?> quote(s)
                            <?php endif; ?>
                        </div>
                        <?php if ($c['description']): ?>
                            <p class="small text-muted mt-1 mb-0"><?= esc($c['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if ($c['status'] === 'draft'): ?>
                            <a href="<?= base_path() ?>/dashboard/claims/new-claim.php?continue=<?= (int)$c['id'] ?>"
                               class="btn btn-sm btn-success">
                                Continue <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('Delete this draft claim?')">
                                <?php csrfField(); ?>
                                <input type="hidden" name="action" value="delete_claim">
                                <input type="hidden" name="claim_id" value="<?= (int)$c['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                        <a href="<?= base_path() ?>/dashboard/claims/view-claim.php?id=<?= (int)$c['id'] ?>"
                           class="btn btn-sm btn-outline-primary">
                            View <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>