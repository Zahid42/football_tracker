<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/match_form.php';
$admin = require_login();
$id = (int)($_GET['id'] ?? 0);
$match = $id ? get_match($id) : null;
if (!$match) { flash_set('danger', 'Match not found.'); redirect('admin/matches.php'); }
[$v, $errors] = process_match_form($match);
$pageTitle = 'Edit Match';
require ROOT_PATH . '/includes/admin_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="page-title h3"><i class="bi bi-pencil-square"></i> Edit Match</h1>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-theme" href="<?= e(url('match-details.php?id=' . $id)) ?>" target="_blank" rel="noopener"><i class="bi bi-eye"></i> View public page</a>
    <a class="btn btn-outline-theme" href="<?= e(url('admin/matches.php')) ?>"><i class="bi bi-arrow-left"></i> Back</a>
  </div>
</div>
<?= render_match_form($v, $errors, $match) ?>
<?php require ROOT_PATH . '/includes/admin_footer.php';
