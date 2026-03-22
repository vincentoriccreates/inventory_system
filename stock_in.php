<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
canAccess('stock_in') || header('Location: ' . BASE_URL . '/dashboard.php?error=Access+denied') && exit();
$pageTitle = 'Stock In';
$pdo = getDB();
$user = currentUser();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'stock_in') {
        $items = $_POST['items'] ?? [];
        $refNo = $_POST['reference_no'] ?? generateReferenceNo('PO', 'stock_in', 'reference_no');
        $date = $_POST['date'] ?? date('Y-m-d');
        
        foreach ($items as $row) {
            if (empty($row['barcode']) || empty($row['qty'])) continue;
            $stmt = $pdo->prepare("INSERT INTO stock_in (reference_no, date, barcode, item_name, category, qty_in, unit_cost, supplier_notes, created_by) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $refNo, $date, $row['barcode'], $row['item_name'], $row['category'],
                intval($row['qty']), floatval($row['unit_cost']), $row['notes'] ?? '', $user['id']
            ]);
        }
        header('Location: ' . BASE_URL . '/stock_in.php?success=Stock+In+recorded+successfully');
        exit();
    }
    
    if ($action === 'delete' && currentRole() === 'admin') {
        $pdo->prepare("DELETE FROM stock_in WHERE id=?")->execute([intval($_POST['id'])]);
        header('Location: ' . BASE_URL . '/stock_in.php?success=Record+deleted');
        exit();
    }
}

// Fetch history
$stockHistory = $pdo->query("SELECT si.*, u.name AS created_by_name FROM stock_in si LEFT JOIN users u ON si.created_by = u.id ORDER BY si.date DESC, si.id DESC LIMIT 100")->fetchAll();
$todayRef = generateReferenceNo('PO', 'stock_in', 'reference_no');

include 'includes/header.php';
?>

<!-- Stock In Form -->
<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title"><i class="fas fa-arrow-down-to-line" style="color:var(--primary)"></i> New Stock In Entry</div>
            <div class="card-subtitle">Scan barcode to auto-fill item details</div>
        </div>
        <div class="scan-indicator"><div class="dot"></div> Scanner Ready</div>
    </div>

    <form method="POST" id="stockInForm">
        <input type="hidden" name="action" value="stock_in">
        <div class="form-row" style="max-width:600px;margin-bottom:16px;">
            <div class="form-group">
                <label><i class="fas fa-hashtag"></i> Reference No.</label>
                <input type="text" name="reference_no" class="form-control" value="<?= sanitize($todayRef) ?>" id="refNo">
            </div>
            <div class="form-group">
                <label><i class="fas fa-calendar"></i> Date</label>
                <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
        </div>

        <div class="table-wrapper" style="margin-bottom:16px;">
            <table id="stockTable">
                <thead>
                    <tr>
                        <th width="180">Barcode (Scan)</th>
                        <th>Item Name</th>
                        <th width="130">Category</th>
                        <th width="80">Qty In</th>
                        <th width="110">Unit Cost (₱)</th>
                        <th width="110">Total Cost</th>
                        <th width="160">Supplier/Notes</th>
                        <th width="60"></th>
                    </tr>
                </thead>
                <tbody id="stockBody">
                    <!-- Rows added by JS -->
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" style="text-align:right;padding:12px;font-weight:700;">Total:</td>
                        <td style="padding:12px;font-weight:800;color:var(--primary)" id="grandTotal">₱0.00</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div style="display:flex;gap:10px;">
            <button type="button" class="btn btn-outline" onclick="addRow()"><i class="fas fa-plus"></i> Add Row</button>
            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                <i class="fas fa-save"></i> Save Stock In
            </button>
        </div>
    </form>
</div>

<!-- History -->
<div class="card">
    <div class="card-header">
        <div class="card-title">Stock In History</div>
        <div class="search-input-wrap" style="width:280px">
            <i class="fas fa-magnifying-glass"></i>
            <input type="text" id="tableSearch" class="form-control" placeholder="Search history...">
        </div>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Date</th><th>Reference No.</th><th>Barcode</th><th>Item Name</th><th>Category</th><th>Qty In</th><th>Unit Cost</th><th>Total Cost</th><th>Notes</th><th>By</th><?php if (currentRole()==='admin'): ?><th></th><?php endif; ?></tr>
            </thead>
            <tbody>
            <?php foreach ($stockHistory as $r): ?>
            <tr>
                <td><?= formatDate($r['date']) ?></td>
                <td><code><?= sanitize($r['reference_no']) ?></code></td>
                <td><code><?= sanitize($r['barcode']) ?></code></td>
                <td><?= sanitize($r['item_name']) ?></td>
                <td><span class="badge badge-info"><?= sanitize($r['category']) ?></span></td>
                <td><?= number_format($r['qty_in']) ?></td>
                <td><?= formatCurrency($r['unit_cost']) ?></td>
                <td><strong><?= formatCurrency($r['total_cost']) ?></strong></td>
                <td><?= sanitize($r['supplier_notes']) ?></td>
                <td><?= sanitize($r['created_by_name'] ?? '-') ?></td>
                <?php if (currentRole()==='admin'): ?>
                <td>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger btn-icon" data-confirm="Delete this record?"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
let rowCount = 0;

function addRow(data = {}) {
    rowCount++;
    const idx = rowCount;
    const row = document.createElement('tr');
    row.id = 'row_' + idx;
    row.innerHTML = `
        <td>
            <input type="text" name="items[${idx}][barcode]" id="bc_${idx}" 
                class="form-control barcode-input" placeholder="Scan barcode..." 
                style="font-size:13px;padding:8px 10px;" autocomplete="off">
        </td>
        <td><input type="text" name="items[${idx}][item_name]" id="name_${idx}" class="form-control" readonly placeholder="Auto-fill"></td>
        <td><input type="text" name="items[${idx}][category]" id="cat_${idx}" class="form-control" readonly placeholder="Auto-fill"></td>
        <td><input type="number" name="items[${idx}][qty]" id="qty_${idx}" class="form-control" min="1" value="1" 
            oninput="calcRow(${idx})" style="text-align:center;"></td>
        <td><input type="number" name="items[${idx}][unit_cost]" id="cost_${idx}" class="form-control" step="0.01" min="0" value="0"
            oninput="calcRow(${idx})"></td>
        <td><input type="text" id="total_${idx}" class="form-control" readonly value="₱0.00" style="font-weight:700;color:var(--primary)"></td>
        <td><input type="text" name="items[${idx}][notes]" class="form-control" placeholder="Supplier..."></td>
        <td><button type="button" class="btn btn-sm btn-danger btn-icon" onclick="removeRow(${idx})"><i class="fas fa-times"></i></button></td>
    `;
    document.getElementById('stockBody').appendChild(row);

    if (data.barcode) {
        document.getElementById('bc_' + idx).value = data.barcode;
        fillRow(idx, data);
    }

    // Barcode listener
    let debounce;
    document.getElementById('bc_' + idx).addEventListener('input', function () {
        clearTimeout(debounce);
        const val = this.value.trim();
        debounce = setTimeout(() => {
            if (val.length >= 4) {
                lookupBarcode(val, function (res) {
                    if (res && res.found) fillRow(idx, res.item);
                });
            }
        }, 300);
    });

    document.getElementById('qty_' + idx).focus();
}

function fillRow(idx, item) {
    document.getElementById('name_' + idx).value = item.item_name || '';
    document.getElementById('cat_' + idx).value = item.category || '';
    document.getElementById('cost_' + idx).value = item.unit_cost || 0;
    calcRow(idx);
}

function calcRow(idx) {
    const qty = parseFloat(document.getElementById('qty_' + idx)?.value || 0);
    const cost = parseFloat(document.getElementById('cost_' + idx)?.value || 0);
    const total = qty * cost;
    const el = document.getElementById('total_' + idx);
    if (el) el.value = formatCurrency(total);
    updateGrandTotal();
}

function updateGrandTotal() {
    let total = 0;
    for (let i = 1; i <= rowCount; i++) {
        const el = document.getElementById('total_' + i);
        if (el) total += parseFloat(el.value.replace(/[₱,]/g,'')) || 0;
    }
    document.getElementById('grandTotal').textContent = formatCurrency(total);
}

function removeRow(idx) {
    const row = document.getElementById('row_' + idx);
    if (row) row.remove();
    updateGrandTotal();
}

// Start with one empty row
addRow();

// Form validation
document.getElementById('stockInForm').addEventListener('submit', function (e) {
    const rows = document.querySelectorAll('#stockBody tr');
    let valid = false;
    rows.forEach(r => {
        const bc = r.querySelector('input[name*="[barcode]"]');
        const qty = r.querySelector('input[name*="[qty]"]');
        if (bc && bc.value.trim() && qty && parseInt(qty.value) > 0) valid = true;
    });
    if (!valid) { e.preventDefault(); alert('Please add at least one valid item row.'); }
});
</script>

<?php include 'includes/footer.php'; ?>
