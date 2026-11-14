<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
$n = fn(string $l, string $k) => ['name' => $k, 'label' => $l, 'type' => 'number', 'min' => 0, 'max' => 5000, 'default' => 0, 'col' => 2, 'required' => true];
team_admin_page('team-records.php', [
    'table' => 'team_records', 'title' => 'Previous Records', 'singular' => 'Record', 'icon' => 'bi-clipboard-data',
    'intro' => 'Season-by-season results of the team.',
    'search' => ['t.season', 't.competition'], 'order' => 't.season DESC, t.id DESC',
    'fields' => [
        ['name' => 'season', 'label' => 'Season', 'required' => true, 'max' => 20, 'help' => 'e.g. 2024/25'],
        ['name' => 'competition', 'label' => 'Competition / League', 'max' => 120],
        $n('Played', 'played'), $n('Won', 'won'), $n('Drawn', 'drawn'), $n('Lost', 'lost'), $n('Goals for', 'goals_for'), $n('Goals against', 'goals_against'),
        ['name' => 'final_position', 'label' => 'Final position', 'max' => 30, 'help' => 'e.g. 1st, Champions, Quarter-final'],
        ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
    ],
    'columns' => [
        ['label' => 'Season', 'key' => 'season'], ['label' => 'Competition', 'key' => fn($r) => $r['competition'] ?? '-'],
        ['label' => 'P-W-D-L', 'key' => fn($r) => "{$r['played']}-{$r['won']}-{$r['drawn']}-{$r['lost']}"],
        ['label' => 'GF:GA', 'key' => fn($r) => "{$r['goals_for']}:{$r['goals_against']}"], ['label' => 'Position', 'key' => fn($r) => $r['final_position'] ?? '-'],
    ],
]);
