<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '' && preg_match('/^#?\d{6}$/', $q)) {                 // exact 6-digit ID: go straight there
    $code = ltrim($q, '#');
    foreach (['players' => 'player_url_by_id', 'teams' => 'team_url_by_id'] as $tbl => $fn) {
        $st = db()->prepare("SELECT id FROM $tbl WHERE public_code = ?" . ($tbl === 'players' ? " AND approval_status = 'approved' AND is_active = 1" : " AND status = 'active'")); $st->execute([$code]);
        if ($id = $st->fetchColumn()) { header('Location: ' . $fn((int)$id)); exit; }
    }
}
$pageTitle = 'Search';
$activePage = 'search.php';
require __DIR__ . '/includes/header.php';
$r = site_search($q, 30);
$total = count($r['players']) + count($r['teams']) + count($r['competitions']);
echo public_page_title('Search', $q === '' ? 'Find players, teams and competitions by name or 6-digit ID' : $total . ' result' . ($total === 1 ? '' : 's') . ' for "' . $q . '"');
?>
<div class="container py-4">
  <form method="get" class="team-search" role="search">
    <i class="bi bi-search"></i><input type="search" name="q" value="<?= e($q) ?>" placeholder="Name or 6-digit ID" maxlength="60" autofocus aria-label="Search">
    <button class="btn btn-accent btn-sm" type="submit">Search</button>
  </form>
  <?php if ($q !== '' && !$total): ?><p class="text-muted-ft">Nothing found. Try part of a name, or the full 6-digit ID.</p><?php endif; ?>
  <?php if ($r['players']): ?><h2 class="h5 section-title mt-3 mb-3"><i class="bi bi-person"></i> Players</h2><div class="row-list mb-4">
    <?php foreach ($r['players'] as $p): ?><a class="row-item squad-member" href="<?= e(player_url_by_id((int)$p['id'])) ?>">
      <img class="sq-photo" src="<?= e(upload_url($p['photo'])) ?>" alt=""><span class="ri-main"><b><?= e($p['full_name']) ?></b><small><?= e(position_name($p['preferred_position'])) ?><?= $p['nickname'] ? ' &middot; "' . e($p['nickname']) . '"' : '' ?></small></span>
      <span class="ri-pill k-event">#<?= e((string)$p['public_code']) ?></span></a><?php endforeach; ?></div><?php endif; ?>
  <?php if ($r['teams']): ?><h2 class="h5 section-title mb-3"><i class="bi bi-shield"></i> Teams</h2><div class="row-list mb-4">
    <?php foreach ($r['teams'] as $t): ?><a class="row-item squad-member" href="<?= e(team_url_by_id((int)$t['id'])) ?>">
      <img class="sq-photo" src="<?= e(upload_url($t['logo'])) ?>" alt=""><span class="ri-main"><b><?= e($t['name']) ?></b><small><?= e((string)$t['short_name']) ?></small></span>
      <span class="ri-pill k-event">#<?= e((string)$t['public_code']) ?></span></a><?php endforeach; ?></div><?php endif; ?>
  <?php if ($r['competitions']): ?><h2 class="h5 section-title mb-3"><i class="bi bi-trophy"></i> Competitions</h2><div class="row-list mb-4">
    <?php foreach ($r['competitions'] as $c): ?><a class="row-item" href="<?= e(url('competitions.php?c=' . (int)$c['id'])) ?>"><span class="ri-main"><b><?= e($c['name']) ?></b><small><?= e($c['type']) ?></small></span><span class="ri-pill k-fixture">Table</span></a><?php endforeach; ?></div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php';
