<?php
/**
 * Breadcrumb navigation for dashboard (and other) pages.
 * Expects $breadcrumb_items = [ [ 'Label', 'url' ], ... ]. Empty url = current page (no link).
 */
if (empty($breadcrumb_items) || !is_array($breadcrumb_items)) {
    return;
}
?>
<nav class="breadcrumb-nav" aria-label="Breadcrumb">
    <ol class="breadcrumb mb-0">
        <?php foreach ($breadcrumb_items as $i => $item): ?>
            <?php
            $label = $item[0] ?? '';
            $url   = $item[1] ?? '';
            if ($label === '') continue;
            ?>
            <li class="breadcrumb-item<?= $url === '' ? ' active' : '' ?>" <?= $url === '' ? 'aria-current="page"' : '' ?>>
                <?php if ($url !== ''): ?>
                    <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"><?= esc($label) ?></a>
                <?php else: ?>
                    <?= esc($label) ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
