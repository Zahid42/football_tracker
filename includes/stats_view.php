<?php
/**
 * Statistics views shared by the public Statistics page and the admin Statistics page.
 * render_statistics($view) returns [html, chartSpecs].
 */
declare(strict_types=1);

const STAT_VIEWS = ['overall' => 'Overall', 'yearly' => 'Yearly', 'competition' => 'Competition', 'position' => 'Position', 'performance' => 'Performance'];

function chart_card(string $id, string $title, int $col = 6): string
{
    return '';   // graphs are switched off site-wide (numbers and tables only)
    return '<div class="col-12 col-lg-' . $col . '"><div class="ft-card p-3 h-100"><h3 class="section-title h6">' . e($title) . '</h3><div class="chart-box"><canvas id="' . e($id) . '"></canvas></div></div></div>';
}

function stat_panel(string $title, string $icon, array $items): string
{
    $h = '<div class="col-12 col-md-6 col-xl-3"><div class="ft-card p-3 h-100"><h3 class="section-title h6"><i class="bi ' . e($icon) . '"></i> ' . e($title) . '</h3><ul class="list-unstyled season-list mb-0">';
    foreach ($items as $label => $val) { $h .= '<li><span>' . e($label) . '</span><strong>' . e($val) . '</strong></li>'; }
    return $h . '</ul></div></div>';
}

function overall_panels(array $o): string
{
    $h = '<div class="row g-3 mb-4">';
    $h .= stat_panel('Overall', 'bi-clipboard-data', ['Matches' => $o['matches'], 'Starts' => $o['starts'], 'Minutes' => number_format($o['minutes']), 'Goals' => $o['goals'], 'Assists' => $o['assists'],
        'Yellow Cards' => $o['yellow'], 'Red Cards' => $o['red'], 'Average Rating' => $o['avg_rating'] !== null ? fmt_num($o['avg_rating']) : '-', 'Win %' => fmt_num($o['win_pct']) . '%']);
    $h .= stat_panel('Attacking', 'bi-bullseye', ['Goals' => $o['goals'], 'Assists' => $o['assists'], 'Goals / Match' => fmt_num($o['goals_per_match'], 2),
        'Assists / Match' => fmt_num($o['assists_per_match'], 2), 'Goal Contributions' => $o['goal_contributions'], 'Penalty Goals' => $o['penalty_goals'], 'Man of the Match' => $o['motm']]);
    $h .= stat_panel('Defensive Contributions', 'bi-shield-check', ['Total Contributions' => $o['defensive'], 'Per Match' => fmt_num($o['defensive_per_match'], 2), 'Tackles' => $o['tackles'], 'Interceptions' => $o['interceptions'],
        'Clearances' => $o['clearances'], 'Blocks' => $o['blocks'], 'Recoveries' => $o['recoveries']]);
    $h .= stat_panel('Discipline', 'bi-square-fill', ['Yellow Cards' => $o['yellow'], 'Red Cards' => $o['red'], 'Fouls' => $o['fouls'], 'Own Goals' => $o['own_goals']]);
    $h .= stat_panel('Performance', 'bi-graph-up-arrow', ['Average Rating' => $o['avg_rating'] !== null ? fmt_num($o['avg_rating']) : '-', 'Best Rating' => $o['best_rating'] !== null ? fmt_num($o['best_rating']) : '-',
        'Most Goals in a Match' => $o['max_goals'], 'Most Assists in a Match' => $o['max_assists']]);
    return $h . '</div>';
}

function summary_cards(array $o): string
{
    $cards = [['Matches', $o['matches'], 'bi-calendar-event', 'c-blue'], ['Wins', $o['wins'], 'bi-emoji-smile', 'c-green'], ['Draws', $o['draws'], 'bi-emoji-neutral', 'c-amber'],
        ['Losses', $o['losses'], 'bi-emoji-frown', 'c-red'], ['Goals', $o['goals'], 'bi-bullseye', 'c-green'], ['Assists', $o['assists'], 'bi-arrow-left-right', 'c-blue'],
        ['Minutes', number_format($o['minutes']), 'bi-stopwatch', 'c-purple'], ['Avg Rating', $o['avg_rating'] !== null ? fmt_num($o['avg_rating']) : '-', 'bi-star', 'c-amber']];
    $h = '<div class="row g-3 mb-4">';
    foreach ($cards as [$l, $v, $i, $c]) { $h .= '<div class="col-6 col-md-4 col-xl-3">' . stat_card($l, $v, $i, $c) . '</div>'; }
    return $h . '</div>';
}

function stat_tabs(string $view, string $base): string
{
    $h = '<ul class="nav nav-pills stat-tabs mb-4">';
    foreach (STAT_VIEWS as $k => $label) {
        $h .= '<li class="nav-item"><a class="nav-link' . ($view === $k ? ' active' : '') . '" href="' . e($base . '?view=' . $k) . '">' . e($label) . '</a></li>';
    }
    return $h . '</ul>';
}

function render_statistics(string $view): array
{
    $charts = []; $h = '';
    if ($view === 'yearly') {
        $seasons = lookup('SELECT id, name FROM seasons ORDER BY name');
        $sel = (int)($_GET['season'] ?? 0);
        if (!isset($seasons[$sel])) { $cur = get_current_season(); $sel = (int)($cur['id'] ?? array_key_first($seasons) ?? 0); }
        $h .= '<form method="get" class="row g-2 mb-3"><input type="hidden" name="view" value="yearly"><div class="col-auto"><select class="form-select" name="season" onchange="this.form.submit()">';
        foreach ($seasons as $id => $name) { $h .= '<option value="' . $id . '"' . ($id === $sel ? ' selected' : '') . '>' . e($name) . '</option>'; }
        $h .= '</select></div><noscript><div class="col-auto"><button class="btn btn-accent">Show</button></div></noscript></form>';
        $h .= $sel ? summary_cards(get_overall_stats($sel)) : '';
        $rows = get_grouped_stats('year');
        $lb = array_column($rows, 'label');
        $h .= '<div class="row g-3 mb-4">' . chart_card('cMatches', 'Matches by year', 4) . chart_card('cResults', 'Wins / Draws / Losses by year', 4) . chart_card('cGA', 'Goals & assists by year', 4) . '</div>';
        $h .= '<div class="ft-card p-3"><h3 class="section-title h6">All years compared</h3>' . render_stats_table($rows, 'Season') . '</div>';
        $charts = [
            ['id' => 'cMatches', 'type' => 'bar', 'labels' => $lb, 'legend' => false, 'datasets' => [['label' => 'Matches', 'data' => array_column($rows, 'matches'), 'color' => 'blue']]],
            ['id' => 'cResults', 'type' => 'bar', 'labels' => $lb, 'datasets' => [['label' => 'Wins', 'data' => array_column($rows, 'wins'), 'color' => 'green'], ['label' => 'Draws', 'data' => array_column($rows, 'draws'), 'color' => 'amber'], ['label' => 'Losses', 'data' => array_column($rows, 'losses'), 'color' => 'red']]],
            ['id' => 'cGA', 'type' => 'bar', 'labels' => $lb, 'datasets' => [['label' => 'Goals', 'data' => array_column($rows, 'goals'), 'color' => 'green'], ['label' => 'Assists', 'data' => array_column($rows, 'assists'), 'color' => 'blue']]],
        ];
    } elseif ($view === 'competition') {
        $comps = lookup('SELECT id, name FROM competitions ORDER BY name');
        $sel = (int)($_GET['competition'] ?? 0);
        $h .= '<form method="get" class="row g-2 mb-3"><input type="hidden" name="view" value="competition"><div class="col-12 col-md-4"><select class="form-select" name="competition" onchange="this.form.submit()"><option value="">Choose a competition...</option>';
        foreach ($comps as $id => $name) { $h .= '<option value="' . $id . '"' . ($id === $sel ? ' selected' : '') . '>' . e($name) . '</option>'; }
        $h .= '</select></div></form>';
        if (isset($comps[$sel])) { $h .= '<h2 class="h5 section-title mb-3">' . e($comps[$sel]) . '</h2>' . summary_cards(get_overall_stats(null, $sel)); }
        $rows = get_grouped_stats('competition');
        $h .= '<div class="row g-3 mb-4">' . chart_card('cMatches', 'Matches by competition', 6) . chart_card('cGA', 'Goals & assists by competition', 6) . '</div>';
        $h .= '<div class="ft-card p-3"><h3 class="section-title h6">All competitions</h3>' . render_stats_table($rows, 'Competition') . '</div>';
        $lb = array_column($rows, 'label');
        $charts = [
            ['id' => 'cMatches', 'type' => 'doughnut', 'labels' => $lb, 'datasets' => [['label' => 'Matches', 'data' => array_column($rows, 'matches'), 'color' => ['green', 'blue', 'amber', 'purple', 'red', '#94a3b8']]]],
            ['id' => 'cGA', 'type' => 'bar', 'labels' => $lb, 'datasets' => [['label' => 'Goals', 'data' => array_column($rows, 'goals'), 'color' => 'green'], ['label' => 'Assists', 'data' => array_column($rows, 'assists'), 'color' => 'blue']]],
        ];
    } elseif ($view === 'position') {
        $rows = array_values(array_filter(get_grouped_stats('position'), fn($r) => $r['label'] !== '-'));
        $h .= '<div class="row g-3 mb-4">';
        foreach ($rows as $r) {
            $h .= '<div class="col-6 col-md-4 col-xl-3"><div class="pos-card"><div class="pos-code">' . e($r['label']) . '<small>' . e(position_name($r['label'])) . '</small></div><ul class="list-unstyled mb-0">'
                . '<li><span>Matches</span><b>' . $r['matches'] . '</b></li><li><span>Goals</span><b>' . $r['goals'] . '</b></li><li><span>Assists</span><b>' . $r['assists']
                . '</b></li><li><span>Avg Rating</span><b>' . e($r['avg_rating'] !== null ? fmt_num($r['avg_rating']) : '-') . '</b></li></ul></div></div>';
        }
        if (!$rows) { $h .= '<div class="col-12"><p class="text-muted-ft">No position data yet.</p></div>'; }
        $h .= '</div><div class="row g-3 mb-4">' . chart_card('cMatches', 'Matches by position', 6) . chart_card('cGA', 'Goals & assists by position', 6) . '</div>';
        $h .= '<div class="ft-card p-3"><h3 class="section-title h6">Position table</h3>' . render_stats_table($rows, 'Position') . '</div>';
        $lb = array_column($rows, 'label');
        $charts = [
            ['id' => 'cMatches', 'type' => 'bar', 'labels' => $lb, 'legend' => false, 'datasets' => [['label' => 'Matches', 'data' => array_column($rows, 'matches'), 'color' => 'blue']]],
            ['id' => 'cGA', 'type' => 'bar', 'labels' => $lb, 'datasets' => [['label' => 'Goals', 'data' => array_column($rows, 'goals'), 'color' => 'green'], ['label' => 'Assists', 'data' => array_column($rows, 'assists'), 'color' => 'blue']]],
        ];
    } elseif ($view === 'performance') {
        $series = get_performance_series();
        $n = count($series);
        $last5 = array_slice($series, -5); $last10 = array_slice($series, -10);
        $form = array_reverse(array_column(array_reverse($last5), 'result'));
        $form = array_column($last5, 'result');
        $o = get_overall_stats();
        $rated = fn($rows) => array_filter(array_column($rows, 'rating'), fn($x) => $x !== null);
        $avg = fn($rows) => ($r = $rated($rows)) ? fmt_num(array_sum($r) / count($r)) : '-';
        $h .= '<div class="row g-3 mb-4"><div class="col-12 col-lg-4"><div class="ft-card p-3 h-100"><h3 class="section-title h6">Form (last 5)</h3><div class="d-flex gap-2 my-3">';
        foreach ($form as $c) { $h .= form_pill($c); }
        $h .= $form ? '' : '<span class="text-muted-ft">No matches yet</span>';
        $h .= '</div><div class="text-muted-ft small">Average rating last 5: <b class="text-body">' . e($avg($last5)) . '</b> &middot; last 10: <b class="text-body">' . e($avg($last10)) . '</b></div></div></div>';
        $h .= '<div class="col-6 col-lg-4">' . stat_card('Win Rate', fmt_num($o['win_pct']) . '%', 'bi-percent', 'c-green') . '</div><div class="col-6 col-lg-4">' . stat_card('Goal Contributions', $o['goal_contributions'], 'bi-bullseye', 'c-blue') . '</div></div>';
        $h .= '<div class="row g-3 mb-4">' . chart_card('cRating', 'Match ratings over time', 12) . chart_card('cGoals', 'Goals over time', 6) . chart_card('cAssists', 'Assists over time', 6) . chart_card('cRecent', 'Rating - last 10 matches', 6) . chart_card('cWin', 'Win rate (running %)', 6) . '</div>';
        $lb = array_map(fn($m) => fmt_date($m['match_date'], 'd M y'), $series);
        $cg = 0; $ca = 0; $wins = 0; $cumG = []; $cumA = []; $winRate = [];
        foreach ($series as $i => $m) { $cg += (int)$m['goals']; $ca += (int)$m['assists']; $cumG[] = $cg; $cumA[] = $ca; $wins += $m['result'] === 'W' ? 1 : 0; $winRate[] = round($wins / ($i + 1) * 100, 1); }
        $charts = [
            ['id' => 'cRating', 'type' => 'line', 'labels' => $lb, 'legend' => false, 'yMax' => 10, 'datasets' => [['label' => 'Rating', 'data' => array_map(fn($m) => $m['rating'] !== null ? (float)$m['rating'] : null, $series), 'color' => 'amber']]],
            ['id' => 'cGoals', 'type' => 'line', 'labels' => $lb, 'datasets' => [['label' => 'Total goals', 'data' => $cumG, 'color' => 'green', 'fill' => true], ['label' => 'Goals in match', 'data' => array_map(fn($m) => (int)$m['goals'], $series), 'color' => 'blue']]],
            ['id' => 'cAssists', 'type' => 'line', 'labels' => $lb, 'datasets' => [['label' => 'Total assists', 'data' => $cumA, 'color' => 'blue', 'fill' => true], ['label' => 'Assists in match', 'data' => array_map(fn($m) => (int)$m['assists'], $series), 'color' => 'purple']]],
            ['id' => 'cRecent', 'type' => 'bar', 'labels' => array_map(fn($m) => fmt_date($m['match_date'], 'd M'), $last10), 'legend' => false, 'yMax' => 10, 'datasets' => [['label' => 'Rating', 'data' => array_map(fn($m) => $m['rating'] !== null ? (float)$m['rating'] : null, $last10), 'color' => 'amber']]],
            ['id' => 'cWin', 'type' => 'line', 'labels' => $lb, 'legend' => false, 'yMax' => 100, 'datasets' => [['label' => 'Win %', 'data' => $winRate, 'color' => 'green', 'fill' => true]]],
        ];
    } else { // overall
        $o = get_overall_stats();
        $h .= summary_cards($o) . overall_panels($o);
        $years = get_grouped_stats('year'); $comps = get_grouped_stats('competition');
        $lb = array_column($years, 'label');
        $h .= '<div class="row g-3 mb-4">' . chart_card('cMatches', 'Matches by year', 4) . chart_card('cResults', 'Wins / Draws / Losses', 4) . chart_card('cGoals', 'Goals by year', 4)
            . chart_card('cAssists', 'Assists by year', 4) . chart_card('cRating', 'Average rating by year', 4) . chart_card('cGA', 'Goals vs assists', 4) . chart_card('cComp', 'Matches by competition', 6)
            . chart_card('cGoalTypes', 'Goals by type', 6) . '</div>';
        $gt = get_type_totals('goals', 'goal_type'); $at = get_type_totals('assists', 'assist_type');
        if ($at) { $h .= '<div class="ft-card p-3 mb-4"><h3 class="section-title h6">Assists by type</h3><div class="d-flex flex-wrap gap-2">'; foreach ($at as $r) { $h .= '<span class="badge-soft">' . e($r['label']) . ' <b>' . (int)$r['total'] . '</b></span>'; } $h .= '</div></div>'; }
        $charts = [
            ['id' => 'cMatches', 'type' => 'bar', 'labels' => $lb, 'legend' => false, 'datasets' => [['label' => 'Matches', 'data' => array_column($years, 'matches'), 'color' => 'blue']]],
            ['id' => 'cResults', 'type' => 'doughnut', 'labels' => ['Wins', 'Draws', 'Losses'], 'datasets' => [['label' => 'Results', 'data' => [$o['wins'], $o['draws'], $o['losses']], 'color' => ['green', 'amber', 'red']]]],
            ['id' => 'cGoals', 'type' => 'bar', 'labels' => $lb, 'legend' => false, 'datasets' => [['label' => 'Goals', 'data' => array_column($years, 'goals'), 'color' => 'green']]],
            ['id' => 'cAssists', 'type' => 'bar', 'labels' => $lb, 'legend' => false, 'datasets' => [['label' => 'Assists', 'data' => array_column($years, 'assists'), 'color' => 'blue']]],
            ['id' => 'cRating', 'type' => 'line', 'labels' => $lb, 'legend' => false, 'yMax' => 10, 'datasets' => [['label' => 'Avg rating', 'data' => array_map(fn($r) => $r['avg_rating'] !== null ? (float)$r['avg_rating'] : null, $years), 'color' => 'amber']]],
            ['id' => 'cGA', 'type' => 'doughnut', 'labels' => ['Goals', 'Assists'], 'datasets' => [['label' => 'G vs A', 'data' => [$o['goals'], $o['assists']], 'color' => ['green', 'blue']]]],
            ['id' => 'cComp', 'type' => 'bar', 'labels' => array_column($comps, 'label'), 'legend' => false, 'datasets' => [['label' => 'Matches', 'data' => array_column($comps, 'matches'), 'color' => 'purple']]],
            ['id' => 'cGoalTypes', 'type' => 'doughnut', 'labels' => array_column($gt, 'label'), 'datasets' => [['label' => 'Goals', 'data' => array_map('intval', array_column($gt, 'total')), 'color' => ['green', 'blue', 'amber', 'purple', 'red', '#94a3b8', '#f472b6', '#2dd4bf']]]],
        ];
    }
    return [$h, []];
}
