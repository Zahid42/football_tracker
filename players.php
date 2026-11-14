<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle  = 'Players';
$activePage = 'players.php';
require __DIR__ . '/includes/header.php';
$players = players_list();
?>
<div class="container py-4">
  <div class="section-head"><h1 class="h2 mb-0">Players</h1></div>
  <p class="text-muted-ft">Tap a player to see his profile, matches, statistics and trophies.</p>
  <?php if (!$players): ?><p class="text-muted-ft">No players yet.</p><?php endif; ?>
  <div class="row g-3">
    <?php foreach ($players as $pl): ?>
      <div class="col-6 col-md-4 col-xl-3"><?= player_card($pl, with_player((int)$pl['id'], fn() => get_overall_stats())) ?></div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
