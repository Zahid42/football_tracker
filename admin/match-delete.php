<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_login();
if (!is_post()) { redirect('admin/matches.php'); }
csrf_require();
$bulk = isset($_POST['ids']);
$ids = $bulk ? array_values(array_unique(array_filter(array_map('intval', (array)$_POST['ids'])))) : [(int)post('id')];
if ($bulk && !$ids) { flash_set('info', 'Tick at least one match first.'); redirect('admin/matches.php'); }
$done = 0; $kept = 0;
foreach ($ids as $mid) {
    $m = get_match($mid);
    if (!$m) { continue; }
    $pid = admin_player_id();
    $has = db()->prepare('SELECT COUNT(*) FROM match_statistics WHERE match_id = ? AND player_id = ?'); $has->execute([$m['id'], $pid]); $has = (int)$has->fetchColumn();
    $all = db()->prepare('SELECT COUNT(*) FROM match_statistics WHERE match_id = ?'); $all->execute([$m['id']]); $all = (int)$all->fetchColumn();
    if ($has) {
        $gone = player_remove_match($pid, (int)$m['id']);
        if ($gone) { $done++; } else { $kept++; }
        if (!$bulk) { flash_set('success', $gone ? 'Match deleted.' : 'Match removed from this player. Other players who played in it keep it.'); }
    } elseif ($all === 0) {
        db()->prepare('DELETE FROM matches WHERE id = ?')->execute([$m['id']]);
        delete_upload($m['photo']); delete_upload($m['team_photo']);
        $done++;
        if (!$bulk) { flash_set('success', 'Match deleted.'); }
    } else {
        $kept++;
        if (!$bulk) { flash_set('warning', 'This match belongs to other players; nothing was deleted.'); }
    }
}
if ($bulk) {
    if ($done) { flash_set('success', $done . ' match' . ($done === 1 ? '' : 'es') . ' deleted.'); }
    if ($kept) { flash_set('warning', $kept . ' kept: other players also played in ' . ($kept === 1 ? 'it' : 'them') . ' (removed from the player you are managing only).'); }
}
redirect('admin/matches.php');
