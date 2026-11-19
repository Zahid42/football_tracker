<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle  = 'Competitions';
$activePage = 'competitions.php';
require __DIR__ . '/includes/header.php';

$comps = db()->query('SELECT * FROM competitions ORDER BY (type IN ("League","Tournament")) DESC, name')->fetchAll();
$cid = (int)($_GET['c'] ?? 0);
$cur = null;
foreach ($comps as $c) { if ((int)$c['id'] === $cid || ($cid >= 100000 && (int)($c['public_code'] ?? 0) === $cid)) { $cur = $c; } }
if (!$cur && $comps) { $cur = $comps[0]; }
$hasKo = $cur && !empty($cur['knockout']) && comp_ko_rows((int)$cur['id']);
$auc = $cur ? auc_get((int)$cur['id']) : null;
$hasAuc = $auc && $auc['status'] !== 'setup';
$tab = in_array($_GET['t'] ?? '', ['players', 'bracket', 'auction'], true) ? $_GET['t'] : 'table';
if ($tab === 'bracket' && !$hasKo) { $tab = 'table'; }
if ($tab === 'auction' && !$hasAuc) { $tab = 'table'; }
$mode = $cur ? (string)$cur['format'] : 'League';
if (isset($_GET['mode']) && in_array($_GET['mode'], ['League', 'Groups'], true)) { $mode = $_GET['mode']; }
$self = fn(array $q) => e(url('competitions.php?' . http_build_query(array_merge(['c' => ($cur['public_code'] ?? '') ?: ($cur['id'] ?? 0), 't' => $tab, 'mode' => $mode], $q))));

echo switch_bar('competitions');
echo public_page_title('Competitions', 'League tables, group-stage tournaments and player leaderboards');
?>
<div class="container py-4">
<?php if (!$comps): ?><p class="text-muted-ft">No competitions yet. The admin adds them in Admin &rarr; Competitions.</p><?php else: ?>
  <form method="get" class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <select class="form-select w-auto" name="c" onchange="this.form.submit()" aria-label="Competition">
      <?php foreach ($comps as $c): ?><option value="<?= e((string)($c['public_code'] ?: $c['id'])) ?>"<?= (int)$c['id'] === (int)$cur['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
    <input type="hidden" name="t" value="<?= e($tab) ?>">
    <div class="sw-tabs ms-auto">
      <a class="sw-tab<?= $tab === 'table' ? ' active' : '' ?>" href="<?= $self(['t' => 'table']) ?>"><i class="bi bi-table"></i> Table</a>
      <a class="sw-tab<?= $tab === 'players' ? ' active' : '' ?>" href="<?= $self(['t' => 'players']) ?>"><i class="bi bi-people"></i> Player stats</a>
      <?php if ($hasAuc): ?><a class="sw-tab<?= $tab === 'auction' ? ' active' : '' ?>" href="<?= $self(['t' => 'auction']) ?>"><i class="bi bi-hammer"></i> Auction</a><?php endif; ?>
      <?php if ($hasKo): ?><a class="sw-tab<?= $tab === 'bracket' ? ' active' : '' ?>" href="<?= $self(['t' => 'bracket']) ?>"><i class="bi bi-diagram-3"></i> Bracket</a><?php endif; ?>
    </div>
  </form>
  <?php if (!empty($cur['public_code'])): ?><p class="small text-muted-ft mb-1">Competition ID <b>#<?= e((string)$cur['public_code']) ?></b><?= $cur['format'] === 'Groups' ? ' &middot; ' . (int)$cur['num_groups'] . ' groups of ' . (int)$cur['teams_per_group'] . ' teams' : ($cur['max_teams'] ? ' &middot; ' . (int)$cur['max_teams'] . ' teams' : '') ?></p><?php endif; ?>
  <?php if (!empty($cur['description'])): ?><p class="text-muted-ft"><?= e($cur['description']) ?></p><?php endif; ?>

  <?php if ($tab === 'table'): ?>
    <div class="sw-tabs mb-3">
      <a class="sw-tab<?= $mode === 'League' ? ' active' : '' ?>" href="<?= $self(['mode' => 'League']) ?>"><i class="bi bi-list-ol"></i> League mode</a>
      <a class="sw-tab<?= $mode === 'Groups' ? ' active' : '' ?>" href="<?= $self(['mode' => 'Groups']) ?>"><i class="bi bi-grid-3x3-gap"></i> Group tournament mode</a>
    </div>
    <?php $groups = comp_standings((int)$cur['id'], $mode); ?>
    <?php if (!$groups): ?><p class="text-muted-ft">No teams or results in this competition yet.</p><?php endif; ?>
    <div class="row g-3">
    <?php foreach ($groups as $gname => $rows): ?>
      <div class="<?= $mode === 'Groups' ? 'col-12 col-xl-6' : 'col-12' ?>"><div class="ft-card p-0 overflow-hidden">
        <?php if ($mode === 'Groups'): ?><div class="px-3 py-2 fw-bold section-title"><i class="bi bi-grid-3x3-gap"></i> <?= e($gname) ?></div><?php endif; ?>
        <div class="table-responsive"><table class="table comp-table align-middle mb-0">
          <thead><tr><th>#</th><th>Team</th><th class="text-center">P</th><th class="text-center">W</th><th class="text-center">D</th><th class="text-center">L</th><th class="text-center">GF</th><th class="text-center">GA</th><th class="text-center">GD</th><th class="text-center">Pts</th><th class="d-none d-md-table-cell">Form</th></tr></thead>
          <tbody>
          <?php foreach ($rows as $i => $r): ?>
            <?php $qn = $mode === 'Groups' ? (int)$cur['qualify_per_group'] : (int)$cur['ko_teams']; ?>
            <tr class="<?= $i === 0 && !$r['susp'] ? 'comp-first' : '' ?><?= !$r['susp'] && $qn && $i < $qn && !empty($cur['knockout']) ? ' comp-qual' : '' ?><?= $r['susp'] ? ' comp-susp' : '' ?>">
              <td><?= $i + 1 ?></td><td class="fw-semibold"><?= comp_team_link($r['team']) ?><?= $r['susp'] ? ' <span class="badge text-bg-danger">Suspended</span>' : '' ?></td>
              <td class="text-center"><?= $r['p'] ?></td><td class="text-center"><?= $r['w'] ?></td><td class="text-center"><?= $r['d'] ?></td><td class="text-center"><?= $r['l'] ?></td>
              <td class="text-center"><?= $r['gf'] ?></td><td class="text-center"><?= $r['ga'] ?></td><td class="text-center"><?= $r['gd'] > 0 ? '+' : '' ?><?= $r['gd'] ?></td>
              <td class="text-center fw-bold"><?= $r['pts'] ?><?php if ($r['adj']): ?> <sup class="<?= $r['adj'] < 0 ? 'adj-neg' : 'adj-pos' ?>" title="<?= e($r['why']) ?>"><?= $r['adj'] > 0 ? '+' : '' ?><?= $r['adj'] ?></sup><?php endif; ?></td>
              <td class="d-none d-md-table-cell"><?php foreach ($r['form'] as $f): ?><span class="sr-res result-<?= ['W' => 'win', 'D' => 'draw', 'L' => 'loss'][$f] ?> form-dot"><?= $f ?></span><?php endforeach; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody></table></div>
      </div></div>
    <?php endforeach; ?>
    </div>
    <?php $notes = []; foreach ($groups as $g) { foreach ($g as $r) { if ($r['adj']) { $notes[] = $r['team'] . ': ' . ($r['adj'] > 0 ? '+' : '') . $r['adj'] . ' pts' . ($r['why'] !== '' ? ' (' . $r['why'] . ')' : ''); } } }
    if ($notes): ?><p class="small mt-2"><b>Point adjustments:</b> <?= e(implode('; ', $notes)) ?></p><?php endif; ?>
    <?php if ($mode === 'Groups'): ?><p class="text-muted-ft small mt-2"><span class="comp-key"></span> Top <?= (int)$cur['qualify_per_group'] ?> of each group qualify. Win 3 pts, draw 1.</p><?php else: ?><p class="text-muted-ft small mt-2">Win 3 pts, draw 1. Sorted by points, goal difference, goals scored.</p><?php endif; ?>

    <?php $res = comp_results((int)$cur['id']); if ($res): ?>
      <h2 class="h5 section-title mt-4 mb-3">Fixtures &amp; results</h2>
      <div class="row-list"><?php foreach (array_reverse($res) as $r): ?>
        <div class="row-item">
          <span class="ri-date"><b><?= $r['date'] ? e(fmt_date($r['date'], 'd')) : '-' ?></b><small><?= $r['date'] ? e(fmt_date($r['date'], 'M Y')) : '' ?></small></span>
          <span class="ri-main"><b><?= comp_team_link($r['home']) ?> <?= $r['hs'] === null ? 'vs' : $r['hs'] . ' - ' . $r['as'] ?> <?= comp_team_link($r['away']) ?></b><small><?= e((string)($r['stage'] ?? '')) ?></small></span>
          <span class="ri-pill <?= $r['hs'] === null ? 'k-match' : '' ?>"><?= $r['hs'] === null ? 'Upcoming' : 'FT' ?></span>
        </div><?php endforeach; ?></div>
    <?php endif; ?>

  <?php elseif ($tab === 'auction'): ?>
    <?php $at = auc_teams($auc); $ap = auc_pool((int)$auc['id']); $ac = null; foreach ($ap as $r) { if ((int)$r['id'] === (int)$auc['current_pool_id']) { $ac = $r; } } ?>
    <h2 class="h5 section-title mb-3"><i class="bi bi-hammer"></i> Player auction <span class="badge text-bg-<?= $auc['status'] === 'live' ? 'success' : 'secondary' ?> align-middle text-uppercase"><?= e($auc['status']) ?></span></h2>
    <p class="small text-muted-ft">Each team has <b><?= (int)$auc['budget'] ?></b> to buy <b><?= (int)$auc['squad_size'] ?></b> players.</p>
    <?php if ($ac): ?><div class="ft-card p-3 mb-3 d-flex flex-wrap align-items-center gap-3" style="border-color:var(--accent)">
      <img src="<?= e(upload_url($ac['photo'])) ?>" alt="" style="width:64px;height:64px;border-radius:14px;object-fit:cover">
      <div><div class="small text-muted-ft">Up for bidding now</div><div class="h5 mb-0"><?= e($ac['full_name']) ?></div><div class="small"><?= e(position_name($ac['category'])) ?> &middot; base <?= (int)$ac['base_price'] ?></div></div>
      <div class="ms-auto text-end"><div class="display-6 fw-bold"><?= $auc['current_team'] !== null ? (int)$auc['current_bid'] : '-' ?></div><div class="small"><?= $auc['current_team'] !== null ? e($auc['current_team']) : 'No bids yet' ?></div></div></div>
      <script>setTimeout(function(){location.reload()},8000)</script><?php endif; ?>
    <div class="row g-3 mb-4">
    <?php foreach ($at as $t): ?><div class="col-md-6"><div class="ft-card p-3 h-100">
      <div class="d-flex justify-content-between"><b><?= e($t['name']) ?></b><span class="small text-muted-ft"><?= (int)$t['count'] ?>/<?= (int)$auc['squad_size'] ?> players &middot; <b><?= (int)$t['left'] ?></b> left</span></div>
      <?php foreach ($t['players'] as $r): ?><div class="d-flex justify-content-between small py-1 border-top" style="border-color:var(--card-border)!important"><span><?= e($r['full_name']) ?> <span class="text-muted-ft"><?= e($r['category']) ?></span></span><b><?= (int)$r['sold_price'] ?></b></div><?php endforeach; ?>
    </div></div><?php endforeach; ?>
    </div>
    <h3 class="h6 section-title">Available players by position</h3>
    <?php $by = []; foreach ($ap as $r) { if ($r['status'] !== 'sold') { $by[$r['category'] ?: 'Other'][] = $r; } } foreach ($by as $cat => $rows): ?>
      <div class="mb-2"><span class="fw-semibold"><?= e(position_name($cat)) ?></span>: <?php foreach ($rows as $r): ?><span class="badge text-bg-secondary me-1"><?= e($r['full_name']) ?> &middot; <?= (int)$r['base_price'] ?><?= $r['status'] === 'unsold' ? ' (unsold)' : '' ?></span><?php endforeach; ?></div>
    <?php endforeach; if (!$by): ?><div class="text-muted-ft small">All players have been sold.</div><?php endif; ?>
  <?php elseif ($tab === 'bracket'): ?>
    <h2 class="h5 section-title mb-3"><i class="bi bi-diagram-3"></i> Knockout bracket</h2>
    <?= comp_bracket_html((int)$cur['id']) ?>
  <?php else: ?>
    <?php $ps = comp_player_stats((int)$cur['id']); ?>
    <?php if (!$ps): ?><p class="text-muted-ft">No player statistics are recorded for this competition yet. Tag matches with this competition when adding them.</p><?php else:
      $top = function (string $key, string $label, string $icon) use ($ps) {
          $rows = array_filter($ps, fn($r) => (int)$r[$key] > 0); usort($rows, fn($a, $b) => (int)$b[$key] <=> (int)$a[$key]);
          $h = '<div class="col-12 col-md-6 col-xl-4"><div class="ft-card p-3 h-100"><div class="fw-bold mb-2"><i class="bi ' . $icon . '"></i> ' . e($label) . '</div>';
          if (!$rows) { return $h . '<span class="text-muted-ft small">No data yet.</span></div></div>'; }
          foreach (array_slice($rows, 0, 5) as $i => $r) { $h .= '<div class="d-flex justify-content-between py-1 border-top"><span>' . ($i + 1) . '. <a href="' . e(player_url_by_id((int)$r['id'])) . '">' . e($r['full_name']) . '</a></span><b>' . (int)$r[$key] . '</b></div>'; }
          return $h . '</div></div>';
      }; ?>
      <h2 class="h5 section-title mb-3"><i class="bi bi-bullseye"></i> Offensive</h2>
      <div class="row g-3 mb-4"><?= $top('goals', 'Top scorers', 'bi-bullseye') ?><?= $top('assists', 'Most assists', 'bi-arrow-left-right') ?><?= $top('contrib', 'Goal contributions', 'bi-lightning') ?></div>
      <h2 class="h5 section-title mb-3"><i class="bi bi-shield-check"></i> Defensive</h2>
      <div class="row g-3 mb-4"><?= $top('tackles', 'Tackles', 'bi-shield-check') ?><?= $top('inter', 'Interceptions', 'bi-hand-index') ?><?= $top('clears', 'Clearances', 'bi-slash-circle') ?><?= $top('blocks', 'Blocks', 'bi-bricks') ?><?= $top('recov', 'Recoveries', 'bi-arrow-repeat') ?><?= $top('def_total', 'Total defensive actions', 'bi-shield-fill') ?></div>
      <div class="ft-card p-0 overflow-hidden"><div class="table-responsive"><table class="table comp-table align-middle mb-0">
        <thead><tr><th>Player</th><th class="text-center">Apps</th><th class="text-center">Goals</th><th class="text-center">Ast</th><th class="text-center">G+A</th><th class="text-center">Tkl</th><th class="text-center">Int</th><th class="text-center">Clr</th><th class="text-center">Blk</th><th class="text-center">Rec</th><th class="text-center">YC</th><th class="text-center">RC</th></tr></thead><tbody>
        <?php foreach ($ps as $r): ?><tr><td class="fw-semibold"><a href="<?= e(player_url_by_id((int)$r['id'])) ?>"><?= e($r['full_name']) ?></a></td>
          <?php foreach (['apps', 'goals', 'assists', 'contrib', 'tackles', 'inter', 'clears', 'blocks', 'recov', 'yc', 'rc'] as $k): ?><td class="text-center"><?= (int)$r[$k] ?></td><?php endforeach; ?></tr><?php endforeach; ?>
        </tbody></table></div></div>
    <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
