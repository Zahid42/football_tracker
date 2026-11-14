<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
team_admin_page('team-achievements.php', [
    'table' => 'team_achievements', 'title' => 'Team Achievements', 'singular' => 'Achievement', 'icon' => 'bi-trophy',
    'search' => ['t.title', 't.competition'], 'order' => 't.year DESC, t.id DESC',
    'fields' => [
        ['name' => 'title', 'label' => 'Title', 'required' => true, 'max' => 150],
        ['name' => 'achievement_type', 'label' => 'Type', 'type' => 'select', 'required' => true, 'options' => ['Trophy', 'Runner-up', 'Award', 'Record', 'Milestone'], 'default' => 'Trophy'],
        ['name' => 'year', 'label' => 'Year', 'type' => 'number', 'min' => 1850, 'max' => 2100, 'default' => date('Y')],
        ['name' => 'competition', 'label' => 'Competition', 'max' => 120],
        ['name' => 'image', 'label' => 'Image', 'type' => 'image', 'dir' => 'team-awards'],
        ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
    ],
    'columns' => [
        ['label' => 'Image', 'key' => fn($r) => thumb($r['image']), 'html' => true], ['label' => 'Title', 'key' => 'title'],
        ['label' => 'Type', 'key' => 'achievement_type'], ['label' => 'Year', 'key' => fn($r) => $r['year'] ?? '-'], ['label' => 'Competition', 'key' => fn($r) => $r['competition'] ?? '-'],
    ],
]);
