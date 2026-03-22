<?php
// Auto-detect the base URL so CSS/JS paths always work
// regardless of whether you're at localhost/inventory_system or a subdomain

function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Get the directory of the root index.php relative to document root
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    // Walk up to find the inventory_system root
    // The root is where index.php / dashboard.php live
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
    $appRoot = realpath(__DIR__ . '/..');
    $relativePath = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
    return rtrim($protocol . '://' . $host . $relativePath, '/');
}

if (!defined('BASE_URL')) {
    define('BASE_URL', getBaseUrl());
}

// Also define a root-relative path for assets (works for most setups)
if (!defined('ASSETS_PATH')) {
    $appRoot = realpath(__DIR__ . '/..');
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
    $rel = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
    define('ASSETS_PATH', rtrim($rel, '/'));
}
