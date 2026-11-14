<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Trophies'; $activePage = 'trophies.php';
require __DIR__ . '/includes/header.php';
$tr = get_trophies();
$count = []; foreach ($tr as $t) { $count[$t['achievement']] = ($count[$t['achievement']] ?? 0) + 1; }
echo public_page_title('Trophies & Achievements', count($tr) . ' honours');
?>
<div class="container py-4">
  <div class="d-flex flex-wrap gap-2 mb-4"><?php foreach ($count as $k => $n): ?><span class="badge-soft"><?= trophy_icon($k) ?> <?= e($k) ?> <b><?= $n ?></b></span><?php endforeach; ?></div>
  <?php if (!$tr): ?><div class="ft-card p-4 text-center text-muted-ft">No trophies recorded yet.</div><?php endif; ?>
  <div class="row g-3"><?php foreach ($tr as $t): ?><div class="col-12 col-md-6 col-xl-4"><?= trophy_card($t) ?></div><?php endforeach; ?></div>
</div>
<?php require __DIR__ . '/includes/footer.php';
