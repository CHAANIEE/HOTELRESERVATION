<?php
// ─────────────────────────────────────────────
//  db.php  — reads .env and returns a PDO object
// ─────────────────────────────────────────────

function loadEnv(string $path): void {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$key, $val] = array_map('trim', explode('=', $line, 2));
        $_ENV[$key] = $val;
        putenv("$key=$val");
    }
}

// Load .env from the same directory as this file
loadEnv(__DIR__ . '/.env');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $port = $_ENV['DB_PORT'] ?? '3306';
    $name = $_ENV['DB_NAME'] ?? 'hoteldb';
    $user = $_ENV['DB_USER'] ?? 'root';
    $pass = $_ENV['DB_PASS'] ?? '';

    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        // Show a clean error instead of raw stack trace in production
        die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
    }

    return $pdo;
}