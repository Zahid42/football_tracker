<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Profile'; $activePage = 'profile.php';
require __DIR__ . '/includes/header.php';
$p = get_profile(); $o = get_overall_stats();
echo public_page_title('Player Profile', $p['full_name']);
?>
<div class="container py-4">
  <div class="row g-4 mb-4">
    <div class="col-md-4 col-lg-3"><div class="text-center">
      <?= player_fut_card($p, $o) ?>
      <h2 class="h5 mt-3 mb-0 section-title"><?= e($p['full_name']) ?></h2>
      <div class="text-muted-ft"><?= e(position_name($p['preferred_position'] ?? null)) ?><?= !empty($p['jersey_number']) ? ' &middot; #' . (int)$p['jersey_number'] : '' ?></div>
      <?php if (!empty($p['public_code'])): ?><div class="id-chip mt-2" data-copy="<?= e(url($p['public_code'])) ?>" title="Click to copy this player's link"><i class="bi bi-link-45deg"></i> ID <?= e($p['public_code']) ?></div><?php endif; ?>
    </div></div>
    <div class="col-md-8 col-lg-5"><div class="ft-card p-4 h-100"><h3 class="section-title h6">Personal details</h3><?= facts_list(profile_facts($p)) ?></div></div>
    <div class="col-lg-4"><div class="ft-card p-4 h-100"><h3 class="section-title h6">Biography</h3><p><?= $p['bio'] ? nl2br(e($p['bio'])) : 'No biography yet.' ?></p>
      <?php if (!empty($p['playing_style'])): ?><h3 class="section-title h6 mt-3">Playing style</h3><p class="mb-0"><?= e($p['playing_style']) ?></p><?php endif; ?></div></div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
