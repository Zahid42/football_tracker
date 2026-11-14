<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
crud_run([
    'table' => 'gallery', 'title' => 'Photo Gallery', 'singular' => 'Photo', 'icon' => 'bi-images',
    'scope' => fn() => 't.player_id IS NULL OR t.player_id = ' . admin_player_id(),
    'search' => ['t.caption', 't.category'], 'order' => 't.photo_date DESC, t.id DESC',
    'fields' => [
        ['name' => 'image', 'label' => 'Photo', 'type' => 'image', 'dir' => 'gallery', 'required' => true, 'col' => 12],
        ['name' => 'player_id', 'label' => 'Player', 'type' => 'select', 'nullable' => true, 'default' => admin_player_id(), 'options' => fn() => player_options(false), 'help' => 'Leave blank for a shared photo (shown for every player).'],
        ['name' => 'category', 'label' => 'Category', 'type' => 'select', 'required' => true, 'options' => ['Match Photos', 'Team Photos', 'Tournament Photos', 'Training Photos', 'Trophies', 'Personal']],
        ['name' => 'photo_date', 'label' => 'Date', 'type' => 'date', 'default' => date('Y-m-d')],
        ['name' => 'caption', 'label' => 'Caption'],
        ['name' => 'match_id', 'label' => 'Related match (optional)', 'type' => 'select', 'nullable' => true, 'options' => fn() => match_options()],
    ],
    'columns' => [
        ['label' => 'Preview', 'key' => fn($r) => thumb($r['image']), 'html' => true],
        ['label' => 'Category', 'key' => 'category'], ['label' => 'Caption', 'key' => fn($r) => $r['caption'] ?? '-'], ['label' => 'Date', 'key' => fn($r) => fmt_date($r['photo_date'])],
    ],
]);
