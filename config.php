<?php
// ─── Database Configuration ───────────────────────────────────────────────────
// Change these to match your cPanel MySQL credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'YOUR_DB_USER');       // e.g. cpanelusername_dbuser
define('DB_PASS', 'YOUR_DB_PASSWORD');   // your MySQL password
define('DB_NAME', 'YOUR_DB_NAME');       // e.g. cpanelusername_tracker

// ─── App Config ───────────────────────────────────────────────────────────────
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'Europe/Sofia');
date_default_timezone_set(TIMEZONE);

// ─── Database Connection ──────────────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]);
            exit;
        }
    }
    return $pdo;
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function jsonError(string $msg, int $code = 400): void {
    jsonResponse(['error' => $msg], $code);
}
