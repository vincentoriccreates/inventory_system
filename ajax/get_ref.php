<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
requireLogin();

$type = $_GET['type'] ?? 'PO';

if ($type === 'PO') {
    $ref = generateReferenceNo('PO', 'stock_in', 'reference_no');
} else {
    $ref = generateReferenceNo('INV', 'sales', 'invoice_no');
}

echo json_encode(['ref' => $ref]);
