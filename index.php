<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

startSession();
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (login($username, $password)) {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit();
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — InventoryPro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; }</style>
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <i class="fas fa-boxes-stacked"></i>
            <h1>InventoryPro</h1>
            <p>Barcode Inventory Management System</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> Username</label>
                <input type="text" id="username" name="username" class="form-control"
                    placeholder="Enter username" autocomplete="username" autofocus
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password</label>
                <div style="position:relative;">
                    <input type="password" id="password" name="password" class="form-control"
                        placeholder="Enter password" autocomplete="current-password"
                        style="padding-right:44px;">
                    <button type="button" onclick="togglePw()" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--gray-400);cursor:pointer;">
                        <i class="fas fa-eye" id="pwIcon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;margin-top:8px;">
                <i class="fas fa-right-to-bracket"></i> Sign In
            </button>
        </form>

        <div style="margin-top:24px;padding:16px;background:var(--gray-50);border-radius:8px;font-size:12px;color:var(--gray-600);">
            <strong>Default Credentials:</strong><br>
            Admin: <code>admin / admin123</code><br>
            Staff: <code>staff / staff123</code><br>
            Cashier: <code>cashier / cashier123</code><br><br>
            <strong style="color:#f59e0b;">⚠️ First time?</strong>
            <a href="setup.php" style="color:#6366f1;font-weight:700;">Run setup.php first!</a>
        </div>
    </div>
</div>
<script>
function togglePw() {
    const pw = document.getElementById('password');
    const ic = document.getElementById('pwIcon');
    if (pw.type === 'password') { pw.type = 'text'; ic.className = 'fas fa-eye-slash'; }
    else { pw.type = 'password'; ic.className = 'fas fa-eye'; }
}
</script>
</body>
</html>
