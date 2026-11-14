<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
$self = 'calendar.php';
$isList = !in_array(get_str('action', 'list'), ['new', 'edit'], true) && !is_post();
$hasId = (int)($_GET['id'] ?? 0);

if ($isList) {
    require_login();
    $pageTitle = 'Calendar';
}

$cfg = [
    'table' => 'calendar_events', 'title' => 'Calendar', 'singular' => 'Calendar Event', 'icon' => 'bi-calendar-week',
    'intro' => 'Add training, fixtures, events and reminders. They show on the public Calendar page row by row (type "Match" also shows under Fixtures on the homepage).',
    'search' => ['t.title', 't.location'], 'order' => 't.event_date DESC, t.start_time',
    'select' => 'SELECT t.*, p.full_name AS player_name FROM calendar_events t LEFT JOIN players p ON p.id = t.player_id',
    'fields' => [
        ['name' => 'title', 'label' => 'Title', 'required' => true, 'max' => 150, 'col' => 12],
        ['name' => 'event_type', 'label' => 'Type', 'type' => 'select', 'required' => true, 'default' => 'Event', 'options' => ['Match', 'Training', 'Event', 'Reminder', 'Other']],
        ['name' => 'event_date', 'label' => 'Date', 'type' => 'date', 'required' => true, 'default' => (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date'] ?? '')) ? $_GET['date'] : date('Y-m-d'))],
        ['name' => 'start_time', 'label' => 'Start time', 'type' => 'time'],
        ['name' => 'end_time', 'label' => 'End time', 'type' => 'time'],
        ['name' => 'location', 'label' => 'Location', 'max' => 150],
        ['name' => 'player_id', 'label' => 'For player', 'type' => 'select', 'nullable' => true, 'options' => fn() => player_options(false), 'help' => 'Leave blank to show it for every player.'],
        ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
    ],
    'columns' => [
        ['label' => 'Date', 'key' => fn($r) => fmt_date($r['event_date'])],
        ['label' => 'Time', 'key' => fn($r) => $r['start_time'] ? substr((string)$r['start_time'], 0, 5) . ($r['end_time'] ? '-' . substr((string)$r['end_time'], 0, 5) : '') : '-'],
        ['label' => 'Title', 'key' => 'title', 'class' => 'fw-semibold'],
        ['label' => 'Type', 'key' => fn($r) => '<span class="ri-pill k-' . e($r['event_type'] === 'Match' ? 'fixture' : strtolower($r['event_type'])) . '">' . e($r['event_type'] === 'Match' ? 'Fixture' : $r['event_type']) . '</span>', 'html' => true],
        ['label' => 'Location', 'key' => fn($r) => $r['location'] ?? '-'],
        ['label' => 'Player', 'key' => fn($r) => $r['player_name'] ?? 'Everyone'],
    ],
];
crud_run($cfg);
