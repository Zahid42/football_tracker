<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/includes/stats_view.php';
$admin = require_login();
$view = get_str('view', 'overall');
if (!isset(STAT_VIEWS[$view])) { $view = 'overall'; }
[$html, $charts] = render_statistics($view);
$pageTitle = 'Statistics - ' . STAT_VIEWS[$view];
require ROOT_PATH . '/includes/admin_header.php';
?>
<h1 class="page-title h3 mb-3"><i class="bi bi-bar-chart-line"></i> Statistics</h1>
<?= stat_tabs($view, url('admin/statistics.php')) ?>
<?= $html ?>
<?php
$extraScripts = chart_scripts(['charts' => $charts], 'charts.js');
require ROOT_PATH . '/includes/admin_footer.php';
