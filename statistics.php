<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$view = get_str('view', 'overall'); if (!isset(STAT_VIEWS[$view])) { $view = 'overall'; }
[$html, $charts] = render_statistics($view);
$pageTitle = 'Statistics'; $activePage = 'statistics.php';
require __DIR__ . '/includes/header.php';
echo public_page_title('Player Statistics', STAT_VIEWS[$view]);
?>
<div class="container py-4"><?= stat_tabs($view, purl('statistics.php')) ?><?= $html ?></div>
<?php $extraScripts = chart_scripts(['charts' => $charts], 'charts.js'); require __DIR__ . '/includes/footer.php';
