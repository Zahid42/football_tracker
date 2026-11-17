<?php
/** Player auction for competitions: pool of players by position, team budgets, live bidding, sales move players into squads. */
declare(strict_types=1);

function ft_auction_ensure(): void
{
    $pdo = db();
    $v = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'auction_v'")->fetchColumn();
    if ($v === '1') { return; }
    $opt = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $pdo->exec("CREATE TABLE IF NOT EXISTS `auctions` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `competition_id` INT UNSIGNED NOT NULL,
        `budget` INT UNSIGNED NOT NULL DEFAULT 1000, `squad_size` TINYINT UNSIGNED NOT NULL DEFAULT 5, `bid_step` INT UNSIGNED NOT NULL DEFAULT 10, `min_price` INT UNSIGNED NOT NULL DEFAULT 10,
        `status` ENUM('setup','live','closed') NOT NULL DEFAULT 'setup', `current_pool_id` INT UNSIGNED DEFAULT NULL, `current_bid` INT UNSIGNED NOT NULL DEFAULT 0, `current_team` VARCHAR(100) DEFAULT NULL,
        PRIMARY KEY (`id`), UNIQUE KEY `uq_auc_comp` (`competition_id`),
        CONSTRAINT `fk_auc_comp` FOREIGN KEY (`competition_id`) REFERENCES `competitions`(`id`) ON DELETE CASCADE)" . $opt);
    $pdo->exec("CREATE TABLE IF NOT EXISTS `auction_pool` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `auction_id` INT UNSIGNED NOT NULL, `player_id` INT UNSIGNED NOT NULL,
        `category` VARCHAR(10) DEFAULT NULL, `base_price` INT UNSIGNED NOT NULL DEFAULT 10, `status` ENUM('pending','sold','unsold') NOT NULL DEFAULT 'pending',
        `sold_team` VARCHAR(100) DEFAULT NULL, `sold_price` INT UNSIGNED DEFAULT NULL, `sold_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`), UNIQUE KEY `uq_pool` (`auction_id`,`player_id`),
        CONSTRAINT `fk_pool_auc` FOREIGN KEY (`auction_id`) REFERENCES `auctions`(`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_pool_pl` FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE)" . $opt);
    $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute(['auction_v', '1']);
}

function auc_get(int $cid): ?array
{
    $st = db()->prepare('SELECT * FROM auctions WHERE competition_id = ?'); $st->execute([$cid]);
    return $st->fetch() ?: null;
}

function auc_pool(int $aid): array
{
    $st = db()->prepare('SELECT ap.*, p.full_name, p.photo, p.public_code, p.jersey_number FROM auction_pool ap JOIN players p ON p.id = ap.player_id WHERE ap.auction_id = ? ORDER BY FIELD(ap.category, \'GK\',\'CB\',\'LB\',\'RB\',\'CDM\',\'CM\',\'CAM\',\'LM\',\'RM\',\'LW\',\'RW\',\'ST\',\'CF\'), p.full_name');
    $st->execute([$aid]);
    return $st->fetchAll();
}

/** Active teams of the competition with money and squad numbers. */
function auc_teams(array $a): array
{
    $st = db()->prepare("SELECT team_name FROM comp_teams WHERE competition_id = ? AND status = 'active' ORDER BY team_name"); $st->execute([$a['competition_id']]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $n) { $out[$n] = ['name' => $n, 'spent' => 0, 'count' => 0, 'players' => []]; }
    foreach (auc_pool((int)$a['id']) as $r) {
        if ($r['status'] === 'sold' && isset($out[$r['sold_team']])) { $out[$r['sold_team']]['spent'] += (int)$r['sold_price']; $out[$r['sold_team']]['count']++; $out[$r['sold_team']]['players'][] = $r; }
    }
    foreach ($out as &$t) {
        $t['left'] = (int)$a['budget'] - $t['spent'];
        $t['slots'] = max(0, (int)$a['squad_size'] - $t['count']);
        // keep enough money for the remaining slots at the minimum price
        $t['max_bid'] = $t['slots'] > 0 ? $t['left'] - ($t['slots'] - 1) * (int)$a['min_price'] : 0;
    }
    return $out;
}

/** Runs one auctioneer action. Returns [type, message]. */
function auc_action(array $a, string $op, array $in): array
{
    $pdo = db(); $aid = (int)$a['id'];
    $pool = fn(int $id) => ($s = $pdo->prepare('SELECT ap.*, p.full_name FROM auction_pool ap JOIN players p ON p.id = ap.player_id WHERE ap.id = ? AND ap.auction_id = ?')) && $s->execute([$id, $aid]) ? $s->fetch() : false;
    switch ($op) {
        case 'status':
            $to = in_array($in['to'] ?? '', ['setup', 'live', 'closed'], true) ? $in['to'] : 'setup';
            if ($to === 'live' && !(int)$pdo->query('SELECT COUNT(*) FROM auction_pool WHERE auction_id = ' . $aid)->fetchColumn()) { return ['danger', 'Add players to the pool first.']; }
            $pdo->prepare('UPDATE auctions SET status = ?, current_pool_id = NULL, current_bid = 0, current_team = NULL WHERE id = ?')->execute([$to, $aid]);
            return ['success', 'Auction is now ' . $to . '.'];
        case 'block':
            if ($a['status'] !== 'live') { return ['danger', 'Start the auction first.']; }
            $p = $pool((int)($in['pool'] ?? 0));
            if (!$p || $p['status'] !== 'pending') { return ['danger', 'That player is not waiting in the pool.']; }
            $pdo->prepare('UPDATE auctions SET current_pool_id = ?, current_bid = 0, current_team = NULL WHERE id = ?')->execute([$p['id'], $aid]);
            return ['success', $p['full_name'] . ' is up for bidding at ' . (int)$p['base_price'] . '.'];
        case 'bid':
            $p = $pool((int)$a['current_pool_id']);
            if ($a['status'] !== 'live' || !$p) { return ['danger', 'No player is up for bidding.']; }
            $teams = auc_teams($a); $t = $teams[$in['team'] ?? ''] ?? null;
            if (!$t) { return ['danger', 'Unknown or suspended team.']; }
            if ($t['slots'] < 1) { return ['danger', $t['name'] . ' already has a full squad.']; }
            $amt = (int)($in['amount'] ?? 0);
            if ($amt <= 0) { $amt = $a['current_team'] === null ? (int)$p['base_price'] : (int)$a['current_bid'] + (int)$a['bid_step']; }
            if ($amt < (int)$p['base_price']) { return ['danger', 'Bid is below the base price of ' . (int)$p['base_price'] . '.']; }
            if ($a['current_team'] !== null && $amt <= (int)$a['current_bid']) { return ['danger', 'Bid must be higher than ' . (int)$a['current_bid'] . '.']; }
            if ($amt > $t['max_bid']) { return ['danger', $t['name'] . ' can bid at most ' . max(0, $t['max_bid']) . ' (it must keep money for its remaining ' . ($t['slots'] - 1) . ' slot(s)).']; }
            $pdo->prepare('UPDATE auctions SET current_bid = ?, current_team = ? WHERE id = ?')->execute([$amt, $t['name'], $aid]);
            return ['success', $t['name'] . ' bids ' . $amt . '.'];
        case 'sold':
            $p = $pool((int)$a['current_pool_id']);
            if (!$p || $a['current_team'] === null) { return ['danger', 'There is no bid to accept.']; }
            auc_sell($a, $p, (string)$a['current_team'], (int)$a['current_bid']);
            return ['success', $p['full_name'] . ' sold to ' . $a['current_team'] . ' for ' . (int)$a['current_bid'] . '.'];
        case 'unsold':
            $p = $pool((int)$a['current_pool_id']);
            if (!$p) { return ['danger', 'No player is up for bidding.']; }
            $pdo->prepare("UPDATE auction_pool SET status = 'unsold' WHERE id = ?")->execute([$p['id']]);
            $pdo->prepare('UPDATE auctions SET current_pool_id = NULL, current_bid = 0, current_team = NULL WHERE id = ?')->execute([$aid]);
            return ['success', $p['full_name'] . ' went unsold.'];
        case 'return':   // undo a sale / unsold
            $p = $pool((int)($in['pool'] ?? 0));
            if (!$p || $p['status'] === 'pending') { return ['danger', 'Nothing to undo.']; }
            if ($p['status'] === 'sold') { auc_unsell($p); }
            $pdo->prepare("UPDATE auction_pool SET status = 'pending', sold_team = NULL, sold_price = NULL, sold_at = NULL WHERE id = ?")->execute([$p['id']]);
            return ['success', $p['full_name'] . ' is back in the pool.'];
        case 'remove':
            $p = $pool((int)($in['pool'] ?? 0));
            if (!$p) { return ['danger', 'Not found.']; }
            if ($p['status'] === 'sold') { auc_unsell($p); }
            if ((int)$a['current_pool_id'] === (int)$p['id']) { $pdo->prepare('UPDATE auctions SET current_pool_id = NULL, current_bid = 0, current_team = NULL WHERE id = ?')->execute([$aid]); }
            $pdo->prepare('DELETE FROM auction_pool WHERE id = ?')->execute([$p['id']]);
            return ['success', $p['full_name'] . ' removed from the pool.'];
    }
    return ['danger', 'Unknown action.'];
}

function auc_team_row(string $name): ?array
{
    $st = db()->prepare('SELECT * FROM teams WHERE name = ? ORDER BY id LIMIT 1'); $st->execute([$name]);
    return $st->fetch() ?: null;
}

/** Records the sale and moves the player into the team's squad (when the team exists on the site). */
function auc_sell(array $a, array $p, string $team, int $price): void
{
    $pdo = db();
    $pdo->prepare("UPDATE auction_pool SET status = 'sold', sold_team = ?, sold_price = ?, sold_at = NOW() WHERE id = ?")->execute([$team, $price, $p['id']]);
    $pdo->prepare('UPDATE auctions SET current_pool_id = NULL, current_bid = 0, current_team = NULL WHERE id = ?')->execute([$a['id']]);
    if ($t = auc_team_row($team)) {
        $pl = $pdo->prepare('SELECT full_name, photo, preferred_position, jersey_number FROM players WHERE id = ?'); $pl->execute([$p['player_id']]); $pl = $pl->fetch();
        $has = $pdo->prepare('SELECT COUNT(*) FROM team_squad WHERE team_id = ? AND player_id = ?'); $has->execute([$t['id'], $p['player_id']]);
        if ($pl && !(int)$has->fetchColumn()) {
            $pdo->prepare('INSERT INTO team_squad (team_id, shirt_number, name, position, photo, player_id, notes) VALUES (?,?,?,?,?,?,?)')
                ->execute([$t['id'], $pl['jersey_number'], $pl['full_name'], $pl['preferred_position'], $pl['photo'], $p['player_id'], 'Auction: ' . $price]);
        }
        $h = $pdo->prepare('SELECT COUNT(*) FROM player_teams WHERE player_id = ? AND team_id = ?'); $h->execute([$p['player_id'], $t['id']]);
        if (!(int)$h->fetchColumn()) { $pdo->prepare('INSERT INTO player_teams (player_id, team_id, is_current) VALUES (?, ?, 0)')->execute([$p['player_id'], $t['id']]); }
    }
}

function auc_unsell(array $p): void
{
    if ($p['sold_team'] && ($t = auc_team_row((string)$p['sold_team']))) {
        db()->prepare("DELETE FROM team_squad WHERE team_id = ? AND player_id = ? AND notes LIKE 'Auction:%'")->execute([$t['id'], $p['player_id']]);
    }
}
