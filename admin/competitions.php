<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
crud_run([
    'table' => 'competitions', 'title' => 'Competitions', 'singular' => 'Competition', 'icon' => 'bi-award',
    'intro' => 'Tournaments, leagues and friendlies you play in. Manage tables under Competition Tables.',
    'select' => 'SELECT t.*, s.name AS season_name FROM competitions t LEFT JOIN seasons s ON s.id = t.season_id',
    'search' => ['t.name', 't.organizer'], 'order' => 't.name',
    'fields' => [
        ['name' => 'name', 'label' => 'Competition Name', 'required' => true, 'max' => 150],
        ['name' => 'season_id', 'label' => 'Season', 'type' => 'select', 'nullable' => true, 'options' => fn() => lookup('SELECT id, name FROM seasons ORDER BY name DESC')],
        ['name' => 'type', 'label' => 'Competition Type', 'type' => 'select', 'required' => true, 'options' => ['Tournament', 'League', 'Friendly', 'Training', 'Other']],
        ['name' => 'format', 'label' => 'Table format', 'type' => 'select', 'required' => true, 'default' => 'League', 'options' => ['League', 'Groups'], 'help' => 'League = one table. Groups = group-stage tournament (A, B, C...).'],
        ['name' => 'max_teams', 'label' => 'League: how many teams will join', 'type' => 'number', 'min' => 2, 'max' => 64, 'col' => 4, 'help' => 'Maximum teams allowed in a League table. Leave blank for no limit.'],
        ['name' => 'num_groups', 'label' => 'Groups: how many groups', 'type' => 'number', 'min' => 2, 'max' => 16, 'col' => 4, 'help' => 'Group A, Group B... (Groups format only).'],
        ['name' => 'teams_per_group', 'label' => 'Groups: teams in each group', 'type' => 'number', 'min' => 2, 'max' => 12, 'col' => 4, 'help' => 'Capacity of every group.'],
        ['name' => 'qualify_per_group', 'label' => 'Teams qualifying from each group', 'type' => 'number', 'min' => 1, 'max' => 8, 'default' => 2, 'col' => 4, 'help' => 'They go into the knockout bracket.'],
        ['name' => 'knockout', 'label' => 'Has a knockout stage', 'type' => 'checkbox', 'col' => 4, 'help' => 'Enables the Knockout bracket tab (built from the table results).'],
        ['name' => 'ko_teams', 'label' => 'League: top teams into knockout', 'type' => 'number', 'min' => 2, 'max' => 64, 'col' => 4, 'help' => 'League format only, e.g. 4 = semi-finals.'],
        ['name' => 'organizer', 'label' => 'Organizer', 'max' => 150],
        ['name' => 'logo', 'label' => 'Logo', 'type' => 'image', 'dir' => 'competitions'],
        ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
    ],
    'before_save' => function (array &$d, ?array $ex) {
        $d['qualify_per_group'] = (int)($d['qualify_per_group'] ?? 2) ?: 2;
        if ($d['format'] === 'Groups') {
            if (!$d['num_groups'] || !$d['teams_per_group']) { return 'For a Groups competition set how many groups and how many teams are in each group.'; }
            if ($d['qualify_per_group'] > $d['teams_per_group']) { return 'Qualifying teams per group cannot be more than the teams in a group.'; }
            $d['max_teams'] = (int)$d['num_groups'] * (int)$d['teams_per_group'];
            $d['ko_teams'] = null;
        } else {
            $d['num_groups'] = $d['teams_per_group'] = null;
            if ($d['knockout'] && !$d['ko_teams']) { return 'Set how many top teams go into the knockout stage.'; }
            if ($d['max_teams'] && $d['ko_teams'] && $d['ko_teams'] > $d['max_teams']) { return 'Knockout teams cannot exceed the number of teams in the league.'; }
        }
        if ($ex) {
            $cnt = db()->prepare('SELECT COUNT(*) FROM comp_teams WHERE competition_id = ?'); $cnt->execute([(int)$ex['id']]);
            if ($d['max_teams'] && (int)$cnt->fetchColumn() > (int)$d['max_teams']) { return 'This competition already has more teams than that limit. Remove teams first.'; }
        }
        return null;
    },
    'columns' => [
        ['label' => 'ID', 'key' => fn($r) => $r['public_code'] ?? '-'],
        ['label' => 'Logo', 'key' => fn($r) => thumb($r['logo']), 'html' => true],
        ['label' => 'Name', 'key' => 'name'], ['label' => 'Type', 'key' => 'type'], ['label' => 'Table', 'key' => fn($r) => $r['format'] === 'Groups' ? 'Groups ' . (int)$r['num_groups'] . 'x' . (int)$r['teams_per_group'] : 'League' . ($r['max_teams'] ? ' (' . (int)$r['max_teams'] . ')' : '') . ($r['knockout'] ? ' + KO' : '')],
        ['label' => 'Season', 'key' => fn($r) => $r['season_name'] ?? '-'], ['label' => 'Organizer', 'key' => fn($r) => $r['organizer'] ?? '-'],
    ],
]);
