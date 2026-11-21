<?php
/**
 * Member accounts (sign up / sign in) and e-mail validation.
 * A member account belongs to one player profile; the profile stays hidden until the admin approves it.
 */
declare(strict_types=1);

function ft_members_ensure(): void
{
    $pdo = db();
    $v = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'members_v'")->fetchColumn();
    if ($v === '3') { return; }
    $pdo->exec("CREATE TABLE IF NOT EXISTS `members` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `player_id` INT UNSIGNED DEFAULT NULL,
        `email` VARCHAR(120) NOT NULL, `password_hash` VARCHAR(255) NOT NULL, `created_at` DATETIME NOT NULL, `last_login_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`), UNIQUE KEY `uq_member_email` (`email`), KEY `idx_member_player` (`player_id`),
        CONSTRAINT `fk_member_player` FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // v2: verified e-mail (6-digit code), sign-in by player ID, outgoing mail log
    if (!$pdo->query("SHOW COLUMNS FROM members LIKE 'email_verified'")->fetch()) {
        $pdo->exec('ALTER TABLE members ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN verify_hash VARCHAR(64) DEFAULT NULL, ADD COLUMN verify_expires DATETIME DEFAULT NULL, ADD COLUMN verify_tries TINYINT UNSIGNED NOT NULL DEFAULT 0, ADD COLUMN verify_sent_at DATETIME DEFAULT NULL');
        $pdo->exec('UPDATE members SET email_verified = 1');   // accounts made before verification existed
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS `mail_log` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `to_email` VARCHAR(120) NOT NULL, `subject` VARCHAR(200) NOT NULL, `ok` TINYINT(1) NOT NULL, `error` VARCHAR(255) DEFAULT NULL, `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // v3: blocking, admin note, sign-in count + sign-in history, requests for new players / teams / competitions
    if (!$pdo->query("SHOW COLUMNS FROM members LIKE 'is_blocked'")->fetch()) {
        $pdo->exec('ALTER TABLE members ADD COLUMN is_blocked TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN admin_note VARCHAR(500) DEFAULT NULL, ADD COLUMN login_count INT UNSIGNED NOT NULL DEFAULT 0');
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS `member_logins` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `member_id` INT UNSIGNED DEFAULT NULL, `ident` VARCHAR(120) NOT NULL, `ok` TINYINT(1) NOT NULL,
        `note` VARCHAR(120) DEFAULT NULL, `ip` VARCHAR(45) DEFAULT NULL, `agent` VARCHAR(160) DEFAULT NULL, `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`), KEY `idx_ml_member` (`member_id`), KEY `idx_ml_time` (`created_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `member_requests` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `member_id` INT UNSIGNED NOT NULL, `kind` VARCHAR(12) NOT NULL, `title` VARCHAR(150) NOT NULL,
        `details` TEXT DEFAULT NULL, `status` VARCHAR(10) NOT NULL DEFAULT 'pending', `admin_reply` VARCHAR(500) DEFAULT NULL, `result_id` INT UNSIGNED DEFAULT NULL, `created_at` DATETIME NOT NULL, `decided_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`), KEY `idx_mr_member` (`member_id`), KEY `idx_mr_status` (`status`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute(['members_v', '3']);
}

const EMAIL_TYPOS = ['gmial.com' => 'gmail.com', 'gmai.com' => 'gmail.com', 'gamil.com' => 'gmail.com', 'gmail.co' => 'gmail.com', 'gmail.con' => 'gmail.com', 'gnail.com' => 'gmail.com',
    'yaho.com' => 'yahoo.com', 'yahooo.com' => 'yahoo.com', 'yahoo.con' => 'yahoo.com', 'hotmial.com' => 'hotmail.com', 'hotmail.con' => 'hotmail.com', 'outlok.com' => 'outlook.com',
    'outlook.con' => 'outlook.com', 'iclod.com' => 'icloud.com', 'icloud.con' => 'icloud.com'];
const EMAIL_DISPOSABLE = ['mailinator.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.com', 'temp-mail.org', 'yopmail.com', 'trashmail.com', 'sharklasers.com', 'getnada.com',
    'throwawaymail.com', 'maildrop.cc', 'dispostable.com', 'fakeinbox.com', 'mintemail.com', 'mailnesia.com'];

/**
 * Checks an e-mail address. Returns ['ok' => bool, 'email' => normalised, 'error' => ?string, 'suggest' => ?string].
 * Steps: format, length, domain shape, disposable list, common-typo hint, and a DNS lookup for mail servers
 * (skipped when this computer cannot reach DNS at all, so a valid address is never rejected because of that).
 */
function ft_check_email(string $raw, bool $dns = true): array
{
    $email = mb_strtolower(trim($raw));
    $r = ['ok' => false, 'email' => $email, 'error' => null, 'suggest' => null];
    if ($email === '') { $r['error'] = 'Please enter your e-mail address.'; return $r; }
    if (strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || str_contains($email, '..') || substr_count($email, '@') !== 1) { $r['error'] = 'That does not look like a valid e-mail address.'; return $r; }
    [$local, $domain] = explode('@', $email);
    if (strlen($local) > 64 || !preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)*\.[a-z]{2,24}$/', $domain) || str_starts_with($domain, '-')) { $r['error'] = 'The part after @ is not a valid domain.'; return $r; }
    if (isset(EMAIL_TYPOS[$domain])) { $r['suggest'] = $local . '@' . EMAIL_TYPOS[$domain]; $r['error'] = 'Did you mean ' . $r['suggest'] . '?'; return $r; }
    if (in_array($domain, EMAIL_DISPOSABLE, true)) { $r['error'] = 'Temporary e-mail addresses are not accepted. Please use your real address.'; return $r; }
    if ($dns && function_exists('checkdnsrr')) {
        $has = @checkdnsrr($domain . '.', 'MX') || @checkdnsrr($domain . '.', 'A');
        if (!$has && (@checkdnsrr('gmail.com.', 'MX'))) { $r['error'] = 'We could not find a mail server for ' . $domain . '. Please check the spelling.'; return $r; }
    }
    $r['ok'] = true;
    return $r;
}

function ft_email_taken(string $email): bool
{
    $q = db()->prepare('SELECT (SELECT COUNT(*) FROM members WHERE email = ?) + (SELECT COUNT(*) FROM players WHERE LOWER(email) = ?)');
    $q->execute([$email, $email]);
    return (int)$q->fetchColumn() > 0;
}

function ft_password_error(string $pw): ?string
{
    if (strlen($pw) < 8) { return 'Password must be at least 8 characters.'; }
    if (strlen($pw) > 72) { return 'Password is too long (max 72 characters).'; }
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) { return 'Password needs at least one letter and one number.'; }
    return null;
}

function current_member(): ?array
{
    static $m = false;
    if ($m !== false) { return $m; }
    if (empty($_SESSION['member_id'])) { return $m = null; }
    $q = db()->prepare('SELECT m.*, p.full_name, p.approval_status, p.public_code FROM members m LEFT JOIN players p ON p.id = m.player_id WHERE m.id = ?');
    $q->execute([(int)$_SESSION['member_id']]);
    $m = $q->fetch() ?: null;
    if ($m && !empty($m['is_blocked'])) { unset($_SESSION['member_id']); $m = null; }   // blocked by the admin: signed out at once
    return $m;
}

function member_login(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['member_id'] = $id;
    db()->prepare('UPDATE members SET last_login_at = NOW(), login_count = login_count + 1 WHERE id = ?')->execute([$id]);
}

/** Writes one line of the sign-in history (successful or not). Never breaks the page. */
function member_log_signin(?int $mid, string $ident, bool $ok, string $note = ''): void
{
    try {
        db()->prepare('INSERT INTO member_logins (member_id, ident, ok, note, ip, agent, created_at) VALUES (?,?,?,?,?,?,NOW())')
            ->execute([$mid, mb_substr($ident, 0, 120), $ok ? 1 : 0, $note !== '' ? mb_substr($note, 0, 120) : null, mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 160)]);
        db()->exec("DELETE FROM member_logins WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)");
    } catch (Throwable $e) { /* history is a bonus */ }
}

/** Finds the account for a sign-in name: an e-mail address or the 6-digit player ID. */
function member_find_for_signin(string $ident): ?array
{
    $ident = trim($ident);
    if (str_contains($ident, '@')) {
        $q = db()->prepare('SELECT m.id, m.password_hash, m.email, m.email_verified, m.is_blocked FROM members m WHERE m.email = ?'); $q->execute([mb_strtolower($ident)]);
    } else {
        $q = db()->prepare('SELECT m.id, m.password_hash, m.email, m.email_verified, m.is_blocked FROM members m JOIN players p ON p.id = m.player_id WHERE p.public_code = ?'); $q->execute([preg_replace('/\D/', '', $ident)]);
    }
    return $q->fetch() ?: null;
}

/* ---------------- Requests for new players / teams / competitions ---------------- */

const MEMBER_REQUEST_KINDS = ['player' => 'Player', 'team' => 'Team', 'competition' => 'Competition'];

/** Details of a request are stored as JSON; this returns them as an array. */
function request_details(array $r): array { $d = json_decode((string)($r['details'] ?? ''), true); return is_array($d) ? $d : []; }

/**
 * Creates the real record for an approved request. Returns [?int id, ?string public_code, string message].
 * Player: approved profile (joins the chosen team's squad). Team: new team. Competition: new competition.
 */
function request_apply(array $r): array
{
    $pdo = db(); $d = request_details($r); $title = trim((string)$r['title']);
    if ($r['kind'] === 'player') {
        $code = ft_new_code();
        $pdo->prepare("INSERT INTO players (full_name, preferred_position, jersey_number, date_of_birth, is_active, approval_status, public_code, registered_at) VALUES (?, ?, ?, ?, 1, 'approved', ?, NOW())")
            ->execute([$title, in_array($d['position'] ?? '', POSITIONS, true) ? $d['position'] : null, ($d['jersey'] ?? '') !== '' ? (int)$d['jersey'] : null, ($d['dob'] ?? '') !== '' ? $d['dob'] : null, $code]);
        $id = (int)$pdo->lastInsertId(); $msg = 'Player created.';
        if (!empty($d['team_id']) && (int)$d['team_id'] > 0) { $res = squad_add_player($id, (int)$d['team_id']); $msg .= $res === 'added' ? ' Added to the team squad.' : ' Team added to the profile.'; }
        return [$id, $code, $msg];
    }
    if ($r['kind'] === 'team') {
        $code = ft_new_code();
        $pdo->prepare("INSERT INTO teams (name, short_name, founded_year, description, status, public_code) VALUES (?, ?, ?, ?, 'active', ?)")
            ->execute([$title, ($d['short_name'] ?? '') !== '' ? mb_substr((string)$d['short_name'], 0, 10) : null, ($d['founded'] ?? '') !== '' ? (int)$d['founded'] : null, ($d['note'] ?? '') !== '' ? (string)$d['note'] : null, $code]);
        return [(int)$pdo->lastInsertId(), $code, 'Team created.'];
    }
    $code = ft_new_code();
    $type = in_array($d['type'] ?? '', ['Tournament', 'League', 'Friendly', 'Training', 'Other'], true) ? $d['type'] : 'Tournament';
    $fmt = ($d['format'] ?? '') === 'Groups' ? 'Groups' : 'League';
    $pdo->prepare('INSERT INTO competitions (name, season_id, type, format, organizer, description, public_code) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$title, !empty($d['season_id']) ? (int)$d['season_id'] : null, $type, $fmt, ($d['organizer'] ?? '') !== '' ? (string)$d['organizer'] : null, ($d['note'] ?? '') !== '' ? (string)$d['note'] : null, $code]);
    return [(int)$pdo->lastInsertId(), $code, 'Competition created.'];
}

/** E-mail the member about the decision on a request (best effort). */
function request_notify(array $r, string $outcome, ?string $code = null): void
{
    $q = db()->prepare('SELECT m.email, p.full_name FROM members m LEFT JOIN players p ON p.id = m.player_id WHERE m.id = ?'); $q->execute([$r['member_id']]);
    if (!($m = $q->fetch())) { return; }
    $kind = strtolower(MEMBER_REQUEST_KINDS[$r['kind']] ?? $r['kind']); $site = setting('site_title', 'Football Tracker');
    $paras = ['Hi ' . ($m['full_name'] ?: 'there') . ',', 'Your request to add the ' . $kind . ' <b>' . e((string)$r['title']) . '</b> was <b>' . ($outcome === 'approved' ? 'approved' : 'not approved') . '</b>.'];
    if ($outcome === 'approved' && $code) { $paras[] = 'Its ID is <b>' . e($code) . '</b>. It is now on the site.'; }
    if (!empty($r['admin_reply'])) { $paras[] = 'Message from the admin: ' . e((string)$r['admin_reply']); }
    ft_mail_send($m['email'], 'Your ' . $kind . ' request: ' . ($outcome === 'approved' ? 'approved' : 'not approved'), $site, $paras);
}

function pending_requests_count(): int
{
    try { return (int)db()->query("SELECT COUNT(*) FROM member_requests WHERE status = 'pending'")->fetchColumn(); } catch (Throwable $e) { return 0; }
}


/** Starts (or restarts) e-mail verification: stores a hashed 6-digit code and e-mails it. Returns [bool sent, ?string error]. */
function member_send_verification(int $mid): array
{
    $q = db()->prepare('SELECT m.*, p.full_name FROM members m LEFT JOIN players p ON p.id = m.player_id WHERE m.id = ?'); $q->execute([$mid]);
    $m = $q->fetch();
    if (!$m) { return [false, 'Account not found.']; }
    $pcode = member_ensure_code($mid);
    $code = (string)random_int(100000, 999999);
    db()->prepare('UPDATE members SET verify_hash = ?, verify_expires = DATE_ADD(NOW(), INTERVAL 30 MINUTE), verify_tries = 0, verify_sent_at = NOW() WHERE id = ?')->execute([hash('sha256', $code), $mid]);
    $link = abs_url('verify.php?e=' . rawurlencode($m['email']) . '&c=' . $code);
    $site = setting('site_title', 'Football Tracker');
    return ft_mail_send($m['email'], 'Your verification code: ' . $code, $site, [
        'Hi ' . ($m['full_name'] ?: 'there') . ',',
        'Use this code to verify your e-mail address:',
        '<b style="font-size:28px;letter-spacing:6px">' . $code . '</b>',
        'It expires in 30 minutes. Or open this link: <a href="' . e($link) . '">' . e($link) . '</a>',
        $pcode ? 'Your player ID is <b style="font-size:20px;letter-spacing:3px">' . e($pcode) . '</b>. Keep it: you sign in with this ID and your password.' : '',
        'If you did not sign up, you can ignore this message.',
    ]);
}

/** The player ID (6-digit public code) of a member's profile; one is created when the profile has none yet. */
function member_ensure_code(int $mid): string
{
    $q = db()->prepare('SELECT p.id, p.public_code FROM members m JOIN players p ON p.id = m.player_id WHERE m.id = ?'); $q->execute([$mid]);
    $p = $q->fetch();
    if (!$p) { return ''; }
    if ($p['public_code']) { return (string)$p['public_code']; }
    $c = ft_new_code();
    db()->prepare('UPDATE players SET public_code = ? WHERE id = ?')->execute([$c, $p['id']]);
    return $c;
}

/** Checks a code. Returns null when verified, else an error message. */
function member_check_code(string $email, string $code): ?string
{
    $q = db()->prepare('SELECT *, (verify_expires < NOW()) AS expired FROM members WHERE email = ?'); $q->execute([mb_strtolower(trim($email))]);
    $m = $q->fetch();
    if (!$m) { return 'Wrong code or e-mail.'; }
    if ($m['email_verified']) { return null; }
    if ((int)$m['verify_tries'] >= 5) { return 'Too many wrong attempts. Please request a new code.'; }
    if (!$m['verify_hash'] || $m['expired']) { return 'This code has expired. Please request a new one.'; }
    if (!hash_equals((string)$m['verify_hash'], hash('sha256', trim($code)))) {
        db()->prepare('UPDATE members SET verify_tries = verify_tries + 1 WHERE id = ?')->execute([$m['id']]);
        return 'Wrong code or e-mail.';
    }
    member_mark_verified((int)$m['id']);
    return null;
}

/** Marks the e-mail verified and e-mails the player his ID. */
function member_mark_verified(int $mid): void
{
    db()->prepare('UPDATE members SET email_verified = 1, verify_hash = NULL, verify_expires = NULL WHERE id = ?')->execute([$mid]);
    member_send_id($mid, true);
}

function member_send_id(int $mid, bool $welcome = false): array
{
    $q = db()->prepare('SELECT m.email, p.full_name, p.public_code FROM members m JOIN players p ON p.id = m.player_id WHERE m.id = ?'); $q->execute([$mid]);
    $m = $q->fetch();
    if (!$m) { return [false, 'No player profile.']; }
    if (!$m['public_code']) { $m['public_code'] = member_ensure_code($mid); }
    $site = setting('site_title', 'Football Tracker'); $in = abs_url('signin.php');
    return ft_mail_send($m['email'], 'Your player ID: ' . $m['public_code'], $site, [
        'Hi ' . $m['full_name'] . ',',
        $welcome ? 'Your e-mail is verified. Your player ID is:' : 'Your player ID is:',
        '<b style="font-size:30px;letter-spacing:4px">' . e((string)$m['public_code']) . '</b>',
        'Sign in with this ID and the password you chose: <a href="' . e($in) . '">' . e($in) . '</a>',
        'You can edit your own profile. It appears on the site after the admin approves it.',
    ]);
}

function member_send_approved(int $pid): void
{
    $q = db()->prepare('SELECT m.email, p.full_name, p.public_code FROM members m JOIN players p ON p.id = m.player_id WHERE p.id = ? AND m.email_verified = 1'); $q->execute([$pid]);
    if (!($m = $q->fetch())) { return; }
    if (!$m['public_code']) { $m['public_code'] = ft_new_code(); db()->prepare('UPDATE players SET public_code = ? WHERE id = ?')->execute([$m['public_code'], $pid]); }
    $site = setting('site_title', 'Football Tracker');
    ft_mail_send($m['email'], 'Your profile is approved', $site, [
        'Hi ' . $m['full_name'] . ',',
        'Good news: the admin approved your profile and it is now visible on the site.',
        'Your player ID: <b>' . e((string)$m['public_code']) . '</b>. Sign in with it any time to update your profile.',
    ]);
}
