<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
$names = fn() => array_combine(comp_team_names(), comp_team_names());
$sc = fn(string $n, string $l) => ['name' => $n, 'label' => $l, 'type' => 'number', 'min' => 0, 'max' => 99, 'col' => 3, 'help' => 'Leave blank until played.'];
comp_admin_page('comp-matches.php', [
    'table' => 'comp_matches', 'title' => 'Fixtures & Results', 'singular' => 'Match', 'icon' => 'bi-calendar-check',
    'intro' => 'Results between any teams in this competition. Your own tracked matches tagged with this competition are added to the table automatically.',
    'search' => ['t.home_team', 't.away_team', 't.stage'], 'order' => 't.match_date DESC, t.id DESC',
    'fields' => [
        ['name' => 'home_team', 'label' => 'Team 1', 'type' => 'select', 'required' => true, 'options' => $names],
        ['name' => 'away_team', 'label' => 'Team 2', 'type' => 'select', 'required' => true, 'options' => $names],
        $sc('home_score', 'Team 1 score'), $sc('away_score', 'Team 2 score'),
        ['name' => 'match_date', 'label' => 'Date', 'type' => 'date', 'col' => 3],
        ['name' => 'stage', 'label' => 'Stage / round', 'max' => 30, 'col' => 3, 'help' => 'e.g. Group A, Matchday 3, Final'],
    ],
    'columns' => [
        ['label' => 'Date', 'key' => fn($r) => $r['match_date'] ? fmt_date($r['match_date']) : '-'],
        ['label' => 'Match', 'key' => fn($r) => $r['home_team'] . ' vs ' . $r['away_team'], 'class' => 'fw-semibold'],
        ['label' => 'Score', 'key' => fn($r) => $r['home_score'] === null ? 'not played' : $r['home_score'] . ' - ' . $r['away_score']],
        ['label' => 'Stage', 'key' => fn($r) => $r['stage'] ?? '-'],
    ],
    'before_save' => function (array &$d, ?array $ex) {
        if (mb_strtolower($d['home_team']) === mb_strtolower($d['away_team'])) { return 'Team 1 and Team 2 must be different.'; }
        if (($d['home_score'] === null) !== ($d['away_score'] === null)) { return 'Enter both scores, or leave both blank.'; }
        return null;
    },
]);
