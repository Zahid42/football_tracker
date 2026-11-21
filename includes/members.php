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
    if ($v === '2') { return; }
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
    $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute(['members_v', '2']);
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
    return $m = ($q->fetch() ?: null);
}

function member_login(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['member_id'] = $id;
    db()->prepare('UPDATE members SET last_login_at = NOW() WHERE id = ?')->execute([$id]);
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
