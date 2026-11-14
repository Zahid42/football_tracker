<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Profile'; $activePage = 'profile.php';
require __DIR__ . '/includes/header.php';
$p = get_profile(); $o = get_overall_stats(); $teams = get_team_history(); $trophies = get_trophies();
echo public_page_title('Player Profile', $p['full_name']);
?>
<div class="container py-4">
  <div class="row g-4 mb-4">
    <div class="col-md-4 col-lg-3"><div class="ft-card p-3 text-center h-100">
      <img class="profile-photo" src="<?= e(upload_url($p['photo'] ?? null)) ?>" alt="<?= e($p['full_name']) ?>">
      <h2 class="h4 mt-3 mb-0 section-title"><?= e($p['full_name']) ?></h2>
      <div class="text-muted-ft"><?= e(position_name($p['preferred_position'] ?? null)) ?><?= !empty($p['jersey_number']) ? ' &middot; #' . (int)$p['jersey_number'] : '' ?></div>
    </div></div>
    <div class="col-md-8 col-lg-5"><div class="ft-card p-4 h-100"><h3 class="section-title h6">Personal details</h3><?= facts_list(profile_facts($p)) ?></div></div>
    <div class="col-lg-4"><div class="ft-card p-4 h-100"><h3 class="section-title h6">Biography</h3><p><?= $p['bio'] ? nl2br(e($p['bio'])) : 'No biography yet.' ?></p>
      <?php if (!empty($p['playing_style'])): ?><h3 class="section-title h6 mt-3">Playing style</h3><p class="mb-0"><?= e($p['playing_style']) ?></p><?php endif; ?></div></div>
  </div>
  <h2 class="section-title h4 mb-3">Career totals</h2>
  <?= summary_cards($o) ?><?= overall_panels($o) ?>
  <h2 class="section-title h4 mb-3">Teams</h2>
  <div class="timeline mb-5"><?php foreach ($teams as $t) { echo team_timeline_item($t); } ?><?php if (!$teams): ?><p class="text-muted-ft">No teams yet.</p><?php endif; ?></div>
  <h2 class="section-title h4 mb-3">Trophies &amp; achievements (<?= count($trophies) ?>)</h2>
  <div class="row g-3"><?php foreach ($trophies as $t): ?><div class="col-12 col-md-6 col-xl-4"><?= trophy_card($t) ?></div><?php endforeach; ?></div>
</div>
<?php require __DIR__ . '/includes/footer.php';
