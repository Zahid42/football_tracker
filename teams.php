<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle  = 'Teams';
$activePage = 'teams.php';
require __DIR__ . '/includes/header.php';
$teams = db()->query("SELECT * FROM teams WHERE status = 'active' ORDER BY name")->fetchAll();
?>
<div class="container py-4">
  <div class="section-head"><h1 class="h2 mb-0">Teams</h1></div>
  <p class="text-muted-ft">Every team has its own page, reachable by its 6-digit ID.</p>
  <?php if (!$teams): ?><p class="text-muted-ft">No teams yet.</p><?php endif; ?>
  <div class="row g-3">
    <?php foreach ($teams as $t): ?>
      <div class="col-6 col-md-4 col-xl-3"><a class="team-tile" href="<?= e(team_url_by_id((int)$t['id'])) ?>">
        <div class="tl-logo"><?= !empty($t['logo']) ? '<img src="' . e(upload_url($t['logo'])) . '" alt="">' : '<i class="bi bi-shield-shaded"></i>' ?></div>
        <div><b><?= e($t['name']) ?></b><div class="small text-muted-ft">ID <?= e($t['public_code']) ?></div></div></a></div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
