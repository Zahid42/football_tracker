<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Matches'; $activePage = 'matches.php';
require __DIR__ . '/includes/header.php';
$f = match_filters($_GET);
$page = max(1, (int)($_GET['page'] ?? 1)); $per = 15;
$total = count_matches($f);
$rows = search_matches($f, $per, ($page - 1) * $per);
$qs = $_GET; unset($qs['page']);
echo public_page_title('Match History', $total . ' match' . ($total === 1 ? '' : 'es') . ' recorded');
?>
<div class="container py-4">
  <?= match_filter_form($f, purl('matches.php')) ?>
  <?= render_match_table($rows, $f, purl('matches.php')) ?>
  <?= pagination($total, $per, $page, purl('matches.php') . ($qs ? '?' . http_build_query($qs) : '')) ?>
</div>
<?php require __DIR__ . '/includes/footer.php';
