<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle  = 'Players';
$activePage = 'players.php';
require __DIR__ . '/includes/header.php';
$f = player_filter_input();
$players = players_filtered($f, false);
?>
<?= switch_bar('players') ?>
<div class="container py-4">
  <div class="section-head"><h1 class="h2 mb-0">Players</h1></div>
  <p class="text-muted-ft">Tap a player to see his profile, matches, statistics and trophies.</p>
  <?= player_filter_bar($f, false) ?>
  <div class="text-muted-ft small mb-2"><?= count($players) ?> player<?= count($players) === 1 ? '' : 's' ?><?= player_filter_active($f) ? ' found' : '' ?></div>
  <?php if (!$players): ?><p class="text-muted-ft py-4 text-center"><?= player_filter_active($f) ? 'No player matches these filters.' : 'No players yet.' ?></p><?php endif; ?>
  <div class="row g-3">
    <?php foreach ($players as $pl): ?>
      <div class="col-6 col-md-4 col-xl-3"><?= player_card($pl, $pl['stats']) ?></div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
