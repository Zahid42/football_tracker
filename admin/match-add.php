<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/match_form.php';
$admin = require_login();
[$v, $errors] = process_match_form(null);
$pageTitle = 'Add Match';
require ROOT_PATH . '/includes/admin_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="page-title h3"><i class="bi bi-plus-circle"></i> Add Match</h1>
  <a class="btn btn-outline-theme" href="<?= e(url('admin/matches.php')) ?>"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<?= render_match_form($v, $errors, null) ?>
<?php require ROOT_PATH . '/includes/admin_footer.php';
