<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
crud_run([
    'table' => 'competitions', 'title' => 'Competitions', 'singular' => 'Competition', 'icon' => 'bi-award',
    'intro' => 'Tournaments, leagues and friendlies you play in.',
    'select' => 'SELECT t.*, s.name AS season_name FROM competitions t LEFT JOIN seasons s ON s.id = t.season_id',
    'search' => ['t.name', 't.organizer'], 'order' => 't.name',
    'fields' => [
        ['name' => 'name', 'label' => 'Competition Name', 'required' => true, 'max' => 150],
        ['name' => 'season_id', 'label' => 'Season', 'type' => 'select', 'nullable' => true, 'options' => fn() => lookup('SELECT id, name FROM seasons ORDER BY name DESC')],
        ['name' => 'type', 'label' => 'Competition Type', 'type' => 'select', 'required' => true, 'options' => ['Tournament', 'League', 'Friendly', 'Training', 'Other']],
        ['name' => 'organizer', 'label' => 'Organizer', 'max' => 150],
        ['name' => 'logo', 'label' => 'Logo', 'type' => 'image', 'dir' => 'competitions'],
        ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
    ],
    'columns' => [
        ['label' => 'Logo', 'key' => fn($r) => thumb($r['logo']), 'html' => true],
        ['label' => 'Name', 'key' => 'name'], ['label' => 'Type', 'key' => 'type'],
        ['label' => 'Season', 'key' => fn($r) => $r['season_name'] ?? '-'], ['label' => 'Organizer', 'key' => fn($r) => $r['organizer'] ?? '-'],
    ],
]);
