<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Venues'; $activePage = 'venues.php';
require __DIR__ . '/includes/header.php';
$rows = db()->query('SELECT v.*, (SELECT COUNT(*) FROM matches m WHERE m.venue_id = v.id) mc FROM venues v ORDER BY v.name')->fetchAll();
?>
<div class="container py-4">
  <h1 class="h2 mb-1">Venues</h1><p class="text-muted-ft mb-4">Where the matches are played.</p>
  <div class="row g-3 venue-grid">
  <?php foreach ($rows as $v): ?>
    <div class="col-md-6 col-lg-4"><a class="venue-tile" href="<?= e(venue_url((int)$v['id'])) ?>">
      <div class="d-flex justify-content-between align-items-start"><b class="fs-5"><?= e($v['name']) ?></b><?= venue_has_geo($v) ? '<span class="badge text-bg-success"><i class="bi bi-geo-alt-fill"></i> Map</span>' : '' ?></div>
      <div class="small text-muted-ft"><?= e(implode(', ', array_filter([$v['city'], $v['country']]))) ?></div>
      <div class="small mt-2"><?= e($v['pitch_type'] ?? '') ?><?= $v['pitch_type'] ? ' &middot; ' : '' ?><?= (int)$v['mc'] ?> match<?= (int)$v['mc'] === 1 ? '' : 'es' ?></div></a></div>
  <?php endforeach; if (!$rows): ?><div class="col-12 text-muted-ft">No venues yet.</div><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
