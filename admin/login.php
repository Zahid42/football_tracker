<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

if (is_logged_in()) {
    redirect('admin/index.php');
}

$error    = '';
$identity = '';

if (is_post()) {
    csrf_require();
    $identity = post('identity');
    $password = (string)($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);

    $locked = login_locked_seconds();
    if ($locked > 0) {
        $error = 'Too many failed attempts. Try again in ' . ceil($locked / 60) . ' minute(s).';
    } elseif ($identity === '' || $password === '') {
        $error = 'Please enter your username/email and password.';
    } elseif (attempt_login($identity, $password, $remember)) {
        redirect('admin/index.php');
    } else {
        $error = login_locked_seconds() > 0
            ? 'Too many failed attempts. Try again in ' . ceil(login_locked_seconds() / 60) . ' minute(s).'
            : 'Invalid username/email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Admin Login | <?= e(setting('site_title', 'Football Tracker')) ?></title>
  <script>(function(){try{var t=localStorage.getItem('ft-theme');if(t){document.documentElement.setAttribute('data-theme',t);}}catch(e){}})();</script>
  <link href="<?= e(url("assets/vendor/bootstrap/bootstrap.min.css")) ?>" rel="stylesheet">
  <link href="<?= e(url("assets/vendor/bootstrap-icons/bootstrap-icons.min.css")) ?>" rel="stylesheet">
  <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
  <link href="<?= e(url('assets/css/admin.css')) ?>" rel="stylesheet">
</head>
<body class="login-body">
  <div class="login-card">
    <div class="text-center mb-4">
      <div class="login-logo"><i class="bi bi-dribbble"></i></div>
      <h1 class="login-title">Football Tracker</h1>
      <p class="text-muted-ft mb-0">Admin sign in</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="on" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label for="identity" class="form-label">Username or Email</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-person"></i></span>
          <input type="text" class="form-control" id="identity" name="identity" value="<?= e($identity) ?>"
                 autocomplete="username" required autofocus>
        </div>
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-key"></i></span>
          <input type="password" class="form-control" id="password" name="password"
                 autocomplete="current-password" required>
        </div>
      </div>
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
        <label class="form-check-label" for="remember">Remember me for <?= REMEMBER_DAYS ?> days</label>
      </div>
      <button type="submit" class="btn btn-accent w-100 btn-lg"><i class="bi bi-box-arrow-in-right"></i> Login</button>
    </form>

    <div class="text-center mt-4">
      <a href="<?= e(url('index.php')) ?>" class="text-muted-ft small"><i class="bi bi-arrow-left"></i> Back to public site</a>
    </div>
  </div>
  <script src="<?= e(url("assets/vendor/bootstrap/bootstrap.bundle.min.js")) ?>"></script>
</body>
</html>
