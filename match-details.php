<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$m = get_match((int)($_GET['id'] ?? 0));
if (!$m) { http_response_code(404); }
$pageTitle = $m ? $m['team_name'] . ' vs ' . $m['opponent_name'] : 'Match not found'; $activePage = 'match-details.php';
require __DIR__ . '/includes/header.php';
if (!$m) { echo '<div class="container py-5 text-center"><h1>Match not found</h1><a class="btn btn-accent" href="' . e(purl('matches.php')) . '">Back to matches</a></div>'; require __DIR__ . '/includes/footer.php'; exit; }
$goals = get_match_goals((int)$m['id']); $assists = get_match_assists((int)$m['id']); $squad = get_squad((int)$m['id']); $perfs = get_match_performances((int)$m['id']);
[$prev, $next] = match_neighbours($m);
$gal = db()->prepare('SELECT * FROM gallery WHERE match_id = ? ORDER BY id'); $gal->execute([$m['id']]); $gal = $gal->fetchAll();
$photos = array_filter([['image' => $m['photo'], 'caption' => 'Match photo'], ['image' => $m['team_photo'], 'caption' => 'Team photo']], fn($x) => !empty($x['image']));
$photos = array_merge($photos, $gal);
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a class="btn btn-outline-theme btn-sm" href="<?= e(purl('matches.php')) ?>"><i class="bi bi-arrow-left"></i> All matches</a>
    <div class="d-flex gap-2">
      <?php if ($prev): ?><a class="btn btn-outline-theme btn-sm" href="?id=<?= (int)$prev ?>"><i class="bi bi-chevron-left"></i> Previous</a><?php endif; ?>
      <?php if ($next): ?><a class="btn btn-outline-theme btn-sm" href="?id=<?= (int)$next ?>">Next <i class="bi bi-chevron-right"></i></a><?php endif; ?>
      <?php if (is_logged_in()): ?><a class="btn btn-accent btn-sm" href="<?= e(url('admin/match-edit.php?id=' . (int)$m['id'])) ?>"><i class="bi bi-pencil"></i> Edit</a><?php endif; ?>
    </div>
  </div>
  <?= render_scoreboard($m) ?>
  <div class="row g-4 mt-1">
    <div class="col-lg-6">
      <div class="ft-card p-4 mb-4"><h2 class="section-title h6">Match information</h2>
        <?= facts_list(['Date' => fmt_date($m['match_date'], 'l, d M Y'), 'Kickoff' => fmt_time($m['kickoff_time']), 'Season' => $m['season_name'], 'Competition' => $m['competition_name'] ?? '-',
            'Match type' => $m['match_type'], 'Venue' => ($m['venue_name'] ?? '-') . ($m['venue_city'] ? ', ' . $m['venue_city'] : ''), 'Home / Away' => $m['home_away'],
            'Home team' => $m['home_away'] === 'Away' ? $m['opponent_name'] : $m['team_name'], 'Away team' => $m['home_away'] === 'Away' ? $m['team_name'] : $m['opponent_name'], 'Formation' => $m['formation'] ?: '-']) ?></div>
      <?php if ($m['goals'] !== null): ?><div class="ft-card p-4"><h2 class="section-title h6"><?= e(player_short_name(get_player(ctx_pid()))) ?>'s performance</h2>
        <?= facts_list(['Starting position' => position_name($m['position']) . ($m['position'] ? ' (' . $m['position'] . ')' : ''), 'Started' => $m['started'] ? 'Yes' : 'From the bench', 'Minutes played' => $m['minutes_played'], 'Goals' => $m['goals'],
            'Assists' => $m['assists'], 'Yellow cards' => $m['yellow_cards'], 'Red cards' => $m['red_cards'], 'Own goals' => $m['own_goals'], 'Penalty goals' => $m['penalty_goals'], 'Fouls' => $m['fouls'], 'Tackles' => $m['tackles'], 'Interceptions' => $m['interceptions'], 'Clearances' => $m['clearances'], 'Blocks' => $m['blocks'], 'Recoveries' => $m['recoveries'],
            'Rating' => $m['rating'] !== null ? fmt_num($m['rating']) : '-', 'Captain' => $m['is_captain'] ? 'Yes' : 'No', 'Man of the Match' => $m['is_motm'] ? 'Yes' : 'No']) ?></div><?php endif; ?>
    </div>
    <div class="col-lg-6">
      <h2 class="section-title h5 mb-3"><i class="bi bi-people"></i> Squad view</h2>
      <?= render_pitch($m, $squad) ?>
    </div>
  </div>
  <div class="row g-4 mt-1">
    <div class="col-md-6"><div class="ft-card p-4 h-100"><h2 class="section-title h6">Goal log</h2>
      <?php if (!$goals): ?><p class="text-muted-ft mb-0">No goals logged.</p><?php endif; ?>
      <?php foreach ($goals as $g): ?><div class="ev"><span class="ev-min"><?= $g['minute'] !== null ? (int)$g['minute'] . "'" : '-' ?></span><i class="bi bi-bullseye text-success"></i> <b><?= e($g['player_name']) ?></b> &middot; <?= e($g['goal_type']) ?><?= $g['assisted_by'] ? ' &middot; assist ' . e($g['assisted_by']) : '' ?><?= $g['description'] ? '<div class="small text-muted-ft ms-5">' . e($g['description']) . '</div>' : '' ?></div><?php endforeach; ?></div></div>
    <div class="col-md-6"><div class="ft-card p-4 h-100"><h2 class="section-title h6">Assist log</h2>
      <?php if (!$assists): ?><p class="text-muted-ft mb-0">No assists logged.</p><?php endif; ?>
      <?php foreach ($assists as $a): ?><div class="ev"><span class="ev-min"><?= $a['minute'] !== null ? (int)$a['minute'] . "'" : '-' ?></span><i class="bi bi-arrow-left-right text-info"></i> <b><?= e($a['player_name']) ?></b> &middot; <?= e($a['assist_type']) ?><?= $a['goal_scorer'] ? ' &middot; to ' . e($a['goal_scorer']) : '' ?><?= $a['description'] ? '<div class="small text-muted-ft ms-5">' . e($a['description']) . '</div>' : '' ?></div><?php endforeach; ?></div></div>
  </div>
  <?php if ($perfs): ?><div class="ft-card p-4 mt-4"><h2 class="section-title h6">Player performances</h2><div class="table-responsive"><table class="table ft-table perf-table align-middle mb-0"><thead><tr><th>Player</th><th>Pos</th><th class="text-end">Min</th><th class="text-end">G</th><th class="text-end">A</th><th class="text-end">YC</th><th class="text-end">RC</th><th class="text-end" title="Tackles + interceptions + clearances + blocks + recoveries">Def</th><th class="text-end">Rating</th></tr></thead><tbody>
  <?php foreach ($perfs as $pf): ?><tr><td><a href="<?= e(url('profile.php?player=' . (int)$pf['player_id'])) ?>"><?= e($pf['full_name']) ?></a><?= $pf['is_motm'] ? ' <span class="badge bg-warning text-dark">MOTM</span>' : '' ?><?= $pf['is_captain'] ? ' <span class="badge bg-secondary">C</span>' : '' ?></td><td><?= e($pf['position'] ?? '-') ?></td><td class="text-end"><?= (int)$pf['minutes_played'] ?></td><td class="text-end"><?= (int)$pf['goals'] ?></td><td class="text-end"><?= (int)$pf['assists'] ?></td><td class="text-end"><?= (int)$pf['yellow_cards'] ?></td><td class="text-end"><?= (int)$pf['red_cards'] ?></td><td class="text-end"><?= (int)$pf['tackles'] + (int)$pf['interceptions'] + (int)$pf['clearances'] + (int)$pf['blocks'] + (int)$pf['recoveries'] ?></td><td class="text-end"><?= e($pf['rating'] !== null ? fmt_num($pf['rating']) : '-') ?></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
  <?php if ($m['notes'] || $m['remarks']): ?><div class="ft-card p-4 mt-4"><h2 class="section-title h6">Match notes</h2><p class="mb-0"><?= nl2br(e($m['notes'] ?? '')) ?></p><?= $m['remarks'] ? '<p class="text-muted-ft mt-2 mb-0">' . e($m['remarks']) . '</p>' : '' ?></div><?php endif; ?>
  <?php if ($photos): ?><h2 class="section-title h5 mt-4 mb-3">Photos</h2><div class="gallery-grid"><?php foreach ($photos as $ph): ?><figure><img src="<?= e(upload_url($ph['image'])) ?>" alt="" loading="lazy"><?= !empty($ph['caption']) ? '<figcaption>' . e($ph['caption']) . '</figcaption>' : '' ?></figure><?php endforeach; ?></div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php';
