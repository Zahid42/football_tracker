<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
$self = 'calendar.php';
$isList = !in_array(get_str('action', 'list'), ['new', 'edit'], true) && !is_post();
$hasId = (int)($_GET['id'] ?? 0);

if ($isList) {
    require_login();
    $pageTitle = 'Calendar';
    $events = [];
    foreach (db()->query('SELECT * FROM calendar_events ORDER BY event_date, start_time')->fetchAll() as $r) {
        $events[] = ['id' => (int)$r['id'], 'date' => $r['event_date'], 'title' => ($r['start_time'] ? substr((string)$r['start_time'], 0, 5) . ' ' : '') . $r['title'], 'kind' => $r['event_type'],
                     'url' => url('admin/calendar.php?action=edit&id=' . (int)$r['id'])];
    }
    foreach (db()->query('SELECT m.id, m.match_date, m.team_score, m.opponent_score, m.result, o.name opp, t.name tn FROM matches m JOIN opponents o ON o.id = m.opponent_id JOIN teams t ON t.id = m.team_id')->fetchAll() as $m) {
        $events[] = ['id' => 0, 'date' => $m['match_date'], 'title' => $m['tn'] . ' ' . $m['team_score'] . '-' . $m['opponent_score'] . ' ' . $m['opp'], 'result' => $m['result'], 'url' => url('admin/match-edit.php?id=' . (int)$m['id'])];
    }
    $GLOBALS['__cal_events'] = $events;
}

$cfg = [
    'table' => 'calendar_events', 'title' => 'Calendar', 'singular' => 'Calendar Event', 'icon' => 'bi-calendar-week',
    'intro' => 'Add training, fixtures, events and reminders. They appear on the public Calendar page next to your played matches.',
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
        ['label' => 'Type', 'key' => 'event_type'],
        ['label' => 'Location', 'key' => fn($r) => $r['location'] ?? '-'],
        ['label' => 'Player', 'key' => fn($r) => $r['player_name'] ?? 'Everyone'],
    ],
];
if ($isList) { $cfg['before_list'] = function () {
    echo '<div class="ft-card p-3 mb-3"><div class="d-flex justify-content-between align-items-center mb-3"><button class="btn btn-outline-theme btn-sm" id="calPrev"><i class="bi bi-chevron-left"></i></button><h2 class="h5 mb-0 section-title" id="calTitle"></h2><div class="d-flex gap-2"><button class="btn btn-outline-theme btn-sm" id="calToday">Today</button><button class="btn btn-outline-theme btn-sm" id="calNext"><i class="bi bi-chevron-right"></i></button></div></div><div class="cal-grid" id="calGrid"></div><p class="text-muted-ft small mt-2 mb-0">Click an event to edit it, or the + on a day to add one. Green/amber/red entries are your played matches.</p></div>';
}; $cfg['scripts'] = chart_scripts(['events' => $GLOBALS['__cal_events'], 'addUrl' => url('admin/calendar.php?action=new&date=')], 'calendar.js'); }
crud_run($cfg);
