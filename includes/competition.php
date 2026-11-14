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
    if ($v === '1') { return; }
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
    $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute(['competition_v', '1']);
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

const COMP_TABS = ['comp-teams.php' => ['Teams & groups', 'bi-people'], 'comp-matches.php' => ['Fixtures & results', 'bi-calendar-check']];

function comp_admin_bar(string $self): void
{
    $cid = admin_comp_id();
    $rows = db()->query('SELECT id, name, `format` FROM competitions ORDER BY name')->fetchAll();
    echo '<div class="ft-card p-3 mb-3"><form method="get" class="d-flex flex-wrap gap-2 align-items-center mb-2"><label class="fw-semibold mb-0"><i class="bi bi-award"></i> Competition</label>'
       . '<select class="form-select w-auto" name="comp" onchange="this.form.submit()">';
    foreach ($rows as $r) { echo '<option value="' . (int)$r['id'] . '"' . ((int)$r['id'] === $cid ? ' selected' : '') . '>' . e($r['name']) . ' (' . e($r['format']) . ')</option>'; }
    echo '</select><a class="btn btn-outline-theme btn-sm" target="_blank" rel="noopener" href="' . e(url('competitions.php?c=' . $cid)) . '"><i class="bi bi-eye"></i> View public table</a>'
       . '<a class="btn btn-outline-theme btn-sm" href="' . e(url('admin/competitions.php?action=edit&id=' . $cid)) . '"><i class="bi bi-gear"></i> Format (League / Groups)</a></form><div class="tp-tabs">';
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

/** Standings. Returns ['' => rows] for a league, or ['Group A' => rows, ...] for groups. */
function comp_standings(int $cid, string $format): array
{
    $st = db()->prepare('SELECT team_name, group_name FROM comp_teams WHERE competition_id = ? ORDER BY group_name, team_name'); $st->execute([$cid]);
    $teams = [];
    foreach ($st->fetchAll() as $r) { $teams[mb_strtolower($r['team_name'])] = ['name' => $r['team_name'], 'group' => $format === 'Groups' ? ($r['group_name'] ?: 'Ungrouped') : '']; }
    $blank = fn(string $n, string $g) => ['team' => $n, 'group' => $g, 'p' => 0, 'w' => 0, 'd' => 0, 'l' => 0, 'gf' => 0, 'ga' => 0, 'gd' => 0, 'pts' => 0, 'form' => []];
    $tab = [];
    foreach ($teams as $k => $t) { $tab[$k] = $blank($t['name'], $t['group']); }
    $res = comp_results($cid);
    // group of a team that only appears in results: take it from its opponent when possible, else leave ungrouped
    foreach ($res as $r) {
        if ($r['hs'] === null || $r['as'] === null) { continue; }
        foreach ([[$r['home'], $r['hs'], $r['as']], [$r['away'], $r['as'], $r['hs']]] as [$n, $f, $a]) {
            $k = mb_strtolower($n);
            if (!isset($tab[$k])) { $tab[$k] = $blank($n, $format === 'Groups' ? 'Ungrouped' : ''); }
            $x =& $tab[$k];
            $x['p']++; $x['gf'] += $f; $x['ga'] += $a;
            if ($f > $a) { $x['w']++; $x['pts'] += 3; $x['form'][] = 'W'; } elseif ($f < $a) { $x['l']++; $x['form'][] = 'L'; } else { $x['d']++; $x['pts']++; $x['form'][] = 'D'; }
            $x['gd'] = $x['gf'] - $x['ga'];
            unset($x);
        }
    }
    $groups = [];
    foreach ($tab as $row) { $row['form'] = array_slice($row['form'], -5); $groups[$row['group']][] = $row; }
    foreach ($groups as &$g) { usort($g, fn($a, $b) => [$b['pts'], $b['gd'], $b['gf'], $a['team']] <=> [$a['pts'], $a['gd'], $a['gf'], $b['team']]); }
    unset($g);
    ksort($groups);
    return $groups;
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
