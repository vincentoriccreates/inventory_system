<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');
$pageTitle = 'System Settings';
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = ['store_name', 'currency_symbol', 'low_stock_threshold', 'store_address', 'store_contact'];
    foreach ($keys as $k) {
        if (isset($_POST[$k])) {
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$k, $_POST[$k], $_POST[$k]]);
        }
    }
    // Change own password
    if (!empty($_POST['new_password'])) {
        if ($_POST['new_password'] === $_POST['confirm_password']) {
            $hash = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $_SESSION['user_id']]);
        }
    }
    header('Location: ' . BASE_URL . '/settings.php?success=Settings+saved'); exit();
}

$settings = [];
foreach ($pdo->query("SELECT * FROM settings")->fetchAll() as $r) {
    $settings[$r['setting_key']] = $r['setting_value'];
}

include 'includes/header.php';
?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
<div>
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-store" style="color:var(--primary)"></i> Store Information</div></div>
    <form method="POST">
        <div class="form-group"><label>Store Name</label>
            <input type="text" name="store_name" class="form-control" value="<?= sanitize($settings['store_name'] ?? 'My Store') ?>"></div>
        <div class="form-group"><label>Store Address</label>
            <input type="text" name="store_address" class="form-control" value="<?= sanitize($settings['store_address'] ?? '') ?>"></div>
        <div class="form-group"><label>Contact Number</label>
            <input type="text" name="store_contact" class="form-control" value="<?= sanitize($settings['store_contact'] ?? '') ?>"></div>
        <div class="form-row">
            <div class="form-group"><label>Currency Symbol</label>
                <input type="text" name="currency_symbol" class="form-control" value="<?= sanitize($settings['currency_symbol'] ?? '₱') ?>" style="width:80px;"></div>
            <div class="form-group"><label>Low Stock Threshold</label>
                <input type="number" name="low_stock_threshold" class="form-control" min="1" value="<?= intval($settings['low_stock_threshold'] ?? 10) ?>" style="width:100px;"></div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
    </form>
</div>
</div>

<div>
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-key" style="color:var(--warning)"></i> Change My Password</div></div>
    <form method="POST">
        <div class="form-group"><label>New Password</label>
            <input type="password" name="new_password" class="form-control" placeholder="Enter new password"></div>
        <div class="form-group"><label>Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password"></div>
        <button type="submit" class="btn btn-warning"><i class="fas fa-lock"></i> Change Password</button>
    </form>
</div>

<div class="card" style="margin-top:0;">
    <div class="card-header"><div class="card-title"><i class="fas fa-database" style="color:var(--info)"></i> System Info</div></div>
    <table>
        <tbody>
            <tr><td style="padding:8px 0;color:var(--gray-500)">PHP Version</td><td><code><?= phpversion() ?></code></td></tr>
            <tr><td style="padding:8px 0;color:var(--gray-500)">System Date</td><td><?= date('M d, Y H:i') ?></td></tr>
            <tr><td style="padding:8px 0;color:var(--gray-500)">Logged In As</td><td><?= sanitize($_SESSION['name']) ?></td></tr>
            <tr><td style="padding:8px 0;color:var(--gray-500)">Role</td><td><?= ucfirst($_SESSION['role']) ?></td></tr>
        </tbody>
    </table>
</div>
</div>
</div>

<?php include 'includes/footer.php'; ?>
