<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
requireLogin();

if (!canAccess('stock_in')) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

try {
    $pdo = getDB();
    $refNo = $data['reference_no'] ?? generateReferenceNo('PO', 'stock_in', 'reference_no');
    $date = $data['date'] ?? date('Y-m-d');
    $user = currentUser();

    $stmt = $pdo->prepare("INSERT INTO stock_in (reference_no, date, barcode, item_name, category, qty_in, unit_cost, supplier_notes, created_by) VALUES (?,?,?,?,?,?,?,?,?)");

    foreach ($data['items'] as $item) {
        if (empty($item['barcode']) || empty($item['qty'])) continue;
        $stmt->execute([
            $refNo, $date, $item['barcode'], $item['item_name'], $item['category'],
            intval($item['qty']), floatval($item['unit_cost']), $item['notes'] ?? '', $user['id']
        ]);
    }

    echo json_encode(['success' => true, 'reference_no' => $refNo]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
