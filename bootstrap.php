<?php
declare(strict_types=1);

session_start();

date_default_timezone_set('Europe/Luxembourg');

require_once __DIR__ . '/lib/Util.php';

$env = Util::loadEnv(__DIR__ . '/.env');
$GLOBALS['env'] = $env; // make .env accessible inside Util

try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $env['DB_HOST'] ?? '127.0.0.1', $env['DB_PORT'] ?? '3306', $env['DB_NAME'] ?? 'dyndns');
    $pdo = new PDO($dsn, $env['DB_USER'] ?? 'root', $env['DB_PASS'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    die('DB-Verbindung fehlgeschlagen: ' . htmlspecialchars($e->getMessage()));
}

require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/HetznerClient.php';
