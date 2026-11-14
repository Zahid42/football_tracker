<?php
/**
 * Navigation definitions (public site + admin sidebar).
 */
declare(strict_types=1);

function public_nav(): array
{
    return [
        ['label' => 'Home',       'href' => 'index.php'],
        ['label' => 'Profile',    'href' => 'profile.php'],
        ['label' => 'Players',    'href' => 'players.php', 'multi' => true],
        ['label' => 'Matches',    'href' => 'matches.php'],
        ['label' => 'Statistics', 'href' => 'statistics.php'],
        ['label' => 'Career',     'href' => 'career.php'],
        ['label' => 'Trophies',   'href' => 'trophies.php'],
        ['label' => 'Gallery',    'href' => 'gallery.php'],
        ['label' => 'Calendar',   'href' => 'calendar.php'],
        ['label' => 'About',      'href' => 'about.php'],
        ['label' => 'Join',       'href' => 'register.php', 'reg' => true],
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
        ['label' => 'Competitions', 'href' => 'admin/competitions.php', 'icon' => 'bi-award'],
        ['label' => 'Seasons',      'href' => 'admin/seasons.php',      'icon' => 'bi-calendar3'],
        ['label' => 'Venues',       'href' => 'admin/venues.php',       'icon' => 'bi-geo-alt'],
        ['label' => 'Goals',        'href' => 'admin/goals.php',        'icon' => 'bi-bullseye'],
        ['label' => 'Assists',      'href' => 'admin/assists.php',      'icon' => 'bi-arrow-left-right'],
        ['label' => 'Trophies',     'href' => 'admin/trophies.php',     'icon' => 'bi-trophy'],
        ['label' => 'Gallery',      'href' => 'admin/gallery.php',      'icon' => 'bi-images'],
        ['label' => 'Calendar',     'href' => 'admin/calendar.php',     'icon' => 'bi-calendar-week'],
        ['heading' => 'Statistics'],
        ['label' => 'Overall',      'href' => 'admin/statistics.php?view=overall',     'icon' => 'bi-bar-chart-line'],
        ['label' => 'Yearly',       'href' => 'admin/statistics.php?view=yearly',      'icon' => 'bi-calendar-range'],
        ['label' => 'Competition',  'href' => 'admin/statistics.php?view=competition','icon' => 'bi-diagram-3'],
        ['label' => 'Position',     'href' => 'admin/statistics.php?view=position',    'icon' => 'bi-grid-3x3-gap'],
        ['label' => 'Performance',  'href' => 'admin/statistics.php?view=performance', 'icon' => 'bi-activity'],
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
