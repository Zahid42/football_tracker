<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
$admin = require_login(true);

$errors = [];

if (is_post()) {
    csrf_require();
    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    $stmt = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
    $stmt->execute([$admin['id']]);
    $hash = (string)$stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $errors[] = 'Current password is incorrect.';
    }
    if ($new !== $confirm) {
        $errors[] = 'New password and confirmation do not match.';
    }
    if ($msg = password_policy_error($new)) {
        $errors[] = $msg;
    }
    if (!$errors && password_verify($new, $hash)) {
        $errors[] = 'New password must be different from the current one.';
    }

    if (!$errors) {
        db()->prepare('UPDATE admins SET password_hash = ?, must_change_password = 0,
                       remember_selector = NULL, remember_hash = NULL, remember_expires = NULL WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
        clear_remember_cookie();
        session_regenerate_id(true);
        flash_set('success', 'Password changed successfully.');
        redirect('admin/index.php');
    }
}

$pageTitle = 'Change Password';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-8 col-lg-5">
    <div class="ft-card p-4">
      <h1 class="page-title h3 mb-1">Change Password</h1>
      <?php if ((int)$admin['must_change_password'] === 1): ?>
        <div class="alert alert-warning mt-3">You are using the default password. Set a new one to continue.</div>
      <?php endif; ?>
      <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger py-2"><?= e($err) ?></div>
      <?php endforeach; ?>

      <form method="post" class="mt-3" autocomplete="off">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label" for="current_password">Current password</label>
          <input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="new_password">New password</label>
          <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" autocomplete="new-password" required>
          <div class="form-text">At least 8 characters, with a letter and a number.</div>
        </div>
        <div class="mb-4">
          <label class="form-label" for="confirm_password">Confirm new password</label>
          <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required>
        </div>
        <button class="btn btn-accent" type="submit"><i class="bi bi-check-lg"></i> Update Password</button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
