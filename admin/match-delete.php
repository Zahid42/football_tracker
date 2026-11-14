<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_login();
if (!is_post()) { redirect('admin/matches.php'); }
csrf_require();
$m = get_match((int)post('id'));
if ($m) {
    $pid = admin_player_id();
    $has = db()->prepare('SELECT COUNT(*) FROM match_statistics WHERE match_id = ? AND player_id = ?'); $has->execute([$m['id'], $pid]); $has = (int)$has->fetchColumn();
    $all = db()->prepare('SELECT COUNT(*) FROM match_statistics WHERE match_id = ?'); $all->execute([$m['id']]); $all = (int)$all->fetchColumn();
    if ($has) {
        $gone = player_remove_match($pid, (int)$m['id']);
        flash_set('success', $gone ? 'Match deleted.' : 'Match removed from this player. Other players who played in it keep it.');
    } elseif ($all === 0) {
        db()->prepare('DELETE FROM matches WHERE id = ?')->execute([$m['id']]);
        delete_upload($m['photo']); delete_upload($m['team_photo']);
        flash_set('success', 'Match deleted.');
    } else {
        flash_set('warning', 'This match belongs to other players; nothing was deleted.');
    }
}
redirect('admin/matches.php');
