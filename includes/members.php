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
    if ($v === '1') { return; }
    $pdo->exec("CREATE TABLE IF NOT EXISTS `members` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `player_id` INT UNSIGNED DEFAULT NULL,
        `email` VARCHAR(120) NOT NULL, `password_hash` VARCHAR(255) NOT NULL, `created_at` DATETIME NOT NULL, `last_login_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`), UNIQUE KEY `uq_member_email` (`email`), KEY `idx_member_player` (`player_id`),
        CONSTRAINT `fk_member_player` FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute(['members_v', '1']);
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
