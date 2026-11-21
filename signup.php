<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle  = 'Sign up';
$activePage = 'signup.php';
if (current_member()) { redirect('account.php'); }
$open = setting('registration_open', '1') === '1';
$errors = []; $v = ['full_name' => '', 'email' => ''];

if ($open && is_post()) {
    csrf_require();
    $v['full_name'] = post('full_name'); $v['email'] = post('email');
    $pw = (string)($_POST['password'] ?? ''); $pw2 = (string)($_POST['password2'] ?? '');
    if (post('website') !== '') { redirect('index.php'); }                                           // honeypot
    if (isset($_SESSION['signup_last']) && time() - (int)$_SESSION['signup_last'] < 20) { $errors[] = 'Please wait a few seconds before trying again.'; }
    if ($v['full_name'] === '' || mb_strlen($v['full_name']) > 120) { $errors[] = 'Please enter your full name (up to 120 characters).'; }
    $chk = ft_check_email($v['email']);
    if (!$chk['ok']) { $errors[] = $chk['error']; }
    elseif (ft_email_taken($chk['email'])) { $errors[] = 'This e-mail is already registered. Please sign in instead.'; }
    if ($e = ft_password_error($pw)) { $errors[] = $e; }
    elseif ($pw !== $pw2) { $errors[] = 'The two passwords do not match.'; }
    if (!$errors && (int)db()->query("SELECT COUNT(*) FROM players WHERE approval_status='pending'")->fetchColumn() >= 100) { $errors[] = 'Sign-ups are temporarily full. Please try again later.'; }
    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO players (full_name, email, approval_status, is_active, is_featured, registered_at, public_code) VALUES (?, ?, 'pending', 0, 0, NOW(), ?)")->execute([$v['full_name'], $chk['email'], ft_new_code()]);
            $pid = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO members (player_id, email, password_hash, created_at) VALUES (?, ?, ?, NOW())')->execute([$pid, $chk['email'], password_hash($pw, PASSWORD_DEFAULT)]);
            $mid = (int)$pdo->lastInsertId();
            $pdo->commit();
            $_SESSION['signup_last'] = time();
            [$sent, $merr] = member_send_verification($mid);
            $_SESSION['verify_email'] = $chk['email'];
            flash_set($sent ? 'success' : 'warning', $sent ? 'We sent a 6-digit code to ' . $chk['email'] . '. Enter it below to verify your e-mail.' : 'Your account is created but we could not send the code (' . $merr . '). Press "Send a new code" or contact the admin.');
            redirect('verify.php');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $errors[] = 'Could not create the account. Please try again.';
        }
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="container py-5" style="max-width:520px">
  <div class="ft-card p-4">
    <h1 class="h3 mb-1"><i class="bi bi-person-plus"></i> Create your account</h1>
    <p class="text-muted-ft">Already have one? <a href="<?= e(url('signin.php')) ?>">Sign in</a></p>
    <?php if (!$open): ?><div class="alert alert-warning">Sign-up is closed at the moment.</div><?php else: ?>
    <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post" novalidate id="signupForm" autocomplete="on">
      <?= csrf_field() ?>
      <div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="mb-3"><label class="form-label" for="full_name">Full name</label>
        <input class="form-control" id="full_name" name="full_name" required maxlength="120" autocomplete="name" value="<?= e($v['full_name']) ?>"></div>
      <div class="mb-3"><label class="form-label" for="email">E-mail</label>
        <input class="form-control" type="email" id="email" name="email" required maxlength="120" autocomplete="email" inputmode="email" autocapitalize="off" spellcheck="false" placeholder="you@example.com" value="<?= e($v['email']) ?>">
        <div class="form-text" id="emailMsg" aria-live="polite">We check the address as you type, then e-mail you a code to confirm it. Your player ID is sent to this address.</div></div>
      <div class="mb-3"><label class="form-label" for="password">Password</label>
        <div class="input-group"><input class="form-control" type="password" id="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password">
          <button class="btn btn-outline-theme" type="button" id="pwToggle" aria-label="Show password"><i class="bi bi-eye"></i></button></div>
        <div class="progress mt-2" style="height:5px"><div class="progress-bar" id="pwBar" style="width:0"></div></div>
        <div class="form-text" id="pwMsg">At least 8 characters with a letter and a number.</div></div>
      <div class="mb-3"><label class="form-label" for="password2">Confirm password</label>
        <input class="form-control" type="password" id="password2" name="password2" required autocomplete="new-password"><div class="form-text" id="pw2Msg"></div></div>
      <button class="btn btn-accent btn-lg w-100" type="submit"><i class="bi bi-person-plus"></i> Sign up</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php
$extraScripts = '<script>window.FT_EMAIL_CHECK = ' . json_encode(url('email-check.php')) . ';</script><script src="' . e(url('assets/js/signup.js') . '?v=' . (int)@filemtime(ROOT_PATH . '/assets/js/signup.js')) . '"></script>';
require __DIR__ . '/includes/footer.php';
