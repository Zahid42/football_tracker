<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
// light throttle: 30 checks per minute per session
$_SESSION['ec'] = array_filter($_SESSION['ec'] ?? [], fn($t) => $t > time() - 60);
if (count($_SESSION['ec']) >= 30) { echo json_encode(['ok' => false, 'message' => 'Too many checks, please wait a moment.']); exit; }
$_SESSION['ec'][] = time();
$r = ft_check_email((string)($_GET['e'] ?? ''));
if ($r['ok'] && ft_email_taken($r['email'])) { $r['ok'] = false; $r['error'] = 'This e-mail is already registered. Try signing in.'; }
echo json_encode(['ok' => $r['ok'], 'message' => $r['ok'] ? 'E-mail looks good.' : $r['error'], 'suggest' => $r['suggest']]);
