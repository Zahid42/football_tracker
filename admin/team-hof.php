<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
team_admin_page('team-hof.php', [
    'table' => 'team_hof', 'title' => 'Hall of Fame', 'singular' => 'Legend', 'icon' => 'bi-stars',
    'intro' => 'The all-time greats of the team.',
    'search' => ['t.name', 't.role'], 'order' => 't.inducted_year DESC, t.id DESC',
    'fields' => [
        ['name' => 'name', 'label' => 'Name', 'required' => true, 'max' => 100],
        ['name' => 'role', 'label' => 'Role', 'max' => 60, 'help' => 'e.g. Captain, Top scorer, Coach'],
        ['name' => 'years_active', 'label' => 'Years with the team', 'max' => 30, 'help' => 'e.g. 2018-2024'],
        ['name' => 'inducted_year', 'label' => 'Inducted in', 'type' => 'number', 'min' => 1850, 'max' => 2100, 'default' => date('Y')],
        ['name' => 'photo', 'label' => 'Photo', 'type' => 'image', 'dir' => 'team-people'],
        ['name' => 'description', 'label' => 'Why they are a legend', 'type' => 'textarea'],
    ],
    'columns' => [
        ['label' => 'Photo', 'key' => fn($r) => thumb($r['photo']), 'html' => true], ['label' => 'Name', 'key' => 'name'], ['label' => 'Role', 'key' => fn($r) => $r['role'] ?? '-'],
        ['label' => 'Years', 'key' => fn($r) => $r['years_active'] ?? '-'], ['label' => 'Inducted', 'key' => fn($r) => $r['inducted_year'] ?? '-'],
    ],
]);
