<?php
requireLogin();
$user = currentUser();
$role = $user['role'];
// All pages live in root — assets are always at ./assets/
$relRoot = './';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' — ' : '' ?>InventoryPro</title>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Main Stylesheet — relative path -->
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        /* System font fallback if Google Fonts unavailable */
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; }
    </style>
</head>
<body>

<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="brand">
            <i class="fas fa-boxes-stacked"></i>
            <span>InventoryPro</span>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    </div>

    <div class="user-card">
        <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
        <div class="user-info">
            <div class="user-name"><?= sanitize($user['name']) ?></div>
            <div class="user-role badge-<?= $role ?>"><?= ucfirst($role) ?></div>
        </div>
    </div>

    <ul class="nav-menu">
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-gauge-high"></i><span>Dashboard</span>
            </a>
        </li>

        <?php if (canAccess('items')): ?>
        <li class="nav-section">INVENTORY</li>
        <li class="nav-item">
            <a href="items.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'items.php' ? 'active' : '' ?>">
                <i class="fas fa-tag"></i><span>Item Master</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (canAccess('stock_in')): ?>
        <li class="nav-item">
            <a href="stock_in.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'stock_in.php' ? 'active' : '' ?>">
                <i class="fas fa-arrow-down-to-line"></i><span>Stock In</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (canAccess('items')): ?>
        <li class="nav-item">
            <a href="inventory.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'inventory.php' ? 'active' : '' ?>">
                <i class="fas fa-warehouse"></i><span>Inventory</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (canAccess('sales')): ?>
        <li class="nav-section">SALES</li>
        <li class="nav-item">
            <a href="sales.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'sales.php' ? 'active' : '' ?>">
                <i class="fas fa-cash-register"></i><span>POS / Sales</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (canAccess('reports')): ?>
        <li class="nav-item">
            <a href="reports.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-line"></i><span>Reports</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (canAccess('suppliers')): ?>
        <li class="nav-section">MANAGEMENT</li>
        <li class="nav-item">
            <a href="suppliers.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'suppliers.php' ? 'active' : '' ?>">
                <i class="fas fa-truck"></i><span>Suppliers</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (canAccess('users')): ?>
        <li class="nav-item">
            <a href="users.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>">
                <i class="fas fa-users"></i><span>Users</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (canAccess('settings')): ?>
        <li class="nav-item">
            <a href="settings.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>">
                <i class="fas fa-gear"></i><span>Settings</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-right-from-bracket"></i><span>Logout</span>
        </a>
    </div>
</nav>

<!-- Main Content -->
<div class="main-wrapper" id="mainWrapper">
    <header class="topbar">
        <button class="topbar-toggle" id="topbarToggle"><i class="fas fa-bars"></i></button>
        <div class="topbar-title"><?= isset($pageTitle) ? sanitize($pageTitle) : 'Dashboard' ?></div>
        <div class="topbar-right">
            <span class="date-badge"><i class="fas fa-calendar"></i> <?= date('M d, Y') ?></span>
        </div>
    </header>
    <main class="content">
<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= sanitize($_GET['success']) ?></div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div class="alert alert-danger"><i class="fas fa-triangle-exclamation"></i> <?= sanitize($_GET['error']) ?></div>
<?php endif; ?>
