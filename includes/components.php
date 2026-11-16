<?php
/**
 * Reusable UI pieces: scoreboard, stat cards, stat tables, match filter form.
 */
declare(strict_types=1);

function stat_card(string $label, $value, string $icon, string $color = 'c-green'): string
{
    return '<div class="stat-card ' . e($color) . '"><div class="stat-icon"><i class="bi ' . e($icon) . '"></i></div>'
         . '<div><div class="stat-value">' . e($value) . '</div><div class="stat-label">' . e($label) . '</div></div></div>';
}

/** Football scoreboard used on match pages. */
function render_scoreboard(array $m): string
{
    $res = ['W' => 'WINNER', 'D' => 'DRAW', 'L' => 'DEFEAT'][$m['result']] ?? '';
    $cls = ['W' => 'win', 'D' => 'draw', 'L' => 'loss'][$m['result']] ?? 'draw';
    $home = ($m['home_away'] ?? '') === 'Away' ? [$m['opponent_name'], $m['opponent_score'], $m['team_name'], $m['team_score'], true]
                                                  : [$m['team_name'], $m['team_score'], $m['opponent_name'], $m['opponent_score'], false];
    [$hn, $hs, $an, $as, $swapped] = $home;
    $h  = '<div class="scoreboard sb-' . $cls . '">';
    $h .= '<div class="sb-status">FULL TIME</div>';
    $h .= '<div class="sb-meta">' . e($m['competition_name'] ?? $m['match_type']) . ' &middot; ' . e(fmt_date($m['match_date'], 'd M Y')) . ($m['kickoff_time'] ? ' &middot; ' . e(fmt_time($m['kickoff_time'])) : '') . '</div>';
    $h .= '<div class="sb-teams"><div class="sb-team' . (!$swapped ? ' mine' : '') . '">' . e($hn) . '</div>'
        . '<div class="sb-score">' . (int)$hs . ' <span>-</span> ' . (int)$as . '</div>'
        . '<div class="sb-team' . ($swapped ? ' mine' : '') . '">' . e($an) . '</div></div>';
    $h .= '<div class="sb-result"><span class="result-badge result-' . $cls . '"><i class="bi bi-flag-fill"></i> ' . e($res) . '</span></div>';
    $h .= $m['goals'] === null ? '' : '<div class="sb-stats">'
        . '<span><i class="bi bi-bullseye"></i> ' . (int)($m['goals'] ?? 0) . ' Goals</span>'
        . '<span><i class="bi bi-arrow-left-right"></i> ' . (int)($m['assists'] ?? 0) . ' Assists</span>'
        . '<span><i class="bi bi-star-fill"></i> ' . e($m['rating'] !== null ? fmt_num($m['rating']) : '-') . ' Rating</span>'
        . '<span><i class="bi bi-stopwatch"></i> ' . (int)($m['minutes_played'] ?? 0) . ' Min</span></div>';
    return $h . '</div>';
}

/** Generic stats table. $rows come from get_grouped_stats(). */
function render_stats_table(array $rows, string $labelHeader): string
{
    if (!$rows) { return '<p class="text-muted-ft mb-0">No data yet.</p>'; }
    $h = '<div class="table-responsive"><table class="table ft-table table-hover align-middle mb-0"><thead><tr><th>' . e($labelHeader)
       . '</th><th class="text-end">Matches</th><th class="text-end">W</th><th class="text-end">D</th><th class="text-end">L</th><th class="text-end">Goals</th>'
       . '<th class="text-end">Assists</th><th class="text-end">Minutes</th><th class="text-end">Avg Rating</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $h .= '<tr><td class="fw-semibold">' . e($r['label']) . '</td><td class="text-end">' . $r['matches'] . '</td><td class="text-end">' . $r['wins'] . '</td><td class="text-end">' . $r['draws']
            . '</td><td class="text-end">' . $r['losses'] . '</td><td class="text-end">' . $r['goals'] . '</td><td class="text-end">' . $r['assists'] . '</td><td class="text-end">' . $r['minutes']
            . '</td><td class="text-end">' . e($r['avg_rating'] !== null ? fmt_num($r['avg_rating']) : '-') . '</td></tr>';
    }
    return $h . '</tbody></table></div>';
}

/** Sortable column header link for match tables. */
function sort_th(string $label, string $key, array $f, string $base, string $class = ''): string
{
    $active = ($f['sort'] ?: 'date') === $key;
    $dir = $active && $f['dir'] === 'desc' ? 'asc' : 'desc';
    $qs = $_GET; unset($qs['page']); $qs['sort'] = $key; $qs['dir'] = $dir;
    $icon = $active ? ($f['dir'] === 'asc' ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>') : '';
    return '<th class="' . e($class) . '"><a class="th-sort" href="' . e($base . '?' . http_build_query($qs)) . '">' . e($label) . $icon . '</a></th>';
}

function sel_opts(array $opts, $selected, string $blank): string
{
    $h = '<option value="">' . e($blank) . '</option>';
    foreach ($opts as $k => $label) {
        $key = array_is_list($opts) ? $label : $k;
        $h .= '<option value="' . e($key) . '"' . ((string)$selected === (string)$key && $selected !== '' && $selected !== 0 ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $h;
}

/** Search + filter bar for match lists. */
function match_filter_form(array $f, string $action): string
{
    $L = lookups();
    $h  = '<form method="get" action="' . e($action) . '" class="ft-card p-3 mb-3"><div class="row g-2">';
    $h .= '<div class="col-12 col-md-4"><input class="form-control" name="q" value="' . e($f['q']) . '" placeholder="Search opponent, team, competition, venue, date, position, win/draw/loss..."></div>';
    $h .= '<div class="col-6 col-md-2"><input type="date" class="form-control" name="from" value="' . e($f['from']) . '" title="From date"></div>';
    $h .= '<div class="col-6 col-md-2"><input type="date" class="form-control" name="to" value="' . e($f['to']) . '" title="To date"></div>';
    $h .= '<div class="col-6 col-md-2"><select class="form-select" name="season">' . sel_opts($L['seasons'], $f['season'], 'All seasons') . '</select></div>';
    $h .= '<div class="col-6 col-md-2"><select class="form-select" name="competition">' . sel_opts($L['competitions'], $f['competition'], 'All competitions') . '</select></div>';
    $h .= '<div class="col-6 col-md-2"><select class="form-select" name="team">' . sel_opts($L['teams'], $f['team'], 'All teams') . '</select></div>';
    $h .= '<div class="col-6 col-md-2"><select class="form-select" name="opponent">' . sel_opts($L['opponents'], $f['opponent'], 'All opponents') . '</select></div>';
    $h .= '<div class="col-6 col-md-2"><select class="form-select" name="result">' . sel_opts(['W' => 'Win', 'D' => 'Draw', 'L' => 'Loss'], $f['result'], 'Any result') . '</select></div>';
    $h .= '<div class="col-6 col-md-2"><select class="form-select" name="position">' . sel_opts(POSITIONS, $f['position'], 'Any position') . '</select></div>';
    $h .= '<div class="col-12 col-md-4 d-flex gap-2"><button class="btn btn-accent flex-grow-1"><i class="bi bi-funnel"></i> Filter</button>'
        . '<a class="btn btn-outline-theme" href="' . e($action) . '">Reset</a></div>';
    return $h . '</div></form>';
}

/** Table of matches. $adminMode adds edit/delete actions. */
function render_match_table(array $rows, array $f, string $base, bool $adminMode = false): string
{
    if (!$rows) { return '<div class="ft-card p-4 text-center text-muted-ft">No matches found.</div>'; }
    $detail = fn($m) => $adminMode ? purl('match-details.php?id=' . $m['id']) : purl('match-details.php?id=' . $m['id']);
    $h = '<div class="ft-card p-0"><div class="table-responsive"><table class="table ft-table table-hover align-middle mb-0"><thead><tr>'
       . sort_th('Date', 'date', $f, $base) . sort_th('Competition', 'competition', $f, $base) . sort_th('Team', 'team', $f, $base)
       . sort_th('Opponent', 'opponent', $f, $base) . '<th>Score</th>' . sort_th('Result', 'result', $f, $base) . '<th>Pos</th>'
       . sort_th('Min', 'minutes', $f, $base, 'text-end') . sort_th('G', 'goals', $f, $base, 'text-end') . sort_th('A', 'assists', $f, $base, 'text-end')
       . sort_th('Rating', 'rating', $f, $base, 'text-end') . '<th class="text-end"></th></tr></thead><tbody>';
    foreach ($rows as $m) {
        $h .= '<tr><td class="text-nowrap">' . e(fmt_date($m['match_date'])) . '</td><td>' . e($m['competition_name'] ?? $m['match_type']) . '</td><td>' . e($m['team_name'])
            . '</td><td>' . e($m['opponent_name']) . '</td><td class="fw-bold text-nowrap">' . (int)$m['team_score'] . ' - ' . (int)$m['opponent_score'] . '</td><td>' . result_badge($m['result'])
            . '</td><td>' . e($m['position'] ?? '-') . '</td><td class="text-end">' . (int)$m['minutes_played'] . '</td><td class="text-end">' . (int)$m['goals'] . '</td><td class="text-end">' . (int)$m['assists']
            . '</td><td class="text-end">' . e($m['rating'] !== null ? fmt_num($m['rating']) : '-') . ($m['is_motm'] ? ' <i class="bi bi-trophy-fill text-warning" title="Man of the Match"></i>' : '') . '</td>'
            . '<td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-theme" title="View" href="' . e($detail($m)) . '"><i class="bi bi-eye"></i></a>';
        if ($adminMode) {
            $h .= ' <a class="btn btn-sm btn-outline-theme" title="Edit" href="' . e(url('admin/match-edit.php?id=' . $m['id'])) . '"><i class="bi bi-pencil"></i></a>'
                . ' <form method="post" action="' . e(url('admin/match-delete.php')) . '" class="d-inline" data-confirm="Delete this match and its squad/goals/assists?">' . csrf_field()
                . '<input type="hidden" name="id" value="' . (int)$m['id'] . '"><button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button></form>';
        }
        $h .= '</td></tr>';
    }
    return $h . '</tbody></table></div></div>';
}

/** Card for a match (used in home page / lists on small screens). */
function match_card(array $m): string
{
    $cls = ['W' => 'win', 'D' => 'draw', 'L' => 'loss'][$m['result']] ?? 'draw';
    return '<a class="match-card result-' . $cls . '" href="' . e(purl('match-details.php?id=' . $m['id'])) . '"><div class="mc-top"><span>' . e(fmt_date($m['match_date'])) . '</span>' . result_badge($m['result']) . '</div>'
        . '<div class="mc-teams"><span>' . e($m['team_name']) . '</span><b>' . (int)$m['team_score'] . ' - ' . (int)$m['opponent_score'] . '</b><span>' . e($m['opponent_name']) . '</span></div>'
        . '<div class="mc-bot">' . e($m['competition_name'] ?? $m['match_type']) . ' &middot; ' . e($m['position'] ?? '-') . ' &middot; ' . (int)$m['goals'] . 'G ' . (int)$m['assists'] . 'A</div></a>';
}

/** Page header for public pages. */
function public_page_title(string $title, string $sub = ''): string
{
    return '<section class="page-hero"><div class="container"><h1>' . e($title) . '</h1>' . ($sub ? '<p>' . e($sub) . '</p>' : '') . '</div></section>';
}

/** Emit window.CH data + loader tag for chart pages. */
function chart_scripts(array $data, string $jsFile): string
{
    $json = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR);
    return '<script>window.CH = ' . $json . ';</script><script src="' . e(url('assets/js/' . $jsFile)) . '"></script>';
}

/** Profile facts (label => value) for profile cards. */
function profile_facts(array $p): array
{
    $age = age_from_dob($p['date_of_birth'] ?? null);
    $years = !empty($p['career_start_date']) ? max(0, (int)(new DateTime($p['career_start_date']))->diff(new DateTime('today'))->y) : null;
    $f = [
        'Full name' => $p['full_name'], 'Nickname' => $p['nickname'] ?: '-',
        'Date of birth' => !empty($p['date_of_birth']) ? fmt_date($p['date_of_birth']) . ($age !== null ? " ($age yrs)" : '') : '-',
        'Preferred position' => position_name($p['preferred_position'] ?? null), 'Secondary position' => position_name($p['secondary_position'] ?? null),
        'Strong foot' => $p['strong_foot'] ?: '-', 'Height' => !empty($p['height_cm']) ? $p['height_cm'] . ' cm' : '-',
        'Weight' => !empty($p['weight_kg']) ? rtrim(rtrim((string)$p['weight_kg'], '0'), '.') . ' kg' : '-',
        'Current team' => $p['team_name'] ?: '-', 'Jersey number' => !empty($p['jersey_number']) ? '#' . $p['jersey_number'] : '-',
        'Career started' => !empty($p['career_start_date']) ? fmt_date($p['career_start_date'], 'M Y') . ($years !== null ? " ($years yrs)" : '') : '-',
    ];
    return $f;
}

function facts_list(array $facts): string
{
    $h = '<ul class="list-unstyled season-list mb-0">';
    foreach ($facts as $k => $v) { $h .= '<li><span>' . e($k) . '</span><strong class="text-end">' . e($v) . '</strong></li>'; }
    return $h . '</ul>';
}

function trophy_card(array $t): string
{
    $img = !empty($t['image']) ? '<img src="' . e(upload_url($t['image'])) . '" alt="">' : '<span class="trophy-emoji">' . trophy_icon($t['achievement']) . '</span>';
    return '<div class="trophy-card ach-' . e(strtolower(str_replace(' ', '-', $t['achievement']))) . '"><div class="trophy-img">' . $img . '</div><div class="trophy-body">'
        . '<div class="trophy-ach">' . trophy_icon($t['achievement']) . ' ' . e($t['achievement']) . ($t['position'] ? ' <small>(' . e($t['position']) . ')</small>' : '') . '</div>'
        . '<div class="trophy-name">' . e($t['tournament_name']) . '</div><div class="trophy-meta">' . e($t['year']) . ($t['team_name'] ? ' &middot; ' . e($t['team_name']) : '') . '</div>'
        . ($t['description'] ? '<p class="trophy-desc">' . e($t['description']) . '</p>' : '') . '</div></div>';
}

function team_timeline_item(array $t): string
{
    $period = ($t['start_date'] ? fmt_date($t['start_date'], 'M Y') : 'Unknown') . ' - ' . ($t['end_date'] ? fmt_date($t['end_date'], 'M Y') : ($t['is_current'] ? 'Present' : '?'));
    $logo = !empty($t['logo']) ? '<img src="' . e(upload_url($t['logo'])) . '" alt="">' : '<i class="bi bi-shield-shaded"></i>';
    return '<div class="tl-item' . ($t['is_current'] ? ' current' : '') . '"><div class="tl-dot"></div><div class="tl-card"><div class="tl-logo">' . $logo . '</div><div class="tl-main">'
        . '<div class="tl-title">' . e($t['name']) . ($t['is_current'] ? ' <span class="badge bg-success">Current</span>' : '') . '</div><div class="tl-period">' . e($period) . '</div>'
        . ($t['description'] ? '<div class="tl-desc">' . e($t['description']) . '</div>' : '') . '</div><div class="tl-stats"><span><b>' . (int)$t['matches'] . '</b> M</span><span><b>' . (int)$t['goals']
        . '</b> G</span><span><b>' . (int)$t['assists'] . '</b> A</span></div></div></div>';
}

/** Player card (players page + homepage grid). $stats = overall stats array for that player. */
function player_card(array $p, array $stats): string
{
    $rating = $stats['avg_rating'] !== null ? fmt_num($stats['avg_rating']) : '-';
    return '<a class="player-card" href="' . e(player_url_by_id((int)$p['id'])) . '">'
        . '<div class="pc-photo"><img src="' . e(upload_url($p['photo'] ?? null)) . '" alt="" loading="lazy">'
        . (!empty($p['jersey_number']) ? '<span class="pc-num">#' . (int)$p['jersey_number'] . '</span>' : '') . ($p['is_featured'] ? '<span class="pc-star"><i class="bi bi-star-fill"></i></span>' : '') . '</div>'
        . '<div class="pc-body"><div class="pc-name">' . e($p['full_name']) . '</div><div class="pc-sub">' . e(position_name($p['preferred_position'] ?? null)) . ($p['team_name'] ? ' &middot; ' . e($p['team_name']) : '') . '</div>'
        . '<div class="pc-stats"><span><b>' . (int)$stats['matches'] . '</b>M</span><span><b>' . (int)$stats['goals'] . '</b>G</span><span><b>' . (int)$stats['assists'] . '</b>A</span><span><b>' . e($rating) . '</b>Rtg</span></div></div></a>';
}


/* ---------------- Performance-based profile card ---------------- */

function ft_clamp(float $v, float $lo = 40, float $hi = 99): int { return (int)round(max($lo, min($hi, $v))); }

/** Six attributes (40-99) and an overall rating worked out from the player's real match numbers. */
function player_ratings(array $p, array $o): array
{
    $n = (int)$o['matches'];
    if ($n === 0) { return ['ovr' => null, 'attrs' => []]; }
    $mins = $o['minutes'] / $n;
    $a = [
        'ATT' => ft_clamp(40 + $o['goals_per_match'] * 35),
        'PLY' => ft_clamp(40 + $o['assists_per_match'] * 45),
        'DEF' => ft_clamp(40 + $o['defensive_per_match'] * 6),
        'FRM' => ft_clamp(($o['avg_rating'] ?? 6) * 10),
        'WIN' => ft_clamp(40 + $o['win_pct'] * 0.5),
        'ENG' => ft_clamp(40 + $mins / 90 * 45 + $o['motm'] * 2),
    ];
    $pos = (string)($p['preferred_position'] ?? '');
    $group = $pos === 'GK' ? 'gk' : (in_array($pos, ['CB', 'LB', 'RB'], true) ? 'def' : (in_array($pos, ['ST', 'CF', 'LW', 'RW'], true) ? 'att' : 'mid'));
    $w = [
        'att' => ['ATT' => .30, 'PLY' => .15, 'DEF' => .05, 'FRM' => .25, 'WIN' => .10, 'ENG' => .15],
        'mid' => ['ATT' => .15, 'PLY' => .25, 'DEF' => .15, 'FRM' => .25, 'WIN' => .10, 'ENG' => .10],
        'def' => ['ATT' => .05, 'PLY' => .10, 'DEF' => .35, 'FRM' => .25, 'WIN' => .15, 'ENG' => .10],
        'gk'  => ['ATT' => 0,   'PLY' => 0,   'DEF' => .45, 'FRM' => .25, 'WIN' => .20, 'ENG' => .10],
    ][$group];
    $ovr = 0.0;
    foreach ($w as $k => $wt) { $ovr += $a[$k] * $wt; }
    return ['ovr' => ft_clamp($ovr, 40, 99), 'attrs' => $a];
}

function player_fut_card(array $p, array $o): string
{
    $r = player_ratings($p, $o);
    $ovr = $r['ovr'];
    $tier = $ovr === null ? 'new' : ($ovr >= 85 ? 'elite' : ($ovr >= 75 ? 'gold' : ($ovr >= 65 ? 'silver' : 'bronze')));
    $h  = '<div class="fut fut-' . $tier . '"><div class="fut-top"><div class="fut-ovr"><b>' . ($ovr ?? '--') . '</b><span>' . e($p['preferred_position'] ?? '') . '</span></div>'
        . '<img class="fut-photo" src="' . e(upload_url($p['photo'] ?? null)) . '" alt=""></div>'
        . '<div class="fut-name">' . e($p['nickname'] ?: $p['full_name']) . '</div>';
    if ($r['attrs']) {
        $h .= '<div class="fut-attrs">';
        foreach ($r['attrs'] as $k => $v) { $h .= '<div><b>' . $v . '</b> ' . $k . '</div>'; }
        $h .= '</div>';
    } else {
        $h .= '<div class="fut-msg">Rating appears after the first match</div>';
    }
    $h .= '<div class="fut-foot"><span>' . (int)$o['matches'] . ' M</span><span>' . (int)$o['goals'] . ' G</span><span>' . (int)$o['assists'] . ' A</span></div></div>';
    return $h;
}


/** Homepage team row: logo, name, period, current jersey, counts and a link to the team page. */
function team_row_item(array $t): string
{
    $tid = (int)$t['id'];
    $period = ($t['start_date'] ? fmt_date($t['start_date'], 'M Y') : 'Unknown') . ' - ' . ($t['end_date'] ? fmt_date($t['end_date'], 'M Y') : ($t['is_current'] ? 'Present' : '?'));
    $logo = !empty($t['logo']) ? '<img src="' . e(upload_url($t['logo'])) . '" alt="">' : '<i class="bi bi-shield-shaded"></i>';
    static $cnt = null;
    if ($cnt === null) {
        $cnt = ['sq' => [], 'ach' => []];
        try {
            $cnt['sq']  = db()->query('SELECT team_id, COUNT(*) FROM team_squad GROUP BY team_id')->fetchAll(PDO::FETCH_KEY_PAIR);
            $cnt['ach'] = db()->query('SELECT team_id, COUNT(*) FROM team_achievements GROUP BY team_id')->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException $e) { }
    }
    $kit = team_current_kit($tid);
    return '<a class="row-item team-row' . ($t['is_current'] ? ' current' : '') . '" href="' . e(team_url_by_id($tid)) . '">'
        . '<span class="tl-logo">' . $logo . '</span>'
        . '<span class="ri-main"><b>' . e($t['name']) . ($t['is_current'] ? ' <span class="badge bg-success">Current</span>' : '') . '</b><small>' . e($period)
        . (!empty($cnt['sq'][$tid]) ? ' &middot; ' . (int)$cnt['sq'][$tid] . ' in squad' : '') . (!empty($cnt['ach'][$tid]) ? ' &middot; ' . (int)$cnt['ach'][$tid] . ' achievements' : '') . '</small></span>'
        . ($kit ? '<span class="jersey-mini" title="Current jersey">' . kit_visual($kit) . '</span>' : '')
        . '<span class="tl-stats"><span><b>' . (int)$t['matches'] . '</b> M</span><span><b>' . (int)$t['goals'] . '</b> G</span><span><b>' . (int)$t['assists'] . '</b> A</span></span>'
        . '<span class="ri-go"><i class="bi bi-chevron-right"></i></span></a>';
}

/** Players | Teams | Competitions switch shown at the top of the homepage and those pages. */
function switch_bar(string $active): string
{
    $tabs = ['players' => ['Players', 'bi-person', 'players.php'], 'teams' => ['Teams', 'bi-shield', 'teams.php'], 'competitions' => ['Competitions', 'bi-trophy', 'competitions.php']];
    $h = '<div class="switch-bar"><div class="container"><div class="sw-tabs">';
    foreach ($tabs as $k => [$l, $i, $f]) { $h .= '<a class="sw-tab' . ($k === $active ? ' active' : '') . '" href="' . e(url($f)) . '"><i class="bi ' . $i . '"></i> ' . $l . '</a>'; }
    return $h . '</div></div></div>';
}

/** Tab strip for the player being viewed: Overview, Matches, Statistics, Career, Trophies... (links keep the player). */
function player_tabs(string $active): string
{
    $pl = get_player(ctx_pid());
    $h = '<div class="player-tabs"><div class="container"><div class="pt-scroll">';
    if ($pl && !empty($pl['full_name'])) { $h .= '<span class="pt-name"><i class="bi bi-person-circle"></i> ' . e($pl['full_name']) . '</span>'; }
    foreach (player_tab_items() as $file => [$label, $icon]) {
        $h .= '<a class="pt-tab' . ($file === $active ? ' active' : '') . '" href="' . e(purl($file)) . '"><i class="bi ' . $icon . '"></i> ' . e($label) . '</a>';
    }
    return $h . '</div></div></div>';
}

/** Search box with a "what are you looking for" dropdown (All / Players / Teams / Competitions) and live suggestions. */
function search_box(string $id, string $class = ''): string
{
    $type = in_array($_GET['type'] ?? '', ['players', 'teams', 'competitions'], true) ? $_GET['type'] : 'all';
    $q = (basename($_SERVER['SCRIPT_NAME']) === 'search.php') ? (string)($_GET['q'] ?? '') : '';
    $opts = ['all' => 'All', 'players' => 'Players', 'teams' => 'Teams', 'competitions' => 'Competitions'];
    $h = '<form class="ft-search ' . e($class) . '" data-ft-search action="' . e(url('search.php')) . '" method="get" role="search" autocomplete="off">'
       . '<select name="type" class="fs-type" aria-label="Search in">';
    foreach ($opts as $k => $l) { $h .= '<option value="' . $k . '"' . ($k === $type ? ' selected' : '') . '>' . $l . '</option>'; }
    $h .= '</select><span class="fs-box"><i class="bi bi-search"></i>'
       . '<input type="search" name="q" id="' . e($id) . '" placeholder="Search by name or 6-digit ID" maxlength="60" aria-label="Search by name or ID" aria-autocomplete="list" value="' . e($q) . '">'
       . '<div class="nav-suggest" role="listbox" hidden></div></span><button class="btn btn-accent fs-go" type="submit" aria-label="Search"><i class="bi bi-arrow-right"></i></button></form>';
    return $h;
}
