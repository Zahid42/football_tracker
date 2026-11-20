<?php
/**
 * Players + "player context".
 * Every statistic on the site is calculated for ONE player at a time - the context player:
 *   - public site : ?player=ID in the URL, otherwise the player marked "Show on homepage" (featured)
 *   - admin       : the player chosen in the sidebar switcher (stored in the session)
 */
declare(strict_types=1);

function is_admin_context(): bool
{
    return str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/admin/');
}

function players_list(bool $activeOnly = true): array
{
    return db()->query('SELECT p.*, t.name AS team_name FROM players p LEFT JOIN teams t ON t.id = p.current_team_id'
        . " WHERE p.approval_status = 'approved'" . ($activeOnly ? ' AND p.is_active = 1' : '') . ' ORDER BY p.is_featured DESC, p.full_name')->fetchAll();
}

function get_player(int $id): ?array
{
    $st = db()->prepare('SELECT p.*, t.name AS team_name FROM players p LEFT JOIN teams t ON t.id = p.current_team_id WHERE p.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** The player shown on the homepage (featured + active, else first active, else first). */
function featured_player_id(): int
{
    static $id = null;
    if ($id === null) {
        $v = db()->query("SELECT id FROM players WHERE approval_status = 'approved' ORDER BY (is_featured = 1 AND is_active = 1) DESC, is_active DESC, id LIMIT 1")->fetchColumn();
        $id = $v ? (int)$v : 0;
    }
    return $id;
}

function public_player_id(): int
{
    $id = (int)($_GET['player'] ?? 0);
    if ($id) {
        $p = get_player($id);
        if ($p && $p['is_active'] && $p['approval_status'] === 'approved') { return $id; }
    }
    return featured_player_id();
}

function admin_player_id(): int
{
    $id = (int)($_SESSION['admin_player_id'] ?? 0);
    return ($id && get_player($id)) ? $id : featured_player_id();
}

/** The player whose numbers are being shown right now. */
function ctx_pid(): int
{
    if (!isset($GLOBALS['ft_ctx_pid'])) {
        $GLOBALS['ft_ctx_pid'] = is_admin_context() ? admin_player_id() : public_player_id();
    }
    return (int)$GLOBALS['ft_ctx_pid'];
}

/** Run $fn with another player as the context (used for player cards / leaderboards). */
function with_player(int $id, callable $fn)
{
    $old = $GLOBALS['ft_ctx_pid'] ?? null;
    $GLOBALS['ft_ctx_pid'] = $id;
    try {
        return $fn();
    } finally {
        if ($old === null) { unset($GLOBALS['ft_ctx_pid']); } else { $GLOBALS['ft_ctx_pid'] = $old; }
    }
}

/** Public URL that keeps the chosen player (adds ?player=ID when it is not the featured one). */
function purl(string $path): string
{
    $id = ctx_pid();
    if (!is_admin_context() && $id && $id !== featured_player_id()) {
        $path .= (str_contains($path, '?') ? '&' : '?') . 'player=' . $id;
    }
    return url($path);
}

/** Short display name: nickname, else first name. */
function player_short_name(array $p): string
{
    return (string)($p['nickname'] ?: strtok((string)$p['full_name'], ' '));
}

/** id => short name for <select> lists. */
function player_options(bool $activeOnly = true): array
{
    $o = [];
    foreach (players_list($activeOnly) as $p) { $o[(int)$p['id']] = $p['full_name'] . ($p['nickname'] ? ' (' . $p['nickname'] . ')' : ''); }
    return $o;
}

function pending_players(): array
{
    return db()->query("SELECT * FROM players WHERE approval_status = 'pending' ORDER BY registered_at, id")->fetchAll();
}

/** Homepage section / chip switches chosen by the admin (settings keys home_sections, home_chips). */
function home_on(string $key, string $item): bool
{
    $defaults = ['home_sections' => 'about,teams,achievements,fixtures,squads', 'home_chips' => 'foot,height,age,matches,goals,assists'];
    return in_array($item, explode(',', setting($key, $defaults[$key])), true);
}

/* ---------------- Unique 6-digit public IDs (players + teams share one number space) ---------------- */

/** Give every player/team without a code a unique 6-digit one (adds the column itself if the migration was not run). */
function ft_codes_ensure(): void
{
    static $done = false;
    if ($done) { return; }
    $done = true;
    foreach (['players', 'teams', 'competitions'] as $t) {   // 1) make sure both columns exist
        try { db()->query("SELECT public_code FROM $t LIMIT 1"); }
        catch (PDOException $e) { db()->exec("ALTER TABLE $t ADD COLUMN public_code CHAR(6) NULL, ADD UNIQUE KEY uq_{$t}_code (public_code)"); }
    }
    // teams may share a name (each player can keep his own copy of a team)
    if (db()->query("SHOW INDEX FROM teams WHERE Key_name = 'uq_team_name'")->fetch()) { db()->exec('ALTER TABLE teams DROP INDEX uq_team_name'); }
    foreach (['players', 'teams', 'competitions'] as $t) {   // 2) fill the gaps
        foreach (db()->query("SELECT id FROM $t WHERE public_code IS NULL")->fetchAll(PDO::FETCH_COLUMN) as $id) {
            db()->prepare("UPDATE $t SET public_code = ? WHERE id = ?")->execute([ft_new_code(), $id]);
        }
    }
}

function ft_new_code(): string
{
    $chk = db()->prepare('SELECT (SELECT COUNT(*) FROM players WHERE public_code = :a) + (SELECT COUNT(*) FROM teams WHERE public_code = :b) + (SELECT COUNT(*) FROM competitions WHERE public_code = :c)');
    for ($i = 0; $i < 50; $i++) {
        $c = (string)random_int(100000, 999999);
        $chk->execute([':a' => $c, ':b' => $c, ':c' => $c]);
        if ((int)$chk->fetchColumn() === 0) { return $c; }
    }
    throw new RuntimeException('Could not generate a unique ID');
}

/** id => 6-digit code, for players or teams (one query per request). */
function ft_code_map(string $table): array
{
    static $maps = [];
    if (!isset($maps[$table])) {
        ft_codes_ensure();
        $maps[$table] = db()->query("SELECT id, public_code FROM $table")->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    return $maps[$table];
}

function competition_url_by_id(int $id): string
{
    $c = ft_code_map('competitions')[$id] ?? null;
    return url('competitions.php?c=' . ($c ?: $id));
}

function player_url_by_id(int $id): string
{
    $c = ft_code_map('players')[$id] ?? null;
    return $c ? url((string)$c) : url('player-home.php?player=' . $id);
}

function team_url_by_id(int $id): string
{
    $c = ft_code_map('teams')[$id] ?? null;
    return $c ? url((string)$c) : url('team.php?id=' . $id);
}

/* ---------------- Per-player removal (never touches other players' data) ---------------- */

/** Remove ONE player's data from a match. The match itself is deleted only when no other player has stats in it. Returns true if the match was deleted. */
function player_remove_match(int $pid, int $mid): bool
{
    $pdo = db();
    foreach (['goals', 'assists', 'match_statistics'] as $t) {
        $pdo->prepare("DELETE FROM $t WHERE match_id = ? AND player_id = ?")->execute([$mid, $pid]);
    }
    $pdo->prepare('UPDATE match_squad SET player_id = NULL WHERE match_id = ? AND player_id = ?')->execute([$mid, $pid]);
    $left = $pdo->prepare('SELECT COUNT(*) FROM match_statistics WHERE match_id = ?');
    $left->execute([$mid]);
    if ((int)$left->fetchColumn() > 0) { return false; }
    $row = $pdo->prepare('SELECT photo, team_photo FROM matches WHERE id = ?');
    $row->execute([$mid]);
    $m = $row->fetch();
    $pdo->prepare('DELETE FROM matches WHERE id = ?')->execute([$mid]);
    if ($m) { delete_upload($m['photo']); delete_upload($m['team_photo']); }
    return true;
}

/** Remove a team from ONE player's profile: his link, his matches for it and his numbers. The team row is deleted only when nobody uses it any more. */
function player_remove_team(int $pid, int $tid): array
{
    $pdo = db();
    $pdo->beginTransaction();
    $st = $pdo->prepare('SELECT m.id FROM matches m JOIN match_statistics s ON s.match_id = m.id AND s.player_id = ? WHERE m.team_id = ?');
    $st->execute([$pid, $tid]);
    $mine = $st->fetchAll(PDO::FETCH_COLUMN);
    $matchesDeleted = 0;
    foreach ($mine as $mid) { if (player_remove_match($pid, (int)$mid)) { $matchesDeleted++; } }
    $pdo->prepare('DELETE FROM player_teams WHERE player_id = ? AND team_id = ?')->execute([$pid, $tid]);
    $pdo->prepare('UPDATE players SET current_team_id = NULL WHERE id = ? AND current_team_id = ?')->execute([$pid, $tid]);
    $used = $pdo->prepare('SELECT (SELECT COUNT(*) FROM player_teams WHERE team_id = ?) + (SELECT COUNT(*) FROM matches WHERE team_id = ?) + (SELECT COUNT(*) FROM players WHERE current_team_id = ?)');
    $used->execute([$tid, $tid, $tid]);
    $teamDeleted = false;
    if ((int)$used->fetchColumn() === 0) {
        $logo = $pdo->prepare('SELECT logo FROM teams WHERE id = ?'); $logo->execute([$tid]); $logo = $logo->fetchColumn();
        $pdo->prepare('DELETE FROM teams WHERE id = ?')->execute([$tid]);
        $teamDeleted = true;
        if ($logo) { delete_upload($logo); }
    }
    $pdo->commit();
    return ['mine' => count($mine), 'matches_deleted' => $matchesDeleted, 'team_deleted' => $teamDeleted];
}

/* ---------------- Per-player team add / edit (copy-on-write) ---------------- */

function copy_upload(?string $rel): ?string
{
    if (!$rel || str_contains($rel, '..')) { return null; }
    $src = ROOT_PATH . '/' . UPLOAD_DIR_NAME . '/' . $rel;
    if (!is_file($src)) { return null; }
    $info = pathinfo($rel);
    $new = ($info['dirname'] !== '.' ? $info['dirname'] . '/' : '') . $info['filename'] . '_' . bin2hex(random_bytes(3)) . (isset($info['extension']) ? '.' . $info['extension'] : '');
    return @copy($src, ROOT_PATH . '/' . UPLOAD_DIR_NAME . '/' . $new) ? $new : null;
}

/** Other players that use a team (linked to it, or with stats in its matches). */
function team_other_players(int $tid, int $pid): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM (SELECT player_id FROM player_teams WHERE team_id = :t1 AND player_id <> :p1
        UNION SELECT s.player_id FROM match_statistics s JOIN matches m ON m.id = s.match_id WHERE m.team_id = :t2 AND s.player_id <> :p2) x');
    $st->execute([':t1' => $tid, ':p1' => $pid, ':t2' => $tid, ':p2' => $pid]);
    return (int)$st->fetchColumn();
}

/** Add a team to ONE player. A team with the same name is reused (and linked); nothing of it is changed for other players. */
function player_add_team(int $pid, array $data, bool $link = true): array
{
    $find = db()->prepare('SELECT * FROM teams WHERE name = ? ORDER BY id LIMIT 1');
    $find->execute([$data['name']]);
    $ex = $find->fetch();
    if ($ex) {
        $tid = (int)$ex['id'];
        if (!empty($data['logo'])) { delete_upload($data['logo']); }   // never overwrite a shared team's details
        $reused = true;
    } else {
        $data['public_code'] = ft_new_code();
        db()->prepare('INSERT INTO teams (' . implode(', ', array_map(fn($k) => "`$k`", array_keys($data))) . ') VALUES (' . implode(',', array_fill(0, count($data), '?')) . ')')->execute(array_values($data));
        $tid = (int)db()->lastInsertId();
        $reused = false;
    }
    if ($link) {
        $has = db()->prepare('SELECT COUNT(*) FROM player_teams WHERE player_id = ? AND team_id = ?'); $has->execute([$pid, $tid]);
        if (!(int)$has->fetchColumn()) { db()->prepare('INSERT INTO player_teams (player_id, team_id, is_current) VALUES (?, ?, 0)')->execute([$pid, $tid]); }
    }
    return ['id' => $tid, 'reused' => $reused];
}

/**
 * Edit a team for ONE player. If nobody else uses it, it is edited in place. If it is shared, the player gets
 * his own copy (new team + his links and matches move to it) and the shared team stays exactly as it was.
 */
function player_update_team(int $pid, array $existing, array $data): array
{
    $tid = (int)$existing['id'];
    $pdo = db();
    if (team_other_players($tid, $pid) === 0) {
        $pdo->prepare('UPDATE teams SET ' . implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data))) . ' WHERE id = ?')->execute([...array_values($data), $tid]);
        if (!empty($data['logo']) && !empty($existing['logo'])) { delete_upload($existing['logo']); }
        return ['id' => $tid, 'forked' => false];
    }
    $pdo->beginTransaction();
    $new = array_merge(array_diff_key($existing, ['id' => 1, 'created_at' => 1, 'public_code' => 1]), $data);
    if (empty($data['logo'])) { $new['logo'] = copy_upload($existing['logo'] ?? null); }
    $new['public_code'] = ft_new_code();
    $pdo->prepare('INSERT INTO teams (' . implode(', ', array_map(fn($k) => "`$k`", array_keys($new))) . ') VALUES (' . implode(',', array_fill(0, count($new), '?')) . ')')->execute(array_values($new));
    $nid = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE player_teams SET team_id = ? WHERE player_id = ? AND team_id = ?')->execute([$nid, $pid, $tid]);
    $pdo->prepare('UPDATE players SET current_team_id = ? WHERE id = ? AND current_team_id = ?')->execute([$nid, $pid, $tid]);
    $ms = $pdo->prepare('SELECT m.* FROM matches m JOIN match_statistics s ON s.match_id = m.id AND s.player_id = ? WHERE m.team_id = ?');
    $ms->execute([$pid, $tid]);
    foreach ($ms->fetchAll() as $m) {
        $old = (int)$m['id'];
        $others = $pdo->prepare('SELECT COUNT(*) FROM match_statistics WHERE match_id = ? AND player_id <> ?'); $others->execute([$old, $pid]);
        if ((int)$others->fetchColumn() === 0) {
            $pdo->prepare('UPDATE matches SET team_id = ? WHERE id = ?')->execute([$nid, $old]);
            continue;
        }
        $c = array_diff_key($m, ['id' => 1, 'result' => 1, 'created_at' => 1, 'updated_at' => 1]);
        $c['team_id'] = $nid; $c['photo'] = copy_upload($m['photo']); $c['team_photo'] = copy_upload($m['team_photo']);
        $pdo->prepare('INSERT INTO matches (' . implode(', ', array_map(fn($k) => "`$k`", array_keys($c))) . ') VALUES (' . implode(',', array_fill(0, count($c), '?')) . ')')->execute(array_values($c));
        $mid = (int)$pdo->lastInsertId();
        foreach (['match_statistics', 'goals', 'assists'] as $t) {
            $pdo->prepare("UPDATE $t SET match_id = ? WHERE match_id = ? AND player_id = ?")->execute([$mid, $old, $pid]);
        }
        $pdo->prepare('INSERT INTO match_squad (match_id, slot, player_name, shirt_number, position, player_id, is_captain)
            SELECT ?, slot, player_name, shirt_number, position, CASE WHEN player_id = ? THEN player_id ELSE NULL END, is_captain FROM match_squad WHERE match_id = ?')->execute([$mid, $pid, $old]);
    }
    $pdo->commit();
    return ['id' => $nid, 'forked' => true];
}


/* ---------------- Player list filters (public players page + admin Players) ---------------- */

function player_filter_input(): array
{
    $pos = (string)($_GET['pos'] ?? '');
    $sort = (string)($_GET['sort'] ?? '');
    $status = (string)($_GET['status'] ?? '');
    return [
        'q'      => trim((string)($_GET['q'] ?? '')),
        'team'   => max(0, (int)($_GET['team'] ?? 0)),
        'comp'   => max(0, (int)($_GET['comp'] ?? 0)),
        'cat'    => isset(POSITION_GROUPS[(string)($_GET['cat'] ?? '')]) ? (string)$_GET['cat'] : '',
        'foot'   => in_array((string)($_GET['foot'] ?? ''), ['Right', 'Left', 'Both'], true) ? (string)$_GET['foot'] : '',
        'pos'    => in_array($pos, POSITIONS, true) ? $pos : '',
        'sort'   => in_array($sort, ['name', 'rating', 'goals', 'assists', 'matches', 'number'], true) ? $sort : '',
        'status' => in_array($status, ['active', 'archived', 'featured'], true) ? $status : '',
    ];
}

/** Position groups for the "category" filter. */
const POSITION_GROUPS = ['Goalkeeper' => ['GK'], 'Defender' => ['CB', 'LB', 'RB'], 'Midfielder' => ['CDM', 'CM', 'CAM', 'LM', 'RM'], 'Forward' => ['LW', 'RW', 'ST', 'CF']];

function player_filter_active(array $f): bool { return $f['q'] !== '' || $f['team'] || $f['comp'] || $f['cat'] !== '' || $f['foot'] !== '' || $f['pos'] !== '' || $f['sort'] !== '' || $f['status'] !== ''; }

/** Approved players matching the filters; each row gets a 'stats' entry. Admin sees archived players too. */
function players_filtered(array $f, bool $admin): array
{
    $w = ["p.approval_status = 'approved'"]; $a = [];
    if (!$admin) { $w[] = 'p.is_active = 1'; }
    if ($f['q'] !== '') {
        $digits = preg_replace('/\D/', '', ltrim($f['q'], '#'));
        $w[] = '(p.full_name LIKE ? OR p.nickname LIKE ?' . ($digits !== '' ? ' OR p.public_code LIKE ?' : '') . ')';
        array_push($a, '%' . $f['q'] . '%', '%' . $f['q'] . '%');
        if ($digits !== '') { $a[] = '%' . $digits . '%'; }
    }
    if ($f['team']) { $w[] = '(p.current_team_id = ? OR EXISTS (SELECT 1 FROM player_teams pt WHERE pt.player_id = p.id AND pt.team_id = ?))'; array_push($a, $f['team'], $f['team']); }
    if ($f['pos'] !== '') { $w[] = '(p.preferred_position = ? OR p.secondary_position = ?)'; array_push($a, $f['pos'], $f['pos']); }
    if ($f['cat'] !== '') {
        $in = implode(',', array_fill(0, count(POSITION_GROUPS[$f['cat']]), '?'));
        $w[] = '(p.preferred_position IN (' . $in . ') OR p.secondary_position IN (' . $in . '))'; array_push($a, ...POSITION_GROUPS[$f['cat']], ...POSITION_GROUPS[$f['cat']]);
    }
    if ($f['foot'] !== '') { $w[] = 'p.strong_foot = ?'; $a[] = $f['foot']; }
    if ($f['comp']) {   // players of the teams that take part in this tournament (current team, past team or squad spot)
        $w[] = 'EXISTS (SELECT 1 FROM comp_teams ct JOIN teams tt ON LOWER(tt.name) = LOWER(ct.team_name) WHERE ct.competition_id = ? AND (tt.id = p.current_team_id
                 OR EXISTS (SELECT 1 FROM player_teams pt2 WHERE pt2.player_id = p.id AND pt2.team_id = tt.id) OR EXISTS (SELECT 1 FROM team_squad sq WHERE sq.player_id = p.id AND sq.team_id = tt.id)))';
        $a[] = $f['comp'];
    }
    if ($admin) {
        if ($f['status'] === 'active') { $w[] = 'p.is_active = 1'; }
        if ($f['status'] === 'archived') { $w[] = 'p.is_active = 0'; }
        if ($f['status'] === 'featured') { $w[] = 'p.is_featured = 1'; }
    }
    $st = db()->prepare('SELECT p.*, t.name AS team_name FROM players p LEFT JOIN teams t ON t.id = p.current_team_id WHERE ' . implode(' AND ', $w)
        . ' ORDER BY p.is_featured DESC, p.full_name');
    $st->execute($a);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) { $r['stats'] = with_player((int)$r['id'], fn() => get_overall_stats()); }
    unset($r);
    $key = ['rating' => 'avg_rating', 'goals' => 'goals', 'assists' => 'assists', 'matches' => 'matches'][$f['sort']] ?? null;
    if ($key) { usort($rows, fn($x, $y) => ($y['stats'][$key] ?? 0) <=> ($x['stats'][$key] ?? 0)); }
    elseif ($f['sort'] === 'name') { usort($rows, fn($x, $y) => strcasecmp($x['full_name'], $y['full_name'])); }
    elseif ($f['sort'] === 'number') { usort($rows, fn($x, $y) => ($x['jersey_number'] ?? 999) <=> ($y['jersey_number'] ?? 999)); }
    return $rows;
}

/** The filter bar (GET form). $admin adds the status filter. */
function player_filter_bar(array $f, bool $admin, array $keep = []): string
{
    $teams = db()->query("SELECT t.id, IF((SELECT COUNT(*) FROM teams x WHERE x.name = t.name) > 1, CONCAT(t.name, ' #', t.public_code), t.name) AS name
        FROM teams t WHERE EXISTS (SELECT 1 FROM player_teams pt JOIN players p ON p.id = pt.player_id WHERE pt.team_id = t.id AND p.approval_status = 'approved'" . ($admin ? '' : ' AND p.is_active = 1') . ') ORDER BY t.name')->fetchAll();
    $sel = fn(string $name, array $opts, $cur, string $all) => '<select class="form-select" name="' . $name . '" onchange="this.form.submit()"><option value="">' . e($all) . '</option>'
        . implode('', array_map(fn($k, $v) => '<option value="' . e((string)$k) . '"' . ((string)$cur === (string)$k && $cur !== '' && $cur !== 0 ? ' selected' : '') . '>' . e($v) . '</option>', array_keys($opts), $opts)) . '</select>';
    $h = '<form method="get" class="pf-bar" role="search">';
    foreach ($keep as $k => $v) { $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">'; }
    $h .= '<div class="pf-search"><i class="bi bi-search"></i><input type="search" name="q" value="' . e($f['q']) . '" placeholder="Search name, nickname or player ID" autocomplete="off"></div>'
        . $sel('comp', lookup('SELECT id, name FROM competitions ORDER BY name'), $f['comp'], 'All tournaments')
        . $sel('team', array_column($teams, 'name', 'id'), $f['team'], 'All teams')
        . $sel('cat', array_combine(array_keys(POSITION_GROUPS), array_map(fn($k) => $k . 's', array_keys(POSITION_GROUPS))), $f['cat'], 'All categories')
        . $sel('pos', POSITION_NAMES, $f['pos'], 'All positions')
        . $sel('foot', ['Right' => 'Right foot', 'Left' => 'Left foot', 'Both' => 'Both feet'], $f['foot'], 'Any foot');
    if ($admin) { $h .= $sel('status', ['active' => 'Active', 'archived' => 'Archived', 'featured' => 'On homepage'], $f['status'], 'Any status'); }
    $h .= $sel('sort', ['name' => 'Name A-Z', 'rating' => 'Best rated', 'goals' => 'Most goals', 'assists' => 'Most assists', 'matches' => 'Most matches', 'number' => 'Jersey number'], $f['sort'], 'Sort: default');
    $h .= '<button class="btn btn-accent" type="submit"><i class="bi bi-funnel"></i> Filter</button>';
    if (player_filter_active($f)) { $h .= '<a class="btn btn-outline-theme" href="' . e(strtok($_SERVER['REQUEST_URI'], '?')) . ($keep ? '?' . http_build_query($keep) : '') . '">Clear</a>'; }
    return $h . '</form>';
}

/**
 * Site-wide search by name or 6-digit ID. Returns ['players' => [], 'teams' => [], 'competitions' => []].
 */
function site_search(string $q, int $limit = 20, string $type = 'all'): array
{
    $q = trim($q);
    $out = ['players' => [], 'teams' => [], 'competitions' => []];
    if ($q === '') { return $out; }
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $digits = preg_replace('/\D/', '', ltrim($q, '#'));
    $idLike = $digits !== '' ? '%' . $digits . '%' : null;

    if ($type === 'all' || $type === 'players') {
    $sql = "SELECT p.id, p.full_name, p.nickname, p.preferred_position, p.photo, p.public_code FROM players p WHERE p.approval_status = 'approved' AND p.is_active = 1 AND (p.full_name LIKE ? OR p.nickname LIKE ?" . ($idLike ? ' OR p.public_code LIKE ?' : '') . ') ORDER BY (p.public_code = ?) DESC, p.full_name LIMIT ' . $limit;
    $st = db()->prepare($sql); $st->execute(array_merge([$like, $like], $idLike ? [$idLike] : [], [$digits]));
    $out['players'] = $st->fetchAll();
    }
    if ($type === 'all' || $type === 'teams') {

    $sql = "SELECT t.id, t.name, t.short_name, t.logo, t.public_code FROM teams t WHERE t.status = 'active' AND (t.name LIKE ? OR t.short_name LIKE ?" . ($idLike ? ' OR t.public_code LIKE ?' : '') . ') ORDER BY (t.public_code = ?) DESC, t.name LIMIT ' . $limit;
    $st = db()->prepare($sql); $st->execute(array_merge([$like, $like], $idLike ? [$idLike] : [], [$digits]));
    $out['teams'] = $st->fetchAll();
    }
    if ($type === 'all' || $type === 'competitions') {

    $st = db()->prepare('SELECT id, name, type, public_code FROM competitions WHERE (name LIKE ?' . ($idLike ? ' OR public_code LIKE ?' : '') . ') ORDER BY (public_code = ?) DESC, name LIMIT ' . $limit); $st->execute(array_merge([$like], $idLike ? [$idLike] : [], [$digits]));
    $out['competitions'] = $st->fetchAll();
    }
    return $out;
}


/**
 * Puts a team on a player's profile ("Teams played for") when he is added to that team's squad.
 * Becomes his current team when he has none yet; otherwise it is added as a team he played for.
 */
function player_join_team(int $pid, int $tid): void
{
    if ($pid <= 0 || $tid <= 0) { return; }
    $pdo = db();
    $has = $pdo->prepare('SELECT COUNT(*) FROM player_teams WHERE player_id = ? AND team_id = ?'); $has->execute([$pid, $tid]);
    if ((int)$has->fetchColumn()) { return; }
    $cur = $pdo->prepare('SELECT current_team_id FROM players WHERE id = ?'); $cur->execute([$pid]);
    $noCurrent = !(int)$cur->fetchColumn();
    $pdo->prepare('INSERT INTO player_teams (player_id, team_id, is_current) VALUES (?, ?, ?)')->execute([$pid, $tid, $noCurrent ? 1 : 0]);
    if ($noCurrent) { $pdo->prepare('UPDATE players SET current_team_id = ? WHERE id = ?')->execute([$tid, $pid]); }
}

/** One-time: players who are already in a team squad get that team on their profile too. */
function ft_squad_profile_backfill(): void
{
    $pdo = db();
    if ($pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'squad_profile_v'")->fetchColumn() === '1') { return; }
    foreach ($pdo->query('SELECT DISTINCT player_id, team_id FROM team_squad WHERE player_id IS NOT NULL')->fetchAll() as $r) { player_join_team((int)$r['player_id'], (int)$r['team_id']); }
    $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute(['squad_profile_v', '1']);
}


/**
 * Makes sure a team-squad spot is a real player: links it to an existing player with the same name,
 * or creates a new approved player profile, and puts the team on that player's profile.
 * Returns the player id (null if the squad spot does not exist).
 */
function squad_make_profile(int $squadId): ?int
{
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM team_squad WHERE id = ?'); $st->execute([$squadId]); $sq = $st->fetch();
    if (!$sq) { return null; }
    $tid = (int)$sq['team_id'];
    if ($sq['player_id']) { player_join_team((int)$sq['player_id'], $tid); return (int)$sq['player_id']; }
    $name = trim((string)$sq['name']);
    if ($name === '') { return null; }
    $f = $pdo->prepare("SELECT id FROM players WHERE LOWER(full_name) = LOWER(?) AND approval_status = 'approved' ORDER BY id LIMIT 1"); $f->execute([$name]);
    $pid = (int)$f->fetchColumn();
    if (!$pid) {
        $pdo->prepare("INSERT INTO players (full_name, preferred_position, jersey_number, photo, is_active, approval_status, public_code) VALUES (?, ?, ?, ?, 1, 'approved', ?)")
            ->execute([$name, $sq['position'] ?: null, $sq['shirt_number'] !== null ? (int)$sq['shirt_number'] : null, $sq['photo'] ?: null, ft_new_code()]);
        $pid = (int)$pdo->lastInsertId();
    }
    $pdo->prepare('UPDATE team_squad SET player_id = ? WHERE id = ?')->execute([$pid, $squadId]);
    player_join_team($pid, $tid);
    return $pid;
}


/**
 * Adds an existing player to a team's squad (the pitch and list views) and puts the team on the player's profile.
 * Returns 'added', 'dup' (already in the squad) or 'full' (squad limit reached; the team still goes on the profile).
 * When the team already has a starting line-up ticked that is not yet full, the new player takes a free place on the pitch.
 */
function squad_add_player(int $pid, int $tid, ?bool &$numberTaken = null): string
{
    $pdo = db();
    player_join_team($pid, $tid);
    $in = $pdo->prepare('SELECT COUNT(*) FROM team_squad WHERE team_id = ? AND player_id = ?'); $in->execute([$tid, $pid]);
    if ((int)$in->fetchColumn()) { return 'dup'; }
    if ((int)$pdo->query('SELECT COUNT(*) FROM team_squad WHERE team_id = ' . $tid)->fetchColumn() >= SQUAD_MAX) { return 'full'; }
    $g = $pdo->prepare("SELECT full_name, photo, preferred_position, jersey_number FROM players WHERE id = ? AND approval_status = 'approved'"); $g->execute([$pid]);
    $p = $g->fetch(); if (!$p) { return 'dup'; }
    $shirt = $p['jersey_number'] !== null && (int)$p['jersey_number'] > 0 ? (int)$p['jersey_number'] : null;
    if ($shirt) {
        $t = $pdo->prepare('SELECT COUNT(*) FROM team_squad WHERE team_id = ? AND shirt_number = ?'); $t->execute([$tid, $shirt]);
        if ((int)$t->fetchColumn()) { $shirt = null; $numberTaken = true; }
    }
    $flagged = (int)$pdo->query('SELECT COUNT(*) FROM team_squad WHERE team_id = ' . $tid . ' AND is_starter = 1')->fetchColumn();
    $starter = $flagged > 0 && $flagged < team_pitch_size($tid) ? 1 : 0;
    $pdo->prepare('INSERT INTO team_squad (team_id, shirt_number, name, position, photo, player_id, is_starter) VALUES (?,?,?,?,?,?,?)')
        ->execute([$tid, $shirt, $p['full_name'], $p['preferred_position'], $p['photo'], $pid, $starter]);
    return 'added';
}

/** Make sure a team name used in a competition exists in the overall team list (teams table). Returns true when a team was created. */
function comp_team_to_list(string $name): bool
{
    $name = trim($name);
    if ($name === '') { return false; }
    $pdo = db();
    $c = $pdo->prepare('SELECT COUNT(*) FROM teams WHERE LOWER(name) = LOWER(?)'); $c->execute([$name]);
    if ((int)$c->fetchColumn()) { return false; }
    $pdo->prepare("INSERT INTO teams (name, status, public_code) VALUES (?, 'active', ?)")->execute([$name, ft_new_code()]);
    return true;
}

/** One-time: every team already in a competition joins the team list; later additions go through comp_team_to_list(). */
function ft_comp_teams_backfill(): void
{
    $pdo = db();
    if ($pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'comp_team_list_v'")->fetchColumn() === '1') { return; }
    if ($pdo->query("SHOW TABLES LIKE 'comp_teams'")->fetch()) {
        foreach ($pdo->query('SELECT DISTINCT team_name FROM comp_teams')->fetchAll(PDO::FETCH_COLUMN) as $n) { comp_team_to_list((string)$n); }
    }
    $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute(['comp_team_list_v', '1']);
}
