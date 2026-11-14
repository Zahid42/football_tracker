<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$st = db()->prepare('SELECT * FROM teams WHERE id = ?'); $st->execute([(int)($_GET['id'] ?? 0)]); $t = $st->fetch();
$pageTitle = $t ? $t['name'] : 'Team not found'; $activePage = 'team.php';
require __DIR__ . '/includes/header.php';
if (!$t) { http_response_code(404); echo '<div class="container py-5 text-center"><h1>Team not found</h1><a class="btn btn-accent" href="' . e(url('index.php')) . '">Home</a></div>'; require __DIR__ . '/includes/footer.php'; exit; }
$q = db()->prepare("SELECT DISTINCT p.*, ? AS team_name FROM players p JOIN player_teams pt ON pt.player_id = p.id WHERE pt.team_id = ? AND p.approval_status='approved' AND p.is_active=1 ORDER BY p.full_name");
$q->execute([$t['name'], $t['id']]); $roster = $q->fetchAll();
$m = db()->prepare('SELECT m.id, m.match_date, m.team_score, m.opponent_score, m.result, o.name opp FROM matches m JOIN opponents o ON o.id = m.opponent_id WHERE m.team_id = ? ORDER BY m.match_date DESC LIMIT 10');
$m->execute([$t['id']]); $ms = $m->fetchAll();
?>
<div class="container py-4">
  <a class="btn btn-outline-theme btn-sm mb-3" href="<?= e(url('index.php')) ?>"><i class="bi bi-arrow-left"></i> Home</a>
  <div class="ft-card p-4 mb-4 d-flex gap-3 align-items-center">
    <div class="tl-logo"><?= !empty($t['logo']) ? '<img src="' . e(upload_url($t['logo'])) . '" alt="">' : '<i class="bi bi-shield-shaded"></i>' ?></div>
    <div><h1 class="h3 mb-0"><?= e($t['name']) ?></h1><div class="text-muted-ft"><?= $t['founded_year'] ? 'Founded ' . (int)$t['founded_year'] : '' ?></div>
    <?php if ($t['description']): ?><p class="mb-0 mt-2"><?= e($t['description']) ?></p><?php endif; ?></div>
  </div>
  <div class="section-head"><h2>Players</h2></div>
  <div class="row g-3 mb-4"><?php foreach ($roster as $pl): ?><div class="col-6 col-md-4 col-xl-3"><?= player_card($pl, with_player((int)$pl['id'], fn() => get_overall_stats())) ?></div><?php endforeach; ?>
  <?php if (!$roster): ?><p class="text-muted-ft">No players listed for this team yet.</p><?php endif; ?></div>
  <div class="section-head"><h2>Recent matches</h2></div>
  <div class="ft-card p-0"><div class="table-responsive"><table class="table ft-table mb-0"><tbody>
    <?php foreach ($ms as $r): ?><tr><td><?= e(fmt_date($r['match_date'])) ?></td><td>vs <?= e($r['opp']) ?></td><td class="fw-bold"><?= (int)$r['team_score'] ?> - <?= (int)$r['opponent_score'] ?></td><td><?= result_badge($r['result']) ?></td><td class="text-end"><a href="<?= e(url('match-details.php?id=' . (int)$r['id'])) ?>">Open</a></td></tr><?php endforeach; ?>
    <?php if (!$ms): ?><tr><td class="text-muted-ft text-center py-3">No matches yet.</td></tr><?php endif; ?>
  </tbody></table></div></div>
</div>
<?php require __DIR__ . '/includes/footer.php';
