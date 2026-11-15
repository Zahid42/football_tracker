<?php
/**
 * Public page header. Usage:
 *   $pageTitle = 'Home'; $activePage = 'index.php';
 *   require __DIR__ . '/includes/header.php';
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
ft_codes_ensure();
require_once __DIR__ . '/nav.php';
$pageTitle  = $pageTitle  ?? 'Home';
$activePage = $activePage ?? basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | <?= e(setting('site_title', 'Football Tracker')) ?></title>
  <script>
    // Apply saved theme before paint to avoid flashing
    (function(){try{var t=localStorage.getItem('ft-theme');if(t){document.documentElement.setAttribute('data-theme',t);if(t==='ocean'){var l=document.createElement('link');l.rel='stylesheet';l.href='https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Manrope:wght@400;600;700&display=swap';document.head.appendChild(l);}}}catch(e){}})();
  </script>
  <link href="<?= e(url("assets/vendor/bootstrap/bootstrap.min.css")) ?>" rel="stylesheet">
  <link href="<?= e(url("assets/vendor/bootstrap-icons/bootstrap-icons.min.css")) ?>" rel="stylesheet">
  <link href="<?= e(url('assets/css/style.css') . '?v=' . (int)@filemtime(ROOT_PATH . '/assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="public-body">
<?php require __DIR__ . '/navbar.php'; ?>
<main>
