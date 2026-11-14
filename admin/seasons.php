<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
crud_run([
    'table' => 'seasons', 'title' => 'Seasons', 'singular' => 'Season', 'icon' => 'bi-calendar3',
    'intro' => 'Create seasons (2024, 2025, 2026 ...). Custom names like "2026/27" also work.',
    'search' => ['t.name'], 'order' => 't.name DESC',
    'fields' => [
        ['name' => 'name', 'label' => 'Season Name', 'required' => true, 'max' => 30, 'help' => 'e.g. 2027 or 2026/27'],
        ['name' => 'start_date', 'label' => 'Start Date', 'type' => 'date'],
        ['name' => 'end_date', 'label' => 'End Date', 'type' => 'date'],
        ['name' => 'is_current', 'label' => 'Current season', 'type' => 'checkbox'],
    ],
    'columns' => [
        ['label' => 'Season', 'key' => 'name', 'class' => 'fw-bold'],
        ['label' => 'Start', 'key' => fn($r) => fmt_date($r['start_date'])], ['label' => 'End', 'key' => fn($r) => fmt_date($r['end_date'])],
        ['label' => 'Matches', 'key' => function ($r) { $s = db()->prepare('SELECT COUNT(*) FROM matches WHERE season_id = ?'); $s->execute([$r['id']]); return (string)$s->fetchColumn(); }],
        ['label' => 'Current', 'key' => fn($r) => $r['is_current'] ? '<span class="badge bg-success">Current</span>' : '', 'html' => true],
    ],
    'before_save' => function (array &$d, ?array $ex): ?string {
        if (!empty($d['start_date']) && !empty($d['end_date']) && $d['end_date'] < $d['start_date']) { return 'End date cannot be before the start date.'; }
        return null;
    },
    'after_save' => function (int $id, array $d, bool $new): void {
        if (!empty($d['is_current'])) { db()->prepare('UPDATE seasons SET is_current = 0 WHERE id <> ?')->execute([$id]); }
    },
]);
