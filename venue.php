<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$st = db()->prepare('SELECT * FROM venues WHERE id = ?'); $st->execute([(int)($_GET['id'] ?? 0)]);
$v = $st->fetch();
if (!$v) { http_response_code(404); }
$pageTitle = $v ? $v['name'] : 'Venue not found'; $activePage = 'venues.php';
require __DIR__ . '/includes/header.php';
if (!$v) { echo '<div class="container py-5 text-center"><h1>Venue not found</h1><a class="btn btn-accent" href="' . e(url('venues.php')) . '">All venues</a></div>'; require __DIR__ . '/includes/footer.php'; exit; }
$ms = db()->prepare('SELECT m.id, m.match_date, m.team_score, m.opponent_score, t.name tn, o.name oname FROM matches m JOIN teams t ON t.id = m.team_id JOIN opponents o ON o.id = m.opponent_id WHERE m.venue_id = ? ORDER BY m.match_date DESC LIMIT 12');
$ms->execute([$v['id']]); $ms = $ms->fetchAll();
$cnt = (int)db()->query('SELECT COUNT(*) FROM matches WHERE venue_id = ' . (int)$v['id'])->fetchColumn();
?>
<div class="container py-4">
  <a class="btn btn-outline-theme btn-sm mb-3" href="<?= e(url('venues.php')) ?>"><i class="bi bi-arrow-left"></i> All venues</a>
  <div class="venue-hero mb-4">
    <div class="small text-uppercase fw-bold" style="color:var(--accent);letter-spacing:.08em"><i class="bi bi-geo-alt-fill"></i> Venue</div>
    <h1 class="display-6 fw-bold mb-1"><?= e($v['name']) ?></h1>
    <div class="text-muted-ft"><?= e(implode(', ', array_filter([$v['location'], $v['city'], $v['country']]))) ?></div>
    <div class="d-flex flex-wrap gap-2 mt-3">
      <?php if ($v['pitch_type']): ?><span class="badge text-bg-secondary"><i class="bi bi-grid-3x3"></i> <?= e($v['pitch_type']) ?></span><?php endif; ?>
      <span class="badge text-bg-secondary"><i class="bi bi-trophy"></i> <?= $cnt ?> match<?= $cnt === 1 ? '' : 'es' ?> played here</span>
    </div>
  </div>
  <div class="row g-4">
    <div class="col-lg-8">
      <?php if ($v['notes']): ?><div class="ft-card p-4 mb-3"><h2 class="section-title h6">About this venue</h2><p class="mb-0"><?= nl2br(e($v['notes'])) ?></p></div><?php endif; ?>
      <div class="ft-card p-4"><h2 class="section-title h6">Matches here</h2>
        <?php foreach ($ms as $m): ?><a class="d-flex justify-content-between small py-2 border-top text-decoration-none" style="border-color:var(--card-border)!important;color:var(--text)" href="<?= e(url('match-details.php?id=' . (int)$m['id'])) ?>"><span><?= e($m['tn']) ?> v <?= e($m['oname']) ?></span><span class="text-muted-ft"><?= e(fmt_date($m['match_date'], 'd M Y')) ?> &middot; <b><?= (int)$m['team_score'] ?>-<?= (int)$m['opponent_score'] ?></b></span></a><?php endforeach; ?>
        <?php if (!$ms): ?><div class="text-muted-ft small">No matches recorded here yet.</div><?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
