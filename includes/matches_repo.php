<?php
/**
 * Match queries shared by the admin, public pages and CSV export.
 */
declare(strict_types=1);

function match_select(bool $onlyPlayed): string
{
    return "SELECT m.*, se.name AS season_name, c.name AS competition_name, v.name AS venue_name, v.city AS venue_city,
        t.name AS team_name, o.name AS opponent_name,
        s.position, s.started, s.minutes_played, s.goals, s.assists, s.yellow_cards, s.red_cards,
        s.own_goals, s.penalty_goals, s.fouls, s.tackles, s.interceptions, s.clearances, s.blocks, s.recoveries, s.rating, s.is_captain, s.is_motm, s.remarks
    FROM matches m
    JOIN seasons se ON se.id = m.season_id
    JOIN teams t ON t.id = m.team_id
    JOIN opponents o ON o.id = m.opponent_id
    LEFT JOIN competitions c ON c.id = m.competition_id
    LEFT JOIN venues v ON v.id = m.venue_id
    " . ($onlyPlayed ? 'JOIN' : 'LEFT JOIN') . " match_statistics s ON s.match_id = m.id AND s.player_id = " . ctx_pid();
}

/** Clean filter values out of $_GET. */
function match_filters(array $src): array
{
    $f = [];
    foreach (['q','from','to','result','position','sort','dir'] as $k) {
        $f[$k] = trim((string)($src[$k] ?? ''));
    }
    foreach (['season','competition','team','opponent'] as $k) {
        $f[$k] = (int)($src[$k] ?? 0);
    }
    if (!in_array($f['result'], ['W','D','L'], true)) { $f['result'] = ''; }
    if (!in_array($f['position'], POSITIONS, true)) { $f['position'] = ''; }
    foreach (['from','to'] as $k) {
        if ($f[$k] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f[$k])) { $f[$k] = ''; }
    }
    $f['dir'] = strtolower($f['dir']) === 'asc' ? 'asc' : 'desc';
    return $f;
}

function match_where(array $f): array
{
    $w = []; $p = [];
    if ($f['q'] !== '') {
        $like = '%' . $f['q'] . '%';
        $w[] = "(o.name LIKE ? OR t.name LIKE ? OR c.name LIKE ? OR v.name LIKE ? OR se.name LIKE ? OR s.position LIKE ?
                 OR DATE_FORMAT(m.match_date,'%Y-%m-%d %d %b %M %Y') LIKE ?)";
        array_push($p, $like, $like, $like, $like, $like, $like, $like);
    }
    $map = ['win' => 'W', 'draw' => 'D', 'loss' => 'L', 'lose' => 'L', 'lost' => 'L'];
    if ($f['q'] !== '' && isset($map[strtolower($f['q'])])) {
        $w[0] = '(' . $w[0] . ' OR m.result = ?)';
        $p[] = $map[strtolower($f['q'])];
    }
    if ($f['from'] !== '')   { $w[] = 'm.match_date >= ?'; $p[] = $f['from']; }
    if ($f['to'] !== '')     { $w[] = 'm.match_date <= ?'; $p[] = $f['to']; }
    if ($f['season'])        { $w[] = 'm.season_id = ?';      $p[] = $f['season']; }
    if ($f['competition'])   { $w[] = 'm.competition_id = ?'; $p[] = $f['competition']; }
    if ($f['team'])          { $w[] = 'm.team_id = ?';        $p[] = $f['team']; }
    if ($f['opponent'])      { $w[] = 'm.opponent_id = ?';    $p[] = $f['opponent']; }
    if ($f['result'] !== '') { $w[] = 'm.result = ?';         $p[] = $f['result']; }
    if ($f['position'] !== '') { $w[] = 's.position = ?';     $p[] = $f['position']; }
    return [$w ? ' WHERE ' . implode(' AND ', $w) : '', $p];
}

function match_order(array $f): string
{
    $cols = ['date' => 'm.match_date', 'opponent' => 'o.name', 'competition' => 'c.name', 'team' => 't.name',
             'result' => 'm.result', 'goals' => 's.goals', 'assists' => 's.assists', 'rating' => 's.rating', 'minutes' => 's.minutes_played'];
    $col = $cols[$f['sort'] ?? ''] ?? 'm.match_date';
    $dir = strtoupper($f['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
    return " ORDER BY $col $dir, m.id $dir";
}

/** $onlyPlayed: null = public pages only list matches the context player played; admin lists every match. */
function search_matches(array $f, int $limit = 0, int $offset = 0, ?bool $onlyPlayed = null): array
{
    [$where, $p] = match_where($f);
    $sql = match_select($onlyPlayed ?? !is_admin_context()) . $where . match_order($f) . ($limit ? ' LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset : '');
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st->fetchAll();
}

function count_matches(array $f, ?bool $onlyPlayed = null): int
{
    [$where, $p] = match_where($f);
    $join = ($onlyPlayed ?? !is_admin_context()) ? 'JOIN' : 'LEFT JOIN';
    $st = db()->prepare('SELECT COUNT(*) FROM matches m JOIN seasons se ON se.id=m.season_id JOIN teams t ON t.id=m.team_id
        JOIN opponents o ON o.id=m.opponent_id LEFT JOIN competitions c ON c.id=m.competition_id
        LEFT JOIN venues v ON v.id=m.venue_id ' . $join . ' match_statistics s ON s.match_id=m.id AND s.player_id=' . ctx_pid() . $where);
    $st->execute($p);
    return (int)$st->fetchColumn();
}

function get_match(int $id): ?array
{
    $st = db()->prepare(match_select(false) . ' WHERE m.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** id => label pairs for <select> lists. $sql must return (id, label). */
function lookup(string $sql): array
{
    $out = [];
    foreach (db()->query($sql)->fetchAll(PDO::FETCH_NUM) as [$id, $label]) { $out[(int)$id] = (string)$label; }
    return $out;
}

function lookups(): array
{
    return [
        'seasons'      => lookup('SELECT id, name FROM seasons ORDER BY name DESC'),
        'competitions' => lookup('SELECT id, name FROM competitions ORDER BY name'),
        'teams'        => lookup('SELECT id, name FROM teams ORDER BY name'),
        'opponents'    => lookup('SELECT id, name FROM opponents ORDER BY name'),
        'venues'       => lookup('SELECT id, name FROM venues ORDER BY name'),
    ];
}

function match_options(): array
{
    return lookup("SELECT m.id, CONCAT(DATE_FORMAT(m.match_date,'%d %b %Y'), ' - ', t.name, ' vs ', o.name, ' (', m.team_score, '-', m.opponent_score, ')')
                   FROM matches m JOIN teams t ON t.id=m.team_id JOIN opponents o ON o.id=m.opponent_id ORDER BY m.match_date DESC, m.id DESC");
}

function opponent_get_or_create(string $name): int
{
    $st = db()->prepare('SELECT id FROM opponents WHERE name = ?');
    $st->execute([$name]);
    $id = $st->fetchColumn();
    if ($id) { return (int)$id; }
    db()->prepare('INSERT INTO opponents (name) VALUES (?)')->execute([$name]);
    return (int)db()->lastInsertId();
}

function get_match_goals(int $matchId): array
{
    $st = db()->prepare('SELECT g.*, COALESCE(p.nickname, p.full_name) AS player_name FROM goals g JOIN players p ON p.id = g.player_id WHERE g.match_id = ? ORDER BY g.minute, g.id'); $st->execute([$matchId]); return $st->fetchAll();
}
function get_match_assists(int $matchId): array
{
    $st = db()->prepare('SELECT a.*, COALESCE(p.nickname, p.full_name) AS player_name FROM assists a JOIN players p ON p.id = a.player_id WHERE a.match_id = ? ORDER BY a.minute, a.id'); $st->execute([$matchId]); return $st->fetchAll();
}

/** Previous / next match by date, for navigation on the details page. */
function match_neighbours(array $m): array
{
    $prev = db()->prepare('SELECT id FROM matches WHERE (match_date < ? OR (match_date = ? AND id < ?)) ORDER BY match_date DESC, id DESC LIMIT 1');
    $prev->execute([$m['match_date'], $m['match_date'], $m['id']]);
    $next = db()->prepare('SELECT id FROM matches WHERE (match_date > ? OR (match_date = ? AND id > ?)) ORDER BY match_date, id LIMIT 1');
    $next->execute([$m['match_date'], $m['match_date'], $m['id']]);
    return [$prev->fetchColumn() ?: null, $next->fetchColumn() ?: null];
}

/** Every player's numbers for one match (for the match page). */
function get_match_performances(int $matchId): array
{
    $st = db()->prepare('SELECT s.*, p.full_name, p.nickname, p.photo, p.jersey_number FROM match_statistics s JOIN players p ON p.id = s.player_id
                         WHERE s.match_id = ? ORDER BY s.is_motm DESC, s.rating DESC, p.full_name');
    $st->execute([$matchId]);
    return $st->fetchAll();
}
