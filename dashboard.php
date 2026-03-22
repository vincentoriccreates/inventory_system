<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
$pageTitle = 'Dashboard';
$user = currentUser();
$stats = getDashboardStats();
$pdo = getDB();

// Sales chart data (last 7 days)
$chartData = $pdo->query("
    SELECT DATE_FORMAT(date,'%b %d') AS day, 
           COALESCE(SUM(total_sales),0) AS sales,
           COALESCE(SUM(profit),0) AS profit
    FROM sales WHERE date >= CURDATE() - INTERVAL 7 DAY
    GROUP BY date ORDER BY date
")->fetchAll();

// Category performance
$catData = getCategoryReport();

// Low stock items
$lowStock = $pdo->query("
    SELECT i.item_name, i.barcode, i.category, i.reorder_level,
           COALESCE(si.total_in,0) - COALESCE(so.total_out,0) AS current_stock
    FROM items i
    LEFT JOIN (SELECT barcode, SUM(qty_in) AS total_in FROM stock_in GROUP BY barcode) si ON i.barcode = si.barcode
    LEFT JOIN (SELECT barcode, SUM(qty_sold) AS total_out FROM sales GROUP BY barcode) so ON i.barcode = so.barcode
    WHERE i.is_active = 1 
    HAVING current_stock <= i.reorder_level
    ORDER BY current_stock ASC LIMIT 10
")->fetchAll();

// Recent sales
$recentSales = $pdo->query("SELECT * FROM sales ORDER BY created_at DESC LIMIT 5")->fetchAll();

include 'includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card primary">
        <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
        <div><div class="stat-value"><?= formatCurrency($stats['total_sales']) ?></div><div class="stat-label">Total Sales</div></div>
    </div>
    <div class="stat-card success">
        <div class="stat-icon"><i class="fas fa-chart-trending-up"></i></div>
        <div><div class="stat-value"><?= formatCurrency($stats['total_profit']) ?></div><div class="stat-label">Total Profit</div></div>
    </div>
    <div class="stat-card info">
        <div class="stat-icon"><i class="fas fa-warehouse"></i></div>
        <div><div class="stat-value"><?= formatCurrency($stats['inventory_value']) ?></div><div class="stat-label">Inventory Value</div></div>
    </div>
    <div class="stat-card warning">
        <div class="stat-icon"><i class="fas fa-box"></i></div>
        <div><div class="stat-value"><?= number_format($stats['total_items']) ?></div><div class="stat-label">Total Items</div></div>
    </div>
    <?php if ($stats['low_stock'] > 0): ?>
    <div class="stat-card warning">
        <div class="stat-icon"><i class="fas fa-triangle-exclamation"></i></div>
        <div><div class="stat-value"><?= $stats['low_stock'] ?></div><div class="stat-label">Low Stock Items</div></div>
    </div>
    <?php endif; ?>
    <?php if ($stats['out_of_stock'] > 0): ?>
    <div class="stat-card danger">
        <div class="stat-icon"><i class="fas fa-circle-xmark"></i></div>
        <div><div class="stat-value"><?= $stats['out_of_stock'] ?></div><div class="stat-label">Out of Stock</div></div>
    </div>
    <?php endif; ?>
    <div class="stat-card success">
        <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
        <div><div class="stat-value"><?= formatCurrency($stats['today_sales']) ?></div><div class="stat-label">Today's Sales</div></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px;">

    <!-- Sales Chart -->
    <div class="card">
        <div class="card-header">
            <div><div class="card-title">Sales Overview (Last 7 Days)</div></div>
        </div>
        <canvas id="salesChart" height="100"></canvas>
    </div>

    <!-- Category Performance -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Sales by Category</div>
        </div>
        <canvas id="catChart" height="180"></canvas>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

    <!-- Low Stock Alert -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title"><i class="fas fa-triangle-exclamation" style="color:var(--warning)"></i> Stock Alerts</div>
            </div>
            <?php if (canAccess('stock_in')): ?>
            <a href="stock_in.php" class="btn btn-sm btn-warning">Restock</a>
            <?php endif; ?>
        </div>
        <?php if (empty($lowStock)): ?>
        <div class="empty-state"><i class="fas fa-check-circle" style="color:var(--success)"></i><p>All items are well stocked!</p></div>
        <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Item</th><th>Stock</th><th>Reorder</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($lowStock as $item): ?>
                <tr>
                    <td><strong><?= sanitize($item['item_name']) ?></strong><br><small style="color:var(--gray-400)"><?= sanitize($item['category']) ?></small></td>
                    <td><?= $item['current_stock'] ?></td>
                    <td><?= $item['reorder_level'] ?></td>
                    <td>
                        <?php if ($item['current_stock'] <= 0): ?>
                        <span class="badge badge-danger">Out of Stock</span>
                        <?php else: ?>
                        <span class="badge badge-warning">Low Stock</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Sales -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-receipt" style="color:var(--primary)"></i> Recent Sales</div>
            <?php if (canAccess('sales')): ?>
            <a href="sales.php" class="btn btn-sm btn-primary">View All</a>
            <?php endif; ?>
        </div>
        <?php if (empty($recentSales)): ?>
        <div class="empty-state"><i class="fas fa-receipt"></i><p>No sales recorded yet.</p></div>
        <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Invoice</th><th>Item</th><th>Qty</th><th>Total</th></tr></thead>
                <tbody>
                <?php foreach ($recentSales as $s): ?>
                <tr>
                    <td><small><?= sanitize($s['invoice_no']) ?></small></td>
                    <td><?= sanitize($s['item_name']) ?></td>
                    <td><?= $s['qty_sold'] ?></td>
                    <td><strong><?= formatCurrency($s['total_sales']) ?></strong></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const chartLabels = <?= json_encode(array_column($chartData, 'day')) ?>;
const salesData   = <?= json_encode(array_map('floatval', array_column($chartData, 'sales'))) ?>;
const profitData  = <?= json_encode(array_map('floatval', array_column($chartData, 'profit'))) ?>;
const catLabels   = <?= json_encode(array_column($catData, 'category')) ?>;
const catSales    = <?= json_encode(array_map('floatval', array_column($catData, 'total_sales'))) ?>;

new Chart(document.getElementById('salesChart'), {
    type: 'bar',
    data: {
        labels: chartLabels,
        datasets: [
            { label: 'Sales', data: salesData, backgroundColor: 'rgba(99,102,241,.8)', borderRadius: 6 },
            { label: 'Profit', data: profitData, backgroundColor: 'rgba(16,185,129,.8)', borderRadius: 6 }
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('catChart'), {
    type: 'doughnut',
    data: {
        labels: catLabels,
        datasets: [{ data: catSales, backgroundColor: ['#6366f1','#10b981','#f59e0b','#ef4444','#3b82f6','#8b5cf6'] }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
</script>

<?php include 'includes/footer.php'; ?>
