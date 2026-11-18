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

function venue_url(int $id): string { return url('venue.php?id=' . $id); }
