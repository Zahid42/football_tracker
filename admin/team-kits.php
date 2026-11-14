<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
team_admin_page('team-kits.php', [
    'table' => 'team_kits', 'title' => 'Jerseys & Archive', 'singular' => 'Jersey', 'icon' => 'bi-tag',
    'intro' => 'Mark one jersey as Current; all others form the Jersey Archive on the team page. Upload a photo, or just pick colours and a jersey is drawn.',
    'search' => ['t.season', 't.label'], 'order' => 't.is_current DESC, t.season DESC, t.id DESC',
    'after_save' => function (int $id, array $data, bool $isNew) {
        if (!empty($data['is_current'])) { db()->prepare('UPDATE team_kits SET is_current = 0 WHERE team_id = ? AND id <> ?')->execute([db()->query('SELECT team_id FROM team_kits WHERE id = ' . $id)->fetchColumn(), $id]); }
    },
    'fields' => [
        ['name' => 'season', 'label' => 'Season', 'max' => 20, 'help' => 'e.g. 2025/26'],
        ['name' => 'kit_type', 'label' => 'Type', 'type' => 'select', 'required' => true, 'options' => KIT_TYPES, 'default' => 'Home'],
        ['name' => 'label', 'label' => 'Name / Sponsor', 'max' => 100],
        ['name' => 'is_current', 'label' => 'This is the current jersey', 'type' => 'checkbox'],
        ['name' => 'primary_color', 'label' => 'Main colour', 'type' => 'color', 'required' => true, 'default' => '#16a34a', 'col' => 3],
        ['name' => 'secondary_color', 'label' => 'Trim / number colour', 'type' => 'color', 'required' => true, 'default' => '#ffffff', 'col' => 3],
        ['name' => 'image', 'label' => 'Photo (optional)', 'type' => 'image', 'dir' => 'kits'],
        ['name' => 'notes', 'label' => 'Story / notes', 'type' => 'textarea'],
    ],
    'columns' => [
        ['label' => 'Jersey', 'key' => fn($r) => '<span class="jersey-mini">' . kit_visual($r) . '</span>', 'html' => true],
        ['label' => 'Season', 'key' => fn($r) => $r['season'] ?? '-'],
        ['label' => 'Type', 'key' => fn($r) => $r['kit_type'] . ($r['label'] ? ' - ' . $r['label'] : '')],
        ['label' => 'Status', 'key' => fn($r) => $r['is_current'] ? '<span class="badge bg-success">Current</span>' : '<span class="badge bg-secondary">Archive</span>', 'html' => true],
    ],
]);
