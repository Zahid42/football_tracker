<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_login();
$sections = ['about' => 'About the Player', 'teams' => 'Teams Played For', 'achievements' => 'Achievements / trophies', 'matches' => 'Latest matches (rows)', 'fixtures' => 'Fixtures (from Calendar)', 'squads' => 'Match squads (pitch view)', 'numbers' => 'Career in numbers (cards)', 'gallery' => 'Gallery strip', 'players' => 'Our Players grid'];
$chips = ['foot' => 'Strong foot', 'height' => 'Height', 'age' => 'Age', 'matches' => 'Matches', 'goals' => 'Goals', 'assists' => 'Assists'];
if (is_post()) {
    csrf_require();
    $fid = (int)post('featured');
    $pl = $fid ? get_player($fid) : null;
    if ($pl && $pl['approval_status'] === 'approved') {
        db()->exec('UPDATE players SET is_featured = 0');
        db()->prepare('UPDATE players SET is_featured = 1, is_active = 1 WHERE id = ?')->execute([$fid]);
    }
    $pick = fn(array $all, string $k) => implode(',', array_values(array_intersect(array_keys($all), (array)($_POST[$k] ?? []))));
    $st = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $st->execute(['home_sections', $pick($sections, 'sections')]);
    $st->execute(['home_chips', $pick($chips, 'chips')]);
    $st->execute(['home_show_players', isset($_POST['sections']) && in_array('players', (array)$_POST['sections'], true) ? '1' : '0']);
    flash_set('success', 'Homepage updated.');
    redirect('admin/homepage.php');
}
$pageTitle = 'Homepage';
require ROOT_PATH . '/includes/admin_header.php';
$cur = featured_player_id();
?>
<h1 class="page-title h3 mb-1"><i class="bi bi-house-gear"></i> Homepage control</h1>
<p class="text-muted-ft">Choose which player is shown on the homepage and exactly which parts of his details are visible.</p>
<form method="post" class="row g-3">
  <?= csrf_field() ?>
  <div class="col-lg-4"><div class="ft-card p-4 h-100"><h2 class="section-title h6">Player on homepage</h2>
    <?php foreach (players_list() as $p): ?>
      <label class="d-flex align-items-center gap-2 mb-2 p-2 border rounded" style="cursor:pointer">
        <input class="form-check-input mt-0" type="radio" name="featured" value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === $cur ? 'checked' : '' ?>>
        <img class="ap-photo" style="width:44px;height:44px" src="<?= e(upload_url($p['photo'])) ?>" alt=""><span><b><?= e($p['full_name']) ?></b><br><small class="text-muted-ft"><?= e(position_name($p['preferred_position'])) ?></small></span></label>
    <?php endforeach; ?></div></div>
  <div class="col-lg-4"><div class="ft-card p-4 h-100"><h2 class="section-title h6">Sections to show</h2>
    <?php foreach ($sections as $k => $l): ?><div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" id="s_<?= $k ?>" name="sections[]" value="<?= $k ?>" <?= home_on('home_sections', $k) ? 'checked' : '' ?>><label class="form-check-label" for="s_<?= $k ?>"><?= e($l) ?></label></div><?php endforeach; ?></div></div>
  <div class="col-lg-4"><div class="ft-card p-4 h-100"><h2 class="section-title h6">Details in the top banner</h2>
    <?php foreach ($chips as $k => $l): ?><div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" id="c_<?= $k ?>" name="chips[]" value="<?= $k ?>" <?= home_on('home_chips', $k) ? 'checked' : '' ?>><label class="form-check-label" for="c_<?= $k ?>"><?= e($l) ?></label></div><?php endforeach; ?></div></div>
  <div class="col-12 d-flex gap-2"><button class="btn btn-accent btn-lg"><i class="bi bi-check-lg"></i> Save homepage</button><a class="btn btn-outline-theme btn-lg" href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">Preview</a></div>
</form>
<?php require ROOT_PATH . '/includes/admin_footer.php';
