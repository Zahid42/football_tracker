<?php
/** Venues: map location (latitude/longitude) and the premium map card. */
declare(strict_types=1);

function ft_venue_ensure(): void
{
    $pdo = db();
    $v = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'venue_geo_v'")->fetchColumn();
    if ($v === '1') { return; }
    foreach (['latitude' => 'DECIMAL(9,6) DEFAULT NULL', 'longitude' => 'DECIMAL(9,6) DEFAULT NULL'] as $c => $def) {
        if (!$pdo->query("SHOW COLUMNS FROM venues LIKE '$c'")->fetch()) { $pdo->exec("ALTER TABLE venues ADD COLUMN `$c` $def"); }
    }
    $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute(['venue_geo_v', '1']);
}

function venue_has_geo(array $v): bool { return isset($v['latitude'], $v['longitude']) && $v['latitude'] !== null && $v['longitude'] !== null && $v['latitude'] !== ''; }

function venue_url(int $id): string { return url('venue.php?id=' . $id); }

/** Leaflet assets + map script, emitted once per page. */
function venue_map_assets(): string
{
    static $done = false;
    if ($done) { return ''; }
    $done = true;
    $js = ROOT_PATH . '/assets/js/venue-map.js';
    return '<link rel="stylesheet" href="' . e(url('assets/vendor/leaflet/leaflet.css')) . '">'
        . '<script src="' . e(url('assets/vendor/leaflet/leaflet.js')) . '"></script>'
        . '<script src="' . e(url('assets/js/venue-map.js') . '?v=' . (int)@filemtime($js)) . '" defer></script>';
}

/** Premium map card for a venue with coordinates. */
function venue_map_html(array $v, string $height = '340px'): string
{
    if (!venue_has_geo($v)) { return ''; }
    $lat = (float)$v['latitude']; $lng = (float)$v['longitude'];
    $g = 'https://www.google.com/maps/search/?api=1&query=' . $lat . ',' . $lng;
    $d = 'https://www.google.com/maps/dir/?api=1&destination=' . $lat . ',' . $lng;
    $addr = trim(implode(', ', array_filter([$v['location'] ?? '', $v['city'] ?? '', $v['country'] ?? ''])));
    return venue_map_assets()
        . '<div class="venue-card"><div class="venue-map" style="height:' . e($height) . '" data-lat="' . $lat . '" data-lng="' . $lng . '" data-name="' . e($v['name']) . '" role="img" aria-label="Map of ' . e($v['name']) . '"></div>'
        . '<div class="venue-chip"><i class="bi bi-geo-alt-fill"></i><div><b>' . e($v['name']) . '</b>' . ($addr !== '' ? '<span>' . e($addr) . '</span>' : '') . '</div></div>'
        . '<div class="venue-bar"><span class="venue-coords"><i class="bi bi-crosshair"></i> ' . number_format($lat, 5) . ', ' . number_format($lng, 5) . '</span>'
        . '<span class="venue-btns"><a class="btn btn-sm btn-accent" href="' . e($d) . '" target="_blank" rel="noopener"><i class="bi bi-sign-turn-right"></i> Directions</a>'
        . '<a class="btn btn-sm btn-outline-theme" href="' . e($g) . '" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Google Maps</a></span></div></div>';
}
