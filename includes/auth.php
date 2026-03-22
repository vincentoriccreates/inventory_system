<?php
require_once __DIR__ . '/../config/database.php';

function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function requireLogin() {
    startSession();
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit();
    }
}

function requireRole(...$roles) {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles)) {
        header('Location: ' . BASE_URL . '/dashboard.php?error=Access+denied');
        exit();
    }
}

function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']);
}

function currentRole() {
    startSession();
    return $_SESSION['role'] ?? null;
}

function currentUser() {
    startSession();
    return [
        'id'       => $_SESSION['user_id'] ?? null,
        'name'     => $_SESSION['name'] ?? '',
        'username' => $_SESSION['username'] ?? '',
        'role'     => $_SESSION['role'] ?? ''
    ];
}

function canAccess($permission) {
    $role = currentRole();
    $perms = [
        'admin'   => ['dashboard','items','stock_in','sales','reports','users','settings','suppliers'],
        'staff'   => ['dashboard','items','stock_in'],
        'cashier' => ['dashboard','sales']
    ];
    return in_array($permission, $perms[$role] ?? []);
}

function login($username, $password) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        startSession();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['username']= $user['username'];
        $_SESSION['role']    = $user['role'];
        return true;
    }
    return false;
}

function logout() {
    startSession();
    session_destroy();
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

function generateReferenceNo($prefix, $table, $col) {
    $pdo = getDB();
    $today = date('Ymd');
    $like  = $prefix . '-' . $today . '-%';
    $stmt  = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $col LIKE ?");
    $stmt->execute([$like]);
    $count = $stmt->fetchColumn();
    return $prefix . '-' . $today . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
}
