<?php
/**
 * Application configuration + bootstrap (session, timezone, constants).
 * Every page includes this file first.
 */
declare(strict_types=1);

// Set to false once everything works to hide PHP errors.
const APP_DEBUG = true;

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
}

date_default_timezone_set('Asia/Dhaka');

// URL path where the app lives (no trailing slash).
const BASE_URL = '/football_tracker';

// Filesystem root of the app.
define('ROOT_PATH', dirname(__DIR__));

// Uploads
const UPLOAD_DIR_NAME   = 'uploads';
const MAX_UPLOAD_BYTES  = 3 * 1024 * 1024; // 3 MB
const ALLOWED_IMG_EXT   = ['jpg', 'jpeg', 'png', 'webp'];
const ALLOWED_IMG_MIME  = ['image/jpeg', 'image/png', 'image/webp'];

// Lists used by forms / validation
const POSITIONS = ['GK','CB','LB','RB','CDM','CM','CAM','LM','RM','LW','RW','ST','CF'];

// Session hardening
if (session_status() === PHP_SESSION_NONE) {
    session_name('FTSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

// Security headers (safe for CDN usage)
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

require_once __DIR__ . '/database.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/csrf.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/player.php';
require_once ROOT_PATH . '/includes/stats.php';
require_once ROOT_PATH . '/includes/matches_repo.php';
require_once ROOT_PATH . '/includes/squad.php';
require_once ROOT_PATH . '/includes/crud.php';
require_once ROOT_PATH . '/includes/components.php';
require_once ROOT_PATH . '/includes/stats_view.php';
require_once ROOT_PATH . '/includes/match_form.php';
require_once ROOT_PATH . '/includes/team_profile.php';
require_once ROOT_PATH . '/includes/competition.php';
require_once ROOT_PATH . '/includes/auction.php';
require_once ROOT_PATH . '/includes/mailer.php';
require_once ROOT_PATH . '/includes/venue.php';
require_once ROOT_PATH . '/includes/members.php';

// Make sure every player/team has its unique 6-digit public ID.
try {
    // one-time: new homepage layout (player details, teams, achievements, latest matches, fixtures, squads)
    $lv = db()->query("SELECT setting_value FROM settings WHERE setting_key = 'home_layout_v'")->fetchColumn();
    if ($lv !== '3') {
        $up = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $up->execute(['home_sections', 'about,teams,achievements,fixtures,squads']);
        $up->execute(['home_layout_v', '3']);
    }
    ft_codes_ensure();
    ft_team_schema_ensure();
    ft_calendar_ensure();
    ft_comp_ensure();
    ft_venue_ensure();
    ft_auction_ensure();
    ft_members_ensure();
    ft_squad_ensure();
} catch (Throwable $e) { /* database not ready yet (e.g. installing) */ }
