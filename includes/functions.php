<?php
/**
 * General helpers: escaping, URLs, flash messages, formatting, uploads.
 */
declare(strict_types=1);

/** HTML-escape (XSS protection). Use on EVERY value printed into HTML. */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Build an absolute app URL, e.g. url('admin/login.php'). */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

/* ---------- Flash messages ---------- */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_render(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $html = '';
    foreach ($_SESSION['flash'] as $f) {
        $type = in_array($f['type'], ['success', 'danger', 'warning', 'info'], true) ? $f['type'] : 'info';
        $html .= '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">'
              . e($f['message'])
              . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

/* ---------- Input helpers ---------- */
function post(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function get_str(string $key, string $default = ''): string
{
    return trim((string)($_GET[$key] ?? $default));
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

/* ---------- Formatting ---------- */
function fmt_date(?string $date, string $format = 'd M Y'): string
{
    if (!$date) {
        return '-';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '-';
}

function fmt_time(?string $time): string
{
    return $time ? date('H:i', strtotime($time)) : '-';
}

function fmt_num($n, int $decimals = 1): string
{
    return $n === null ? '-' : number_format((float)$n, $decimals);
}

/** Convert result code (W/D/L) to label. */
function result_label(string $code): string
{
    return ['W' => 'WIN', 'D' => 'DRAW', 'L' => 'LOSS'][$code] ?? '-';
}

/** Coloured result badge. */
function result_badge(string $code): string
{
    $class = ['W' => 'win', 'D' => 'draw', 'L' => 'loss'][$code] ?? 'draw';
    return '<span class="result-badge result-' . $class . '">' . e(result_label($code)) . '</span>';
}

/** Small W/D/L form pill (single letter). */
function form_pill(string $code): string
{
    $class = ['W' => 'win', 'D' => 'draw', 'L' => 'loss'][$code] ?? 'draw';
    return '<span class="form-pill result-' . $class . '">' . e($code) . '</span>';
}

function age_from_dob(?string $dob): ?int
{
    if (!$dob) {
        return null;
    }
    try {
        return (new DateTime($dob))->diff(new DateTime('today'))->y;
    } catch (Exception $e) {
        return null;
    }
}

/* ---------- Settings & profile ---------- */
function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT setting_key, setting_value FROM settings') as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

function get_profile(): array
{
    $row = get_player(ctx_pid());
    return $row ?: [
        'full_name' => 'Your Name', 'nickname' => '', 'preferred_position' => 'CAM',
        'jersey_number' => null, 'team_name' => null, 'bio' => '', 'photo' => null,
        'playing_style' => '', 'career_start_date' => null, 'date_of_birth' => null,
        'secondary_position' => null, 'strong_foot' => null, 'height_cm' => null, 'weight_kg' => null,
    ];
}

const POSITION_NAMES = [
    'GK' => 'Goalkeeper', 'CB' => 'Centre Back', 'LB' => 'Left Back', 'RB' => 'Right Back',
    'CDM' => 'Defensive Midfielder', 'CM' => 'Central Midfielder', 'CAM' => 'Attacking Midfielder',
    'LM' => 'Left Midfielder', 'RM' => 'Right Midfielder', 'LW' => 'Left Winger',
    'RW' => 'Right Winger', 'ST' => 'Striker', 'CF' => 'Centre Forward',
];

function position_name(?string $code): string
{
    return POSITION_NAMES[$code ?? ''] ?? ($code ?: '-');
}

/** URL of an uploaded file, or a fallback image. */
function upload_url(?string $relative, string $fallback = 'assets/images/placeholder-player.svg'): string
{
    if ($relative && is_file(ROOT_PATH . '/' . UPLOAD_DIR_NAME . '/' . $relative)) {
        return url(UPLOAD_DIR_NAME . '/' . $relative);
    }
    return url($fallback);
}

/**
 * Securely store an uploaded image.
 * Validates: upload error, size, extension, real MIME type, real image.
 * Returns relative path (e.g. "profile/abc123.jpg") or throws RuntimeException.
 * Returns null when no file was chosen.
 */
function handle_image_upload(string $field, string $subDir): ?string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (error code ' . (int)$file['error'] . ').');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid upload.');
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('Image is too large (max ' . (MAX_UPLOAD_BYTES / 1024 / 1024) . ' MB).');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_IMG_EXT, true)) {
        throw new RuntimeException('Only JPG, PNG and WEBP images are allowed.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_IMG_MIME, true)) {
        throw new RuntimeException('File content is not a valid JPG/PNG/WEBP image.');
    }
    if (@getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('File is not a valid image.');
    }
    $allowedDirs = ['profile', 'matches', 'teams', 'gallery', 'trophies', 'competitions'];
    if (!in_array($subDir, $allowedDirs, true)) {
        throw new RuntimeException('Invalid upload folder.');
    }
    $dir = ROOT_PATH . '/' . UPLOAD_DIR_NAME . '/' . $subDir;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Cannot create upload folder.');
    }
    $safeExt  = $ext === 'jpeg' ? 'jpg' : $ext;
    $filename = bin2hex(random_bytes(12)) . '.' . $safeExt;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }
    return $subDir . '/' . $filename;
}

/** Delete a previously uploaded file (path relative to /uploads). */
function delete_upload(?string $relative): void
{
    if (!$relative || str_contains($relative, '..')) {
        return;
    }
    $path = ROOT_PATH . '/' . UPLOAD_DIR_NAME . '/' . $relative;
    if (is_file($path)) {
        @unlink($path);
    }
}
