<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
header('Content-Type: application/manifest+json; charset=utf-8');
$icon = fn(string $f, int $s, string $p = 'any') => ['src' => url('assets/icons/' . $f), 'sizes' => $s . 'x' . $s, 'type' => 'image/png', 'purpose' => $p];
echo json_encode([
    'name' => setting('site_title', 'Football Tracker'),
    'short_name' => 'Football',
    'description' => 'Football profile, teams, matches and competitions.',
    'start_url' => url('index.php') . '?source=pwa',
    'scope' => url('') === '' ? '/' : rtrim(url(''), '/') . '/',
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => '#0b1210',
    'theme_color' => '#0b1210',
    'icons' => [$icon('icon-192.png', 192), $icon('icon-512.png', 512), $icon('icon-maskable-512.png', 512, 'maskable')],
    'shortcuts' => [
        ['name' => 'Players', 'url' => url('players.php')], ['name' => 'Teams', 'url' => url('teams.php')],
        ['name' => 'Competitions', 'url' => url('competitions.php')], ['name' => 'Calendar', 'url' => url('calendar.php')],
    ],
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
