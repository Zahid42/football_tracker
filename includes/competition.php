<?php
/**
 * Competitions: league tables, group-stage tournaments and player leaderboards.
 * Results come from two places: results the admin enters under Admin > Competition Tables
 * (comp_matches) and the player's own tracked matches that are tagged with the competition.
 */
declare(strict_types=1);

function ft_comp_ensure(): void
{
    $pdo = db();
    $v = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'competition_v'")->fetchColumn();
    if ($v === '2') { return; }
    if (!$pdo->query("SHOW COLUMNS FROM competitions LIKE 'format'")->fetch()) {
        $pdo->exec("ALTER TABLE competitions ADD COLUMN `format` ENUM('League','Groups') NOT NULL DEFAULT 'League' AFTER `type`");
    }
    $opt = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $pdo->exec("CREATE TABLE IF NOT EXISTS `comp_teams` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `competition_id` INT UNSIGNED NOT NULL,
        `team_name` VARCHAR(100) NOT NULL, `group_name` VARCHAR(30) DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uq_comp_team` (`competition_id`,`team_name`),
        CONSTRAINT `fk_ct_comp` FOREIGN KEY (`competition_id`) REFERENCES `competitions`(`id`) ON DELETE CASCADE)" . $opt);
    $pdo->exec("CREATE TABLE IF NOT EXISTS `comp_matches` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `competition_id` INT UNSIGNED NOT NULL,
        `stage` VARCHAR(30) DEFAULT NULL, `match_date` DATE DEFAULT NULL, `home_team` VARCHAR(100) NOT NULL, `away_team` VARCHAR(100) NOT NULL,
        `home_score` TINYINT UNSIGNED DEFAULT NULL, `away_score` TINYINT UNSIGNED DEFAULT NULL, PRIMARY KEY (`id`), KEY `idx_cm_comp` (`competition_id`),
        CONSTRAINT `fk_cm_comp` FOREIGN KEY (`competition_id`) REFERENCES `competitions`(`id`) ON DELETE CASCADE)" . $opt);
    // v2: league/group rules, suspension, point deductions, knockout bracket
    $add = function (string $table, string $col, string $def) use ($pdo) {
        if (!$pdo->query("SHOW COLUMNS FROM `$table` LIKE '$col'")->fetch()) { $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def"); }
    };
    $add('competitions', 'max_teams', 'SMALLINT UNSIGNED DEFAULT NULL');
    $add('competitions', 'num_groups', 'TINYINT UNSIGNED DEFAULT NULL');
    $add('competitions', 'teams_per_group', 'TINYINT UNSIGNED DEFAULT NULL');
    $add('competitions', 'qualify_per_group', 'TINYINT UNSIGNED NOT NULL DEFAULT 2');
    $add('competitions', 'knockout', 'TINYINT(1) NOT NULL DEFAULT 0');
    $add('competitions', 'ko_teams', 'TINYINT UNSIGNED DEFAULT NULL');
    $add('comp_teams', 'status', "ENUM('active','suspended') NOT NULL DEFAULT 'active'");
    $add('comp_teams', 'point_adjust', 'SMALLINT NOT NULL DEFAULT 0');
    $add('comp_teams', 'adjust_reason', 'VARCHAR(150) DEFAULT NULL');
    $pdo->exec("CREATE TABLE IF NOT EXISTS `comp_ko` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `competition_id` INT UNSIGNED NOT NULL, `round_no` TINYINT UNSIGNED NOT NULL, `slot` TINYINT UNSIGNED NOT NULL,
        `team_a` VARCHAR(100) DEFAULT NULL, `team_b` VARCHAR(100) DEFAULT NULL, `score_a` TINYINT UNSIGNED DEFAULT NULL, `score_b` TINYINT UNSIGNED DEFAULT NULL,
        `pen_a` TINYINT UNSIGNED DEFAULT NULL, `pen_b` TINYINT UNSIGNED DEFAULT NULL, `match_date` DATE DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uq_ko` (`competition_id`,`round_no`,`slot`),
        CONSTRAINT `fk_ko_comp` FOREIGN KEY (`competition_id`) REFERENCES `competitions`(`id`) ON DELETE CASCADE)" . $opt);
    $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute(['competition_v', '2']);
}

function comp_team_names(): array
{
    return array_values(array_unique(db()->query('SELECT name FROM teams UNION SELECT name FROM opponents ORDER BY name')->fetchAll(PDO::FETCH_COLUMN)));
}

/** Competition the admin is working on (kept in the session, like the team picker). */
function admin_comp_id(): int
{
    if (isset($_GET['comp']) && (int)$_GET['comp'] > 0) {
        $c = db()->prepare('SELECT id FROM competitions WHERE id = ?'); $c->execute([(int)$_GET['comp']]);
        if ($c->fetchColumn()) { $_SESSION['admin_comp_id'] = (int)$_GET['comp']; }
    }
    $id = (int)($_SESSION['admin_comp_id'] ?? 0);
    if ($id) { $c = db()->prepare('SELECT id FROM competitions WHERE id = ?'); $c->execute([$id]); if ($c->fetchColumn()) { return $id; } }
    $first = (int)db()->query('SELECT id FROM competitions ORDER BY name LIMIT 1')->fetchColumn();
    $_SESSION['admin_comp_id'] = $first;
    return $first;
}

const COMP_TABS = ['comp-teams.php' => ['Teams & groups', 'bi-people'], 'comp-matches.php' => ['Fixtures & results', 'bi-calendar-check'], 'comp-knockout.php' => ['Knockout bracket', 'bi-diagram-3']];

function comp_admin_bar(string $self): void
{
    $cid = admin_comp_id();
    $rows = db()->query('SELECT id, name, `format` FROM competitions ORDER BY name')->fetchAll();
    echo '<div class="ft-card p-3 mb-3"><form method="get" class="d-flex flex-wrap gap-2 align-items-center mb-2"><label class="fw-semibold mb-0"><i class="bi bi-award"></i> Competition</label>'
       . '<select class="form-select w-auto" name="comp" onchange="this.form.submit()">';
    foreach ($rows as $r) { echo '<option value="' . (int)$r['id'] . '"' . ((int)$r['id'] === $cid ? ' selected' : '') . '>' . e($r['name']) . ' (' . e($r['format']) . ')</option>'; }
    echo '</select><a class="btn btn-outline-theme btn-sm" target="_blank" rel="noopener" href="' . e(competition_url_by_id($cid)) . '"><i class="bi bi-eye"></i> View public table</a>'
       . '<a class="btn btn-outline-theme btn-sm" href="' . e(url('admin/competitions.php?action=edit&id=' . $cid)) . '"><i class="bi bi-gear"></i> Rules (teams, groups, knockout)</a></form><div class="tp-tabs">';
    foreach (COMP_TABS as $file => [$label, $icon]) {
        echo '<a class="tp-tab' . ($file === $self ? ' active' : '') . '" href="' . e(url('admin/' . $file)) . '"><i class="bi ' . $icon . '"></i> ' . e($label) . '</a>';
    }
    echo '</div></div>';
}

function comp_admin_page(string $self, array $cfg): void
{
    require_login();
    ft_comp_ensure();
    $cid = admin_comp_id();
    if (!$cid) { flash_set('warning', 'Create a competition first.'); redirect('admin/competitions.php'); }
    $userBefore = $cfg['before_save'] ?? null;
    $cfg['scope'] = fn() => 't.competition_id = ' . $cid;
    $cfg['before_list'] = function () use ($self) { comp_admin_bar($self); };
    $cfg['before_save'] = function (array &$data, ?array $existing) use ($cid, $userBefore) {
        $data['competition_id'] = $existing ? (int)$existing['competition_id'] : $cid;
        return $userBefore ? $userBefore($data, $existing) : null;
    };
    crud_run($cfg);
}

/** Every played result of a competition: admin-entered plus tracked matches tagged with it. */
function comp_results(int $cid): array
{
    $out = [];
    $st = db()->prepare('SELECT * FROM comp_matches WHERE competition_id = ? ORDER BY match_date, id'); $st->execute([$cid]);
    foreach ($st->fetchAll() as $r) {
        $out[] = ['date' => $r['match_date'], 'stage' => $r['stage'], 'home' => $r['home_team'], 'away' => $r['away_team'],
                  'hs' => $r['home_score'] === null ? null : (int)$r['home_score'], 'as' => $r['away_score'] === null ? null : (int)$r['away_score'], 'tracked' => false];
    }
    $st = db()->prepare('SELECT m.match_date, m.team_score, m.opponent_score, m.match_type, t.name tn, o.name opp FROM matches m JOIN teams t ON t.id = m.team_id JOIN opponents o ON o.id = m.opponent_id WHERE m.competition_id = ? ORDER BY m.match_date, m.id');
    $st->execute([$cid]);
    foreach ($st->fetchAll() as $r) {
        $out[] = ['date' => $r['match_date'], 'stage' => $r['match_type'], 'home' => $r['tn'], 'away' => $r['opp'], 'hs' => (int)$r['team_score'], 'as' => (int)$r['opponent_score'], 'tracked' => true];
    }
    usort($out, fn($a, $b) => strcmp((string)$a['date'], (string)$b['date']));
    return $out;
}

/** One competition row (rules included). */
function comp_row(int $cid): array
{
    $st = db()->prepare('SELECT * FROM competitions WHERE id = ?'); $st->execute([$cid]);
    return $st->fetch() ?: [];
}

/** "Group A", "Group B"... for the number of groups the admin set. */
function comp_group_names(array $comp): array
{
    $n = max(0, (int)($comp['num_groups'] ?? 0)); $out = [];
    for ($i = 0; $i < $n; $i++) { $out[] = 'Group ' . chr(65 + $i); }
    return $out;
}

/**
 * Standings. Returns ['' => rows] for a league, or ['Group A' => rows, ...] for groups.
 * Suspended teams are listed after the active ones and never qualify; point deductions/bonuses are applied to Pts.
 */
function comp_standings(int $cid, string $format): array
{
    $st = db()->prepare('SELECT team_name, group_name, status, point_adjust, adjust_reason FROM comp_teams WHERE competition_id = ? ORDER BY group_name, team_name'); $st->execute([$cid]);
    $teams = [];
    foreach ($st->fetchAll() as $r) { $teams[mb_strtolower($r['team_name'])] = ['name' => $r['team_name'], 'group' => $format === 'Groups' ? ($r['group_name'] ?: 'Ungrouped') : '', 'susp' => $r['status'] === 'suspended', 'adj' => (int)$r['point_adjust'], 'why' => (string)$r['adjust_reason']]; }
    $blank = fn(string $n, string $g, bool $susp = false, int $adj = 0, string $why = '') => ['team' => $n, 'group' => $g, 'p' => 0, 'w' => 0, 'd' => 0, 'l' => 0, 'gf' => 0, 'ga' => 0, 'gd' => 0, 'pts' => 0, 'raw' => 0, 'adj' => $adj, 'why' => $why, 'susp' => $susp, 'form' => []];
    $tab = [];
    foreach ($teams as $k => $t) { $tab[$k] = $blank($t['name'], $t['group'], $t['susp'], $t['adj'], $t['why']); }
    foreach (comp_results($cid) as $r) {
        if ($r['hs'] === null || $r['as'] === null) { continue; }
        foreach ([[$r['home'], $r['hs'], $r['as']], [$r['away'], $r['as'], $r['hs']]] as [$n, $f, $a]) {
            $k = mb_strtolower($n);
            if (!isset($tab[$k])) { $tab[$k] = $blank($n, $format === 'Groups' ? 'Ungrouped' : ''); }
            $x =& $tab[$k];
            $x['p']++; $x['gf'] += $f; $x['ga'] += $a;
            if ($f > $a) { $x['w']++; $x['raw'] += 3; $x['form'][] = 'W'; } elseif ($f < $a) { $x['l']++; $x['form'][] = 'L'; } else { $x['d']++; $x['raw']++; $x['form'][] = 'D'; }
            $x['gd'] = $x['gf'] - $x['ga'];
            unset($x);
        }
    }
    $groups = [];
    foreach ($tab as $row) { $row['pts'] = $row['raw'] + $row['adj']; $row['form'] = array_slice($row['form'], -5); $groups[$row['group']][] = $row; }
    foreach ($groups as &$g) { usort($g, fn($a, $b) => [(int)$a['susp'], $b['pts'], $b['gd'], $b['gf'], $a['team']] <=> [(int)$b['susp'], $a['pts'], $a['gd'], $a['gf'], $b['team']]); }
    unset($g);
    ksort($groups);
    return $groups;
}

/** Teams that go through to the knockout stage, best seed first: [['team','group','rank'], ...]. */
function comp_qualifiers(int $cid): array
{
    $c = comp_row($cid);
    $groups = comp_standings($cid, (string)$c['format']);
    $q = [];
    if ($c['format'] === 'Groups') {
        $per = max(1, (int)$c['qualify_per_group']);
        foreach ($groups as $gname => $rows) {
            if ($gname === 'Ungrouped') { continue; }
            $rank = 0;
            foreach ($rows as $r) { if ($r['susp']) { continue; } $rank++; if ($rank > $per) { break; } $q[] = ['team' => $r['team'], 'group' => $gname, 'rank' => $rank, 'pts' => $r['pts'], 'gd' => $r['gd'], 'gf' => $r['gf']]; }
        }
    } else {
        $n = max(2, (int)$c['ko_teams']); $rank = 0;
        foreach ($groups[''] ?? [] as $r) { if ($r['susp']) { continue; } $rank++; if ($rank > $n) { break; } $q[] = ['team' => $r['team'], 'group' => '', 'rank' => $rank, 'pts' => $r['pts'], 'gd' => $r['gd'], 'gf' => $r['gf']]; }
    }
    return $q;
}

function comp_ko_round_name(int $round, int $rounds): string
{
    $left = $rounds - $round;
    return match (true) { $left === 0 => 'Final', $left === 1 => 'Semi-finals', $left === 2 => 'Quarter-finals', default => 'Round of ' . (2 ** ($left + 1)) };
}

/** Standard bracket order of seeds for a bracket of $n (power of 2): [1,16,8,9,...]. */
function comp_seed_order(int $n): array
{
    $o = [1];
    while (count($o) < $n) { $m = count($o) * 2 + 1; $next = []; foreach ($o as $s) { $next[] = $s; $next[] = $m - $s; } $o = $next; }
    return $o;
}

/** Builds (or rebuilds) the knockout bracket from the group / league table. Returns an error string or null. */
function comp_ko_generate(int $cid): ?string
{
    $c = comp_row($cid);
    $q = comp_qualifiers($cid);
    if (count($q) < 2) { return 'At least 2 qualified teams are needed. Check the table and the "teams that qualify" setting.'; }
    $b = 2; while ($b < count($q)) { $b *= 2; }
    $pairs = [];
    $byGroup = [];
    foreach ($q as $x) { $byGroup[$x['group']][$x['rank']] = $x['team']; }
    $gk = array_keys($byGroup);
    if ($c['format'] === 'Groups' && (int)$c['qualify_per_group'] === 2 && count($gk) >= 2 && count($gk) % 2 === 0 && count($q) === 2 * count($gk)) {
        for ($i = 0; $i < count($gk); $i += 2) { $pairs[] = [$byGroup[$gk[$i]][1], $byGroup[$gk[$i + 1]][2]]; }      // A1 v B2, C1 v D2 ...
        for ($i = 0; $i < count($gk); $i += 2) { $pairs[] = [$byGroup[$gk[$i + 1]][1], $byGroup[$gk[$i]][2]]; }      // B1 v A2, D1 v C2 ...
    } else {
        usort($q, fn($x, $y) => [$x['rank'], -$x['pts'], -$x['gd'], -$x['gf']] <=> [$y['rank'], -$y['pts'], -$y['gd'], -$y['gf']]);
        $seed = array_column($q, 'team');
        $order = comp_seed_order($b);
        for ($i = 0; $i < $b; $i += 2) { $pairs[] = [$seed[$order[$i] - 1] ?? 'BYE', $seed[$order[$i + 1] - 1] ?? 'BYE']; }
    }
    $pdo = db();
    $pdo->prepare('DELETE FROM comp_ko WHERE competition_id = ?')->execute([$cid]);
    $ins = $pdo->prepare('INSERT INTO comp_ko (competition_id, round_no, slot, team_a, team_b) VALUES (?, ?, ?, ?, ?)');
    $rounds = (int)log($b, 2); $ties = $b / 2;
    for ($r = 1; $r <= $rounds; $r++) {
        for ($s = 1; $s <= $ties; $s++) { $p = $r === 1 ? ($pairs[$s - 1] ?? [null, null]) : [null, null]; $ins->execute([$cid, $r, $s, $p[0], $p[1]]); }
        $ties /= 2;
    }
    comp_ko_propagate($cid);
    return null;
}

/** Winner of a tie (team name) or null while undecided. */
function comp_ko_winner(array $t): ?string
{
    if (($t['team_b'] ?? null) === 'BYE') { return $t['team_a']; }
    if (($t['team_a'] ?? null) === 'BYE') { return $t['team_b']; }
    if (!$t['team_a'] || !$t['team_b'] || $t['score_a'] === null || $t['score_b'] === null) { return null; }
    if ((int)$t['score_a'] !== (int)$t['score_b']) { return (int)$t['score_a'] > (int)$t['score_b'] ? $t['team_a'] : $t['team_b']; }
    if ($t['pen_a'] !== null && $t['pen_b'] !== null && (int)$t['pen_a'] !== (int)$t['pen_b']) { return (int)$t['pen_a'] > (int)$t['pen_b'] ? $t['team_a'] : $t['team_b']; }
    return null;
}

/** Fills each later round from the winners of the round before; clears scores of ties whose teams changed. */
function comp_ko_propagate(int $cid): void
{
    $pdo = db();
    $rows = $pdo->prepare('SELECT * FROM comp_ko WHERE competition_id = ? ORDER BY round_no, slot'); $rows->execute([$cid]);
    $by = [];
    foreach ($rows->fetchAll() as $t) { $by[$t['round_no']][$t['slot']] = $t; }
    $upd = $pdo->prepare('UPDATE comp_ko SET team_a = ?, team_b = ?, score_a = ?, score_b = ?, pen_a = ?, pen_b = ? WHERE id = ?');
    foreach ($by as $r => $ties) {
        if ($r === 1) { continue; }
        foreach ($ties as $s => $t) {
            $a = isset($by[$r - 1][2 * $s - 1]) ? comp_ko_winner($by[$r - 1][2 * $s - 1]) : null;
            $b = isset($by[$r - 1][2 * $s]) ? comp_ko_winner($by[$r - 1][2 * $s]) : null;
            if ($a !== $t['team_a'] || $b !== $t['team_b']) {
                $t['team_a'] = $a; $t['team_b'] = $b; $t['score_a'] = $t['score_b'] = $t['pen_a'] = $t['pen_b'] = null;
                $upd->execute([$a, $b, null, null, null, null, $t['id']]);
                $by[$r][$s] = $t;
            }
        }
    }
}

function comp_ko_rows(int $cid): array
{
    $st = db()->prepare('SELECT * FROM comp_ko WHERE competition_id = ? ORDER BY round_no, slot'); $st->execute([$cid]);
    $out = [];
    foreach ($st->fetchAll() as $t) { $out[(int)$t['round_no']][] = $t; }
    return $out;
}

/** Player leaderboards (offensive + defensive) for one competition (0 = all competitions). */
function comp_player_stats(int $cid): array
{
    $w = $cid > 0 ? 'WHERE m.competition_id = ' . $cid : '';
    return db()->query("SELECT p.id, p.full_name, p.public_code, COUNT(*) apps, SUM(s.goals) goals, SUM(s.assists) assists, SUM(s.goals + s.assists) contrib,
        SUM(s.penalty_goals) pens, SUM(s.tackles) tackles, SUM(s.interceptions) inter, SUM(s.clearances) clears, SUM(s.blocks) blocks, SUM(s.recoveries) recov,
        SUM(s.tackles + s.interceptions + s.clearances + s.blocks + s.recoveries) def_total, SUM(s.yellow_cards) yc, SUM(s.red_cards) rc, SUM(s.minutes_played) mins
        FROM match_statistics s JOIN matches m ON m.id = s.match_id JOIN players p ON p.id = s.player_id AND p.approval_status = 'approved' $w GROUP BY p.id ORDER BY goals DESC, assists DESC")->fetchAll();
}

/** Bracket as columns of ties (read-only). */
function comp_bracket_html(int $cid): string
{
    $ko = comp_ko_rows($cid);
    if (!$ko) { return ''; }
    $rounds = count($ko);
    $h = '<div class="ko-wrap"><div class="ko-bracket">';
    foreach ($ko as $r => $ties) {
        $h .= '<div class="ko-round"><div class="ko-title">' . e(comp_ko_round_name((int)$r, $rounds)) . '</div><div class="ko-ties">';
        foreach ($ties as $t) {
            $w = comp_ko_winner($t);
            $h .= '<div class="ko-tie">';
            foreach (['a', 'b'] as $side) {
                $name = $t['team_' . $side]; $sc = $t['score_' . $side]; $pen = $t['pen_' . $side];
                $cls = 'ko-team' . ($w !== null && $w === $name ? ' win' : '') . ($name === 'BYE' ? ' bye' : '');
                $h .= '<div class="' . $cls . '"><span class="ko-name">' . ($name === null ? '<i>TBD</i>' : e($name)) . '</span><span class="ko-sc">' . ($sc === null ? '' : (int)$sc . ($pen !== null ? ' <small>(' . (int)$pen . ')</small>' : '')) . '</span></div>';
            }
            $h .= '</div>';
        }
        $h .= '</div></div>';
    }
    $last = end($ko)[0] ?? null;
    $champ = $last ? comp_ko_winner($last) : null;
    if ($champ) { $h .= '<div class="ko-round"><div class="ko-title">Champion</div><div class="ko-ties"><div class="ko-tie ko-champ"><div class="ko-team win"><span class="ko-name"><i class="bi bi-trophy-fill"></i> ' . e($champ) . '</span></div></div></div></div>'; }
    return $h . '</div></div>';
}
