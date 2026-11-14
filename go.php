<?php
/** Resolves a 6-digit public ID (e.g. /482913) to a player profile or a team page. */
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$code = preg_replace('/\D/', '', (string)($_GET['c'] ?? ''));
if (strlen($code) === 6) {
    $st = db()->prepare("SELECT id FROM players WHERE public_code = ? AND approval_status = 'approved' AND is_active = 1");
    $st->execute([$code]);
    if ($id = $st->fetchColumn()) { $_GET['player'] = (string)$id; require __DIR__ . '/profile.php'; exit; }
    $st = db()->prepare('SELECT id FROM teams WHERE public_code = ?');
    $st->execute([$code]);
    if ($id = $st->fetchColumn()) { $_GET['id'] = (string)$id; require __DIR__ . '/team.php'; exit; }
}
http_response_code(404);
$pageTitle = 'Not found'; $activePage = '';
require __DIR__ . '/includes/header.php';
echo '<div class="container py-5 text-center"><h1>Nothing found for this ID</h1><p class="text-muted-ft">Check the 6-digit number and try again.</p><a class="btn btn-accent" href="' . e(url('index.php')) . '">Home</a></div>';
require __DIR__ . '/includes/footer.php';
