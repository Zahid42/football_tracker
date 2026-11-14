<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle  = 'Teams';
$activePage = 'teams.php';
require __DIR__ . '/includes/header.php';

$q = trim((string)($_GET['q'] ?? ''));
$qDigits = preg_replace('/\D/', '', ltrim($q, '#'));
$sql = "SELECT * FROM teams WHERE status = 'active'";
$args = [];
if ($q !== '') {
    $sql .= ' AND (name LIKE ? OR short_name LIKE ?' . ($qDigits !== '' ? ' OR public_code LIKE ?' : '') . ')';
    $args = ['%' . $q . '%', '%' . $q . '%'];
    if ($qDigits !== '') { $args[] = '%' . $qDigits . '%'; }
}
$st = db()->prepare($sql . ' ORDER BY name');
$st->execute($args);
$teams = $st->fetchAll();

$count = fn(string $t) => db()->query("SELECT team_id, COUNT(*) FROM $t GROUP BY team_id")->fetchAll(PDO::FETCH_KEY_PAIR);
$sq = $count('team_squad'); $ach = $count('team_achievements');
$titles = db()->query("SELECT team_id, COUNT(*) FROM team_achievements WHERE achievement_type = 'Trophy' GROUP BY team_id")->fetchAll(PDO::FETCH_KEY_PAIR);
$pls = db()->query('SELECT team_id, COUNT(DISTINCT player_id) FROM player_teams GROUP BY team_id')->fetchAll(PDO::FETCH_KEY_PAIR);
$total = (int)db()->query("SELECT COUNT(*) FROM teams WHERE status = 'active'")->fetchColumn();
echo switch_bar('teams');
echo public_page_title('Teams', 'Find a team by name or by its 6-digit ID');
?>
<div class="container py-4">
  <form method="get" class="team-search" role="search">
    <i class="bi bi-search"></i>
    <input type="search" id="teamQ" name="q" value="<?= e($q) ?>" placeholder="Search by team name or ID (e.g. 201988)" autocomplete="off" autofocus>
    <button class="btn btn-accent" type="submit">Search</button>
    <?php if ($q !== ''): ?><a class="btn btn-outline-theme" href="<?= e(url('teams.php')) ?>">Clear</a><?php endif; ?>
  </form>
  <div class="d-flex justify-content-between align-items-center mb-2">
    <div class="text-muted-ft small" id="teamCount"><?= count($teams) ?> of <?= $total ?> team<?= $total === 1 ? '' : 's' ?></div>
  </div>

  <div class="row-list" id="teamList">
    <?php foreach ($teams as $t):
        $tid = (int)$t['id']; $kit = team_current_kit($tid); ?>
      <a class="row-item team-listrow" href="<?= e(team_url_by_id($tid)) ?>" data-search="<?= e(strtolower($t['name'] . ' ' . $t['short_name'] . ' ' . $t['public_code'])) ?>">
        <span class="tl-logo tl-logo-md"><?= !empty($t['logo']) ? '<img src="' . e(upload_url($t['logo'])) . '" alt="">' : '<i class="bi bi-shield-shaded"></i>' ?></span>
        <span class="ri-main">
          <b><?= e($t['name']) ?><?= $t['short_name'] ? ' <span class="tr-short">' . e($t['short_name']) . '</span>' : '' ?></b>
          <small><?= $t['founded_year'] ? 'Founded ' . (int)$t['founded_year'] . ' &middot; ' : '' ?><span class="id-chip">ID <?= e((string)$t['public_code']) ?></span></small>
        </span>
        <?php if ($kit): ?><span class="jersey-mini" title="Current jersey"><?= kit_visual($kit) ?></span><?php endif; ?>
        <span class="tr-stats">
          <span><b><?= (int)($sq[$tid] ?? 0) ?></b><small>Squad</small></span>
          <span><b><?= (int)($pls[$tid] ?? 0) ?></b><small>Players</small></span>
          <span><b><?= (int)($titles[$tid] ?? 0) ?></b><small>Titles</small></span>
          <span><b><?= (int)($ach[$tid] ?? 0) ?></b><small>Honours</small></span>
        </span>
        <span class="ri-go"><i class="bi bi-chevron-right"></i></span>
      </a>
    <?php endforeach; ?>
  </div>
  <p class="text-muted-ft text-center py-4" id="teamEmpty"<?= $teams ? ' hidden' : '' ?>>No team matches &ldquo;<?= e($q) ?>&rdquo;.</p>
</div>
<script>
(function(){var i=document.getElementById('teamQ'),rows=[].slice.call(document.querySelectorAll('.team-listrow')),c=document.getElementById('teamCount'),e=document.getElementById('teamEmpty'),total=<?= $total ?>;
function f(){var v=i.value.trim().toLowerCase().replace(/^#/,'');var n=0;rows.forEach(function(r){var ok=!v||r.getAttribute('data-search').indexOf(v)>-1;r.hidden=!ok;if(ok){n++;}});
  c.textContent=n+' of '+total+' team'+(total===1?'':'s');e.hidden=n>0;e.textContent='No team matches "'+i.value.trim()+'".';}
i.addEventListener('input',f);f();})();
</script>
<?php require __DIR__ . '/includes/footer.php';
