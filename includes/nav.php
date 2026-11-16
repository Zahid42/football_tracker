<?php
/**
 * Navigation definitions (public site + admin sidebar).
 */
declare(strict_types=1);

function public_nav(): array
{
    return [
        ['label' => 'Home',         'href' => 'index.php'],
        ['label' => 'Competitions', 'href' => 'competitions.php', 'global' => true],
        ['label' => 'About',        'href' => 'about.php'],
    ];
}

/** Per-player sections: shown as a tab strip on the player's page (they follow the player being viewed). */
function player_tab_items(): array
{
    return [
        'player-home.php' => ['Overview',  'bi-house'],
        'profile.php'    => ['Profile',    'bi-person-vcard'],
        'matches.php'    => ['Matches',    'bi-calendar-event'],
        'statistics.php' => ['Statistics', 'bi-bar-chart'],
        'career.php'     => ['Career',     'bi-signpost-split'],
        'trophies.php'   => ['Trophies',   'bi-trophy'],
        'gallery.php'    => ['Gallery',    'bi-images'],
        'calendar.php'   => ['Schedule',   'bi-calendar-week'],
    ];
}

function admin_nav(): array
{
    return [
        ['label' => 'Dashboard',    'href' => 'admin/index.php',        'icon' => 'bi-speedometer2'],
        ['heading' => 'Manage'],
        ['label' => 'Players',      'href' => 'admin/players.php',      'icon' => 'bi-people', 'also' => ['profile.php']],
        ['label' => 'Approvals',    'href' => 'admin/players.php?view=pending', 'icon' => 'bi-person-check', 'badge' => 'pending'],
        ['label' => 'Homepage',     'href' => 'admin/homepage.php',     'icon' => 'bi-house-gear'],
        ['label' => 'Matches',      'href' => 'admin/matches.php',      'icon' => 'bi-calendar-event', 'also' => ['match-add.php', 'match-edit.php']],
        ['label' => 'Teams',        'href' => 'admin/teams.php',        'icon' => 'bi-shield'],
        ['label' => 'Team Profile', 'href' => 'admin/team-squad.php',   'icon' => 'bi-person-badge', 'also' => ['team-kits.php', 'team-records.php', 'team-achievements.php', 'team-hof.php']],
        ['label' => 'Competitions', 'href' => 'admin/competitions.php', 'icon' => 'bi-award'],
        ['label' => 'Competition Tables', 'href' => 'admin/comp-teams.php', 'icon' => 'bi-table', 'also' => ['comp-matches.php']],
        ['label' => 'Seasons',      'href' => 'admin/seasons.php',      'icon' => 'bi-calendar3'],
        ['label' => 'Venues',       'href' => 'admin/venues.php',       'icon' => 'bi-geo-alt'],
        ['label' => 'Goals',        'href' => 'admin/goals.php',        'icon' => 'bi-bullseye'],
        ['label' => 'Assists',      'href' => 'admin/assists.php',      'icon' => 'bi-arrow-left-right'],
        ['label' => 'Trophies',     'href' => 'admin/trophies.php',     'icon' => 'bi-trophy'],
        ['label' => 'Gallery',      'href' => 'admin/gallery.php',      'icon' => 'bi-images'],
        ['label' => 'Calendar',     'href' => 'admin/calendar.php',     'icon' => 'bi-calendar-week'],
        ['heading' => 'More'],
        ['label' => 'Calendar',     'href' => 'calendar.php',           'icon' => 'bi-calendar-week', 'external' => true],
        ['label' => 'Reports & Export', 'href' => 'admin/reports.php',  'icon' => 'bi-printer'],
        ['label' => 'Settings',     'href' => 'admin/settings.php',     'icon' => 'bi-gear'],
        ['label' => 'Change Password', 'href' => 'admin/change-password.php', 'icon' => 'bi-key'],
    ];
}

/** Is a sidebar item the current page? */
function admin_nav_active(array $item): bool
{
    $script = basename($_SERVER['SCRIPT_NAME']);
    $parts = parse_url($item['href']);
    if (basename($parts['path']) !== $script && !in_array($script, $item['also'] ?? [], true)) { return false; }
    if (empty($parts['query']) && $script === 'players.php' && ($_GET['view'] ?? '') === 'pending') { return false; }
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $q);
        foreach ($q as $k => $v) { if (($_GET[$k] ?? ($k === 'view' ? 'overall' : '')) !== $v) { return false; } }
    }
    return true;
}
