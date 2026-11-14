<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_login();
if (!is_post()) { redirect('admin/index.php'); }
csrf_require();
$pid = (int)post('player_id');
if ($pid && get_player($pid)) { $_SESSION['admin_player_id'] = $pid; }
// only redirect back into the admin area
$ret = post('return');
$prefix = BASE_URL . '/admin/';
if ($ret === '' || !str_starts_with($ret, $prefix) || str_contains($ret, "\n") || str_contains($ret, "\r") || str_contains(substr($ret, strlen($prefix)), '//')) { $ret = $prefix . 'index.php'; }
header('Location: ' . $ret);
exit;
