<?php
declare(strict_types=1);

/*
 * Database connection using PDO.
 * XAMPP default: user "root" with an EMPTY password.
 */

const DB_HOST = '127.0.0.1';
const DB_NAME = 'presenter_management';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

/**
 * Returns the one shared PDO connection.
 * Usage anywhere:  $pdo = db();
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            exit('Could not connect to the database. Check that MySQL is running in XAMPP and that config/database.php is correct.');
        }
    }

    return $pdo;
}
