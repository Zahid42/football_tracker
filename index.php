<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle  = 'Home';
$activePage = 'index.php';
require __DIR__ . '/includes/header.php';
$counts = [
    ['Players', (int)db()->query("SELECT COUNT(*) FROM players WHERE approval_status = 'approved' AND is_active = 1")->fetchColumn(), 'bi-person', 'players.php'],
    ['Teams', (int)db()->query("SELECT COUNT(*) FROM teams WHERE status = 'active'")->fetchColumn(), 'bi-shield', 'teams.php'],
    ['Competitions', (int)db()->query('SELECT COUNT(*) FROM competitions')->fetchColumn(), 'bi-trophy', 'competitions.php'],
];
?>
<div class="container home-search">
  <h1><?= e(setting('site_title', 'Football Tracker')) ?></h1>
  <p>Find any player, team or competition by name or 6-digit ID.</p>
  <?= search_box('homeSearch', 'ft-search-lg') ?>
  <div class="home-stats">
    <?php foreach ($counts as [$label, $n, $icon, $href]): ?>
      <a class="home-stat" href="<?= e(url($href)) ?>"><i class="bi <?= e($icon) ?>"></i><b><?= number_format($n) ?></b><span><?= e($label) ?></span></a>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
