<?php
/**
 * includes/header.php
 * Global HTML <head> — include at the top of every page.
 * Set $pageTitle before including this file.
 */
$pageTitle = $pageTitle ?? 'PawShield — Pet Insurance Made Simple';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="PawShield — AI-powered pet insurance. Upload your vet receipt and get an instant claim quote.">
    <?php if (!empty($csrfToken)): ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>

    <!-- Bootstrap 5 (CDN) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Custom styles -->
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= base_path() ?>/assets/css/accessibility.css">
</head>
<body>
<!-- Skip to main content (WCAG) -->
<a class="skip-link" href="#main-content">Skip to main content</a>