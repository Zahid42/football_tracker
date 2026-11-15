<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle  = 'Sign in';
$activePage = 'signin.php';
if (current_member()) { redirect('account.php'); }
$error = null; $email = '';
if (is_post()) {
    csrf_require();
    $email = mb_strtolower(post('email')); $pw = (string)($_POST['password'] ?? '');
    $lock = max(0, (int)($_SESSION['m_lock'] ?? 0) - time());
    if ($lock > 0) { $error = 'Too many attempts. Try again in ' . $lock . ' seconds.'; }
    else {
        $q = db()->prepare('SELECT id, password_hash FROM members WHERE email = ?'); $q->execute([$email]);
        $m = $q->fetch();
        $ok = $m && password_verify($pw, $m['password_hash']);
        if (!$m) { password_verify($pw, '$2y$10$usesomesillystringforsalt.abcdefghijklmnopqrstuvwxyz012'); }   // keep timing similar
        if ($ok) { unset($_SESSION['m_fail'], $_SESSION['m_lock']); member_login((int)$m['id']); redirect('account.php'); }
        $_SESSION['m_fail'] = (int)($_SESSION['m_fail'] ?? 0) + 1;
        if ($_SESSION['m_fail'] >= 5) { $_SESSION['m_lock'] = time() + 60; $_SESSION['m_fail'] = 0; }
        $error = 'Wrong e-mail or password.';
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="container py-5" style="max-width:460px">
  <div class="ft-card p-4">
    <h1 class="h3 mb-1"><i class="bi bi-box-arrow-in-right"></i> Sign in</h1>
    <p class="text-muted-ft">New here? <a href="<?= e(url('signup.php')) ?>">Create an account</a></p>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label" for="email">E-mail</label><input class="form-control" type="email" id="email" name="email" required autocomplete="email" autocapitalize="off" value="<?= e($email) ?>"></div>
      <div class="mb-3"><label class="form-label" for="password">Password</label><input class="form-control" type="password" id="password" name="password" required autocomplete="current-password"></div>
      <button class="btn btn-accent btn-lg w-100" type="submit">Sign in</button>
    </form>
    <p class="small text-muted-ft mt-3 mb-0">Forgot your password? Ask the admin to reset it.</p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
