<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle  = 'Home';
$activePage = 'index.php';
$mainClass  = 'is-home';
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
  <div class="home-stats" style="grid-template-columns:repeat(<?= max(1, count(array_filter(['players', 'teams', 'competitions'], fn($k) => setting('home_stat_' . $k . '_show', '1') === '1'))) ?>,1fr)">
    <?php foreach ($counts as [$label, $n, $icon, $href]):
        $k = strtolower($label);
        if (setting('home_stat_' . $k . '_show', '1') !== '1') { continue; }
        $link = setting('home_stat_' . $k . '_link', '1') === '1';
        $tag = $link ? 'a href="' . e(url($href)) . '"' : 'div'; ?>
      <<?= $tag ?> class="home-stat<?= $link ? '' : ' no-link' ?>"><i class="bi <?= e($icon) ?>"></i><b><?= number_format($n) ?></b><span><?= e($label) ?></span></<?= $link ? 'a' : 'div' ?>>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
