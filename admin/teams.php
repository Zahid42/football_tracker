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
crud_run([
    'save_override' => function (array $data, ?array $existing) use ($pid, $pl) {
        $who = $pl['full_name'] ?? 'this player';
        if ($existing) {
            $r = player_update_team($pid, $existing, $data);
            return ['id' => $r['id'], 'msg' => $r['forked']
                ? 'Saved for ' . $who . ' only. Other players share this team, so they keep it unchanged and ' . $who . ' now has their own copy.'
                : 'Team saved.'];
        }
        $r = player_add_team($pid, $data);
        return ['id' => $r['id'], 'msg' => $r['reused']
            ? 'A team with this name already exists, so it was added to ' . $who . "'s profile. Other players are not affected."
            : 'Team added to ' . $who . "'s profile."];
    },
    'force_delete' => true,
    'delete_confirm' => 'Remove this team from ' . ($pl['full_name'] ?? 'this player') . "'s profile? Only THEIR matches, goals, assists and stats for this team are removed. Other players who share the team are not affected.",
    'scope' => fn() => '(t.id IN (SELECT team_id FROM player_teams WHERE player_id = ' . $pid . ') OR t.id IN (SELECT m.team_id FROM matches m JOIN match_statistics s ON s.match_id = m.id WHERE s.player_id = ' . $pid . ')'
        . ' OR (NOT EXISTS (SELECT 1 FROM player_teams x WHERE x.team_id = t.id) AND NOT EXISTS (SELECT 1 FROM matches y WHERE y.team_id = t.id)))',
    'table' => 'teams', 'title' => 'Teams', 'singular' => 'Team', 'icon' => 'bi-shield',
    'intro' => 'Teams of the player you are managing. Removing a team here only affects this player; other players who share the team keep theirs. To give a player an existing team, assign it on the Players page.',
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
        ['label' => 'Matches', 'key' => fn($r) => (string)db()->query('SELECT COUNT(*) FROM matches m JOIN match_statistics s ON s.match_id = m.id AND s.player_id = ' . (int)$GLOBALS['__tpid'] . ' WHERE m.team_id = ' . (int)$r['id'])->fetchColumn()],
        ['label' => 'Also used by', 'key' => fn($r) => (string)db()->query('SELECT COUNT(DISTINCT player_id) FROM player_teams WHERE team_id = ' . (int)$r['id'] . ' AND player_id <> ' . (int)$GLOBALS['__tpid'])->fetchColumn() . ' other player(s)'],
        ['label' => 'Status', 'key' => fn($r) => '<span class="badge bg-' . ($r['status'] === 'active' ? 'success' : 'secondary') . '">' . e(ucfirst($r['status'])) . '</span>', 'html' => true],
    ],
]);
