<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$st = db()->prepare('SELECT * FROM teams WHERE id = ?'); $st->execute([(int)($_GET['id'] ?? 0)]); $t = $st->fetch();
$pageTitle = $t ? $t['name'] : 'Team not found'; $activePage = 'team.php';
require __DIR__ . '/includes/header.php';
if (!$t) { http_response_code(404); echo '<div class="container py-5 text-center"><h1>Team not found</h1><a class="btn btn-accent" href="' . e(url('index.php')) . '">Home</a></div>'; require __DIR__ . '/includes/footer.php'; exit; }
$q = db()->prepare("SELECT DISTINCT p.*, ? AS team_name FROM players p JOIN player_teams pt ON pt.player_id = p.id WHERE pt.team_id = ? AND p.approval_status='approved' AND p.is_active=1 ORDER BY p.full_name");
$q->execute([$t['name'], $t['id']]); $roster = $q->fetchAll();
$kits = db()->prepare('SELECT * FROM team_kits WHERE team_id = ? ORDER BY is_current DESC, season DESC, id DESC'); $kits->execute([$t['id']]); $kits = $kits->fetchAll();
$current = $kits && $kits[0]['is_current'] ? $kits[0] : null; $archive = $current ? array_slice($kits, 1) : $kits;
$records = db()->prepare('SELECT * FROM team_records WHERE team_id = ? ORDER BY season DESC, id DESC'); $records->execute([$t['id']]); $records = $records->fetchAll();
$achs = db()->prepare('SELECT * FROM team_achievements WHERE team_id = ? ORDER BY year DESC, id DESC'); $achs->execute([$t['id']]); $achs = $achs->fetchAll();
$hof = db()->prepare('SELECT * FROM team_hof WHERE team_id = ? ORDER BY inducted_year DESC, id DESC'); $hof->execute([$t['id']]); $hof = $hof->fetchAll();
$squad = db()->prepare('SELECT s.*, p.public_code FROM team_squad s LEFT JOIN players p ON p.id = s.player_id WHERE s.team_id = ? ORDER BY s.shirt_number IS NULL, s.shirt_number, s.name'); $squad->execute([$t['id']]); $squad = $squad->fetchAll();
$m = db()->prepare('SELECT m.id, m.match_date, m.team_score, m.opponent_score, m.result, o.name opp FROM matches m JOIN opponents o ON o.id = m.opponent_id WHERE m.team_id = ? ORDER BY m.match_date DESC LIMIT 10');
$m->execute([$t['id']]); $ms = $m->fetchAll();
?>
<div class="container py-4">
  <a class="btn btn-outline-theme btn-sm mb-3" href="<?= e(url('teams.php')) ?>"><i class="bi bi-arrow-left"></i> All teams</a>
  <div class="ft-card p-4 mb-3 d-flex flex-wrap gap-4 align-items-center">
    <div class="tl-logo tl-logo-lg"><?= !empty($t['logo']) ? '<img src="' . e(upload_url($t['logo'])) . '" alt="">' : '<i class="bi bi-shield-shaded"></i>' ?></div>
    <div class="flex-grow-1"><h1 class="h3 mb-0"><?= e($t['name']) ?></h1><div class="text-muted-ft"><?= $t['founded_year'] ? 'Founded ' . (int)$t['founded_year'] . ' &middot; ' : '' ?><span class="id-chip" data-copy="<?= e(url((string)$t['public_code'])) ?>" title="Click to copy this team's link"><i class="bi bi-link-45deg"></i> ID <?= e($t['public_code']) ?></span></div>
    <?php if ($t['description']): ?><p class="mb-0 mt-2"><?= e($t['description']) ?></p><?php endif; ?></div>
    <?php if ($current): ?><div class="text-center"><div class="jersey-big"><?= kit_visual($current) ?></div><div class="small text-muted-ft mt-1">Current jersey<?= $current['season'] ? ' &middot; ' . e($current['season']) : '' ?></div></div><?php endif; ?>
  </div>
  <div class="tp-tabs sticky-tabs mb-4">
    <a class="tp-tab" href="#squad">Squad</a><a class="tp-tab" href="#records">Records</a><a class="tp-tab" href="#achievements">Achievements</a><a class="tp-tab" href="#hof">Hall of Fame</a><a class="tp-tab" href="#archive">Jersey Archive</a><a class="tp-tab" href="#players">Players</a>
  </div>

  <div class="section-head" id="squad"><h2>Current Squad</h2><span class="text-muted-ft"><?= count($squad) ?> / <?= SQUAD_MAX ?> players</span></div>
  <?php if (!$squad): ?><p class="text-muted-ft mb-4">The squad has not been announced yet.</p><?php else: ?>
  <div class="row g-3 mb-4 align-items-start">
    <div class="col-lg-8" id="sqv-pitch"><div class="small fw-semibold text-muted-ft mb-2"><i class="bi bi-diagram-3"></i> Pitch view</div><?php try { echo team_pitch($squad, isset($_GET['pitch']) && in_array((int)$_GET['pitch'], PITCH_SIZES, true) ? (int)$_GET['pitch'] : team_pitch_size((int)$t['id'])); } catch (Throwable $e) { echo '<p class="text-muted-ft">The pitch view could not be drawn.</p>'; } ?></div>
    <div class="col-lg-4" id="sqv-list"><div class="small fw-semibold text-muted-ft mb-2"><i class="bi bi-list-ul"></i> List view</div>
      <div class="row-list sq-list-scroll"><?php foreach ($squad as $s): ?>
        <<?= $s['public_code'] ? 'a href="' . e(url($s['public_code'])) . '"' : 'div' ?> class="row-item squad-member">
          <span class="sq-no"><?= $s['shirt_number'] !== null ? (int)$s['shirt_number'] : '-' ?></span>
          <img class="sq-photo" src="<?= e(upload_url($s['photo'])) ?>" alt="">
          <span class="ri-main"><b><?= e($s['name']) ?><?= $s['is_captain'] ? ' <span class="badge bg-warning text-dark">C</span>' : '' ?></b><small><?= e(position_name($s['position'])) ?><?= $s['notes'] ? ' &middot; ' . e($s['notes']) : '' ?></small></span>
          <?= $s['public_code'] ? '<span class="ri-pill k-event">Profile</span>' : '' ?>
        </<?= $s['public_code'] ? 'a' : 'div' ?>><?php endforeach; ?></div></div>
  </div><?php endif; ?>

  <div class="section-head" id="records"><h2>Previous Records</h2></div>
  <?php if (!$records): ?><p class="text-muted-ft mb-4">No records added yet.</p><?php else: ?>
  <div class="ft-card p-0 mb-4"><div class="table-responsive"><table class="table ft-table mb-0"><thead><tr><th>Season</th><th>Competition</th><th>P</th><th>W</th><th>D</th><th>L</th><th>GF</th><th>GA</th><th>Pts</th><th>Finish</th></tr></thead><tbody>
  <?php foreach ($records as $r): ?><tr><td class="fw-bold"><?= e($r['season']) ?></td><td><?= e((string)$r['competition']) ?></td><td><?= (int)$r['played'] ?></td><td><?= (int)$r['won'] ?></td><td><?= (int)$r['drawn'] ?></td><td><?= (int)$r['lost'] ?></td><td><?= (int)$r['goals_for'] ?></td><td><?= (int)$r['goals_against'] ?></td><td><?= (int)$r['won'] * 3 + (int)$r['drawn'] ?></td><td><?= e((string)$r['final_position']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div></div><?php endif; ?>

  <div class="section-head" id="achievements"><h2>Achievements</h2></div>
  <?php if (!$achs): ?><p class="text-muted-ft mb-4">No achievements added yet.</p><?php else: ?>
  <div class="row-list mb-4"><?php foreach ($achs as $a): ?>
    <div class="row-item"><span class="ri-date"><b><?= $a['year'] ? (int)$a['year'] : '-' ?></b><small>Year</small></span>
      <?php if ($a['image']): ?><img class="sq-photo" src="<?= e(upload_url($a['image'])) ?>" alt=""><?php endif; ?>
      <span class="ri-main"><b><?= e($a['title']) ?></b><small><?= e((string)$a['competition']) ?><?= $a['description'] ? ' &middot; ' . e($a['description']) : '' ?></small></span>
      <span class="ri-pill k-<?= $a['achievement_type'] === 'Trophy' ? 'fixture' : 'event' ?>"><?= e($a['achievement_type']) ?></span></div><?php endforeach; ?></div><?php endif; ?>

  <div class="section-head" id="hof"><h2><i class="bi bi-stars"></i> Hall of Fame</h2></div>
  <?php if (!$hof): ?><p class="text-muted-ft mb-4">No legends inducted yet.</p><?php else: ?>
  <div class="row g-3 mb-4"><?php foreach ($hof as $h): ?><div class="col-6 col-md-4 col-xl-3"><div class="hof-card">
    <img src="<?= e(upload_url($h['photo'])) ?>" alt=""><div class="hof-name"><?= e($h['name']) ?></div>
    <div class="hof-role"><?= e((string)$h['role']) ?></div><div class="small text-muted-ft"><?= e((string)$h['years_active']) ?><?= $h['inducted_year'] ? ' &middot; Inducted ' . (int)$h['inducted_year'] : '' ?></div>
    <?php if ($h['description']): ?><p class="small mt-2 mb-0"><?= e($h['description']) ?></p><?php endif; ?></div></div><?php endforeach; ?></div><?php endif; ?>

  <div class="section-head" id="archive"><h2>Jersey Archive</h2></div>
  <?php if (!$archive): ?><p class="text-muted-ft mb-4">No past jerseys added yet.</p><?php else: ?>
  <div class="row g-3 mb-4"><?php foreach ($archive as $k): ?><div class="col-6 col-md-3 col-xl-2"><div class="kit-card"><div class="kit-vis"><?= kit_visual($k) ?></div>
    <div class="fw-bold"><?= e((string)($k['season'] ?: '-')) ?></div><div class="small text-muted-ft"><?= e($k['kit_type']) ?><?= $k['label'] ? ' &middot; ' . e($k['label']) : '' ?></div></div></div><?php endforeach; ?></div><?php endif; ?>

  <div class="section-head" id="players"><h2>Players on this site</h2><span class="text-muted-ft"><?= count($roster) ?> registered</span></div>
  <?php $bySeason = team_players_by_season((int)$t['id']); ?>
  <?php if (!$roster && !$bySeason): ?><p class="text-muted-ft mb-4">No registered players for this team yet.</p><?php endif; ?>
  <?php if ($roster): ?>
    <div class="pl-block"><h3 class="pl-block-title"><i class="bi bi-people"></i> All registered players <span class="badge text-bg-secondary align-middle"><?= count($roster) ?></span></h3>
      <?= player_tile_grid($roster) ?></div>
  <?php endif; ?>
  <?php foreach ($bySeason as $se): ?>
    <div class="pl-season"><h3 class="pl-season-title"><i class="bi bi-calendar3"></i> <?= e($se['season']) ?></h3>
    <?php foreach ($se['comps'] as $co): ?>
      <div class="pl-block"><h4 class="pl-block-title"><i class="bi bi-trophy"></i> <?= e($co['name']) ?> <span class="badge text-bg-secondary align-middle"><?= count($co['players']) ?></span></h4>
        <?= player_tile_grid($co['players']) ?></div>
    <?php endforeach; ?></div>
  <?php endforeach; ?>
  <div class="mb-4"></div>

  <div class="section-head"><h2>Recent matches</h2></div>
  <div class="ft-card p-0"><div class="table-responsive"><table class="table ft-table mb-0"><tbody>
    <?php foreach ($ms as $r): ?><tr><td><?= e(fmt_date($r['match_date'])) ?></td><td>vs <?= e($r['opp']) ?></td><td class="fw-bold"><?= (int)$r['team_score'] ?> - <?= (int)$r['opponent_score'] ?></td><td><?= result_badge($r['result']) ?></td><td class="text-end"><a href="<?= e(url('match-details.php?id=' . (int)$r['id'])) ?>">Open</a></td></tr><?php endforeach; ?>
    <?php if (!$ms): ?><tr><td class="text-muted-ft text-center py-3">No matches yet.</td></tr><?php endif; ?>
  </tbody></table></div></div>
</div>
<?php require __DIR__ . '/includes/footer.php';
