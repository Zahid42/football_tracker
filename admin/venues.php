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
        ['name' => 'pitch_type', 'label' => 'Pitch Type', 'type' => 'select', 'nullable' => true, 'options' => ['Grass', 'Artificial Turf', 'Futsal', 'Concrete', 'Indoor', 'Other']],
        ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
    ],
    'columns' => [
        ['label' => 'Venue', 'key' => 'name', 'class' => 'fw-semibold'], ['label' => 'Location', 'key' => fn($r) => $r['location'] ?? '-'],
        ['label' => 'City', 'key' => fn($r) => $r['city'] ?? '-'], ['label' => 'Pitch', 'key' => fn($r) => $r['pitch_type'] ?? '-'],
    ],
]);
