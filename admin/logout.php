<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

// Logout only via POST + CSRF so another site cannot log you out by embedding a link.
if (!is_post()) {
    redirect(is_logged_in() ? 'admin/index.php' : 'admin/login.php');
}
csrf_require();
logout_admin();

// Start a fresh session just to carry a message.
session_start();
flash_set('success', 'You have been logged out.');
redirect('admin/login.php');
