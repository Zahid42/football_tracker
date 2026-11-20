<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_login();
ft_team_schema_ensure();
$sqTid = admin_team_id();

/** How many players stand on the pitch for this team (5-a-side ... 11-a-side). */
if ($sqTid && is_post() && post('op') === 'pitch_size') {
    csrf_require();
    $n = (int)post('pitch_size');
    if (in_array($n, PITCH_SIZES, true)) {
        try { ft_pitch_size_ensure(true); db()->prepare('UPDATE teams SET pitch_size = ? WHERE id = ?')->execute([$n, $sqTid]); }
        catch (Throwable $e) { flash_set('danger', 'Could not save the pitch size: the database refused the update.'); redirect('admin/team-squad.php'); }
        $flag = (int)db()->query('SELECT COUNT(*) FROM team_squad WHERE team_id = ' . $sqTid . ' AND is_starter = 1')->fetchColumn();
        flash_set('success', 'The pitch now holds ' . $n . ' players.' . ($flag > $n ? ' ' . $flag . ' players are ticked as starters, so only the first ' . $n . ' (balanced by position) are shown; untick the extra ones to choose yourself.' : ''));
    } else { flash_set('danger', 'Choose a pitch size from 5 to 11.'); }
    redirect('admin/team-squad.php');
}

/** Give every squad spot that is only a typed name a real player profile (linked to a same-name player when one exists). */
if ($sqTid && is_post() && post('op') === 'make_profiles') {
    csrf_require();
    $ids = db()->query('SELECT id FROM team_squad WHERE team_id = ' . $sqTid . ' AND player_id IS NULL')->fetchAll(PDO::FETCH_COLUMN);
    $made = 0; foreach ($ids as $sid) { if (squad_make_profile((int)$sid)) { $made++; } }
    flash_set($made ? 'success' : 'warning', $made . ' squad member' . ($made === 1 ? '' : 's') . ' now ' . ($made === 1 ? 'has' : 'have') . ' a player profile, and this team is on their profiles.');
    redirect('admin/team-squad.php');
}

team_admin_page('team-squad.php', [
    'before_list' => function () use ($sqTid) {
        $size = team_pitch_size($sqTid);
        $noProf = (int)db()->query('SELECT COUNT(*) FROM team_squad WHERE team_id = ' . (int)$sqTid . ' AND player_id IS NULL')->fetchColumn();
        if ($noProf) {
            echo '<form method="post" class="ft-card p-3 mb-3 d-flex flex-wrap gap-2 align-items-center" style="border-color:var(--accent)">' . csrf_field() . '<input type="hidden" name="op" value="make_profiles">'
               . '<span><i class="bi bi-person-exclamation"></i> <b>' . $noProf . '</b> squad member' . ($noProf === 1 ? ' is' : 's are') . ' only a name, with no player profile.</span>'
               . '<button class="btn btn-accent btn-sm ms-md-auto" onclick="return confirm(\'Create a player profile for each of these squad members? Names that already match a player are linked instead.\')"><i class="bi bi-person-plus"></i> Make them players</button></form>';
        }
        echo '<form method="post" class="ft-card p-3 mb-3 d-flex flex-wrap gap-2 align-items-center">' . csrf_field() . '<input type="hidden" name="op" value="pitch_size">'
           . '<label class="fw-semibold mb-0" for="pitchSize"><i class="bi bi-people"></i> Players allowed on the pitch</label>'
           . '<select class="form-select w-auto" id="pitchSize" name="pitch_size">';
        foreach (PITCH_SIZES as $n) { echo '<option value="' . $n . '"' . ($n === $size ? ' selected' : '') . '>' . $n . ' (' . $n . '-a-side)</option>'; }
        echo '</select><button class="btn btn-outline-theme btn-sm">Save</button><span class="small text-muted-ft">Used for the team pitch view and the Starting XI limit.</span></form>';
    },
    'table' => 'team_squad', 'title' => 'Current Squad', 'singular' => 'Squad Player', 'icon' => 'bi-people',
    'intro' => 'Up to ' . SQUAD_MAX . ' players per team. Set how many players are allowed on the pitch, then tick "Starting XI" for that many: they stand on the pitch by position (if none are ticked, the pitch is picked automatically).',
    'search' => ['t.name'], 'order' => 't.shirt_number IS NULL, t.shirt_number, t.name',
    'before_save' => function (array &$data, ?array $existing) {
        $tid = $existing ? (int)$existing['team_id'] : admin_team_id();
        if (!$existing) {
            $n = db()->prepare('SELECT COUNT(*) FROM team_squad WHERE team_id = ?'); $n->execute([$tid]);
            if ((int)$n->fetchColumn() >= SQUAD_MAX) { return 'The squad is full (' . SQUAD_MAX . ' players). Remove someone first.'; }
        }
        if (!empty($data['is_starter'])) {
            $c = db()->prepare('SELECT COUNT(*) FROM team_squad WHERE team_id = ? AND is_starter = 1 AND id <> ?'); $c->execute([$tid, (int)($existing['id'] ?? 0)]);
            $lim = team_pitch_size($tid);
            if ((int)$c->fetchColumn() >= $lim) { return 'The pitch is set to ' . $lim . ' players and ' . $lim . ' are already ticked as starters. Untick someone first, or raise the pitch size above.'; }
        }
        if (!empty($data['shirt_number'])) {
            $d = db()->prepare('SELECT COUNT(*) FROM team_squad WHERE team_id = ? AND shirt_number = ? AND id <> ?'); $d->execute([$tid, $data['shirt_number'], (int)($existing['id'] ?? 0)]);
            if ((int)$d->fetchColumn() > 0) { return 'Shirt number ' . $data['shirt_number'] . ' is already taken in this squad.'; }
        }
        return null;
    },
    'after_save' => function (int $id, array $data, bool $isNew) {
        squad_make_profile($id);   // a squad spot is always a real player: linked to the same-name player or given a new profile
        if (!empty($data['is_captain'])) { db()->prepare('UPDATE team_squad SET is_captain = 0 WHERE team_id = ? AND id <> ?')->execute([db()->query('SELECT team_id FROM team_squad WHERE id = ' . $id)->fetchColumn(), $id]); }
    },
    'fields' => [
        ['name' => 'name', 'label' => 'Player Name', 'required' => true, 'max' => 100],
        ['name' => 'shirt_number', 'label' => 'Shirt Number', 'type' => 'number', 'min' => 1, 'max' => 99],
        ['name' => 'position', 'label' => 'Position', 'type' => 'select', 'nullable' => true, 'options' => fn() => POSITION_NAMES],
        ['name' => 'is_starter', 'label' => 'Starting XI (shown on the pitch)', 'type' => 'checkbox'],
        ['name' => 'is_captain', 'label' => 'Captain', 'type' => 'checkbox'],
        ['name' => 'player_id', 'label' => 'Linked website player (optional)', 'type' => 'select', 'nullable' => true, 'options' => fn() => player_options(false), 'help' => 'Leave empty to create a player profile from the name automatically. Links this squad spot to a registered player\'s profile.'],
        ['name' => 'photo', 'label' => 'Photo', 'type' => 'image', 'dir' => 'team-people'],
        ['name' => 'notes', 'label' => 'Note', 'max' => 255],
    ],
    'columns' => [
        ['label' => 'Photo', 'key' => fn($r) => thumb($r['photo']), 'html' => true],
        ['label' => '#', 'key' => fn($r) => $r['shirt_number'] ?? '-'],
        ['label' => 'Player', 'key' => fn($r) => $r['name'] . ($r['is_captain'] ? ' (C)' : '')],
        ['label' => 'Position', 'key' => fn($r) => position_name($r['position'])],
        ['label' => 'XI', 'key' => fn($r) => $r['is_starter'] ? 'Starter' : '-'],
    ],
]);
