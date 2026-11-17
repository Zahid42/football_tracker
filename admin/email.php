<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
$admin = require_login();
$errors = [];
if (is_post()) {
    csrf_require();
    $op = post('op');
    if ($op === 'save') {
        $v = ['smtp_host' => post('smtp_host'), 'smtp_port' => (string)(int)post('smtp_port'), 'smtp_secure' => in_array(post('smtp_secure'), ['tls', 'ssl', 'none'], true) ? post('smtp_secure') : 'tls',
              'smtp_user' => post('smtp_user'), 'smtp_from' => post('smtp_from'), 'smtp_name' => post('smtp_name')];
        if ($v['smtp_host'] !== '' && !filter_var($v['smtp_from'], FILTER_VALIDATE_EMAIL)) { $errors[] = 'Enter a valid "From" e-mail address.'; }
        if (!preg_match('/^[a-z0-9.\-]*$/i', $v['smtp_host'])) { $errors[] = 'The server name is not valid.'; }
        if (!$errors) {
            if (post('smtp_pass') !== '') { $v['smtp_pass'] = (string)($_POST['smtp_pass'] ?? ''); }
            $st = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            foreach ($v as $k => $val) { $st->execute([$k, $val]); }
            flash_set('success', 'E-mail settings saved.'); redirect('admin/email.php');
        }
    } elseif ($op === 'test') {
        $to = post('to');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { flash_set('danger', 'Enter a valid address to send the test to.'); }
        else { [$ok, $er] = ft_mail_send($to, 'Test e-mail', setting('site_title', 'Football Tracker'), ['This is a test message. Your e-mail settings work.']); flash_set($ok ? 'success' : 'danger', $ok ? 'Test e-mail sent to ' . $to . '.' : 'Could not send: ' . $er); }
        redirect('admin/email.php');
    }
}
$pageTitle = 'E-mail settings';
require ROOT_PATH . '/includes/admin_header.php';
$g = fn($k, $d = '') => is_post() ? post($k, $d) : setting($k, $d);
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="page-title h3 mb-0"><i class="bi bi-envelope-at"></i> E-mail settings</h1><a class="btn btn-outline-theme" href="<?= e(url('admin/settings.php')) ?>"><i class="bi bi-arrow-left"></i> Settings</a></div>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<p class="text-muted-ft">The site e-mails verification codes, player IDs and approval notices through your own mail account. For Gmail use host <code>smtp.gmail.com</code>, port <code>587</code>, TLS, your Gmail address, and an <b>App password</b> (Google account &rarr; Security &rarr; 2-Step Verification &rarr; App passwords).</p>
<div class="row g-3"><div class="col-lg-7">
<form method="post" class="ft-card p-4"><?= csrf_field() ?><input type="hidden" name="op" value="save">
  <div class="row g-3">
    <div class="col-md-8"><label class="form-label">SMTP server</label><input class="form-control" name="smtp_host" value="<?= e($g('smtp_host')) ?>" placeholder="smtp.gmail.com"></div>
    <div class="col-md-4"><label class="form-label">Port</label><input class="form-control" type="number" name="smtp_port" value="<?= e($g('smtp_port', '587')) ?>"></div>
    <div class="col-md-4"><label class="form-label">Security</label><select class="form-select" name="smtp_secure"><?php foreach (['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None'] as $k => $l): ?><option value="<?= $k ?>"<?= $g('smtp_secure', 'tls') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="col-md-8"><label class="form-label">Username</label><input class="form-control" name="smtp_user" value="<?= e($g('smtp_user')) ?>" autocomplete="off"></div>
    <div class="col-12"><label class="form-label">Password / App password</label><input class="form-control" type="password" name="smtp_pass" autocomplete="new-password" placeholder="<?= setting('smtp_pass') !== '' ? '(saved - leave blank to keep)' : '' ?>"></div>
    <div class="col-md-6"><label class="form-label">From e-mail</label><input class="form-control" name="smtp_from" value="<?= e($g('smtp_from')) ?>" placeholder="you@gmail.com"></div>
    <div class="col-md-6"><label class="form-label">From name</label><input class="form-control" name="smtp_name" value="<?= e($g('smtp_name')) ?>" placeholder="<?= e(setting('site_title', 'Football Tracker')) ?>"></div>
  </div>
  <button class="btn btn-accent mt-3"><i class="bi bi-check-lg"></i> Save</button>
</form></div>
<div class="col-lg-5">
<form method="post" class="ft-card p-4 mb-3"><?= csrf_field() ?><input type="hidden" name="op" value="test"><h2 class="h6 section-title">Send a test e-mail</h2>
  <div class="input-group"><input class="form-control" type="email" name="to" required placeholder="your address"><button class="btn btn-outline-theme">Send</button></div>
  <div class="form-text"><?= ft_mail_configured() ? 'Settings are saved.' : 'Save the settings first.' ?></div></form>
<div class="ft-card p-4"><h2 class="h6 section-title">Recent mail</h2>
<?php foreach (db()->query('SELECT * FROM mail_log ORDER BY id DESC LIMIT 12')->fetchAll() as $l): ?>
  <div class="small py-1 border-top" style="border-color:var(--card-border)!important"><span class="badge text-bg-<?= $l['ok'] ? 'success' : 'danger' ?>"><?= $l['ok'] ? 'sent' : 'failed' ?></span> <?= e($l['to_email']) ?> &middot; <?= e($l['subject']) ?><?= $l['error'] ? '<div class="text-danger">' . e($l['error']) . '</div>' : '' ?></div>
<?php endforeach; ?></div></div></div>
<?php require ROOT_PATH . '/includes/admin_footer.php';
