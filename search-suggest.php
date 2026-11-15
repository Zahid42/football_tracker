<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$_SESSION['sg'] = array_filter($_SESSION['sg'] ?? [], fn($t) => $t > time() - 10);
if (count($_SESSION['sg']) >= 25) { echo '[]'; exit; }
$_SESSION['sg'][] = time();
$q = trim((string)($_GET['q'] ?? ''));
$items = [];
if (mb_strlen($q) >= 2 && mb_strlen($q) <= 60) {
    $r = site_search($q, 5);
    foreach ($r['players'] as $p) { $items[] = ['type' => 'Player', 'icon' => 'bi-person', 'title' => $p['full_name'], 'sub' => trim(($p['preferred_position'] ?? '') . ' #' . $p['public_code']), 'url' => player_url_by_id((int)$p['id'])]; }
    foreach ($r['teams'] as $t) { $items[] = ['type' => 'Team', 'icon' => 'bi-shield', 'title' => $t['name'], 'sub' => '#' . $t['public_code'], 'url' => team_url_by_id((int)$t['id'])]; }
    foreach ($r['competitions'] as $c) { $items[] = ['type' => 'Competition', 'icon' => 'bi-trophy', 'title' => $c['name'], 'sub' => $c['type'], 'url' => url('competitions.php?c=' . (int)$c['id'])]; }
}
echo json_encode(array_slice($items, 0, 10), JSON_UNESCAPED_SLASHES);
