<?php
declare(strict_types=1);

/**
 * Create a PDO connection using environment variables.
 *
 * Required environment variables:
 *   DB_HOST (defaults to 127.0.0.1)
 *   DB_PORT (defaults to 3306)
 *   DB_NAME (defaults to ai_image_detector)
 *   DB_USER
 *   DB_PASSWORD
 *
 * Never commit real database credentials to Git.
 */
function createDatabaseConnection(): PDO
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $database = getenv('DB_NAME') ?: 'ai_image_detector';
    $username = getenv('DB_USER');
    $password = getenv('DB_PASSWORD');

    if (!is_string($username) || $username === '' ||
        !is_string($password) || $password === '') {
        throw new RuntimeException('Database credentials are not configured.');
    }

    if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
        throw new RuntimeException('Database port configuration is invalid.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $host,
        (int) $port,
        $database
    );

    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
