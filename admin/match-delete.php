<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_login();
if (!is_post()) { redirect('admin/matches.php'); }
csrf_require();
$m = get_match((int)post('id'));
if ($m) {
    db()->prepare('DELETE FROM matches WHERE id = ?')->execute([$m['id']]);
    delete_upload($m['photo']); delete_upload($m['team_photo']);
    flash_set('success', 'Match deleted.');
}
redirect('admin/matches.php');
