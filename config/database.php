<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'inventory_system');

// Auto-detect BASE_URL — works for localhost/inventory_system/ or any subfolder
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // __DIR__ = .../inventory_system/config → parent = inventory_system root
    $appRoot  = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $docRoot  = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? $appRoot));
    $subPath  = str_replace($docRoot, '', $appRoot); // e.g. /inventory_system
    define('BASE_URL', rtrim($protocol . '://' . $host . $subPath, '/'));
}

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                 PDO::ATTR_EMULATE_PREPARES => false]
            );
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
    return $pdo;
}
