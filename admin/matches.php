<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
$admin = require_login();
$f = match_filters($_GET);
$page = max(1, (int)($_GET['page'] ?? 1)); $per = 20;
$total = count_matches($f);
$rows = search_matches($f, $per, ($page - 1) * $per);
$pageTitle = 'Matches';
require ROOT_PATH . '/includes/admin_header.php';
$qs = $_GET; unset($qs['page']);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div><h1 class="page-title h3 mb-0"><i class="bi bi-calendar-event"></i> Matches</h1><p class="text-muted-ft mb-0"><?= $total ?> match<?= $total === 1 ? '' : 'es' ?> found</p></div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-theme" href="<?= e(url('admin/export.php?type=matches&' . http_build_query($qs))) ?>"><i class="bi bi-filetype-csv"></i> Export CSV</a>
    <a class="btn btn-accent" href="<?= e(url('admin/match-add.php')) ?>"><i class="bi bi-plus-lg"></i> Add Match</a>
  </div>
</div>
<?= match_filter_form($f, url('admin/matches.php')) ?>
<?= render_match_table($rows, $f, url('admin/matches.php'), true) ?>
<?= pagination($total, $per, $page, url('admin/matches.php') . ($qs ? '?' . http_build_query($qs) : '')) ?>
<?php require ROOT_PATH . '/includes/admin_footer.php';
