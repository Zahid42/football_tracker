<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_login();
redirect('admin/players.php?action=edit&id=' . ctx_pid());
