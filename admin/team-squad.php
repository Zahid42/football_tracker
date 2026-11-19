<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_login();
ft_team_schema_ensure();
$sqTid = admin_team_id();

/** Add players picked from the overall player list straight into this team's squad. */
if ($sqTid && is_post() && post('op') === 'add_players') {
    csrf_require();
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['pick'] ?? [])))));
    $pdo = db();
    $have = (int)$pdo->query('SELECT COUNT(*) FROM team_squad WHERE team_id = ' . $sqTid)->fetchColumn();
    $added = 0; $dup = 0; $full = 0; $noNum = 0;
    $get = $pdo->prepare("SELECT full_name, photo, preferred_position, jersey_number FROM players WHERE id = ? AND approval_status = 'approved'");
    $in  = $pdo->prepare('SELECT COUNT(*) FROM team_squad WHERE team_id = ? AND player_id = ?');
    $num = $pdo->prepare('SELECT COUNT(*) FROM team_squad WHERE team_id = ? AND shirt_number = ?');
    $ins = $pdo->prepare('INSERT INTO team_squad (team_id, shirt_number, name, position, photo, player_id) VALUES (?,?,?,?,?,?)');
    foreach ($ids as $pid) {
        $get->execute([$pid]); $pl = $get->fetch(); if (!$pl) { continue; }
        $in->execute([$sqTid, $pid]); if ((int)$in->fetchColumn()) { $dup++; continue; }
        if ($have >= SQUAD_MAX) { $full++; continue; }
        $shirt = $pl['jersey_number'] !== null ? (int)$pl['jersey_number'] : null;
        if ($shirt) { $num->execute([$sqTid, $shirt]); if ((int)$num->fetchColumn()) { $shirt = null; $noNum++; } }
        $ins->execute([$sqTid, $shirt ?: null, $pl['full_name'], $pl['preferred_position'], $pl['photo'], $pid]);
        $have++; $added++;
    }
    $msg = $added . ' player' . ($added === 1 ? '' : 's') . ' added to the squad.';
    if ($noNum) { $msg .= ' ' . $noNum . ' had a shirt number already taken, so it was left empty.'; }
    if ($dup) { $msg .= ' ' . $dup . ' already in the squad.'; }
    if ($full) { $msg .= ' ' . $full . ' not added: the squad is full (' . SQUAD_MAX . ').'; }
    flash_set($added ? 'success' : 'warning', $msg);
    redirect('admin/team-squad.php');
}

team_admin_page('team-squad.php', [
    'before_list' => function () use ($sqTid) {
        $st = db()->prepare("SELECT p.id, p.full_name, p.nickname, p.preferred_position, p.jersey_number, p.public_code, t.name AS team_name FROM players p LEFT JOIN teams t ON t.id = p.current_team_id
            WHERE p.approval_status = 'approved' AND p.id NOT IN (SELECT player_id FROM team_squad WHERE team_id = ? AND player_id IS NOT NULL) ORDER BY p.full_name");
        $st->execute([$sqTid]); $list = $st->fetchAll();
        $cnt = (int)db()->query('SELECT COUNT(*) FROM team_squad WHERE team_id = ' . (int)$sqTid)->fetchColumn();
        echo '<div class="ft-card p-3 mb-3"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2"><h2 class="h6 mb-0"><i class="bi bi-person-plus"></i> Add players from the player list</h2>'
           . '<button class="btn btn-accent btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#sqPicker">Choose players (' . count($list) . ' available)</button></div>'
           . '<div class="collapse" id="sqPicker"><form method="post" class="mt-3">' . csrf_field() . '<input type="hidden" name="op" value="add_players">';
        if (!$list) { echo '<p class="text-muted-ft mb-0">Every player in the list is already in this squad.</p>'; }
        else {
            echo '<div class="d-flex flex-wrap gap-2 align-items-center mb-2"><input class="form-control w-auto flex-grow-1" type="search" id="sqFind" placeholder="Search by name, position or ID..." autocomplete="off">'
               . '<button class="btn btn-outline-theme btn-sm" type="button" id="sqAll">Tick all shown</button><button class="btn btn-outline-theme btn-sm" type="button" id="sqNone">Clear</button>'
               . '<span class="small text-muted-ft">Squad: ' . $cnt . '/' . SQUAD_MAX . ' &middot; <b id="sqCount">0</b> ticked</span></div>'
               . '<div class="sq-pick-list">';
            foreach ($list as $p) {
                $hay = mb_strtolower($p['full_name'] . ' ' . $p['nickname'] . ' ' . $p['preferred_position'] . ' ' . position_name($p['preferred_position']) . ' ' . $p['public_code'] . ' ' . $p['team_name']);
                echo '<label class="sq-pick" data-hay="' . e($hay) . '"><input class="form-check-input" type="checkbox" name="pick[]" value="' . (int)$p['id'] . '"> <span class="fw-semibold">' . e($p['full_name']) . '</span>'
                   . ' <span class="small text-muted-ft">' . e($p['preferred_position'] ? position_name($p['preferred_position']) : 'no position') . ($p['jersey_number'] !== null ? ' &middot; #' . (int)$p['jersey_number'] : '') . ' &middot; ID ' . e((string)$p['public_code']) . ($p['team_name'] ? ' &middot; ' . e($p['team_name']) : '') . '</span></label>';
            }
            echo '</div><div class="mt-3"><button class="btn btn-accent"><i class="bi bi-check-lg"></i> Add ticked players to the squad</button></div>'
               . '<script>(function(){var f=document.getElementById("sqFind"),rows=[].slice.call(document.querySelectorAll(".sq-pick")),c=document.getElementById("sqCount");'
               . 'function cnt(){c.textContent=document.querySelectorAll(".sq-pick input:checked").length}'
               . 'f.addEventListener("input",function(){var q=f.value.toLowerCase().trim();rows.forEach(function(r){r.hidden=q&&r.dataset.hay.indexOf(q)<0})});'
               . 'document.getElementById("sqAll").onclick=function(){rows.forEach(function(r){if(!r.hidden)r.querySelector("input").checked=true});cnt()};'
               . 'document.getElementById("sqNone").onclick=function(){rows.forEach(function(r){r.querySelector("input").checked=false});cnt()};'
               . 'rows.forEach(function(r){r.querySelector("input").addEventListener("change",cnt)});})();</script>';
        }
        echo '</form></div></div>';
    },
    'table' => 'team_squad', 'title' => 'Current Squad', 'singular' => 'Squad Player', 'icon' => 'bi-people',
    'intro' => 'Up to ' . SQUAD_MAX . ' players per team. Tick "Starting XI" for up to 11 players: they stand on the pitch by position (if none are ticked, the pitch is picked automatically).',
    'search' => ['t.name'], 'order' => 't.shirt_number IS NULL, t.shirt_number, t.name',
    'before_save' => function (array &$data, ?array $existing) {
        $tid = $existing ? (int)$existing['team_id'] : admin_team_id();
        if (!$existing) {
            $n = db()->prepare('SELECT COUNT(*) FROM team_squad WHERE team_id = ?'); $n->execute([$tid]);
            if ((int)$n->fetchColumn() >= SQUAD_MAX) { return 'The squad is full (' . SQUAD_MAX . ' players). Remove someone first.'; }
        }
        if (!empty($data['is_starter'])) {
            $c = db()->prepare('SELECT COUNT(*) FROM team_squad WHERE team_id = ? AND is_starter = 1 AND id <> ?'); $c->execute([$tid, (int)($existing['id'] ?? 0)]);
            if ((int)$c->fetchColumn() >= 11) { return 'The starting XI already has 11 players. Untick someone first.'; }
        }
        if (!empty($data['shirt_number'])) {
            $d = db()->prepare('SELECT COUNT(*) FROM team_squad WHERE team_id = ? AND shirt_number = ? AND id <> ?'); $d->execute([$tid, $data['shirt_number'], (int)($existing['id'] ?? 0)]);
            if ((int)$d->fetchColumn() > 0) { return 'Shirt number ' . $data['shirt_number'] . ' is already taken in this squad.'; }
        }
        return null;
    },
    'after_save' => function (int $id, array $data, bool $isNew) {
        if (!empty($data['is_captain'])) { db()->prepare('UPDATE team_squad SET is_captain = 0 WHERE team_id = ? AND id <> ?')->execute([db()->query('SELECT team_id FROM team_squad WHERE id = ' . $id)->fetchColumn(), $id]); }
    },
    'fields' => [
        ['name' => 'name', 'label' => 'Player Name', 'required' => true, 'max' => 100],
        ['name' => 'shirt_number', 'label' => 'Shirt Number', 'type' => 'number', 'min' => 1, 'max' => 99],
        ['name' => 'position', 'label' => 'Position', 'type' => 'select', 'nullable' => true, 'options' => fn() => POSITION_NAMES],
        ['name' => 'is_starter', 'label' => 'Starting XI (shown on the pitch)', 'type' => 'checkbox'],
        ['name' => 'is_captain', 'label' => 'Captain', 'type' => 'checkbox'],
        ['name' => 'player_id', 'label' => 'Linked website player (optional)', 'type' => 'select', 'nullable' => true, 'options' => fn() => player_options(false), 'help' => 'Links this squad spot to a registered player\'s profile.'],
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
