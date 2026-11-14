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
        ['name' => 'event_type', 'label' => 'Type', 'type' => 'select', 'required' => true, 'default' => 'Event', 'options' => ['Match', 'Training', 'Event', 'Reminder', 'Other']],
        ['name' => 'title', 'label' => 'Title', 'max' => 150, 'help' => 'For a Match the title is generated from the two teams.'],
        ['name' => 'home_team', 'label' => 'Team 1', 'type' => 'select', 'nullable' => true, 'options' => fn() => array_combine(calendar_team_names(), calendar_team_names())],
        ['name' => 'away_team', 'label' => 'Team 2 (opponent)', 'type' => 'select', 'nullable' => true, 'options' => fn() => array_combine(calendar_team_names(), calendar_team_names())],
        ['name' => 'event_date', 'label' => 'Date', 'type' => 'date', 'required' => true, 'default' => (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date'] ?? '')) ? $_GET['date'] : date('Y-m-d'))],
        ['name' => 'start_time', 'label' => 'Start time', 'type' => 'time'],
        ['name' => 'end_time', 'label' => 'End time', 'type' => 'time'],
        ['name' => 'location', 'label' => 'Location', 'max' => 150],
        ['name' => 'player_id', 'label' => 'For player', 'type' => 'select', 'nullable' => true, 'options' => fn() => player_options(false), 'help' => 'Leave blank to show it for every player.'],
        ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
    ],
    'before_save' => function (array &$d, ?array $existing) {
        if (($d['event_type'] ?? '') === 'Match') {
            if (empty($d['home_team']) || empty($d['away_team'])) { return 'Pick both teams for a Match.'; }
            if (mb_strtolower($d['home_team']) === mb_strtolower($d['away_team'])) { return 'Team 1 and Team 2 must be different.'; }
            $d['title'] = $d['home_team'] . ' vs ' . $d['away_team'];
        } else {
            $d['home_team'] = $d['away_team'] = null;
            if (empty($d['title'])) { return 'Title is required.'; }
        }
        return null;
    },
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
if (!$isList) { $cfg['scripts'] = '<script>(function(){var t=document.getElementById("f_event_type");if(!t)return;var a=document.getElementById("f_home_team"),b=document.getElementById("f_away_team"),ti=document.getElementById("f_title");if(!a||!b||!ti)return;var wa=a.closest("[class*=col-]"),wb=b.closest("[class*=col-]");function upd(){var m=t.value==="Match";wa.style.display=wb.style.display=m?"":"none";a.required=b.required=m;ti.readOnly=m;if(m){ti.placeholder="Auto: Team 1 vs Team 2";if(a.value&&b.value)ti.value=a.value+" vs "+b.value;else ti.value="";}else{ti.placeholder="";}}t.addEventListener("change",upd);a.addEventListener("change",upd);b.addEventListener("change",upd);upd();})();</script>'; }
crud_run($cfg);
