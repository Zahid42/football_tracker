<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
crud_run([
    'table' => 'venues', 'title' => 'Venues', 'singular' => 'Venue', 'icon' => 'bi-geo-alt',
    'search' => ['t.name', 't.city', 't.location'], 'order' => 't.name',
    'fields' => [
        ['name' => 'name', 'label' => 'Venue Name', 'required' => true, 'max' => 150],
        ['name' => 'location', 'label' => 'Location / Address'],
        ['name' => 'city', 'label' => 'City', 'max' => 80],
        ['name' => 'country', 'label' => 'Country', 'max' => 80, 'default' => 'Bangladesh'],
        ['name' => 'latitude', 'label' => 'Latitude', 'max' => 12, 'col' => 6, 'help' => 'Set by clicking the map below (or type it).'],
        ['name' => 'longitude', 'label' => 'Longitude', 'max' => 12, 'col' => 6],
        ['name' => 'pitch_type', 'label' => 'Pitch Type', 'type' => 'select', 'nullable' => true, 'options' => ['Grass', 'Artificial Turf', 'Futsal', 'Concrete', 'Indoor', 'Other']],
        ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
    ],
    'before_save' => function (array &$d, ?array $ex) {
        foreach (['latitude' => 90, 'longitude' => 180] as $k => $lim) {
            $x = trim((string)($d[$k] ?? ''));
            if ($x === '') { $d[$k] = null; continue; }
            if (!is_numeric($x) || abs((float)$x) > $lim) { return ucfirst($k) . ' must be a number between -' . $lim . ' and ' . $lim . '.'; }
            $d[$k] = round((float)$x, 6);
        }
        if (($d['latitude'] === null) !== ($d['longitude'] === null)) { return 'Set both latitude and longitude, or leave both empty.'; }
        return null;
    },
    'scripts' => venue_map_assets() . '<script src="' . e(url('assets/js/venue-picker.js') . '?v=' . (int)@filemtime(ROOT_PATH . '/assets/js/venue-picker.js')) . '" defer></script>',
    'columns' => [
        ['label' => 'Venue', 'key' => 'name', 'class' => 'fw-semibold'], ['label' => 'Location', 'key' => fn($r) => $r['location'] ?? '-'],
        ['label' => 'City', 'key' => fn($r) => $r['city'] ?? '-'], ['label' => 'Map', 'key' => fn($r) => venue_has_geo($r) ? '<a href="' . e(venue_url((int)$r['id'])) . '" target="_blank" rel="noopener"><i class="bi bi-geo-alt-fill"></i> Pinned</a>' : '-', 'html' => true], ['label' => 'Pitch', 'key' => fn($r) => $r['pitch_type'] ?? '-'],
    ],
]);
