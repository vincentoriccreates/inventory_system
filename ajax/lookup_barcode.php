<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
startSession();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['found' => false, 'error' => 'Unauthorized']);
    exit();
}

$barcode = trim($_GET['barcode'] ?? '');
if (empty($barcode)) {
    echo json_encode(['found' => false]);
    exit();
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT i.*, 
    COALESCE(si.total_in, 0) - COALESCE(so.total_out, 0) AS current_stock
    FROM items i
    LEFT JOIN (SELECT barcode, SUM(qty_in) AS total_in FROM stock_in GROUP BY barcode) si ON i.barcode = si.barcode
    LEFT JOIN (SELECT barcode, SUM(qty_sold) AS total_out FROM sales GROUP BY barcode) so ON i.barcode = so.barcode
    WHERE i.barcode = ? AND i.is_active = 1");
$stmt->execute([$barcode]);
$item = $stmt->fetch();

if ($item) {
    echo json_encode(['found' => true, 'item' => $item]);
} else {
    echo json_encode(['found' => false, 'message' => 'Barcode not found']);
}
