<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle = 'About'; $activePage = 'about.php';
require __DIR__ . '/includes/header.php';
$p = get_profile(); $o = get_overall_stats();
echo public_page_title('About', setting('site_tagline'));
?>
<div class="container py-4">
  <div class="row g-4">
    <div class="col-lg-8"><div class="ft-card p-4 mb-4"><h2 class="section-title h5">About this website</h2><p class="lead-text mb-0"><?= nl2br(e(setting('about_text'))) ?></p></div>
      <div class="ft-card p-4"><h2 class="section-title h5">About <?= e($p['full_name']) ?></h2><p class="mb-0"><?= $p['bio'] ? nl2br(e($p['bio'])) : 'No biography yet.' ?></p></div></div>
    <div class="col-lg-4"><div class="ft-card p-4"><h2 class="section-title h6">At a glance</h2>
      <?= facts_list(['Matches' => $o['matches'], 'Goals' => $o['goals'], 'Assists' => $o['assists'], 'Trophies' => get_trophy_count(), 'Position' => position_name($p['preferred_position'] ?? null)]) ?>
      <?php if (setting('contact_email') !== ''): ?><hr><a href="mailto:<?= e(setting('contact_email')) ?>"><i class="bi bi-envelope"></i> <?= e(setting('contact_email')) ?></a><?php endif; ?></div></div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
