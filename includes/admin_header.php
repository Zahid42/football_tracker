<?php
/**
 * Admin page header + sidebar. Usage at top of an admin page:
 *   require_once __DIR__ . '/../config/config.php';
 *   $admin = require_login();
 *   $pageTitle = 'Dashboard';
 *   require __DIR__ . '/../includes/admin_header.php';
 */
declare(strict_types=1);
require_once __DIR__ . '/nav.php';
$pageTitle  = $pageTitle ?? 'Admin';
$activePage = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title><?= e($pageTitle) ?> | Admin - <?= e(setting('site_title', 'Football Tracker')) ?></title>
  <script>
    (function(){try{var t=localStorage.getItem('ft-theme');if(t){document.documentElement.setAttribute('data-theme',t);}}catch(e){}})();
  </script>
  <link href="<?= e(url("assets/vendor/bootstrap/bootstrap.min.css")) ?>" rel="stylesheet">
  <link href="<?= e(url("assets/vendor/bootstrap-icons/bootstrap-icons.min.css")) ?>" rel="stylesheet">
  <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
  <link href="<?= e(url('assets/css/admin.css')) ?>" rel="stylesheet">
</head>
<body class="admin-body">

<!-- Mobile top bar -->
<header class="admin-topbar d-lg-none">
  <button class="btn btn-outline-theme btn-sm" type="button" data-bs-toggle="offcanvas"
          data-bs-target="#adminSidebar" aria-controls="adminSidebar" aria-label="Open menu">
    <i class="bi bi-list"></i>
  </button>
  <span class="brand"><i class="bi bi-dribbble"></i> Football Tracker</span>
  <button class="btn btn-outline-theme btn-sm" type="button" data-theme-toggle aria-label="Toggle theme"><i class="bi bi-circle-half"></i></button>
</header>

<!-- Sidebar: offcanvas on mobile, fixed on desktop -->
<aside class="admin-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="adminSidebar" aria-label="Admin navigation">
  <div class="sidebar-head">
    <a href="<?= e(url('admin/index.php')) ?>" class="brand"><i class="bi bi-dribbble"></i> Football Tracker</a>
    <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar" aria-label="Close"></button>
  </div>
  <?php $swPlayers = players_list(false); $swCur = admin_player_id(); ?>
  <form method="post" action="<?= e(url('admin/switch-player.php')) ?>" class="player-switch">
    <?= csrf_field() ?><input type="hidden" name="return" value="<?= e((string)($_SERVER['REQUEST_URI'] ?? '')) ?>">
    <label for="swPlayer"><i class="bi bi-person-check"></i> Managing player</label>
    <select id="swPlayer" name="player_id" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php foreach ($swPlayers as $sp): ?><option value="<?= (int)$sp['id'] ?>"<?= (int)$sp['id'] === $swCur ? ' selected' : '' ?>><?= e($sp['full_name']) ?><?= $sp['is_featured'] ? ' (homepage)' : '' ?></option><?php endforeach; ?>
    </select>
    <noscript><button class="btn btn-sm btn-accent mt-1">Switch</button></noscript>
  </form>
  <nav class="sidebar-nav">
    <?php foreach (admin_nav() as $item): ?>
      <?php if (isset($item['heading'])): ?>
        <div class="sidebar-heading"><?= e($item['heading']) ?></div>
        <?php continue; ?>
      <?php endif; ?>
      <a class="sidebar-link <?= admin_nav_active($item) ? 'active' : '' ?>" href="<?= e(url($item['href'])) ?>"<?= !empty($item['external']) ? ' target="_blank" rel="noopener"' : '' ?>><i class="bi <?= e($item['icon']) ?>"></i> <?= e($item['label']) ?><?php if (($item['badge'] ?? '') === 'pending' && ($pc = count(pending_players()))): ?> <span class="badge bg-danger ms-1"><?= $pc ?></span><?php endif; ?></a>
    <?php endforeach; ?>
    <a class="sidebar-link" href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> View Public Site</a>
    <form method="post" action="<?= e(url('admin/logout.php')) ?>" class="mt-2">
      <?= csrf_field() ?>
      <button type="submit" class="sidebar-link logout w-100 text-start"><i class="bi bi-box-arrow-right"></i> Logout</button>
    </form>
  </nav>
  <div class="sidebar-foot">
    <i class="bi bi-person-circle"></i> <?= e($admin['username'] ?? '') ?>
  </div>
</aside>

<div class="admin-main">
  <div class="admin-content container-fluid">
    <div class="d-none d-lg-flex justify-content-end mb-2">
      <button class="btn btn-outline-theme btn-sm" type="button" data-theme-toggle aria-label="Toggle theme"><i class="bi bi-circle-half"></i> Theme</button>
    </div>
    <?= flash_render() ?>
