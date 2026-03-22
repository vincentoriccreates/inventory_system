<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');
$pageTitle = 'Reports & Analytics';
$pdo = getDB();

$dateFrom = $_GET['from'] ?? date('Y-m-01');
$dateTo   = $_GET['to']   ?? date('Y-m-d');

// Summary
$summary = $pdo->prepare("SELECT COALESCE(SUM(total_sales),0) ts, COALESCE(SUM(total_cost),0) tc, COALESCE(SUM(profit),0) tp FROM sales WHERE date BETWEEN ? AND ?");
$summary->execute([$dateFrom, $dateTo]);
$sum = $summary->fetch();

// Daily Sales
$daily = $pdo->prepare("SELECT date, SUM(total_sales) sales, SUM(profit) profit, COUNT(DISTINCT invoice_no) txns FROM sales WHERE date BETWEEN ? AND ? GROUP BY date ORDER BY date");
$daily->execute([$dateFrom, $dateTo]);
$dailyData = $daily->fetchAll();

// Category
$catReport = $pdo->prepare("SELECT category, SUM(total_sales) sales, SUM(total_cost) cost, SUM(profit) profit, SUM(qty_sold) qty FROM sales WHERE date BETWEEN ? AND ? GROUP BY category ORDER BY sales DESC");
$catReport->execute([$dateFrom, $dateTo]);
$catData = $catReport->fetchAll();

// Top Items
$topItems = $pdo->prepare("SELECT item_name, barcode, SUM(qty_sold) qty, SUM(total_sales) sales, SUM(profit) profit FROM sales WHERE date BETWEEN ? AND ? GROUP BY barcode, item_name ORDER BY sales DESC LIMIT 10");
$topItems->execute([$dateFrom, $dateTo]);
$topItemsData = $topItems->fetchAll();

// Stock In Summary
$stockSummary = $pdo->prepare("SELECT COALESCE(SUM(total_cost),0) total_purchased, COUNT(*) transactions FROM stock_in WHERE date BETWEEN ? AND ?");
$stockSummary->execute([$dateFrom, $dateTo]);
$stockSum = $stockSummary->fetch();

include 'includes/header.php';
?>

<!-- Date Filter -->
<div class="card" style="padding:16px;">
    <form method="GET" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <label style="margin:0;font-weight:600;">Date Range:</label>
        <input type="date" name="from" class="form-control" value="<?= $dateFrom ?>" style="width:160px;">
        <span style="color:var(--gray-500)">to</span>
        <input type="date" name="to" class="form-control" value="<?= $dateTo ?>" style="width:160px;">
        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
        <a href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline">This Month</a>
        <a href="?from=<?= date('Y-m-d', strtotime('-30 days')) ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline">Last 30 Days</a>
        <a href="?from=<?= date('Y-01-01') ?>&to=<?= date('Y-12-31') ?>" class="btn btn-outline">This Year</a>
        <button type="button" class="btn btn-outline" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
    </form>
</div>

<!-- Summary Cards -->
<div class="stats-grid">
    <div class="stat-card primary">
        <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
        <div><div class="stat-value"><?= formatCurrency($sum['ts']) ?></div><div class="stat-label">Total Sales</div></div>
    </div>
    <div class="stat-card danger">
        <div class="stat-icon"><i class="fas fa-cart-shopping"></i></div>
        <div><div class="stat-value"><?= formatCurrency($sum['tc']) ?></div><div class="stat-label">Total Cost</div></div>
    </div>
    <div class="stat-card success">
        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
        <div><div class="stat-value"><?= formatCurrency($sum['tp']) ?></div><div class="stat-label">Total Profit</div></div>
    </div>
    <div class="stat-card info">
        <div class="stat-icon"><i class="fas fa-percent"></i></div>
        <div><div class="stat-value"><?= $sum['ts'] > 0 ? number_format(($sum['tp'] / $sum['ts']) * 100, 1) : 0 ?>%</div><div class="stat-label">Profit Margin</div></div>
    </div>
    <div class="stat-card warning">
        <div class="stat-icon"><i class="fas fa-truck-ramp-box"></i></div>
        <div><div class="stat-value"><?= formatCurrency($stockSum['total_purchased']) ?></div><div class="stat-label">Total Purchased</div></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px;">
    <!-- Daily Sales Chart -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Daily Sales & Profit</div>
        </div>
        <canvas id="dailyChart" height="120"></canvas>
    </div>

    <!-- Category Pie -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Sales by Category</div>
        </div>
        <canvas id="catPie" height="200"></canvas>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

    <!-- Category Table -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Performance by Category</div>
        </div>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Category</th><th>Qty Sold</th><th>Sales</th><th>Cost</th><th>Profit</th><th>Margin</th></tr></thead>
                <tbody>
                <?php foreach ($catData as $r):
                    $margin = $r['sales'] > 0 ? ($r['profit'] / $r['sales']) * 100 : 0;
                ?>
                <tr>
                    <td><span class="badge badge-info"><?= sanitize($r['category']) ?></span></td>
                    <td><?= number_format($r['qty']) ?></td>
                    <td><?= formatCurrency($r['sales']) ?></td>
                    <td><?= formatCurrency($r['cost']) ?></td>
                    <td style="color:var(--success)"><strong><?= formatCurrency($r['profit']) ?></strong></td>
                    <td><?= number_format($margin, 1) ?>%</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Items -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Top 10 Best Sellers</div>
        </div>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>#</th><th>Item</th><th>Qty</th><th>Sales</th><th>Profit</th></tr></thead>
                <tbody>
                <?php foreach ($topItemsData as $i => $r): ?>
                <tr>
                    <td><strong style="color:var(--primary)"><?= $i + 1 ?></strong></td>
                    <td><?= sanitize($r['item_name']) ?></td>
                    <td><?= number_format($r['qty']) ?></td>
                    <td><?= formatCurrency($r['sales']) ?></td>
                    <td style="color:var(--success)"><?= formatCurrency($r['profit']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Daily Breakdown Table -->
<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <div class="card-title">Daily Sales Breakdown</div>
    </div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Date</th><th>Transactions</th><th>Total Sales</th><th>Profit</th><th>Margin</th></tr></thead>
            <tbody>
            <?php
            $runningTotal = 0;
            foreach ($dailyData as $r):
                $runningTotal += $r['sales'];
                $margin = $r['sales'] > 0 ? ($r['profit'] / $r['sales']) * 100 : 0;
            ?>
            <tr>
                <td><?= formatDate($r['date']) ?></td>
                <td><?= $r['txns'] ?></td>
                <td><strong><?= formatCurrency($r['sales']) ?></strong></td>
                <td style="color:var(--success)"><?= formatCurrency($r['profit']) ?></td>
                <td><?= number_format($margin, 1) ?>%</td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($dailyData)): ?>
            <tr><td colspan="5"><div class="empty-state"><i class="fas fa-chart-line"></i><p>No sales data for this period.</p></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const dLabels = <?= json_encode(array_map(fn($r) => date('M d', strtotime($r['date'])), $dailyData)) ?>;
const dSales  = <?= json_encode(array_map(fn($r) => floatval($r['sales']), $dailyData)) ?>;
const dProfit = <?= json_encode(array_map(fn($r) => floatval($r['profit']), $dailyData)) ?>;
const cLabels = <?= json_encode(array_column($catData, 'category')) ?>;
const cSales  = <?= json_encode(array_map(fn($r) => floatval($r['sales']), $catData)) ?>;

new Chart(document.getElementById('dailyChart'), {
    type: 'line',
    data: {
        labels: dLabels,
        datasets: [
            { label: 'Sales', data: dSales, borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,.1)', fill: true, tension: .4 },
            { label: 'Profit', data: dProfit, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.1)', fill: true, tension: .4 }
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('catPie'), {
    type: 'doughnut',
    data: {
        labels: cLabels,
        datasets: [{ data: cSales, backgroundColor: ['#6366f1','#10b981','#f59e0b','#ef4444','#3b82f6','#8b5cf6','#ec4899'] }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
</script>

<?php include 'includes/footer.php'; ?>
