<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
$admin = require_login();

$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/admin_header.php';

?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h1 class="page-title">Football Tracker Dashboard</h1>
    <p class="text-muted-ft mb-0">Welcome back, <?= e($admin['username']) ?>.</p>
  </div>
</div>

<!-- Quick actions -->
<div class="ft-card p-3 mb-4">
  <h2 class="section-title h5 mb-3">Quick Actions</h2>
  <div class="d-flex flex-wrap gap-2">
    <?php
    $actions = [
        ['+ Add Match',          'admin/match-add.php'],
        ['+ Add Team',           'admin/teams.php?action=new'],
        ['+ Add Competition',    'admin/competitions.php?action=new'],
        ['+ Add Venue',          'admin/venues.php?action=new'],
        ['+ Add Player', 'admin/players.php?action=new'],
    ];
    foreach ($actions as [$label, $href]): ?>
      <a class="btn btn-outline-theme" href="<?= e(url($href)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Sections grid -->
<?php $pending = count(pending_players()); $group = ''; $open = false; ?>
<?php foreach (admin_nav() as $item):
    if (isset($item['heading'])) { if ($open) { echo '</div>'; } $open = true; echo '<h2 class="section-title h6 mt-4 mb-2">' . e($item['heading']) . '</h2><div class="row g-3">'; continue; }
    if ($item['href'] === 'admin/index.php') { continue; }
?>
  <div class="col-6 col-md-4 col-xl-3">
    <a class="dash-tile" href="<?= e(url($item['href'])) ?>"<?= !empty($item['external']) ? ' target="_blank" rel="noopener"' : '' ?>>
      <i class="bi <?= e($item['icon']) ?>"></i><span><?= e($item['label']) ?></span>
      <?php if (($item['badge'] ?? '') === 'pending' && $pending): ?><b class="badge bg-danger"><?= $pending ?></b><?php endif; ?><?php if (($item['badge'] ?? '') === 'requests' && ($rc = pending_requests_count())): ?><b class="badge bg-danger"><?= $rc ?></b><?php endif; ?>
    </a>
  </div>
<?php endforeach; if ($open) { echo '</div>'; } ?>
<?php
require __DIR__ . '/../includes/admin_footer.php';
