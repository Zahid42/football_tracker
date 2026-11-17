<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle  = 'Verify e-mail';
$activePage = 'signup.php';
$email = mb_strtolower(trim((string)($_GET['e'] ?? ($_SESSION['verify_email'] ?? ''))));
$code = preg_replace('/\D/', '', (string)($_GET['c'] ?? ''));
$error = null; $done = false;
if (is_post()) {
    csrf_require();
    $email = mb_strtolower(trim(post('email'))); $code = preg_replace('/\D/', '', post('code'));
    if (post('op') === 'resend') {
        $q = db()->prepare('SELECT id, email_verified, TIMESTAMPDIFF(SECOND, verify_sent_at, NOW()) AS age FROM members WHERE email = ?'); $q->execute([$email]); $m = $q->fetch();
        if ($m && !$m['email_verified']) {
            if ($m['age'] !== null && (int)$m['age'] < 60) { $error = 'Please wait a minute before asking for another code.'; }
            else { [$ok, $er] = member_send_verification((int)$m['id']); flash_set($ok ? 'success' : 'danger', $ok ? 'A new code is on its way.' : 'Could not send the e-mail: ' . $er); $_SESSION['verify_email'] = $email; redirect('verify.php'); }
        } else { flash_set('success', 'If that address is waiting for verification, a new code was sent.'); redirect('verify.php'); }
    } else {
        $lock = max(0, (int)($_SESSION['v_lock'] ?? 0) - time());
        if ($lock > 0) { $error = 'Too many attempts. Try again in ' . $lock . ' seconds.'; }
        elseif (($error = member_check_code($email, $code)) === null) { unset($_SESSION['verify_email'], $_SESSION['v_fail']); $done = true; }
        else { $_SESSION['v_fail'] = (int)($_SESSION['v_fail'] ?? 0) + 1; if ($_SESSION['v_fail'] >= 8) { $_SESSION['v_lock'] = time() + 120; $_SESSION['v_fail'] = 0; } }
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="container py-5" style="max-width:480px">
  <div class="ft-card p-4">
  <?php if ($done): ?>
    <h1 class="h3 mb-2"><i class="bi bi-patch-check-fill text-success"></i> E-mail verified</h1>
    <p>We have e-mailed your <b>player ID</b> to <b><?= e($email) ?></b>. Sign in with that ID and the password you chose. You can edit your profile; it goes live after the admin approves it.</p>
    <a class="btn btn-accent" href="<?= e(url('signin.php')) ?>">Go to sign in</a>
  <?php else: ?>
    <h1 class="h3 mb-1"><i class="bi bi-envelope-check"></i> Verify your e-mail</h1>
    <p class="text-muted-ft">Enter the 6-digit code we sent to your e-mail.</p>
    <?= flash_render() ?>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
    <form method="post"><?= csrf_field() ?>
      <div class="mb-3"><label class="form-label" for="email">E-mail</label><input class="form-control" type="email" id="email" name="email" required autocapitalize="off" value="<?= e($email) ?>"></div>
      <div class="mb-3"><label class="form-label" for="code">Code</label><input class="form-control form-control-lg text-center" id="code" name="code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code" required value="<?= e($code) ?>" style="letter-spacing:.4em"></div>
      <button class="btn btn-accent btn-lg w-100" type="submit">Verify</button>
    </form>
    <form method="post" class="mt-3 text-center"><?= csrf_field() ?><input type="hidden" name="op" value="resend"><input type="hidden" name="email" value="<?= e($email) ?>"><button class="btn btn-link btn-sm">Send a new code</button></form>
  <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
