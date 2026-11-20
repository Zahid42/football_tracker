<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
$adm = require_login();
$pid = admin_player_id();
$pl  = get_player($pid);
$GLOBALS['__tpid'] = $pid;
if (is_post() && post('op') === 'delete') {
    csrf_require();
    $tid = (int)post('id');
    $row = db()->prepare('SELECT * FROM teams WHERE id = ?'); $row->execute([$tid]); $row = $row->fetch();
    if ($row) {
        $r = player_remove_team($pid, $tid);
        flash_set('success', $row['name'] . ' was removed from ' . ($pl['full_name'] ?? 'this player') . "'s profile"
            . ($r['mine'] ? ' (' . $r['mine'] . ' of their match record(s) removed)' : '') . '. Other players were not affected'
            . ($r['team_deleted'] ? '; nobody else used the team, so it was deleted completely.' : '.'));
    }
    redirect('admin/teams.php');
}
/** Teams listed here: the managed player's teams, teams nobody uses yet, and every team that takes part in a competition. */
$teamScope = function () use ($pid): string {
    $sql = '(t.id IN (SELECT team_id FROM player_teams WHERE player_id = ' . $pid . ') OR t.id IN (SELECT m.team_id FROM matches m JOIN match_statistics s ON s.match_id = m.id WHERE s.player_id = ' . $pid . ')'
        . ' OR (NOT EXISTS (SELECT 1 FROM player_teams x WHERE x.team_id = t.id) AND NOT EXISTS (SELECT 1 FROM matches y WHERE y.team_id = t.id)))';
    if (db()->query("SHOW TABLES LIKE 'comp_teams'")->fetch()) { $sql = '(' . $sql . ') OR LOWER(t.name) IN (SELECT LOWER(team_name) FROM comp_teams)'; }
    return $sql;
};
if (is_post() && post('op') === 'bulk_delete') {   // tick teams: take them off this player's profile (a team nobody uses any more is deleted)
    csrf_require();
    $gone = 0; $removed = 0;
    foreach (array_unique(array_map('intval', (array)($_POST['ids'] ?? []))) as $tid) {
        $in = db()->prepare('SELECT COUNT(*) FROM teams t WHERE t.id = ? AND (' . $teamScope() . ')'); $in->execute([$tid]);
        if ($tid > 0 && (int)$in->fetchColumn()) { $r = player_remove_team($pid, $tid); $gone++; if ($r['team_deleted']) { $removed++; } }
    }
    flash_set($gone ? 'success' : 'info', $gone ? $gone . ' team' . ($gone === 1 ? '' : 's') . ' removed from ' . ($pl['full_name'] ?? 'this player') . "'s profile" . ($removed ? '; ' . $removed . ' used by nobody else and deleted completely' : '') . '.' : 'Nothing was selected.');
    redirect('admin/teams.php');
}
crud_run([
    'save_override' => function (array $data, ?array $existing) use ($pid, $pl) {
        $who = $pl['full_name'] ?? 'this player';
        if ($existing) {
            $r = player_update_team($pid, $existing, $data);
            return ['id' => $r['id'], 'msg' => $r['forked']
                ? 'Saved for ' . $who . ' only. Other players share this team, so they keep it unchanged and ' . $who . ' now has their own copy.'
                : 'Team saved.'];
        }
        $r = player_add_team($pid, $data, false);   // new teams are NOT attached to the selected player
        return ['id' => $r['id'], 'msg' => $r['reused']
            ? 'A team with this name already exists, so nothing new was created. It is not linked to any player; assign it on the Players page.'
            : 'Team created and not linked to any player. Assign it to players on the Players page.'];
    },
    'force_delete' => true,
    'delete_confirm' => 'Remove this team from ' . ($pl['full_name'] ?? 'this player') . "'s profile? Only THEIR matches, goals, assists and stats for this team are removed. Other players who share the team are not affected.",
    'scope' => $teamScope,
    'table' => 'teams', 'title' => 'Teams', 'singular' => 'Team', 'icon' => 'bi-shield',
    'intro' => 'Teams of the player you are managing and every team that plays in a competition. Removing a team here only affects this player; other players who share the team keep theirs. New teams are created on their own and are not attached to the player you are managing; assign a team to a player on the Players page.',
    'search' => ['t.name', 't.short_name'], 'order' => 't.name',
    'fields' => [
        ['name' => 'name', 'label' => 'Team Name', 'required' => true, 'max' => 100],
        ['name' => 'short_name', 'label' => 'Short Name', 'max' => 10],
        ['name' => 'logo', 'label' => 'Logo', 'type' => 'image', 'dir' => 'teams'],
        ['name' => 'founded_year', 'label' => 'Founded (year)', 'type' => 'number', 'min' => 1850, 'max' => 2100],
        ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['active' => 'Active', 'inactive' => 'Inactive'], 'default' => 'active'],
        ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
    ],
    'columns' => [
        ['label' => 'Logo', 'key' => fn($r) => thumb($r['logo']), 'html' => true],
        ['label' => 'Team', 'key' => fn($r) => $r['name'] . ($r['short_name'] ? ' (' . $r['short_name'] . ')' : '')],
        ['label' => 'ID', 'key' => fn($r) => '<a class="id-chip" href="' . e(team_url_by_id((int)$r['id'])) . '" target="_blank" rel="noopener">' . e((string)$r['public_code']) . '</a>', 'html' => true],
        ['label' => 'Profile', 'key' => fn($r) => '<a class="btn btn-sm btn-outline-theme" href="' . e(url('admin/team-squad.php?team=' . (int)$r['id'])) . '"><i class="bi bi-person-badge"></i> Squad, kits &amp; more</a>', 'html' => true],
        ['label' => 'Matches', 'key' => fn($r) => (string)db()->query('SELECT COUNT(*) FROM matches m JOIN match_statistics s ON s.match_id = m.id AND s.player_id = ' . (int)$GLOBALS['__tpid'] . ' WHERE m.team_id = ' . (int)$r['id'])->fetchColumn()],
        ['label' => 'Also used by', 'key' => fn($r) => (string)db()->query('SELECT COUNT(DISTINCT player_id) FROM player_teams WHERE team_id = ' . (int)$r['id'] . ' AND player_id <> ' . (int)$GLOBALS['__tpid'])->fetchColumn() . ' other player(s)'],
        ['label' => 'Status', 'key' => fn($r) => '<span class="badge bg-' . ($r['status'] === 'active' ? 'success' : 'secondary') . '">' . e(ucfirst($r['status'])) . '</span>', 'html' => true],
    ],
]);
