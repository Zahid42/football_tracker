<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
team_admin_page('team-squad.php', [
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
