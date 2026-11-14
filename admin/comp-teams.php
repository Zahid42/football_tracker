<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
comp_admin_page('comp-teams.php', [
    'table' => 'comp_teams', 'title' => 'Competition Teams', 'singular' => 'Team', 'icon' => 'bi-people',
    'intro' => 'Teams in the table. For a Groups tournament give each team a group (A, B, C...); for a League leave the group empty.',
    'search' => ['t.team_name', 't.group_name'], 'order' => 't.group_name, t.team_name',
    'fields' => [
        ['name' => 'team_name', 'label' => 'Team', 'type' => 'select', 'required' => true, 'options' => fn() => array_combine(comp_team_names(), comp_team_names())],
        ['name' => 'group_name', 'label' => 'Group', 'max' => 30, 'help' => 'e.g. Group A'],
    ],
    'columns' => [['label' => 'Team', 'key' => 'team_name', 'class' => 'fw-semibold'], ['label' => 'Group', 'key' => fn($r) => $r['group_name'] ?? '-']],
    'before_save' => function (array &$d, ?array $ex) {
        $q = db()->prepare('SELECT COUNT(*) FROM comp_teams WHERE competition_id = ? AND team_name = ? AND id <> ?');
        $q->execute([admin_comp_id(), $d['team_name'], (int)($ex['id'] ?? 0)]);
        return $q->fetchColumn() ? 'That team is already in this competition.' : null;
    },
]);
