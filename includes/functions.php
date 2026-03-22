<?php
// BASE_URL not needed server-side — all links use relative paths

function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function getInventory($barcode = null) {
    $pdo = getDB();
    $sql = "SELECT 
        i.barcode, i.item_id, i.item_name, i.category, i.unit, i.supplier,
        i.unit_cost, i.selling_price, i.reorder_level,
        COALESCE(si.total_in, 0) AS total_in,
        COALESCE(so.total_out, 0) AS total_out,
        (COALESCE(si.total_in, 0) - COALESCE(so.total_out, 0)) AS current_stock,
        (COALESCE(si.total_in, 0) - COALESCE(so.total_out, 0)) * i.unit_cost AS inventory_value
    FROM items i
    LEFT JOIN (SELECT barcode, SUM(qty_in) AS total_in FROM stock_in GROUP BY barcode) si ON i.barcode = si.barcode
    LEFT JOIN (SELECT barcode, SUM(qty_sold) AS total_out FROM sales GROUP BY barcode) so ON i.barcode = so.barcode
    WHERE i.is_active = 1";
    
    if ($barcode) {
        $sql .= " AND i.barcode = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$barcode]);
        return $stmt->fetch();
    }
    $stmt = $pdo->prepare($sql . " ORDER BY i.category, i.item_name");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getDashboardStats() {
    $pdo = getDB();
    $stats = [];
    
    // Total sales & profit
    $row = $pdo->query("SELECT COALESCE(SUM(total_sales),0) AS total_sales, COALESCE(SUM(profit),0) AS total_profit FROM sales")->fetch();
    $stats['total_sales'] = $row['total_sales'];
    $stats['total_profit'] = $row['total_profit'];
    
    // Inventory value
    $inv = $pdo->query("SELECT COALESCE(SUM(current_stock * unit_cost), 0) AS inv_value FROM (
        SELECT i.unit_cost, COALESCE(si.total_in,0) - COALESCE(so.total_out,0) AS current_stock
        FROM items i
        LEFT JOIN (SELECT barcode, SUM(qty_in) AS total_in FROM stock_in GROUP BY barcode) si ON i.barcode = si.barcode
        LEFT JOIN (SELECT barcode, SUM(qty_sold) AS total_out FROM sales GROUP BY barcode) so ON i.barcode = so.barcode
        WHERE i.is_active = 1
    ) t")->fetch();
    $stats['inventory_value'] = $inv['inv_value'];
    
    // Item counts
    $stats['total_items'] = $pdo->query("SELECT COUNT(*) FROM items WHERE is_active = 1")->fetchColumn();
    
    // Low stock (below reorder level but > 0)
    $stats['low_stock'] = $pdo->query("SELECT COUNT(*) FROM (
        SELECT i.reorder_level, COALESCE(si.total_in,0) - COALESCE(so.total_out,0) AS cs
        FROM items i
        LEFT JOIN (SELECT barcode, SUM(qty_in) AS total_in FROM stock_in GROUP BY barcode) si ON i.barcode = si.barcode
        LEFT JOIN (SELECT barcode, SUM(qty_sold) AS total_out FROM sales GROUP BY barcode) so ON i.barcode = so.barcode
        WHERE i.is_active = 1
    ) t WHERE cs > 0 AND cs <= reorder_level")->fetchColumn();
    
    // Out of stock
    $stats['out_of_stock'] = $pdo->query("SELECT COUNT(*) FROM (
        SELECT COALESCE(si.total_in,0) - COALESCE(so.total_out,0) AS cs
        FROM items i
        LEFT JOIN (SELECT barcode, SUM(qty_in) AS total_in FROM stock_in GROUP BY barcode) si ON i.barcode = si.barcode
        LEFT JOIN (SELECT barcode, SUM(qty_sold) AS total_out FROM sales GROUP BY barcode) so ON i.barcode = so.barcode
        WHERE i.is_active = 1
    ) t WHERE cs <= 0")->fetchColumn();
    
    // Today's sales
    $today = $pdo->query("SELECT COALESCE(SUM(total_sales),0) AS ts, COALESCE(SUM(profit),0) AS tp FROM sales WHERE date = CURDATE()")->fetch();
    $stats['today_sales'] = $today['ts'];
    $stats['today_profit'] = $today['tp'];
    
    return $stats;
}

function getCategoryReport() {
    $pdo = getDB();
    return $pdo->query("SELECT 
        s.category,
        SUM(s.total_sales) AS total_sales,
        SUM(s.total_cost) AS total_cost,
        SUM(s.profit) AS total_profit,
        COUNT(DISTINCT s.invoice_no) AS transactions
    FROM sales s
    GROUP BY s.category
    ORDER BY total_sales DESC")->fetchAll();
}
