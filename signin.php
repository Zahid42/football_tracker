<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle  = 'Sign in';
$activePage = 'signin.php';
if (current_member()) { redirect('account.php'); }
$error = null; $pid = ''; $mode = get_str('forgot') ? 'forgot' : 'signin';
if (is_post()) {
    csrf_require();
    if (post('op') === 'forgot') {      // e-mail the player his ID (same answer whether or not the address exists)
        $mode = 'forgot';
        if (time() - (int)($_SESSION['forgot_last'] ?? 0) < 30) { $error = 'Please wait a few seconds before trying again.'; }
        else {
            $_SESSION['forgot_last'] = time();
            $q = db()->prepare('SELECT id FROM members WHERE email = ? AND email_verified = 1'); $q->execute([mb_strtolower(trim(post('email')))]);
            if ($mid = $q->fetchColumn()) { member_send_id((int)$mid); }
            flash_set('success', 'If that e-mail belongs to a verified account, we have sent the player ID to it.');
            redirect('signin.php');
        }
    } else {
        $ident = trim(post('login')); $pw = (string)($_POST['password'] ?? ''); $pid = $ident;
        $lock = max(0, (int)($_SESSION['m_lock'] ?? 0) - time());
        if ($lock > 0) { $error = 'Too many attempts. Try again in ' . $lock . ' seconds.'; }
        else {
            $m = member_find_for_signin($ident);
            $ok = $m && password_verify($pw, $m['password_hash']);
            if (!$m) { password_verify($pw, '$2y$10$usesomesillystringforsalt.abcdefghijklmnopqrstuvwxyz012'); }   // keep timing similar
            if ($ok && !empty($m['is_blocked'])) { member_log_signin((int)$m['id'], $ident, false, 'blocked'); $error = 'This account has been blocked. Please contact the admin.'; }
            elseif ($ok && !$m['email_verified']) { member_log_signin((int)$m['id'], $ident, false, 'e-mail not verified'); $_SESSION['verify_email'] = $m['email']; flash_set('warning', 'Please verify your e-mail first.'); redirect('verify.php'); }
            elseif ($ok) { unset($_SESSION['m_fail'], $_SESSION['m_lock']); member_login((int)$m['id']); member_log_signin((int)$m['id'], $ident, true); redirect('account.php'); }
            else {
                member_log_signin($m ? (int)$m['id'] : null, $ident, false, $m ? 'wrong password' : 'unknown ID or e-mail');
                $_SESSION['m_fail'] = (int)($_SESSION['m_fail'] ?? 0) + 1;
                if ($_SESSION['m_fail'] >= 5) { $_SESSION['m_lock'] = time() + 60; $_SESSION['m_fail'] = 0; }
                $error = 'Wrong player ID / e-mail or password.';
            }
        }
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="container py-5" style="max-width:460px">
  <div class="ft-card p-4">
    <?= flash_render() ?>
  <?php if ($mode === 'forgot'): ?>
    <h1 class="h3 mb-1"><i class="bi bi-envelope"></i> Forgot your ID?</h1>
    <p class="text-muted-ft">Enter the e-mail you signed up with and we will send your player ID to it.</p>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="forgot">
      <div class="mb-3"><label class="form-label" for="email">E-mail</label><input class="form-control" type="email" id="email" name="email" required autocomplete="email" autocapitalize="off"></div>
      <button class="btn btn-accent btn-lg w-100" type="submit">E-mail me my ID</button></form>
    <p class="small mt-3 mb-0"><a href="<?= e(url('signin.php')) ?>">Back to sign in</a></p>
  <?php else: ?>
    <h1 class="h3 mb-1"><i class="bi bi-box-arrow-in-right"></i> Sign in</h1>
    <p class="text-muted-ft">New here? <a href="<?= e(url('signup.php')) ?>">Create an account</a>. You can sign in with your player ID or your e-mail address.</p>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label" for="login">Player ID or e-mail</label><input class="form-control form-control-lg" id="login" name="login" required maxlength="120" autocomplete="username" autocapitalize="off" spellcheck="false" placeholder="6-digit ID or you@example.com" value="<?= e($pid) ?>"></div>
      <div class="mb-3"><label class="form-label" for="password">Password</label><input class="form-control" type="password" id="password" name="password" required autocomplete="current-password"></div>
      <button class="btn btn-accent btn-lg w-100" type="submit">Sign in</button>
    </form>
    <p class="small text-muted-ft mt-3 mb-0"><a href="<?= e(url('signin.php?forgot=1')) ?>">Forgot your ID?</a> &middot; Forgot your password? Ask the admin to reset it.</p>
  <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
