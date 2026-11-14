<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
crud_run([
    'table' => 'trophies', 'title' => 'Trophies & Achievements', 'singular' => 'Trophy', 'icon' => 'bi-trophy',
    'scope' => fn() => 't.player_id = ' . admin_player_id(),
    'select' => 'SELECT t.*, tm.name AS team_name FROM trophies t LEFT JOIN teams tm ON tm.id = t.team_id',
    'search' => ['t.tournament_name', 't.achievement'], 'order' => 't.year DESC, t.id DESC',
    'fields' => [
        ['name' => 'player_id', 'label' => 'Player', 'type' => 'select', 'required' => true, 'default' => admin_player_id(), 'options' => fn() => player_options(false)],
        ['name' => 'tournament_name', 'label' => 'Tournament Name', 'required' => true, 'max' => 150],
        ['name' => 'year', 'label' => 'Year', 'type' => 'number', 'required' => true, 'min' => 1950, 'max' => 2100, 'default' => date('Y')],
        ['name' => 'team_id', 'label' => 'Team', 'type' => 'select', 'nullable' => true, 'options' => fn() => lookup('SELECT id, name FROM teams ORDER BY name')],
        ['name' => 'achievement', 'label' => 'Achievement', 'type' => 'select', 'required' => true, 'options' => ['Champion', 'Runner-up', 'Third Place', 'Best Player', 'Top Scorer', 'Top Assist', 'Other']],
        ['name' => 'position', 'label' => 'Position', 'max' => 30, 'help' => 'e.g. 1st, 2nd, Semi-final'],
        ['name' => 'image', 'label' => 'Trophy Image', 'type' => 'image', 'dir' => 'trophies'],
        ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
    ],
    'columns' => [
        ['label' => 'Image', 'key' => fn($r) => thumb($r['image']), 'html' => true],
        ['label' => 'Achievement', 'key' => fn($r) => trophy_icon($r['achievement']) . ' ' . $r['achievement']],
        ['label' => 'Tournament', 'key' => 'tournament_name'], ['label' => 'Year', 'key' => 'year'], ['label' => 'Team', 'key' => fn($r) => $r['team_name'] ?? '-'],
    ],
]);
