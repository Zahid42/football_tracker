<?php
/**
 * CSRF protection: one token per session, checked on every POST.
 */
declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input to place inside every <form method="post">. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && $sent !== ''
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $sent);
}

/** Call at the top of any POST handler. Stops the request on failure. */
function csrf_require(): void
{
    if (!csrf_valid()) {
        http_response_code(419);
        exit('Security check failed (invalid or expired form token). Go back, refresh the page and try again.');
    }
}
