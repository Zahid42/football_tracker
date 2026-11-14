<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
$admin = require_login();
$types = ['career' => 'Career Report', 'season' => 'Season Report', 'competition' => 'Competition Report', 'opponent' => 'Opponent Report', 'performance' => 'Performance Report'];
$type = get_str('type', 'career');
if (!isset($types[$type])) { $type = 'career'; }
$id = (int)($_GET['id'] ?? 0);
$L = lookups();
$filters = ['q'=>'','from'=>'','to'=>'','result'=>'','position'=>'','sort'=>'date','dir'=>'asc','season'=>0,'competition'=>0,'team'=>0,'opponent'=>0];
$subtitle = 'All matches';
$opts = null;
if ($type === 'season')      { $opts = $L['seasons'];      $id = isset($opts[$id]) ? $id : (int)array_key_first($opts); $filters['season'] = $id; $subtitle = 'Season ' . ($opts[$id] ?? ''); }
if ($type === 'competition') { $opts = $L['competitions']; $id = isset($opts[$id]) ? $id : (int)array_key_first($opts); $filters['competition'] = $id; $subtitle = $opts[$id] ?? ''; }
if ($type === 'opponent')    { $opts = $L['opponents'];    $id = isset($opts[$id]) ? $id : (int)array_key_first($opts); $filters['opponent'] = $id; $subtitle = 'vs ' . ($opts[$id] ?? ''); }
$rows = search_matches($filters, 0, 0, true);
if ($type === 'performance') { $subtitle = 'Last 10 matches'; $rows = array_slice($rows, -10); }

$t = ['n' => count($rows), 'w' => 0, 'd' => 0, 'l' => 0, 'g' => 0, 'a' => 0, 'min' => 0, 'y' => 0, 'r' => 0, 'rs' => 0.0, 'rn' => 0, 'gf' => 0, 'ga' => 0];
foreach ($rows as $m) {
    $t[strtolower($m['result'])]++; $t['g'] += $m['goals']; $t['a'] += $m['assists']; $t['min'] += $m['minutes_played']; $t['y'] += $m['yellow_cards']; $t['r'] += $m['red_cards'];
    $t['gf'] += $m['team_score']; $t['ga'] += $m['opponent_score'];
    if ($m['rating'] !== null) { $t['rs'] += (float)$m['rating']; $t['rn']++; }
}
$avg = $t['rn'] ? fmt_num($t['rs'] / $t['rn']) : '-';
$winPct = $t['n'] ? fmt_num($t['w'] / $t['n'] * 100) : '0.0';
$best = null; foreach ($rows as $m) { if ($m['rating'] !== null && ($best === null || $m['rating'] > $best['rating'])) { $best = $m; } }
$p = get_profile();
$pageTitle = $types[$type];
require ROOT_PATH . '/includes/admin_header.php';
?>
<div class="no-print">
  <h1 class="page-title h3 mb-3"><i class="bi bi-printer"></i> Reports &amp; Export</h1>
  <ul class="nav nav-pills stat-tabs mb-3">
    <?php foreach ($types as $k => $label): ?><li class="nav-item"><a class="nav-link <?= $k === $type ? 'active' : '' ?>" href="?type=<?= e($k) ?>"><?= e($label) ?></a></li><?php endforeach; ?>
  </ul>
  <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <?php if ($opts !== null): ?>
      <form method="get" class="d-flex gap-2"><input type="hidden" name="type" value="<?= e($type) ?>">
        <select class="form-select" name="id" onchange="this.form.submit()"><?php foreach ($opts as $k => $label): ?><option value="<?= $k ?>" <?= $k === $id ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></form>
    <?php endif; ?>
    <button class="btn btn-accent" onclick="window.print()"><i class="bi bi-printer"></i> Print Report</button>
    <span class="text-muted-ft small">Tip: choose "Save as PDF" in the print dialog to get a PDF.</span>
  </div>
  <div class="ft-card p-3 mb-4"><h2 class="section-title h6"><i class="bi bi-filetype-csv"></i> Export CSV</h2>
    <div class="d-flex flex-wrap gap-2">
      <?php foreach (['matches' => 'Matches', 'goals' => 'Goals', 'assists' => 'Assists', 'trophies' => 'Trophies', 'squads' => 'Squads'] as $k => $label): ?>
        <a class="btn btn-outline-theme btn-sm" href="<?= e(url('admin/export.php?type=' . $k)) ?>"><i class="bi bi-download"></i> <?= e($label) ?></a>
      <?php endforeach; ?></div></div>
</div>

<div class="report-sheet">
  <div class="report-head"><div><h2><?= e($types[$type]) ?></h2><div class="text-muted-ft"><?= e($subtitle) ?></div></div>
    <div class="text-end"><b><?= e($p['full_name']) ?></b><div class="text-muted-ft small">Generated <?= e(date('d M Y, H:i')) ?></div></div></div>
  <div class="row g-2 report-kpis my-3">
    <?php foreach (['Matches' => $t['n'], 'Wins' => $t['w'], 'Draws' => $t['d'], 'Losses' => $t['l'], 'Win %' => $winPct . '%', 'Goals' => $t['g'], 'Assists' => $t['a'], 'Minutes' => $t['min'], 'Yellow' => $t['y'], 'Red' => $t['r'], 'Avg Rating' => $avg, 'Team GF-GA' => $t['gf'] . '-' . $t['ga']] as $k => $v): ?>
      <div class="col-4 col-md-2"><div class="kpi"><b><?= e($v) ?></b><span><?= e($k) ?></span></div></div>
    <?php endforeach; ?>
  </div>
  <?php if ($best): ?><p class="mb-3"><b>Best rated match:</b> <?= e(fmt_date($best['match_date'])) ?> vs <?= e($best['opponent_name']) ?> (<?= (int)$best['team_score'] ?>-<?= (int)$best['opponent_score'] ?>), rating <?= e(fmt_num($best['rating'])) ?>.</p><?php endif; ?>
  <div class="table-responsive"><table class="table ft-table table-sm align-middle"><thead><tr><th>Date</th><th>Competition</th><th>Team</th><th>Opponent</th><th>Score</th><th>Result</th><th>Pos</th><th class="text-end">Min</th><th class="text-end">G</th><th class="text-end">A</th><th class="text-end">Rating</th></tr></thead><tbody>
    <?php foreach ($rows as $m): ?><tr><td><?= e(fmt_date($m['match_date'])) ?></td><td><?= e($m['competition_name'] ?? $m['match_type']) ?></td><td><?= e($m['team_name']) ?></td><td><?= e($m['opponent_name']) ?></td>
      <td><?= (int)$m['team_score'] ?>-<?= (int)$m['opponent_score'] ?></td><td><?= e(result_label($m['result'])) ?></td><td><?= e($m['position'] ?? '-') ?></td><td class="text-end"><?= (int)$m['minutes_played'] ?></td>
      <td class="text-end"><?= (int)$m['goals'] ?></td><td class="text-end"><?= (int)$m['assists'] ?></td><td class="text-end"><?= e($m['rating'] !== null ? fmt_num($m['rating']) : '-') ?></td></tr><?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="11" class="text-center text-muted-ft">No matches for this report.</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<?php require ROOT_PATH . '/includes/admin_footer.php';
