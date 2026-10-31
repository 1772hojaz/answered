<?php
/**
 * PDO connection helper. All queries in the application use prepared
 * statements through db().
 */

declare(strict_types=1);

if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

/**
 * @return PDO
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = (string) config('db.host');
    $name = (string) config('db.name');
    $user = (string) config('db.user');
    $pass = (string) config('db.pass');
    $charset = (string) config('db.charset', 'utf8mb4');

    if ($name === 'YOUR_DB_NAME') {
        http_response_code(500);
        exit('Database not configured yet. Edit config/config.php with your database details.');
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $name, $charset);

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Could not connect to the database. Check the credentials in config/config.php.');
    }

    return $pdo;
}

/**
 * Run a prepared statement and return the statement handle.
 *
 * @param array $params
 */
function q(string $sql, array $params = []): PDOStatement
{
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
    } catch (PDOException $e) {
        // Friendly message when the schema has not been imported yet.
        if ((string) $e->getCode() === '42S02' || strpos((string) $e->getMessage(), "42S02") !== false) {
            http_response_code(500);
            exit('The database tables are missing. Run install.php (or import schema.sql) to finish the setup.');
        }
        throw $e;
    }
    return $stmt;
}

/**
 * Fetch a single row (or null).
 *
 * @return array|null
 */
function q_row(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/**
 * Fetch all rows.
 *
 * @return array[]
 */
function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

/**
 * Fetch a single scalar value.
 *
 * @return mixed
 */
function q_one(string $sql, array $params = [])
{
    $value = q($sql, $params)->fetchColumn();
    return $value === false ? null : $value;
}
