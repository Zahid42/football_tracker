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
        if ($p && $p['is_active']) { return $id; }
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
    foreach (['players', 'teams'] as $t) {   // 1) make sure both columns exist
        try { db()->query("SELECT public_code FROM $t LIMIT 1"); }
        catch (PDOException $e) { db()->exec("ALTER TABLE $t ADD COLUMN public_code CHAR(6) NULL, ADD UNIQUE KEY uq_{$t}_code (public_code)"); }
    }
    foreach (['players', 'teams'] as $t) {   // 2) fill the gaps
        foreach (db()->query("SELECT id FROM $t WHERE public_code IS NULL")->fetchAll(PDO::FETCH_COLUMN) as $id) {
            db()->prepare("UPDATE $t SET public_code = ? WHERE id = ?")->execute([ft_new_code(), $id]);
        }
    }
}

function ft_new_code(): string
{
    $chk = db()->prepare('SELECT (SELECT COUNT(*) FROM players WHERE public_code = :a) + (SELECT COUNT(*) FROM teams WHERE public_code = :b)');
    for ($i = 0; $i < 50; $i++) {
        $c = (string)random_int(100000, 999999);
        $chk->execute([':a' => $c, ':b' => $c]);
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

function player_url_by_id(int $id): string
{
    $c = ft_code_map('players')[$id] ?? null;
    return $c ? url((string)$c) : url('profile.php?player=' . $id);
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
