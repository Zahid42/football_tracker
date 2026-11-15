<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_once ROOT_PATH . '/includes/stats_view.php';
$pageTitle  = 'Home';
$activePage = 'index.php';
require __DIR__ . '/includes/header.php';

$p        = get_profile();
$o        = get_overall_stats();
$form     = array_reverse(get_recent_form(5));
$teams    = get_team_history();
$trophies = get_trophies();
$matches  = search_matches(match_filters([]), 10);
$gallery  = db()->query('SELECT * FROM gallery WHERE player_id IS NULL OR player_id = ' . ctx_pid() . ' ORDER BY photo_date DESC, id DESC LIMIT 6')->fetchAll();
$allPlayers = players_list();
$isFeatured = ctx_pid() === featured_player_id();
$bio = trim((string)($p['bio'] ?? ''));
?>
<?php
?>
<?= switch_bar('players') ?>
<?= player_tabs('index.php') ?>
<?php if (!$isFeatured): ?>
<div class="container pt-3"><div class="alert alert-info py-2 mb-0 d-flex justify-content-between align-items-center flex-wrap gap-2"><span><i class="bi bi-person"></i> You are viewing <b><?= e($p['full_name']) ?></b>.</span><a class="btn btn-sm btn-outline-theme" href="<?= e(url('index.php')) ?>">Back to homepage player</a></div></div>
<?php endif; ?>
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
        <p class="hero-intro"><?= e($p['playing_style'] ?: setting('site_tagline')) ?></p>
        <div class="hero-chips">
          <?php foreach (['foot' => ['Strong foot', $p['strong_foot'] ?? null], 'height' => ['Height', !empty($p['height_cm']) ? $p['height_cm'] . ' cm' : null], 'age' => ['Age', ($a = age_from_dob($p['date_of_birth'] ?? null)) !== null ? $a . ' yrs' : null], 'matches' => ['Matches', $o['matches']], 'goals' => ['Goals', $o['goals']], 'assists' => ['Assists', $o['assists']]] as $ck => [$k, $v]): if ($v === null || $v === '' || !home_on('home_chips', $ck)) { continue; } ?>
            <span class="chip"><small><?= e($k) ?></small> <?= e($v) ?></span>
          <?php endforeach; ?>
        </div>
        <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-lg-start mt-4">
          <a class="btn btn-accent btn-lg" href="<?= e(purl('matches.php')) ?>"><i class="bi bi-calendar-event"></i> View Matches</a>
          <a class="btn btn-outline-theme btn-lg" href="<?= e(purl('statistics.php')) ?>"><i class="bi bi-bar-chart-line"></i> Statistics</a>
          <a class="btn btn-outline-theme btn-lg" href="#squads"><i class="bi bi-people"></i> Match Squads</a>
        </div>
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

<?php if (home_on('home_sections', 'numbers')): ?>
<!-- ============ CAREER NUMBERS ============ -->
<section class="container home-section">
  <div class="section-head"><h2>Career in Numbers</h2><a href="<?= e(purl('statistics.php')) ?>">All statistics <i class="bi bi-arrow-right"></i></a></div>
  <div class="row g-3 mb-3"><?php foreach ([['Matches', $o['matches'], 'bi-calendar-event', 'c-blue'], ['Goals', $o['goals'], 'bi-bullseye', 'c-green'], ['Assists', $o['assists'], 'bi-arrow-left-right', 'c-blue'], ['Minutes Played', number_format($o['minutes']), 'bi-stopwatch', 'c-purple'],
      ['Yellow Cards', $o['yellow'], 'bi-square-fill', 'c-amber'], ['Red Cards', $o['red'], 'bi-square-fill', 'c-red'], ['Goals / Match', fmt_num($o['goals_per_match'], 2), 'bi-graph-up', 'c-green'], ['Assists / Match', fmt_num($o['assists_per_match'], 2), 'bi-graph-up-arrow', 'c-blue']] as [$l, $v, $i, $c]): ?>
    <div class="col-6 col-md-3"><?= stat_card($l, $v, $i, $c) ?></div><?php endforeach; ?></div>
</section>

<?php endif; ?>

<?php if (home_on('home_sections', 'teams')): ?>
<!-- ============ TEAMS ============ -->
<section class="container home-section">
  <div class="section-head"><h2>Teams Played For</h2><a href="<?= e(purl('career.php')) ?>">Full career <i class="bi bi-arrow-right"></i></a></div>
  <?php if (!$teams): ?><p class="text-muted-ft">No teams yet.</p><?php else: ?>
    <div class="row-list"><?php foreach (array_slice($teams, 0, 8) as $t) { echo team_row_item($t); } ?></div>
  <?php endif; ?>
</section>

<?php if (home_on('home_sections', 'about')): ?>
<?php endif; ?>
<!-- ============ ABOUT THE PLAYER ============ -->
<section class="container home-section about-compact">
  <div class="section-head"><h2>About the Player</h2><a href="<?= e(purl('profile.php')) ?>">Full profile <i class="bi bi-arrow-right"></i></a></div>
  <div class="row g-3">
    <div class="col-lg-7"><div class="ft-card p-3 h-100">
      <h3 class="section-title h6">Biography</h3>
      <p class="lead-text bio-clamp mb-0"><?= $bio !== '' ? nl2br(e($bio)) : 'No biography yet. Add one from Admin &rarr; Players.' ?></p>
    </div></div>
    <div class="col-lg-5"><div class="ft-card p-3 h-100"><h3 class="section-title h6">Player card</h3><?= facts_list(array_slice(profile_facts($p), 2, 4)) ?></div></div>
  </div>
</section>

<?php endif; ?>
<?php if (home_on('home_sections', 'achievements')): ?>
<!-- ============ ACHIEVEMENTS ============ -->
<section class="container home-section">
  <div class="section-head"><h2>Achievements</h2><a href="<?= e(purl('trophies.php')) ?>">All trophies <i class="bi bi-arrow-right"></i></a></div>
  <?php if (!$trophies): ?><p class="text-muted-ft">No trophies yet.</p><?php else: ?>
    <div class="row g-3"><?php foreach (array_slice($trophies, 0, 6) as $t): ?><div class="col-12 col-md-6 col-xl-4"><?= trophy_card($t) ?></div><?php endforeach; ?></div>
  <?php endif; ?>
</section>

<?php endif; ?>
<?php
$today = date('Y-m-d');
$fx = db()->prepare("SELECT * FROM calendar_events WHERE event_type = 'Match' AND event_date >= ? AND (player_id IS NULL OR player_id = ?) ORDER BY event_date, start_time LIMIT 5");
$fx->execute([$today, ctx_pid()]); $fixtures = $fx->fetchAll();
?>
<?php if (home_on('home_sections', 'fixtures')): ?>
<!-- ============ FIXTURES ============ -->
<section class="container home-section">
  <div class="section-head"><h2>Fixtures</h2><a href="<?= e(url('calendar.php')) ?>">Calendar <i class="bi bi-arrow-right"></i></a></div>
  <?php if (!$fixtures): ?><p class="text-muted-ft">No upcoming fixtures. The admin adds them in Admin &rarr; Calendar (type &ldquo;Match&rdquo;).</p><?php else: ?>
  <div class="row-list"><?php foreach ($fixtures as $f): ?>
    <div class="row-item">
      <span class="ri-date"><b><?= e(fmt_date($f['event_date'], 'd')) ?></b><small><?= e(fmt_date($f['event_date'], 'M Y')) ?></small></span>
      <span class="ri-main"><b><?= e($f['title']) ?></b><small><?= $f['start_time'] ? '<i class="bi bi-clock"></i> ' . e(substr((string)$f['start_time'], 0, 5)) . ' ' : '' ?><?= $f['location'] ? '<i class="bi bi-geo-alt"></i> ' . e($f['location']) : '' ?></small></span>
      <span class="ri-pill k-match">Fixture</span>
    </div><?php endforeach; ?></div>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php if (home_on('home_sections', 'squads')): ?>
<!-- ============ MATCH SQUADS ============ -->
<section class="container home-section" id="squads">
  <div class="section-head"><h2>Match Squads</h2><a href="<?= e(purl('matches.php')) ?>">All matches <i class="bi bi-arrow-right"></i></a></div>
  <?php if (!$matches): ?><p class="text-muted-ft">No matches yet.</p><?php else: ?>
  <div class="squad-layout">
  <div class="squad-list" id="squadList"><div class="nav nav-pills flex-column squad-rows" role="tablist">
    <?php foreach ($matches as $i => $m): $rc = ['W' => 'win', 'D' => 'draw', 'L' => 'loss'][$m['result']]; ?>
      <button class="nav-link squad-row<?= $i === 0 ? ' active' : '' ?>" data-bs-toggle="pill" data-bs-target="#sq-<?= (int)$m['id'] ?>" type="button" role="tab">
        <span class="sr-res result-<?= $rc ?>"><?= e($m['result']) ?></span>
        <span class="sr-main"><b>vs <?= e($m['opponent_name']) ?></b><small><?= e(fmt_date($m['match_date'], 'd M Y')) ?></small></span>
        <span class="sr-score"><?= (int)$m['team_score'] ?> - <?= (int)$m['opponent_score'] ?></span>
      </button>
    <?php endforeach; ?>
  </div></div>
  <div class="tab-content squad-content">
    <?php foreach ($matches as $i => $m): $goals = get_match_goals((int)$m['id']); $assists = get_match_assists((int)$m['id']); ?>
      <div class="tab-pane fade<?= $i === 0 ? ' show active' : '' ?>" id="sq-<?= (int)$m['id'] ?>" role="tabpanel">
        <div class="squad-pane">
          <div class="squad-main"><?= render_pitch($m, get_squad((int)$m['id']), true) ?></div>
          <div class="squad-side"><div class="squad-info">
            <?= render_scoreboard($m) ?>
            <div class="ft-card p-3 mt-3">
              <div class="row g-3">
                <div class="col-md-6"><h3 class="section-title h6">Goals</h3>
                  <?php if (!$goals): ?><p class="text-muted-ft small mb-0">No goal log for this match.</p><?php endif; ?>
                  <?php foreach ($goals as $g): ?><div class="ev"><span class="ev-min"><?= $g['minute'] !== null ? (int)$g['minute'] . "'" : '-' ?></span><i class="bi bi-bullseye text-success"></i> <?= e($g['goal_type']) ?><?= $g['assisted_by'] ? ' <small class="text-muted-ft">(assist: ' . e($g['assisted_by']) . ')</small>' : '' ?></div><?php endforeach; ?></div>
                <div class="col-md-6"><h3 class="section-title h6">Assists</h3>
                  <?php if (!$assists): ?><p class="text-muted-ft small mb-0">No assist log for this match.</p><?php endif; ?>
                  <?php foreach ($assists as $a): ?><div class="ev"><span class="ev-min"><?= $a['minute'] !== null ? (int)$a['minute'] . "'" : '-' ?></span><i class="bi bi-arrow-left-right text-info"></i> <?= e($a['assist_type']) ?><?= $a['goal_scorer'] ? ' <small class="text-muted-ft">(to ' . e($a['goal_scorer']) . ')</small>' : '' ?></div><?php endforeach; ?></div>
              </div>
              <a class="btn btn-accent btn-sm mt-3" href="<?= e(purl('match-details.php?id=' . (int)$m['id'])) ?>">Open full match page <i class="bi bi-arrow-right"></i></a>
            </div>
          </div></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  </div>
  <?php endif; ?>
<script>
(function(){var L=document.getElementById('squadList');if(!L){return;}
function sync(){var pane=document.querySelector('#squads .tab-pane.active');var m=pane&&pane.querySelector('.squad-main');var inf=pane&&pane.querySelector('.squad-info');
  if(m&&window.innerWidth>=1200){var h=m.offsetHeight;L.style.height=h+'px';if(inf){inf.style.height=h+'px';}}else{L.style.height='';if(inf){inf.style.height='';}}}
window.addEventListener('load',sync);window.addEventListener('resize',sync);
document.querySelectorAll('.squad-row').forEach(function(b){b.addEventListener('shown.bs.tab',function(){setTimeout(sync,60)})});setTimeout(sync,100);})();
</script>
</section>

<?php endif; ?>
<!-- ============ GALLERY ============ -->
<?php if ($gallery && home_on('home_sections', 'gallery')): ?>
<section class="container home-section">
  <div class="section-head"><h2>Gallery</h2><a href="<?= e(purl('gallery.php')) ?>">Full gallery <i class="bi bi-arrow-right"></i></a></div>
  <div class="gallery-strip"><?php foreach ($gallery as $g): ?><a href="<?= e(purl('gallery.php')) ?>"><img src="<?= e(upload_url($g['image'])) ?>" alt="<?= e($g['caption'] ?? '') ?>" loading="lazy"></a><?php endforeach; ?></div>
</section>
<?php endif; ?>
<?php
require __DIR__ . '/includes/footer.php';
