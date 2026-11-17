<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
$admin = require_login();
$keys = ['site_title' => ['Site Title', 100], 'site_tagline' => ['Tagline', 160], 'contact_email' => ['Public contact e-mail (optional)', 120], 'about_text' => ['About text (About page)', 3000]];
$errors = [];
if (is_post()) {
    csrf_require();
    $vals = [];
    foreach ($keys as $k => [$label, $max]) {
        $vals[$k] = post($k);
        if (mb_strlen($vals[$k]) > $max) { $errors[] = "$label is too long (max $max)."; }
    }
    $vals['registration_open'] = isset($_POST['registration_open']) ? '1' : '0';
    if ($vals['site_title'] === '') { $errors[] = 'Site title is required.'; }
    if ($vals['contact_email'] !== '' && !filter_var($vals['contact_email'], FILTER_VALIDATE_EMAIL)) { $errors[] = 'Contact e-mail is not valid.'; }
    if (!$errors) {
        $st = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($vals as $k => $v) { $st->execute([$k, $v]); }
        flash_set('success', 'Settings saved.');
        redirect('admin/settings.php');
    }
}
$pageTitle = 'Settings';
require ROOT_PATH . '/includes/admin_header.php';
?>
<h1 class="page-title h3 mb-3"><i class="bi bi-gear"></i> Settings</h1>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<div class="row g-3">
  <div class="col-lg-8">
    <form method="post" class="ft-card p-4">
      <?= csrf_field() ?>
      <?php foreach ($keys as $k => [$label, $max]): $val = is_post() ? post($k) : setting($k); ?>
        <div class="mb-3"><label class="form-label" for="<?= e($k) ?>"><?= e($label) ?></label>
        <?php if ($k === 'about_text'): ?><textarea class="form-control" rows="5" id="<?= e($k) ?>" name="<?= e($k) ?>" maxlength="<?= $max ?>"><?= e($val) ?></textarea>
        <?php else: ?><input class="form-control" id="<?= e($k) ?>" name="<?= e($k) ?>" value="<?= e($val) ?>" maxlength="<?= $max ?>"><?php endif; ?></div>
      <?php endforeach; ?>
      <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="registration_open" name="registration_open" value="1"<?= setting('registration_open', '1') === '1' ? ' checked' : '' ?>>
        <label class="form-check-label" for="registration_open">Allow new players to register on the public "Join" page (admin approval still required)</label></div>
      <button class="btn btn-accent"><i class="bi bi-check-lg"></i> Save Settings</button>
    </form>
  </div>
  <div class="col-lg-4">
    <div class="ft-card p-4 mb-3"><h2 class="section-title h6">Account</h2>
      <p class="text-muted-ft small">Signed in as <b><?= e($admin['username']) ?></b>.</p>
      <a class="btn btn-outline-theme" href="<?= e(url('admin/change-password.php')) ?>"><i class="bi bi-key"></i> Change password</a></div>
    <div class="ft-card p-4 mb-3"><h2 class="section-title h6">E-mail</h2>
      <p class="text-muted-ft small">Verification codes, player IDs and approval notices are sent from here.</p>
      <a class="btn btn-outline-theme" href="<?= e(url('admin/email.php')) ?>"><i class="bi bi-envelope-at"></i> E-mail settings</a></div>
    <div class="ft-card p-4"><h2 class="section-title h6">Data export</h2>
      <p class="text-muted-ft small">Download your records as CSV files.</p>
      <a class="btn btn-outline-theme" href="<?= e(url('admin/reports.php')) ?>"><i class="bi bi-filetype-csv"></i> Reports &amp; exports</a></div>
  </div>
</div>
<?php require ROOT_PATH . '/includes/admin_footer.php';
