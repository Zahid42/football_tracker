<?php
/**
 * Team profile: current jersey, jersey archive, previous records, achievements, hall of fame, current squad (max 25).
 * Everything is edited by the admin (admin/team-*.php).
 */
declare(strict_types=1);

const SQUAD_MAX = 25;
const KIT_TYPES = ['Home', 'Away', 'Third', 'Goalkeeper', 'Special'];

/** Creates the team-profile tables once (so existing installs need no manual SQL). */
function ft_team_schema_ensure(): void
{
    $pdo = db();
    $v = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'team_profile_v'")->fetchColumn();
    if ($v === '1') { return; }
    $opt = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $fk = fn(string $n) => "CONSTRAINT `fk_{$n}_team` FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE";
    $pdo->exec("CREATE TABLE IF NOT EXISTS `team_kits` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `team_id` INT UNSIGNED NOT NULL, `season` VARCHAR(20) DEFAULT NULL,
        `kit_type` VARCHAR(20) NOT NULL DEFAULT 'Home', `label` VARCHAR(100) DEFAULT NULL, `primary_color` CHAR(7) NOT NULL DEFAULT '#16a34a', `secondary_color` CHAR(7) NOT NULL DEFAULT '#ffffff',
        `image` VARCHAR(255) DEFAULT NULL, `is_current` TINYINT(1) NOT NULL DEFAULT 0, `notes` TEXT DEFAULT NULL, PRIMARY KEY (`id`), KEY `idx_kit_team` (`team_id`), {$fk('kit')})" . $opt);
    $pdo->exec("CREATE TABLE IF NOT EXISTS `team_records` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `team_id` INT UNSIGNED NOT NULL, `season` VARCHAR(20) NOT NULL, `competition` VARCHAR(120) DEFAULT NULL,
        `played` SMALLINT UNSIGNED NOT NULL DEFAULT 0, `won` SMALLINT UNSIGNED NOT NULL DEFAULT 0, `drawn` SMALLINT UNSIGNED NOT NULL DEFAULT 0, `lost` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        `goals_for` SMALLINT UNSIGNED NOT NULL DEFAULT 0, `goals_against` SMALLINT UNSIGNED NOT NULL DEFAULT 0, `final_position` VARCHAR(30) DEFAULT NULL, `notes` TEXT DEFAULT NULL,
        PRIMARY KEY (`id`), KEY `idx_rec_team` (`team_id`), {$fk('rec')})" . $opt);
    $pdo->exec("CREATE TABLE IF NOT EXISTS `team_achievements` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `team_id` INT UNSIGNED NOT NULL, `title` VARCHAR(150) NOT NULL, `year` SMALLINT UNSIGNED DEFAULT NULL,
        `competition` VARCHAR(120) DEFAULT NULL, `achievement_type` VARCHAR(30) NOT NULL DEFAULT 'Trophy', `description` TEXT DEFAULT NULL, `image` VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (`id`), KEY `idx_ach_team` (`team_id`), {$fk('ach')})" . $opt);
    $pdo->exec("CREATE TABLE IF NOT EXISTS `team_hof` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `team_id` INT UNSIGNED NOT NULL, `name` VARCHAR(100) NOT NULL, `role` VARCHAR(60) DEFAULT NULL,
        `years_active` VARCHAR(30) DEFAULT NULL, `inducted_year` SMALLINT UNSIGNED DEFAULT NULL, `photo` VARCHAR(255) DEFAULT NULL, `description` TEXT DEFAULT NULL,
        PRIMARY KEY (`id`), KEY `idx_hof_team` (`team_id`), {$fk('hof')})" . $opt);
    $pdo->exec("CREATE TABLE IF NOT EXISTS `team_squad` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `team_id` INT UNSIGNED NOT NULL, `shirt_number` TINYINT UNSIGNED DEFAULT NULL, `name` VARCHAR(100) NOT NULL,
        `position` VARCHAR(5) DEFAULT NULL, `is_captain` TINYINT(1) NOT NULL DEFAULT 0, `photo` VARCHAR(255) DEFAULT NULL, `player_id` INT UNSIGNED DEFAULT NULL, `notes` VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (`id`), KEY `idx_sq_team` (`team_id`), {$fk('sq')}, CONSTRAINT `fk_sq_player` FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE SET NULL)" . $opt);
    ft_team_seed_samples();
    $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute(['team_profile_v', '1']);
}

/** Example data for the first sample team, so the new sections are not empty (admin can edit or delete it). */
function ft_team_seed_samples(): void
{
    $pdo = db();
    $tid = (int)$pdo->query('SELECT id FROM teams WHERE is_sample = 1 ORDER BY id LIMIT 1')->fetchColumn();
    if (!$tid || (int)$pdo->query('SELECT COUNT(*) FROM team_kits')->fetchColumn() > 0) { return; }
    $ins = fn(string $sql, array $rows) => array_map(fn($r) => $pdo->prepare($sql)->execute([$tid, ...$r]), $rows);
    $ins('INSERT INTO team_kits (team_id, season, kit_type, label, primary_color, secondary_color, is_current) VALUES (?,?,?,?,?,?,?)', [
        ['2026/27', 'Home', 'Emerald home', '#16a34a', '#ffffff', 1], ['2026/27', 'Away', 'White away', '#f8fafc', '#16a34a', 0],
        ['2025/26', 'Home', 'Classic green', '#15803d', '#fde047', 0], ['2024/25', 'Third', 'Midnight', '#1e293b', '#38bdf8', 0], ['2023/24', 'Home', 'Retro stripes', '#b91c1c', '#ffffff', 0]]);
    $ins('INSERT INTO team_records (team_id, season, competition, played, won, drawn, lost, goals_for, goals_against, final_position) VALUES (?,?,?,?,?,?,?,?,?,?)', [
        ['2025/26', 'City League', 20, 13, 4, 3, 41, 18, '1st'], ['2024/25', 'City League', 18, 9, 5, 4, 30, 22, '3rd'], ['2023/24', 'Cup', 6, 4, 1, 1, 15, 7, 'Semi-final']]);
    $ins('INSERT INTO team_achievements (team_id, title, year, competition, achievement_type, description) VALUES (?,?,?,?,?,?)', [
        ['League Champions', 2026, 'City League', 'Trophy', 'Unbeaten at home all season'], ['Cup Runners-up', 2025, 'Community Cup', 'Runner-up', 'Lost the final on penalties'], ['Best Attack', 2025, 'City League', 'Award', '41 goals in 20 matches']]);
    $ins('INSERT INTO team_hof (team_id, name, role, years_active, inducted_year, description) VALUES (?,?,?,?,?,?)', [
        ['Imran Chowdhury', 'Founding Captain', '2018-2023', 2024, 'Led the team in its first five seasons.'], ['Sabbir Khan', 'All-time top scorer', '2019-2025', 2025, '112 goals for the club.']]);
    $ins('INSERT INTO team_squad (team_id, shirt_number, name, position, is_captain) VALUES (?,?,?,?,?)', [
        [1, 'Rahim Uddin', 'GK', 0], [2, 'Tanvir Alam', 'RB', 0], [4, 'Shakib Hasan', 'CB', 1], [5, 'Mehedi Rana', 'CB', 0], [3, 'Arif Hossain', 'LB', 0], [6, 'Nayeem Islam', 'CDM', 0],
        [8, 'Sohag Mia', 'CM', 0], [10, 'Faisal Ahmed', 'CAM', 0], [7, 'Jewel Rana', 'RW', 0], [9, 'Rakib Hasan', 'ST', 0], [11, 'Masud Karim', 'LW', 0]]);
}

/** The team the admin is editing (remembered in the session, ?team=ID switches it). */
function admin_team_id(): int
{
    if (isset($_GET['team']) && (int)$_GET['team'] > 0) {
        $c = db()->prepare('SELECT id FROM teams WHERE id = ?'); $c->execute([(int)$_GET['team']]);
        if ($c->fetchColumn()) { $_SESSION['admin_team_id'] = (int)$_GET['team']; }
    }
    $id = (int)($_SESSION['admin_team_id'] ?? 0);
    if ($id) { $c = db()->prepare('SELECT id FROM teams WHERE id = ?'); $c->execute([$id]); if ($c->fetchColumn()) { return $id; } }
    $first = (int)db()->query('SELECT id FROM teams ORDER BY name LIMIT 1')->fetchColumn();
    $_SESSION['admin_team_id'] = $first;
    return $first;
}

const TEAM_TABS = [
    'team-squad.php'        => ['Squad', 'bi-people'],
    'team-kits.php'         => ['Jerseys', 'bi-tag'],
    'team-records.php'      => ['Records', 'bi-clipboard-data'],
    'team-achievements.php' => ['Achievements', 'bi-trophy'],
    'team-hof.php'          => ['Hall of Fame', 'bi-stars'],
];

/** Team picker + tabs shown above every team-profile admin list. */
function team_admin_bar(string $self): void
{
    $tid = admin_team_id();
    $teams = db()->query('SELECT id, name, public_code FROM teams ORDER BY name')->fetchAll();
    echo '<div class="ft-card p-3 mb-3"><form method="get" class="d-flex flex-wrap gap-2 align-items-center mb-2"><label class="fw-semibold mb-0"><i class="bi bi-shield"></i> Team</label>'
       . '<select class="form-select w-auto" name="team" onchange="this.form.submit()">';
    foreach ($teams as $t) { echo '<option value="' . (int)$t['id'] . '"' . ((int)$t['id'] === $tid ? ' selected' : '') . '>' . e($t['name']) . ' (' . e((string)$t['public_code']) . ')</option>'; }
    echo '</select><a class="btn btn-outline-theme btn-sm" target="_blank" rel="noopener" href="' . e(team_url_by_id($tid)) . '"><i class="bi bi-eye"></i> View public page</a></form><div class="tp-tabs">';
    foreach (TEAM_TABS as $file => [$label, $icon]) {
        echo '<a class="tp-tab' . ($file === $self ? ' active' : '') . '" href="' . e(url('admin/' . $file)) . '"><i class="bi ' . $icon . '"></i> ' . e($label) . '</a>';
    }
    echo '</div></div>';
}

/** Runs a team-scoped CRUD page. $cfg is a normal crud config; team scoping, the picker bar and team_id are added here. */
function team_admin_page(string $self, array $cfg): void
{
    require_login();
    ft_team_schema_ensure();
    $tid = admin_team_id();
    if (!$tid) { flash_set('warning', 'Create a team first.'); redirect('admin/teams.php'); }
    $userBefore = $cfg['before_save'] ?? null;
    $cfg['scope'] = fn() => 't.team_id = ' . $tid;
    $userList = $cfg['before_list'] ?? null;
    $cfg['before_list'] = function () use ($self, $userList) { team_admin_bar($self); if ($userList) { $userList(); } };
    $cfg['before_save'] = function (array &$data, ?array $existing) use ($tid, $userBefore) {
        $data['team_id'] = $existing ? (int)$existing['team_id'] : $tid;
        return $userBefore ? $userBefore($data, $existing) : null;
    };
    crud_run($cfg);
}

/* ---------- jersey drawing ---------- */

function jersey_svg(string $primary, string $secondary, string $number = ''): string
{
    $p = e($primary); $s = e($secondary);
    return '<svg class="jersey-svg" viewBox="0 0 120 120" role="img" aria-label="Jersey"><path d="M40 8 L16 22 L4 50 L26 58 L30 46 L30 112 L90 112 L90 46 L94 58 L116 50 L104 22 L80 8 C74 18 46 18 40 8 Z" fill="' . $p . '" stroke="rgba(0,0,0,.35)" stroke-width="2" stroke-linejoin="round"/>'
        . '<path d="M40 8 C46 18 74 18 80 8" fill="none" stroke="' . $s . '" stroke-width="4"/><path d="M4 50 L26 58 M116 50 L94 58" stroke="' . $s . '" stroke-width="5"/>'
        . ($number !== '' ? '<text x="60" y="72" text-anchor="middle" font-size="30" font-weight="800" fill="' . $s . '" font-family="Arial,sans-serif">' . e($number) . '</text>' : '') . '</svg>';
}

/** Photo of the kit if one was uploaded, otherwise a drawn jersey in its colours. */
function kit_visual(array $k, string $number = ''): string
{
    if (!empty($k['image']) && is_file(ROOT_PATH . '/' . UPLOAD_DIR_NAME . '/' . $k['image'])) {
        return '<img class="jersey-img" src="' . e(upload_url($k['image'])) . '" alt="' . e(($k['kit_type'] ?? '') . ' kit') . '">';
    }
    return jersey_svg((string)($k['primary_color'] ?? '#16a34a'), (string)($k['secondary_color'] ?? '#ffffff'), $number);
}

function team_current_kit(int $tid): ?array
{
    static $map = null;
    if ($map === null) {
        $map = [];
        try {
            foreach (db()->query('SELECT * FROM team_kits ORDER BY is_current DESC, id DESC')->fetchAll() as $k) { $map[(int)$k['team_id']] ??= $k; }
        } catch (PDOException $e) { /* tables not created yet */ }
    }
    return $map[$tid] ?? null;
}

/** Adds the two-sides columns to calendar_events once (Match events pick two teams). */
function ft_calendar_ensure(): void
{
    $pdo = db();
    $v = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'calendar_sides_v'")->fetchColumn();
    if ($v === '1') { return; }
    $have = $pdo->query("SHOW COLUMNS FROM calendar_events LIKE 'home_team'")->fetch();
    if (!$have) { $pdo->exec("ALTER TABLE calendar_events ADD COLUMN home_team VARCHAR(100) DEFAULT NULL AFTER title, ADD COLUMN away_team VARCHAR(100) DEFAULT NULL AFTER home_team"); }
    $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute(['calendar_sides_v', '1']);
}

/** All team names (managed teams + opponents) for the Match event dropdowns. */
function calendar_team_names(): array
{
    $n = db()->query('SELECT name FROM teams UNION SELECT name FROM opponents ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
    return array_values(array_unique($n));
}

/** Number of players a team puts on the pitch (5-a-side ... 11-a-side). Adds the column once. */
function ft_pitch_size_ensure(bool $verify = false): void
{
    $pdo = db();
    static $verified = false;
    if ($verify) { if ($verified) { return; } $verified = true; }   // $verify: also re-check the column when the "done" flag is already set
    elseif ($pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'pitch_size_v'")->fetchColumn() === '1') { return; }
    if (!$pdo->query("SHOW COLUMNS FROM teams LIKE 'pitch_size'")->fetch()) { $pdo->exec('ALTER TABLE teams ADD COLUMN pitch_size TINYINT UNSIGNED NOT NULL DEFAULT 11'); }
    $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute(['pitch_size_v', '1']);
}

/** Pitch size of a team (clamped to 5-11). */
function team_pitch_size(int $teamId): int
{
    try {
        ft_pitch_size_ensure(true);
        $st = db()->prepare('SELECT pitch_size FROM teams WHERE id = ?'); $st->execute([$teamId]);
        $n = (int)$st->fetchColumn();
    } catch (Throwable $e) { return 11; }   // never let this break a page
    return $n >= 5 && $n <= 11 ? $n : 11;
}

/** Adds the "starting XI" flag to the team squad once. */
function ft_squad_ensure(): void
{
    $pdo = db();
    ft_pitch_size_ensure();
    ft_squad_profile_backfill();
    ft_comp_teams_backfill();
    $v = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'squad_starter_v'")->fetchColumn();
    if ($v === '1') { return; }
    if ($pdo->query("SHOW TABLES LIKE 'team_squad'")->fetch() && !$pdo->query("SHOW COLUMNS FROM team_squad LIKE 'is_starter'")->fetch()) {
        $pdo->exec('ALTER TABLE team_squad ADD COLUMN is_starter TINYINT(1) NOT NULL DEFAULT 0');
    }
    $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute(['squad_starter_v', '1']);
}

/**
 * Pitch view of a team squad. The starting XI (flagged by the admin, or picked automatically from positions)
 * stands on a horizontal pitch, own goal on the left; everyone else is listed as substitutes.
 */
function team_pitch(array $squad, int $size = 11): string
{
    $size = in_array($size, PITCH_SIZES, true) ? $size : 11;
    $line = ['GK' => ['gk', 8, 1], 'LB' => ['def', 26, 0], 'CB' => ['def', 26, 1], 'RB' => ['def', 26, 2], 'CDM' => ['dm', 40, 1], 'LM' => ['mid', 53, 0], 'CM' => ['mid', 53, 1],
             'RM' => ['mid', 53, 2], 'CAM' => ['am', 66, 1], 'LW' => ['fwd', 82, 0], 'ST' => ['fwd', 85, 1], 'CF' => ['fwd', 85, 1], 'RW' => ['fwd', 82, 2]];
    $info = fn(array $p) => $line[$p['position'] ?? ''] ?? ['mid', 53, 1];
    // players per line (defence, midfield, attack) for each pitch size; the goalkeeper is always one more
    $shape = [5 => [1, 2, 1], 6 => [2, 2, 1], 7 => [2, 3, 1], 8 => [3, 3, 1], 9 => [3, 3, 2], 10 => [4, 4, 1], 11 => [4, 4, 2]][$size];
    $bucket = function (array $p) use ($info) { $g = $info($p)[0]; return in_array($g, ['dm', 'am'], true) ? 'mid' : $g; };
    $pool = array_values(array_filter($squad, fn($p) => !empty($p['is_starter'])));
    if (!$pool) { $pool = $squad; }                     // nobody flagged: choose automatically from the whole squad
    $want = ['gk' => 1, 'def' => $shape[0], 'mid' => $shape[1], 'fwd' => $shape[2]]; $have = array_fill_keys(array_keys($want), 0); $ids = [];
    foreach ($pool as $p) { $g = $bucket($p); if (count($ids) < $size && $have[$g] < $want[$g]) { $have[$g]++; $ids[$p['id']] = true; } }
    foreach ($pool as $p) { if (count($ids) < $size && !isset($ids[$p['id']])) { $ids[$p['id']] = true; } }
    $starters = array_values(array_filter($pool, fn($p) => isset($ids[$p['id']])));
    $isStart = array_flip(array_column($starters, 'id'));
    $subs = array_filter($squad, fn($p) => !isset($isStart[$p['id']]));
    $groups = [];
    foreach ($starters as $p) { $i = $info($p); $groups[$i[0]]['x'] = $i[1]; $groups[$i[0]]['p'][] = [$i[2], $p]; }
    $h = '<div class="squad-view team-pitch" data-fp="team" data-h="1">' . formation_picker('team', '', $size) . '<div class="pitch pitch-h">' . pitch_svg(true);
    foreach ($groups as $g) {
        usort($g['p'], fn($a, $b) => [$a[0], $a[1]['shirt_number'] ?? 99] <=> [$b[0], $b[1]['shirt_number'] ?? 99]);
        $n = count($g['p']);
        foreach ($g['p'] as $k => [, $p]) {
            $y = $n === 1 ? 50 : 14 + (72 * $k / ($n - 1));
            $name = !empty($p['public_code']) ? '<a class="pname" href="' . e(url($p['public_code'])) . '">' . e($p['name']) . '</a>' : '<span class="pname">' . e($p['name']) . '</span>';
            $h .= '<div class="pl' . (($p['position'] ?? '') === 'GK' ? ' is-gk' : '') . (!empty($p['public_code']) ? ' is-reg' : '') . '" data-pos="' . e($p['position'] ?? '') . '" style="left:' . $g['x'] . '%;top:' . round($y, 1) . '%" title="' . e($p['name'] . ' - ' . position_name($p['position'] ?? null)) . '">'
                . '<span class="shirt">' . e($p['shirt_number'] ?? '') . (!empty($p['is_captain']) ? '<i class="cap">C</i>' : '') . '</span>' . $name . '<span class="ppos">' . e($p['position'] ?? '') . '</span></div>';
        }
    }
    $h .= '</div>';
    if ($subs) {
        $h .= '<div class="bench"><div class="bench-title"><i class="bi bi-arrow-repeat"></i> Substitutes &amp; reserves</div><div class="bench-list">';
        foreach ($subs as $p) { $h .= '<span class="bench-p"><b>' . e($p['shirt_number'] ?? '-') . '</b> ' . e($p['name']) . ' <small>' . e($p['position'] ?? '') . '</small></span>'; }
        $h .= '</div></div>';
    }
    return $h . '</div>';
}


/**
 * Profile players who played for this team, season by season and tournament by tournament (from the match line-ups).
 * Returns [ ['season' => name, 'comps' => [ ['name' => tournament, 'players' => [...]] ]] ], newest season first.
 */
function team_players_by_season(int $teamId): array
{
    $st = db()->prepare("SELECT m.season_id, m.competition_id, s.name AS season, s.start_date, c.name AS comp, p.id, p.full_name, p.photo, p.preferred_position, p.jersey_number, p.public_code, COUNT(DISTINCT m.id) AS apps
        FROM matches m JOIN match_squad ms ON ms.match_id = m.id AND ms.player_id IS NOT NULL
        JOIN players p ON p.id = ms.player_id AND p.approval_status = 'approved'
        LEFT JOIN seasons s ON s.id = m.season_id LEFT JOIN competitions c ON c.id = m.competition_id
        WHERE m.team_id = ? GROUP BY m.season_id, m.competition_id, p.id, s.name, s.start_date, c.name, p.full_name, p.photo, p.preferred_position, p.jersey_number, p.public_code");
    $st->execute([$teamId]);
    $seasons = [];
    foreach ($st->fetchAll() as $r) {
        $sk = (int)$r['season_id']; $ck = (int)$r['competition_id'];
        $seasons[$sk]['season'] = $r['season'] !== null ? $r['season'] : 'No season';
        $seasons[$sk]['sort'] = $r['start_date'] ?? '0000-00-00';
        $seasons[$sk]['comps'][$ck]['name'] = $r['comp'] !== null ? $r['comp'] : 'Friendlies & other matches';
        $seasons[$sk]['comps'][$ck]['players'][] = $r;
    }
    uasort($seasons, fn($a, $b) => strcmp($b['sort'], $a['sort']));
    $order = array_flip(POSITIONS);
    foreach ($seasons as &$se) {
        uasort($se['comps'], fn($a, $b) => [$a['name'] === 'Friendlies & other matches', $a['name']] <=> [$b['name'] === 'Friendlies & other matches', $b['name']]);
        foreach ($se['comps'] as &$co) { usort($co['players'], fn($a, $b) => [$order[$a['preferred_position']] ?? 99, $a['full_name']] <=> [$order[$b['preferred_position']] ?? 99, $b['full_name']]); }
        unset($co);
    }
    unset($se);
    return array_values($seasons);
}

/** A block of player tiles in a 6-column grid (6 x 6 = 36 shown, the rest behind a button). */
function player_tile_grid(array $players, ?string $uid = null): string
{
    $uid = $uid ?: bin2hex(random_bytes(3));
    $h = '<div class="pl-grid" id="g' . $uid . '">';
    foreach ($players as $i => $p) {
        $pos = $p['preferred_position'] ?? null;
        $h .= '<a class="pl-tile' . ($i >= 36 ? ' pl-extra' : '') . '"' . ($i >= 36 ? ' hidden' : '') . ' href="' . e(player_url_by_id((int)$p['id'])) . '">'
            . '<span class="pl-tile-img"><img src="' . e(upload_url($p['photo'] ?? null)) . '" alt="" loading="lazy">' . (!empty($p['jersey_number']) ? '<span class="pl-tile-no">#' . (int)$p['jersey_number'] . '</span>' : '') . ($pos ? '<span class="pl-tile-pos">' . e($pos) . '</span>' : '') . '</span>'
            . '<span class="pl-tile-name">' . e($p['full_name']) . '</span>'
            . '<span class="pl-tile-sub">' . e($pos ? position_name($pos) : 'Player') . (isset($p['apps']) ? ' &middot; ' . (int)$p['apps'] . ' match' . ((int)$p['apps'] === 1 ? '' : 'es') : '') . '</span></a>';
    }
    $h .= '</div>';
    if (count($players) > 36) {
        $h .= '<button class="btn btn-outline-theme btn-sm mt-2" type="button" onclick="document.querySelectorAll(\'#g' . $uid . ' .pl-extra\').forEach(function(x){x.hidden=false});this.remove()">Show all ' . count($players) . ' players</button>';
    }
    return $h;
}
