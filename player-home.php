<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_once ROOT_PATH . '/includes/stats_view.php';
$pageTitle  = 'Overview';
$activePage = 'player-home.php';
require __DIR__ . '/includes/header.php';

$p        = get_profile();
$o        = get_overall_stats();
$form     = array_reverse(get_recent_form(5));
$teams    = get_team_history();
$trophies = get_trophies();
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

<!-- ============ KEY STATS ============ -->
<div class="container">
  <div class="stat-strip">
    <?php foreach ([['Matches', $o['matches']], ['Wins', $o['wins']], ['Goals', $o['goals']], ['Assists', $o['assists']], ['Avg Rating', $o['avg_rating'] !== null ? fmt_num($o['avg_rating']) : '-'], ['Win %', fmt_num($o['win_pct']) . '%'], ['Trophies', count($trophies)]] as [$l, $v]): ?>
      <div><b><?= e($v) ?></b><span><?= e($l) ?></span></div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ============ CAREER NUMBERS ============ -->
<section class="container home-section">
  <div class="section-head"><h2>Career in Numbers</h2><a href="<?= e(purl('statistics.php')) ?>">All statistics <i class="bi bi-arrow-right"></i></a></div>
  <div class="row g-3 mb-3"><?php foreach ([['Matches', $o['matches'], 'bi-calendar-event', 'c-blue'], ['Goals', $o['goals'], 'bi-bullseye', 'c-green'], ['Assists', $o['assists'], 'bi-arrow-left-right', 'c-blue'], ['Minutes Played', number_format($o['minutes']), 'bi-stopwatch', 'c-purple'],
      ['Yellow Cards', $o['yellow'], 'bi-square-fill', 'c-amber'], ['Red Cards', $o['red'], 'bi-square-fill', 'c-red'], ['Goals / Match', fmt_num($o['goals_per_match'], 2), 'bi-graph-up', 'c-green'], ['Assists / Match', fmt_num($o['assists_per_match'], 2), 'bi-graph-up-arrow', 'c-blue']] as [$l, $v, $i, $c]): ?>
    <div class="col-6 col-md-3"><?= stat_card($l, $v, $i, $c) ?></div><?php endforeach; ?></div>
</section>



<!-- ============ TEAMS ============ -->
<section class="container home-section">
  <div class="section-head"><h2>Teams Played For</h2><a href="<?= e(purl('career.php')) ?>">Full career <i class="bi bi-arrow-right"></i></a></div>
  <?php if (!$teams): ?><p class="text-muted-ft">No teams yet.</p><?php else: ?>
    <div class="row-list"><?php foreach (array_slice($teams, 0, 8) as $t) { echo team_row_item($t); } ?></div>
  <?php endif; ?>
</section>
<?php
require __DIR__ . '/includes/footer.php';
