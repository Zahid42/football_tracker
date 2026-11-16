<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_login();
$stats = ['players' => ['Players', 'bi-person', 'players.php'], 'teams' => ['Teams', 'bi-shield', 'teams.php'], 'competitions' => ['Competitions', 'bi-trophy', 'competitions.php']];
if (is_post()) {
    csrf_require();
    $st = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    foreach ($stats as $k => $_) {
        $st->execute(['home_stat_' . $k . '_show', isset($_POST['show'][$k]) ? '1' : '0']);
        $st->execute(['home_stat_' . $k . '_link', isset($_POST['link'][$k]) ? '1' : '0']);
    }
    flash_set('success', 'Homepage updated.');
    redirect('admin/homepage.php');
}
$pageTitle = 'Homepage';
require ROOT_PATH . '/includes/admin_header.php';
?>
<h1 class="page-title h3 mb-1"><i class="bi bi-house-gear"></i> Homepage control</h1>
<p class="text-muted-ft">The homepage shows the search bar and three totals. Choose which totals are visible and whether visitors can click them to open the list.</p>
<form method="post" class="ft-card p-4" style="max-width:640px">
  <?= csrf_field() ?>
  <table class="table align-middle mb-3">
    <thead><tr><th>Total</th><th class="text-center">Show</th><th class="text-center">Clickable</th></tr></thead>
    <tbody>
    <?php foreach ($stats as $k => [$label, $icon, $href]): ?>
      <tr>
        <td><i class="bi <?= e($icon) ?>"></i> <b><?= e($label) ?></b></td>
        <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input" type="checkbox" role="switch" name="show[<?= e($k) ?>]" value="1" <?= setting('home_stat_' . $k . '_show', '1') === '1' ? 'checked' : '' ?> aria-label="Show <?= e($label) ?>"></div></td>
        <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input" type="checkbox" role="switch" name="link[<?= e($k) ?>]" value="1" <?= setting('home_stat_' . $k . '_link', '1') === '1' ? 'checked' : '' ?> aria-label="<?= e($label) ?> clickable"></div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <button class="btn btn-accent"><i class="bi bi-check-lg"></i> Save</button>
</form>
<?php require ROOT_PATH . '/includes/admin_footer.php';
