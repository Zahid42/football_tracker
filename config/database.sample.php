<?php
/**
 * Database configuration - the ONLY place credentials live.
 * Default XAMPP: user "root", empty password.
 */
declare(strict_types=1);

const DB_HOST    = 'localhost';
const DB_NAME    = 'football_tracker';
const DB_USER    = 'root';
const DB_PASS    = '';
const DB_CHARSET = 'utf8mb4';

/**
 * Returns a shared PDO connection.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        exit('<h2 style="font-family:sans-serif">Database connection failed.</h2>'
           . '<p style="font-family:sans-serif">Check <code>config/database.php</code> and make sure MySQL is running '
           . 'and <code>database.sql</code> has been imported.</p>');
    }
    return $pdo;
}
