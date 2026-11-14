<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
$adm = require_login();
if (is_post() && post('op') === 'delete') {
    csrf_require();
    $tid = (int)post('id');
    $force = post('force') === '1';
    $n = db()->prepare('SELECT COUNT(*) FROM matches WHERE team_id = ?'); $n->execute([$tid]); $n = (int)$n->fetchColumn();
    $row = db()->prepare('SELECT * FROM teams WHERE id = ?'); $row->execute([$tid]); $row = $row->fetch();
    if ($row && $n > 0 && !$force) {
        flash_set('warning', $row['name'] . ' is used by ' . $n . ' match(es); nothing was deleted.');
    } elseif ($row) {
        db()->beginTransaction();
        db()->prepare('DELETE FROM matches WHERE team_id = ?')->execute([$tid]);   // squad, stats, goals, assists cascade
        db()->prepare('DELETE FROM teams WHERE id = ?')->execute([$tid]);
        db()->commit();
        delete_upload($row['logo']);
        flash_set('success', 'Team deleted' . ($n ? " together with $n match(es)." : '.'));
    }
    redirect('admin/teams.php');
}
crud_run([
    'force_delete' => true, 'delete_confirm' => 'Delete this team? Any matches played for it will also be deleted (with their squads, goals and assists). This cannot be undone.',
    'table' => 'teams', 'title' => 'Teams', 'singular' => 'Team', 'icon' => 'bi-shield',
    'intro' => 'Clubs and teams. Assign the teams a player played for on the Players page.',
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
        ['label' => 'Matches', 'key' => fn($r) => (string)db()->query('SELECT COUNT(*) FROM matches WHERE team_id = ' . (int)$r['id'])->fetchColumn()],
        ['label' => 'Status', 'key' => fn($r) => '<span class="badge bg-' . ($r['status'] === 'active' ? 'success' : 'secondary') . '">' . e(ucfirst($r['status'])) . '</span>', 'html' => true],
    ],
]);
