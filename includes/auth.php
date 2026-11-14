<?php
/**
 * Authentication: login, session checks, remember-me, logout.
 */
declare(strict_types=1);

const REMEMBER_COOKIE   = 'ft_remember';
const REMEMBER_DAYS     = 30;
const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_SECONDS    = 300;

function current_admin(): ?array
{
    static $admin = false;
    if ($admin !== false) {
        return $admin;
    }
    if (empty($_SESSION['admin_id'])) {
        return $admin = null;
    }
    $stmt = db()->prepare('SELECT id, username, email, must_change_password, last_login_at FROM admins WHERE id = ?');
    $stmt->execute([(int)$_SESSION['admin_id']]);
    return $admin = ($stmt->fetch() ?: null);
}

function is_logged_in(): bool
{
    if (current_admin() !== null) {
        return true;
    }
    return login_from_remember_cookie();
}

/**
 * Protect an admin page. Call at the very top of every admin page.
 * $allowPasswordChangePage = true only for the change-password page itself.
 */
function require_login(bool $allowPasswordChangePage = false): array
{
    if (!is_logged_in()) {
        redirect('admin/login.php');
    }
    $admin = current_admin();
    if ($admin === null) { // session pointed to a deleted admin
        logout_admin();
        redirect('admin/login.php');
    }
    if ((int)$admin['must_change_password'] === 1 && !$allowPasswordChangePage) {
        flash_set('warning', 'Please change the default password before continuing.');
        redirect('admin/change-password.php');
    }
    return $admin;
}

/* ---------- Login throttling (per session) ---------- */
function login_locked_seconds(): int
{
    $until = (int)($_SESSION['login_lock_until'] ?? 0);
    return max(0, $until - time());
}

function login_register_failure(): void
{
    $_SESSION['login_attempts'] = (int)($_SESSION['login_attempts'] ?? 0) + 1;
    if ($_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
        $_SESSION['login_lock_until'] = time() + LOCKOUT_SECONDS;
        $_SESSION['login_attempts']   = 0;
    }
}

/**
 * Try to log in with username OR email. Returns true on success.
 */
function attempt_login(string $identity, string $password, bool $remember): bool
{
    if (login_locked_seconds() > 0) {
        return false;
    }
    $stmt = db()->prepare('SELECT * FROM admins WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$identity, $identity]);
    $admin = $stmt->fetch();

    // Always run password_verify to keep timing similar when the user doesn't exist.
    $dummy = '$2y$10$usesomesillystringforsalt.usesomesillystringforsaltOOOOOOOOOO';
    $ok = password_verify($password, $admin['password_hash'] ?? $dummy);

    if (!$admin || !$ok) {
        login_register_failure();
        return false;
    }

    session_regenerate_id(true); // prevent session fixation
    $_SESSION['admin_id'] = (int)$admin['id'];
    unset($_SESSION['login_attempts'], $_SESSION['login_lock_until']);

    if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
        $upd = db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
        $upd->execute([password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
    }
    db()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?')->execute([$admin['id']]);

    if ($remember) {
        issue_remember_cookie((int)$admin['id']);
    }
    return true;
}

/* ---------- Remember me (selector + hashed validator) ---------- */
function issue_remember_cookie(int $adminId): void
{
    $selector  = bin2hex(random_bytes(8));
    $validator = bin2hex(random_bytes(32));
    $expires   = time() + REMEMBER_DAYS * 86400;

    db()->prepare('UPDATE admins SET remember_selector=?, remember_hash=?, remember_expires=? WHERE id=?')
        ->execute([$selector, hash('sha256', $validator), date('Y-m-d H:i:s', $expires), $adminId]);

    setcookie(REMEMBER_COOKIE, $selector . ':' . $validator, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function login_from_remember_cookie(): bool
{
    $cookie = $_COOKIE[REMEMBER_COOKIE] ?? '';
    if (!is_string($cookie) || !str_contains($cookie, ':')) {
        return false;
    }
    [$selector, $validator] = explode(':', $cookie, 2);
    if (!ctype_xdigit($selector) || !ctype_xdigit($validator)) {
        return false;
    }
    $stmt = db()->prepare('SELECT id, remember_hash, remember_expires FROM admins WHERE remember_selector = ?');
    $stmt->execute([$selector]);
    $row = $stmt->fetch();
    if (!$row || !$row['remember_hash'] || strtotime((string)$row['remember_expires']) < time()) {
        clear_remember_cookie();
        return false;
    }
    if (!hash_equals($row['remember_hash'], hash('sha256', $validator))) {
        clear_remember_cookie();
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$row['id'];
    issue_remember_cookie((int)$row['id']); // rotate token
    return current_admin() !== null;
}

function clear_remember_cookie(): void
{
    setcookie(REMEMBER_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
}

function logout_admin(): void
{
    if (!empty($_SESSION['admin_id'])) {
        db()->prepare('UPDATE admins SET remember_selector=NULL, remember_hash=NULL, remember_expires=NULL WHERE id=?')
            ->execute([(int)$_SESSION['admin_id']]);
    }
    clear_remember_cookie();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
    }
    session_destroy();
}

/** Password rules used when changing password. Returns error message or null. */
function password_policy_error(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Password must contain at least one letter and one number.';
    }
    if (strtolower($password) === 'admin@12345') {
        return 'Please choose a password different from the default one.';
    }
    return null;
}
