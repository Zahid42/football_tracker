<?php
/**
 * Statistics queries. All numbers shown anywhere come from here (never hard-coded).
 * Presentation files call these functions; SQL stays in one place.
 */
declare(strict_types=1);

/**
 * Overall totals, optionally limited to a season.
 * Averages/percentages are computed safely (no division by zero).
 */
function get_overall_stats(?int $seasonId = null, ?int $competitionId = null): array
{
    $sql = "SELECT
              COUNT(m.id)                                   AS matches,
              COALESCE(SUM(s.started), 0)                   AS starts,
              COALESCE(SUM(m.result = 'W'), 0)              AS wins,
              COALESCE(SUM(m.result = 'D'), 0)              AS draws,
              COALESCE(SUM(m.result = 'L'), 0)              AS losses,
              COALESCE(SUM(s.goals), 0)                     AS goals,
              COALESCE(SUM(s.assists), 0)                   AS assists,
              COALESCE(SUM(s.minutes_played), 0)            AS minutes,
              COALESCE(SUM(s.yellow_cards), 0)              AS yellow,
              COALESCE(SUM(s.red_cards), 0)                 AS red,
              COALESCE(SUM(s.own_goals), 0)                 AS own_goals,
              COALESCE(SUM(s.penalty_goals), 0)             AS penalty_goals,
              COALESCE(SUM(s.fouls), 0)                     AS fouls,
              COALESCE(SUM(s.tackles), 0)                   AS tackles,
              COALESCE(SUM(s.interceptions), 0)             AS interceptions,
              COALESCE(SUM(s.clearances), 0)                AS clearances,
              COALESCE(SUM(s.blocks), 0)                    AS blocks,
              COALESCE(SUM(s.recoveries), 0)                AS recoveries,
              COALESCE(SUM(s.is_motm), 0)                   AS motm,
              ROUND(AVG(s.rating), 2)                       AS avg_rating,
              MAX(s.rating)                                 AS best_rating,
              COALESCE(MAX(s.goals), 0)                     AS max_goals,
              COALESCE(MAX(s.assists), 0)                   AS max_assists
            FROM matches m
            JOIN match_statistics s ON s.match_id = m.id AND s.player_id = " . ctx_pid() . "";
    $params = []; $w = [];
    if ($seasonId !== null)      { $w[] = 'm.season_id = ?';      $params[] = $seasonId; }
    if ($competitionId !== null) { $w[] = 'm.competition_id = ?'; $params[] = $competitionId; }
    if ($w) { $sql .= ' WHERE ' . implode(' AND ', $w); }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $r = $stmt->fetch();

    foreach (['matches','starts','wins','draws','losses','goals','assists','minutes','yellow','red',
              'own_goals','penalty_goals','fouls','tackles','interceptions','clearances','blocks','recoveries','motm','max_goals','max_assists'] as $k) {
        $r[$k] = (int)$r[$k];
    }
    $r['defensive'] = $r['tackles'] + $r['interceptions'] + $r['clearances'] + $r['blocks'] + $r['recoveries'];
    $n = $r['matches'];
    $r['win_pct']             = $n ? round($r['wins'] / $n * 100, 1) : 0.0;
    $r['defensive_per_match'] = $n ? round($r['defensive'] / $n, 2) : 0.0;
    $r['goals_per_match']     = $n ? round($r['goals'] / $n, 2) : 0.0;
    $r['assists_per_match']   = $n ? round($r['assists'] / $n, 2) : 0.0;
    $r['goal_contributions']  = $r['goals'] + $r['assists'];
    return $r;
}

function get_trophy_count(): int
{
    return (int)db()->query('SELECT COUNT(*) FROM trophies WHERE player_id = ' . ctx_pid())->fetchColumn();
}

function get_current_season(): ?array
{
    $row = db()->query('SELECT * FROM seasons WHERE is_current = 1 ORDER BY name DESC LIMIT 1')->fetch();
    if (!$row) {
        $row = db()->query('SELECT * FROM seasons ORDER BY name DESC LIMIT 1')->fetch();
    }
    return $row ?: null;
}

/** Latest matches with joined names + my stats. */
function get_recent_matches(int $limit = 5): array
{
    $sql = "SELECT m.id, m.match_date, m.kickoff_time, m.team_score, m.opponent_score, m.result,
                   t.name AS team_name, o.name AS opponent_name, c.name AS competition_name,
                   s.position, s.minutes_played, s.goals, s.assists, s.rating
            FROM matches m
            JOIN teams t ON t.id = m.team_id
            JOIN opponents o ON o.id = m.opponent_id
            LEFT JOIN competitions c ON c.id = m.competition_id
            JOIN match_statistics s ON s.match_id = m.id AND s.player_id = " . ctx_pid() . "
            ORDER BY m.match_date DESC, m.id DESC
            LIMIT " . (int)$limit;
    return db()->query($sql)->fetchAll();
}

/** Per-season rollup (used by charts). */
function get_stats_by_season(): array
{
    return get_grouped_stats('season');
}

/** Last N matches oldest -> newest, for form/rating charts. */
function get_last_matches_for_charts(int $limit = 10): array
{
    $sql = "SELECT * FROM (
                SELECT m.id, m.match_date, m.result, s.rating, s.goals, s.assists
                FROM matches m
                JOIN match_statistics s ON s.match_id = m.id AND s.player_id = " . ctx_pid() . "
                ORDER BY m.match_date DESC, m.id DESC
                LIMIT " . (int)$limit . "
            ) x ORDER BY match_date ASC, id ASC";
    return db()->query($sql)->fetchAll();
}

/** Counts for the dashboard (admin sidebar badges / quick info). */
function get_entity_counts(): array
{
    $tables = ['players', 'matches', 'teams', 'competitions', 'venues', 'seasons', 'opponents', 'goals', 'assists'];
    $out = [];
    foreach ($tables as $t) {
        $out[$t] = (int)db()->query("SELECT COUNT(*) FROM `$t`")->fetchColumn(); // table names are fixed literals above
    }
    return $out;
}

/** Data for the three Stage-1 charts (results, goals/assists by season, rating of last 10). */
function get_chart_data(array $overall): array
{
    $bySeason = get_stats_by_season();
    $lastTen  = get_last_matches_for_charts(10);
    return [
        'results' => [(int)$overall['wins'], (int)$overall['draws'], (int)$overall['losses']],
        'seasons' => [
            'labels'  => array_column($bySeason, 'label'),
            'goals'   => array_map('intval', array_column($bySeason, 'goals')),
            'assists' => array_map('intval', array_column($bySeason, 'assists')),
        ],
        'rating' => [
            'labels' => array_map(fn($m) => fmt_date($m['match_date'], 'd M'), $lastTen),
            'values' => array_map(fn($m) => $m['rating'] !== null ? (float)$m['rating'] : null, $lastTen),
        ],
    ];
}

/** Last N results as 'W','D','L' (newest first). */
function get_recent_form(int $limit = 5): array
{
    return db()->query('SELECT m.result FROM matches m JOIN match_statistics s ON s.match_id = m.id AND s.player_id = ' . ctx_pid() . ' ORDER BY m.match_date DESC, m.id DESC LIMIT ' . (int)$limit)
               ->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Grouped statistics. $by: year | season | competition | position | team | opponent
 * Returns rows with label, matches, wins, draws, losses, goals, assists, minutes, yellow, red, avg_rating.
 */
function get_grouped_stats(string $by, ?int $seasonId = null): array
{
    $exprs = [
        'year'        => ['se.name', 'se.name'],
        'season'      => ['se.name', 'se.name'],
        'competition' => ["COALESCE(c.name, 'No competition')", "COALESCE(c.name, 'No competition')"],
        'position'    => ["COALESCE(s.position, '-')", "FIELD(s.position,'GK','CB','LB','RB','CDM','CM','CAM','LM','RM','LW','RW','ST','CF')"],
        'team'        => ['t.name', 't.name'],
        'opponent'    => ['o.name', 'o.name'],
    ];
    if (!isset($exprs[$by])) { return []; }
    [$label, $order] = $exprs[$by];
    $sql = "SELECT $label AS label, COUNT(m.id) AS matches,
              SUM(m.result='W') AS wins, SUM(m.result='D') AS draws, SUM(m.result='L') AS losses,
              COALESCE(SUM(s.goals),0) AS goals, COALESCE(SUM(s.assists),0) AS assists,
              COALESCE(SUM(s.minutes_played),0) AS minutes, COALESCE(SUM(s.yellow_cards),0) AS yellow,
              COALESCE(SUM(s.red_cards),0) AS red, ROUND(AVG(s.rating),2) AS avg_rating
            FROM matches m
            JOIN seasons se ON se.id = m.season_id JOIN teams t ON t.id = m.team_id
            JOIN opponents o ON o.id = m.opponent_id
            LEFT JOIN competitions c ON c.id = m.competition_id
            JOIN match_statistics s ON s.match_id = m.id AND s.player_id = " . ctx_pid() . ""
         . ($seasonId ? ' WHERE m.season_id = ' . (int)$seasonId : '')
         . " GROUP BY $label ORDER BY $order";
    $rows = db()->query($sql)->fetchAll();
    foreach ($rows as &$r) {
        foreach (['matches','wins','draws','losses','goals','assists','minutes','yellow','red'] as $k) { $r[$k] = (int)$r[$k]; }
    }
    return $rows;
}

function get_type_totals(string $table, string $col): array
{
    if (!in_array([$table, $col], [['goals', 'goal_type'], ['assists', 'assist_type']], true)) { return []; }
    return db()->query("SELECT `$col` AS label, COUNT(*) AS total FROM `$table` WHERE player_id = " . ctx_pid() . " GROUP BY `$col` ORDER BY total DESC")->fetchAll();
}

/** Teams the context player played for (current first), with their numbers for each team. */
function get_team_history(): array
{
    return db()->query("SELECT t.*, pt.start_date, pt.end_date, pt.is_current,
              COALESCE(st.matches,0) AS matches, COALESCE(st.wins,0) AS wins, COALESCE(st.goals,0) AS goals, COALESCE(st.assists,0) AS assists
            FROM player_teams pt
            JOIN teams t ON t.id = pt.team_id
            LEFT JOIN (SELECT m.team_id, COUNT(*) AS matches, SUM(m.result='W') AS wins, SUM(s.goals) AS goals, SUM(s.assists) AS assists
                       FROM match_statistics s JOIN matches m ON m.id = s.match_id
                       WHERE s.player_id = " . ctx_pid() . " GROUP BY m.team_id) st ON st.team_id = t.id
            WHERE pt.player_id = " . ctx_pid() . "
            ORDER BY pt.is_current DESC, COALESCE(pt.start_date, '0000-00-00') DESC, pt.id DESC")->fetchAll();
}

function get_trophies(): array
{
    return db()->query("SELECT tr.*, t.name AS team_name FROM trophies tr LEFT JOIN teams t ON t.id = tr.team_id
                        WHERE tr.player_id = " . ctx_pid() . "
                        ORDER BY tr.year DESC, FIELD(tr.achievement,'Champion','Runner-up','Third Place','Best Player','Top Scorer','Top Assist','Other'), tr.id")->fetchAll();
}

function trophy_icon(string $achievement): string
{
    return ['Champion' => '🏆', 'Runner-up' => '🥈', 'Third Place' => '🥉', 'Best Player' => '⭐', 'Top Scorer' => '⚽', 'Top Assist' => '🎯'][$achievement] ?? '🏅';
}

/** Rows for the performance dashboard: every match, oldest first. */
function get_performance_series(): array
{
    return db()->query("SELECT m.id, m.match_date, m.result, s.goals, s.assists, s.rating
                        FROM matches m JOIN match_statistics s ON s.match_id = m.id AND s.player_id = " . ctx_pid() . "
                        ORDER BY m.match_date, m.id")->fetchAll();
}
