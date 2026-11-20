<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
$comp = comp_row(admin_comp_id());
$groupOpts = [];
foreach (comp_group_names($comp) as $g) { $groupOpts[$g] = $g; }
$isGroups = ($comp['format'] ?? '') === 'Groups';
$intro = $isGroups
    ? 'This competition has ' . (int)$comp['num_groups'] . ' groups of ' . (int)$comp['teams_per_group'] . ' teams. Put each team in a group. Suspend a team to remove it from the table and qualification; use point adjustment for deductions.'
    : 'League table' . ($comp['max_teams'] ? ': up to ' . (int)$comp['max_teams'] . ' teams may join' : '') . '. Suspend a team to remove it from the ranking; use point adjustment for deductions (negative) or bonuses.';
$fields = [['name' => 'team_name', 'label' => 'Team', 'type' => 'select', 'required' => true, 'options' => fn() => array_combine(comp_team_names(), comp_team_names())]];
if ($isGroups) { $fields[] = ['name' => 'group_name', 'label' => 'Group', 'type' => 'select', 'required' => true, 'options' => $groupOpts, 'help' => 'Groups are set in Competitions (number of groups).']; }
$fields[] = ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'default' => 'active', 'options' => ['active' => 'Active', 'suspended' => 'Suspended'], 'col' => 4];
$fields[] = ['name' => 'point_adjust', 'label' => 'Point adjustment', 'type' => 'number', 'min' => -99, 'max' => 99, 'default' => 0, 'col' => 4, 'help' => 'Negative = points deducted as punishment, e.g. -3.'];
$fields[] = ['name' => 'adjust_reason', 'label' => 'Reason', 'max' => 150, 'col' => 4, 'help' => 'Shown on the public table, e.g. fielded an ineligible player.'];
comp_admin_page('comp-teams.php', [
    'table' => 'comp_teams', 'title' => 'Competition Teams', 'singular' => 'Team', 'icon' => 'bi-people',
    'intro' => $intro,
    'search' => ['t.team_name', 't.group_name'], 'order' => 't.group_name, t.team_name',
    'fields' => $fields,
    'columns' => [
        ['label' => 'Team', 'key' => 'team_name', 'class' => 'fw-semibold'],
        ['label' => 'Group', 'key' => fn($r) => $r['group_name'] ?? '-'],
        ['label' => 'Status', 'key' => fn($r) => $r['status'] === 'suspended' ? 'Suspended' : 'Active'],
        ['label' => 'Pts adj.', 'key' => fn($r) => (int)$r['point_adjust'] ? ((int)$r['point_adjust'] > 0 ? '+' : '') . (int)$r['point_adjust'] : '-'],
        ['label' => 'Reason', 'key' => fn($r) => $r['adjust_reason'] ?? '-'],
    ],
    'after_save' => function (int $id, array $d, bool $isNew) { comp_team_to_list((string)($d['team_name'] ?? '')); },
    'before_save' => function (array &$d, ?array $ex) use ($comp, $isGroups) {
        $cid = (int)$comp['id']; $id = (int)($ex['id'] ?? 0);
        $d['point_adjust'] = (int)($d['point_adjust'] ?? 0);
        $q = db()->prepare('SELECT COUNT(*) FROM comp_teams WHERE competition_id = ? AND team_name = ? AND id <> ?');
        $q->execute([$cid, $d['team_name'], $id]);
        if ($q->fetchColumn()) { return 'That team is already in this competition.'; }
        if (!$isGroups) { $d['group_name'] = null; }
        $q = db()->prepare('SELECT COUNT(*) FROM comp_teams WHERE competition_id = ? AND id <> ?'); $q->execute([$cid, $id]);
        $total = (int)$q->fetchColumn();
        $max = (int)$comp['max_teams'];
        if ($max && $total >= $max) { return 'This competition is full: only ' . $max . ' teams can join. Raise the limit in Competitions or remove a team.'; }
        if ($isGroups) {
            $q = db()->prepare('SELECT COUNT(*) FROM comp_teams WHERE competition_id = ? AND group_name = ? AND id <> ?'); $q->execute([$cid, $d['group_name'], $id]);
            if ((int)$q->fetchColumn() >= (int)$comp['teams_per_group']) { return $d['group_name'] . ' is full (' . (int)$comp['teams_per_group'] . ' teams).'; }
        }
        return null;
    },
]);
