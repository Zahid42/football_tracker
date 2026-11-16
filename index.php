<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle  = 'Home';
$activePage = 'index.php';
require __DIR__ . '/includes/header.php';
?>
<div class="container home-search">
  <h1><?= e(setting('site_title', 'Football Tracker')) ?></h1>
  <p>Find any player, team or competition by name or 6-digit ID.</p>
  <?= search_box('homeSearch', 'ft-search-lg') ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
