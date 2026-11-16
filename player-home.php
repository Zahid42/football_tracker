<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_once ROOT_PATH . '/includes/stats_view.php';
$pageTitle  = 'Overview';
$activePage = 'player-home.php';
require __DIR__ . '/includes/header.php';

$p        = get_profile();
$form     = array_reverse(get_recent_form(5));
$isFeatured = ctx_pid() === featured_player_id();
$bio = trim((string)($p['bio'] ?? ''));
?>
<?php
?>
<?= player_tabs('player-home.php') ?>
<!-- ============ HERO ============ -->
<section class="hero">
  <div class="container hero-inner">
    <div class="row align-items-center g-4">
      <div class="col-lg-5 text-center">
        <div class="photo-frame">
          <img class="hero-photo" src="<?= e(upload_url($p['photo'] ?? null)) ?>" alt="<?= e($p['full_name']) ?>">
          <?php if (!empty($p['jersey_number'])): ?><span class="photo-number">#<?= (int)$p['jersey_number'] ?></span><?php endif; ?>
        </div>
      </div>
      <div class="col-lg-7 text-center text-lg-start">
        <div class="hero-kicker">Football Player Profile</div>
        <h1 class="hero-name"><?= e($p['full_name']) ?></h1>
        <?php if (!empty($p['nickname'])): ?><div class="hero-nick">&ldquo;<?= e($p['nickname']) ?>&rdquo;</div><?php endif; ?>
        <div class="hero-meta"><?= e(position_name($p['preferred_position'] ?? null)) ?><?php if (!empty($p['team_name'])): ?> &middot; <?= e($p['team_name']) ?><?php endif; ?></div>
        <?php if ($form): ?><div class="mt-4 d-flex justify-content-center justify-content-lg-start align-items-center gap-2"><span class="text-muted-ft small text-uppercase">Form</span><?php foreach ($form as $c) { echo form_pill($c); } ?></div><?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php
require __DIR__ . '/includes/footer.php';
