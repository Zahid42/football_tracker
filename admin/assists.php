<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
crud_run([
    'table' => 'assists', 'title' => 'Assist Log', 'singular' => 'Assist', 'icon' => 'bi-arrow-left-right',
    'intro' => 'Every assist made by the player you are managing: minute, type and who scored.',
    'scope' => fn() => 't.player_id = ' . admin_player_id(),
    'select' => 'SELECT t.*, m.match_date, tm.name AS team_name, o.name AS opp FROM assists t JOIN matches m ON m.id = t.match_id JOIN teams tm ON tm.id = m.team_id JOIN opponents o ON o.id = m.opponent_id',
    'search' => ['o.name', 't.assist_type', 't.goal_scorer'], 'order' => 'm.match_date DESC, t.minute',
    'fields' => [
        ['name' => 'player_id', 'label' => 'Player', 'type' => 'select', 'required' => true, 'default' => admin_player_id(), 'options' => fn() => player_options(false)],
        ['name' => 'match_id', 'label' => 'Match', 'type' => 'select', 'required' => true, 'col' => 12, 'options' => fn() => match_options()],
        ['name' => 'minute', 'label' => 'Minute', 'type' => 'number', 'min' => 0, 'max' => 150],
        ['name' => 'assist_type', 'label' => 'Assist Type', 'type' => 'select', 'required' => true, 'options' => ['Through Ball', 'Cross', 'Corner', 'Free Kick', 'Cut Back', 'Pass', 'Set Piece', 'Other']],
        ['name' => 'goal_scorer', 'label' => 'Goal Scorer', 'max' => 100],
        ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'max' => 255],
    ],
    'columns' => [
        ['label' => 'Match', 'key' => fn($r) => fmt_date($r['match_date']) . ' - ' . $r['team_name'] . ' vs ' . $r['opp']],
        ['label' => 'Min', 'key' => fn($r) => $r['minute'] !== null ? $r['minute'] . "'" : '-'], ['label' => 'Type', 'key' => 'assist_type'],
        ['label' => 'Scorer', 'key' => fn($r) => $r['goal_scorer'] ?? '-'], ['label' => 'Description', 'key' => fn($r) => $r['description'] ?? ''],
    ],
]);
