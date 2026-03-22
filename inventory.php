<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
canAccess('items') || header('Location: ' . BASE_URL . '/dashboard.php?error=Access+denied') && exit();
$pageTitle = 'Inventory Ledger';

$inventory = getInventory();
$categories = array_unique(array_column($inventory, 'category'));

$filterCat = $_GET['cat'] ?? '';
$filterStatus = $_GET['status'] ?? '';

include 'includes/header.php';
?>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
<?php
$totalValue = array_sum(array_column($inventory, 'inventory_value'));
$totalItems = count($inventory);
$lowStock = array_filter($inventory, fn($i) => $i['current_stock'] > 0 && $i['current_stock'] <= $i['reorder_level']);
$outStock = array_filter($inventory, fn($i) => $i['current_stock'] <= 0);
?>
    <div class="stat-card info">
        <div class="stat-icon"><i class="fas fa-warehouse"></i></div>
        <div><div class="stat-value"><?= formatCurrency($totalValue) ?></div><div class="stat-label">Total Inventory Value</div></div>
    </div>
    <div class="stat-card primary">
        <div class="stat-icon"><i class="fas fa-boxes"></i></div>
        <div><div class="stat-value"><?= $totalItems ?></div><div class="stat-label">Total SKUs</div></div>
    </div>
    <div class="stat-card warning">
        <div class="stat-icon"><i class="fas fa-triangle-exclamation"></i></div>
        <div><div class="stat-value"><?= count($lowStock) ?></div><div class="stat-label">Low Stock</div></div>
    </div>
    <div class="stat-card danger">
        <div class="stat-icon"><i class="fas fa-circle-xmark"></i></div>
        <div><div class="stat-value"><?= count($outStock) ?></div><div class="stat-label">Out of Stock</div></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">Inventory Ledger</div>
            <div class="card-subtitle">Real-time stock levels computed from Stock In and Sales</div>
        </div>
        <div style="display:flex;gap:8px;">
            <select class="form-control" style="width:160px" onchange="filterTable(this.value,'cat')">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= sanitize($cat) ?>"><?= sanitize($cat) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-control" style="width:150px" onchange="filterTable(this.value,'status')">
                <option value="">All Status</option>
                <option value="ok">OK</option>
                <option value="low">Low Stock</option>
                <option value="out">Out of Stock</option>
            </select>
        </div>
    </div>

    <div class="search-bar">
        <div class="search-input-wrap">
            <i class="fas fa-magnifying-glass"></i>
            <input type="text" id="tableSearch" class="form-control" placeholder="Search items...">
        </div>
    </div>

    <div class="table-wrapper">
        <table id="invTable">
            <thead>
                <tr>
                    <th>Barcode</th><th>Item ID</th><th>Item Name</th><th>Category</th>
                    <th>Unit</th><th>Supplier</th>
                    <th>Total In</th><th>Total Out</th>
                    <th>Current Stock</th>
                    <th>Unit Cost</th><th>Sell Price</th>
                    <th>Inv. Value</th>
                    <th>Reorder Lvl</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($inventory as $row):
                if ($row['current_stock'] <= 0) { $status = 'out'; $badge = '<span class="badge badge-danger">Out of Stock</span>'; }
                elseif ($row['current_stock'] <= $row['reorder_level']) { $status = 'low'; $badge = '<span class="badge badge-warning">Low Stock</span>'; }
                else { $status = 'ok'; $badge = '<span class="badge badge-success">OK</span>'; }
            ?>
            <tr data-status="<?= $status ?>" data-cat="<?= sanitize($row['category']) ?>">
                <td><code><?= sanitize($row['barcode']) ?></code></td>
                <td><?= sanitize($row['item_id']) ?></td>
                <td><strong><?= sanitize($row['item_name']) ?></strong></td>
                <td><span class="badge badge-info"><?= sanitize($row['category']) ?></span></td>
                <td><?= sanitize($row['unit']) ?></td>
                <td><?= sanitize($row['supplier']) ?></td>
                <td style="color:var(--success);font-weight:600"><?= number_format($row['total_in']) ?></td>
                <td style="color:var(--danger);font-weight:600"><?= number_format($row['total_out']) ?></td>
                <td>
                    <strong style="font-size:16px;color:<?= $row['current_stock'] <= 0 ? 'var(--danger)' : ($row['current_stock'] <= $row['reorder_level'] ? 'var(--warning)' : 'var(--gray-800)') ?>">
                        <?= number_format($row['current_stock']) ?>
                    </strong>
                </td>
                <td><?= formatCurrency($row['unit_cost']) ?></td>
                <td><?= formatCurrency($row['selling_price']) ?></td>
                <td><strong><?= formatCurrency($row['inventory_value']) ?></strong></td>
                <td><?= $row['reorder_level'] ?></td>
                <td><?= $badge ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function filterTable(val, type) {
    document.querySelectorAll('#invTable tbody tr').forEach(row => {
        let show = true;
        const status = row.dataset.status;
        const cat = row.dataset.cat;
        
        if (type === 'status' && val && status !== val) show = false;
        if (type === 'cat' && val && cat !== val) show = false;
        row.style.display = show ? '' : 'none';
    });
}
</script>

<?php include 'includes/footer.php'; ?>
