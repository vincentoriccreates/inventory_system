<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
canAccess('sales') || header('Location: ' . BASE_URL . '/dashboard.php?error=Access+denied') && exit();
$pageTitle = 'POS — Sales';
$pdo = getDB();
$user = currentUser();

// Handle sale submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'sale') {
        $items = $_POST['items'] ?? [];
        $invNo = $_POST['invoice_no'] ?? generateReferenceNo('INV', 'sales', 'invoice_no');
        $date  = $_POST['date'] ?? date('Y-m-d');
        foreach ($items as $row) {
            if (empty($row['barcode']) || empty($row['qty'])) continue;
            $stmt = $pdo->prepare("INSERT INTO sales (invoice_no,date,barcode,item_name,category,qty_sold,selling_price,unit_cost,cashier_id) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$invNo, $date, $row['barcode'], $row['item_name'], $row['category'],
                intval($row['qty']), floatval($row['selling_price']), floatval($row['unit_cost']), $user['id']]);
        }
        header('Location: ' . BASE_URL . '/sales.php?success=Sale+recorded&inv=' . urlencode($invNo));
        exit();
    }

    if ($action === 'delete' && currentRole() === 'admin') {
        $pdo->prepare("DELETE FROM sales WHERE id=?")->execute([intval($_POST['id'])]);
        header('Location: ' . BASE_URL . '/sales.php?success=Record+deleted');
        exit();
    }
}

// ── AJAX: return receipt HTML for popup printing ──────────────────────────
if (isset($_GET['receipt_ajax'])) {
    $invNo = trim($_GET['receipt_ajax']);
    $stmt  = $pdo->prepare("SELECT s.*, u.name AS cashier_name FROM sales s LEFT JOIN users u ON s.cashier_id = u.id WHERE s.invoice_no = ? ORDER BY s.id");
    $stmt->execute([$invNo]);
    $rows = $stmt->fetchAll();
    if (!$rows) { echo '<p>Invoice not found.</p>'; exit(); }
    $storeName = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='store_name'")->fetchColumn() ?: 'My Store';
    $total = array_sum(array_column($rows, 'total_sales'));
    $profit = array_sum(array_column($rows, 'profit'));
    ob_start(); ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Receipt <?= htmlspecialchars($invNo) ?></title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:'Courier New',Courier,monospace; font-size:12px; width:300px; padding:16px; color:#000; background:#fff; }
  h2 { text-align:center; font-size:16px; margin-bottom:2px; }
  .center { text-align:center; }
  .divider { border-top:1px dashed #000; margin:8px 0; }
  .row { display:flex; justify-content:space-between; margin-bottom:3px; }
  .item-name { font-weight:bold; margin-bottom:1px; }
  .item-detail { color:#555; }
  .total-row { display:flex; justify-content:space-between; font-size:15px; font-weight:900; margin-top:6px; }
  .footer { text-align:center; margin-top:10px; font-size:11px; color:#555; }
  @media print {
    body { width:80mm; }
    @page { margin:4mm; size:80mm auto; }
  }
</style>
</head>
<body>
  <h2><?= htmlspecialchars($storeName) ?></h2>
  <div class="center" style="font-size:10px;margin-bottom:6px;">Barcode Inventory System</div>
  <div class="divider"></div>
  <div class="row"><span>Invoice:</span><strong><?= htmlspecialchars($invNo) ?></strong></div>
  <div class="row"><span>Date:</span><span><?= date('M d, Y H:i', strtotime($rows[0]['created_at'])) ?></span></div>
  <div class="row"><span>Cashier:</span><span><?= htmlspecialchars($rows[0]['cashier_name'] ?? 'N/A') ?></span></div>
  <div class="divider"></div>
  <?php foreach ($rows as $r): ?>
  <div class="item-name"><?= htmlspecialchars($r['item_name']) ?></div>
  <div class="row item-detail">
    <span><?= $r['qty_sold'] ?> x ₱<?= number_format($r['selling_price'], 2) ?></span>
    <span>₱<?= number_format($r['total_sales'], 2) ?></span>
  </div>
  <?php endforeach; ?>
  <div class="divider"></div>
  <div class="row"><span>Items:</span><span><?= array_sum(array_column($rows, 'qty_sold')) ?></span></div>
  <div class="total-row"><span>TOTAL</span><span>₱<?= number_format($total, 2) ?></span></div>
  <div class="divider"></div>
  <div class="footer">Thank you for your purchase!<br><?= date('Y') ?> <?= htmlspecialchars($storeName) ?></div>
</body>
</html>
    <?php
    echo ob_get_clean();
    exit();
}

// Sales history
$salesHistory = $pdo->query("SELECT s.*, u.name AS cashier_name FROM sales s LEFT JOIN users u ON s.cashier_id = u.id ORDER BY s.date DESC, s.id DESC LIMIT 100")->fetchAll();
$todayInv = generateReferenceNo('INV', 'sales', 'invoice_no');

include 'includes/header.php';
?>

<!-- POS Layout -->
<div class="pos-grid">
    <div>
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title"><i class="fas fa-cash-register" style="color:var(--primary)"></i> Point of Sale</div>
                    <div class="card-subtitle">Scan barcode to add items</div>
                </div>
                <div class="scan-indicator"><div class="dot"></div> Scanner Ready</div>
            </div>

            <!-- Quick Scan Bar -->
            <div style="background:var(--primary-light);padding:16px;border-radius:var(--radius-sm);margin-bottom:16px;">
                <label style="color:var(--primary-dark);font-size:13px;font-weight:700;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-barcode" style="font-size:20px;"></i> SCAN BARCODE — Add to Cart
                </label>
                <div style="display:flex;gap:10px;margin-top:8px;">
                    <input type="text" id="quickScan" class="form-control barcode-input"
                        placeholder="Scan barcode here..." style="font-size:16px;" autocomplete="off">
                    <input type="number" id="quickQty" class="form-control" min="1" value="1"
                        style="width:80px;text-align:center;color:#1e293b;background:#fff;font-weight:600;" placeholder="Qty">
                    <button type="button" class="btn btn-primary" onclick="addFromScan()">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>
                <div id="scanFeedback" style="margin-top:8px;font-size:12px;color:var(--primary-dark);min-height:16px;"></div>
            </div>

            <form method="POST" id="saleForm">
                <input type="hidden" name="action" value="sale">
                <input type="hidden" name="invoice_no" id="currentInvNo" value="<?= sanitize($todayInv) ?>">
                <input type="hidden" name="date" value="<?= date('Y-m-d') ?>">

                <div class="table-wrapper">
                    <table id="cartTable">
                        <thead>
                            <tr><th>#</th><th>Item Name</th><th>Category</th><th width="80">Qty</th><th width="110">Price (₱)</th><th width="110">Total</th><th width="110">Profit</th><th width="50"></th></tr>
                        </thead>
                        <tbody id="cartBody">
                            <tr id="emptyRow"><td colspan="8"><div class="empty-state"><i class="fas fa-barcode"></i><p>Scan items to add to cart</p></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>

    <!-- POS Summary Panel -->
    <div>
        <div class="pos-summary" id="posSummary">
            <div style="margin-bottom:20px;">
                <div style="font-size:12px;color:var(--gray-400);text-transform:uppercase;letter-spacing:1px;">Invoice</div>
                <div style="font-size:16px;font-weight:700;color:var(--primary)" id="invNoDisplay"><?= sanitize($todayInv) ?></div>
                <div style="font-size:12px;color:var(--gray-400);margin-top:4px;"><?= date('M d, Y') ?></div>
            </div>

            <div class="pos-total-row"><span>Subtotal</span><span id="subtotal">₱0.00</span></div>
            <div class="pos-total-row"><span>Items</span><span id="itemCount">0</span></div>
            <div class="pos-total-row"><span>Profit</span><span id="totalProfit" style="color:var(--success)">₱0.00</span></div>
            <div class="pos-total-row grand-total"><span>TOTAL</span><span id="grandTotalPos">₱0.00</span></div>

            <div style="margin-top:20px;display:flex;flex-direction:column;gap:10px;">
                <button type="button" class="btn btn-success btn-lg" onclick="submitSale()" style="justify-content:center;font-size:16px;">
                    <i class="fas fa-check-circle"></i> Complete Sale
                </button>
                <button type="button" id="printReceiptBtn" class="btn btn-lg" onclick="printLastReceipt()"
                    style="display:none;justify-content:center;background:#3b82f6;color:#fff;font-size:15px;">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
                <button type="button" class="btn btn-outline" onclick="clearCart()"
                    style="justify-content:center;background:rgba(255,255,255,.05);color:var(--gray-300);border-color:rgba(255,255,255,.1);">
                    <i class="fas fa-trash"></i> Clear Cart
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Sales History -->
<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <div class="card-title">Sales History</div>
        <div class="search-input-wrap" style="width:280px">
            <i class="fas fa-magnifying-glass"></i>
            <input type="text" id="tableSearch" class="form-control" placeholder="Search sales...">
        </div>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Date</th><th>Invoice</th><th>Barcode</th><th>Item</th><th>Qty</th><th>Price</th><th>Total Sales</th><th>Profit</th><th>Cashier</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($salesHistory as $r): ?>
            <tr>
                <td><?= formatDate($r['date']) ?></td>
                <td><code><?= sanitize($r['invoice_no']) ?></code></td>
                <td><code><?= sanitize($r['barcode']) ?></code></td>
                <td><?= sanitize($r['item_name']) ?></td>
                <td><?= $r['qty_sold'] ?></td>
                <td><?= formatCurrency($r['selling_price']) ?></td>
                <td><strong><?= formatCurrency($r['total_sales']) ?></strong></td>
                <td style="color:var(--success)"><strong><?= formatCurrency($r['profit']) ?></strong></td>
                <td><?= sanitize($r['cashier_name'] ?? '-') ?></td>
                <td style="display:flex;gap:4px;">
                    <button class="btn btn-sm btn-outline btn-icon" onclick="printReceiptByInv('<?= sanitize($r['invoice_no']) ?>')" title="Print Receipt">
                        <i class="fas fa-receipt"></i>
                    </button>
                    <?php if (currentRole()==='admin'): ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger btn-icon" data-confirm="Delete this sale?"><i class="fas fa-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
let cart = {};
let cartIdx = 0;
let lastInvoiceNo = null;

const quickScan = document.getElementById('quickScan');
const feedback  = document.getElementById('scanFeedback');

// Auto-focus scan input
quickScan.focus();

let debounce;
quickScan.addEventListener('input', function () {
    clearTimeout(debounce);
    const val = this.value.trim();
    if (val.length >= 4) {
        debounce = setTimeout(() => {
            lookupBarcode(val, function (res) {
                if (res && res.found) {
                    feedback.innerHTML = '<span style="color:var(--success)"><i class="fas fa-check-circle"></i> ' + res.item.item_name + ' — ' + formatCurrency(res.item.selling_price) + '</span>';
                    showScanFlash(quickScan, true);
                } else if (val.length >= 8) {
                    feedback.innerHTML = '<span style="color:var(--danger)"><i class="fas fa-circle-xmark"></i> Barcode not found</span>';
                    showScanFlash(quickScan, false);
                }
            });
        }, 200);
    }
});

quickScan.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); addFromScan(); }
});

function addFromScan() {
    const barcode = quickScan.value.trim();
    const qty = parseInt(document.getElementById('quickQty').value) || 1;
    if (!barcode) return;
    lookupBarcode(barcode, function (res) {
        if (res && res.found) {
            const item = res.item;
            if (item.current_stock <= 0) {
                feedback.innerHTML = '<span style="color:var(--danger)"><i class="fas fa-circle-xmark"></i> Out of stock!</span>';
                return;
            }
            addToCart(item, qty);
            quickScan.value = '';
            document.getElementById('quickQty').value = 1;
            feedback.innerHTML = '';
            quickScan.focus();
        } else {
            feedback.innerHTML = '<span style="color:var(--danger)">Barcode not found: ' + barcode + '</span>';
        }
    });
}

function addToCart(item, qty) {
    if (cart[item.barcode]) {
        cart[item.barcode].qty += qty;
        updateCartRow(item.barcode);
    } else {
        cartIdx++;
        cart[item.barcode] = { ...item, qty: qty, idx: cartIdx };
        renderCartRow(item.barcode);
    }
    updateTotals();
    const emptyRow = document.getElementById('emptyRow');
    if (emptyRow) emptyRow.remove();
}

function renderCartRow(barcode) {
    const item = cart[barcode];
    const idx  = item.idx;
    const tbody = document.getElementById('cartBody');
    const row = document.createElement('tr');
    row.id = 'cartrow_' + barcode.replace(/\D/g,'');
    row.innerHTML = `
        <input type="hidden" name="items[${idx}][barcode]" value="${item.barcode}">
        <input type="hidden" name="items[${idx}][item_name]" value="${item.item_name}">
        <input type="hidden" name="items[${idx}][category]" value="${item.category}">
        <input type="hidden" name="items[${idx}][selling_price]" id="hp_${idx}" value="${item.selling_price}">
        <input type="hidden" name="items[${idx}][unit_cost]" value="${item.unit_cost}">
        <td style="color:var(--gray-500);font-size:12px">#${idx}</td>
        <td><strong>${item.item_name}</strong><br><small style="color:var(--gray-400)">${item.barcode}</small></td>
        <td><span class="badge badge-info">${item.category}</span></td>
        <td>
            <input type="number" name="items[${idx}][qty]" id="qty_${idx}"
                value="${item.qty}" min="1" max="${item.current_stock}"
                class="form-control" style="width:64px;text-align:center;color:#1e293b;background:#fff;font-weight:700;"
                onchange="updateQty('${barcode}', this.value)">
        </td>
        <td>${formatCurrency(item.selling_price)}</td>
        <td id="rowTotal_${idx}"><strong>${formatCurrency(item.qty * item.selling_price)}</strong></td>
        <td id="rowProfit_${idx}" style="color:var(--success)">${formatCurrency(item.qty * (item.selling_price - item.unit_cost))}</td>
        <td><button type="button" class="btn btn-sm btn-danger btn-icon" onclick="removeFromCart('${barcode}')"><i class="fas fa-times"></i></button></td>
    `;
    tbody.appendChild(row);
}

function updateCartRow(barcode) {
    const item = cart[barcode];
    const idx  = item.idx;
    document.getElementById('qty_' + idx).value = item.qty;
    document.getElementById('rowTotal_' + idx).innerHTML = '<strong>' + formatCurrency(item.qty * item.selling_price) + '</strong>';
    document.getElementById('rowProfit_' + idx).textContent = formatCurrency(item.qty * (item.selling_price - item.unit_cost));
}

function updateQty(barcode, qty) {
    if (cart[barcode]) {
        cart[barcode].qty = parseInt(qty) || 1;
        updateCartRow(barcode);
        updateTotals();
    }
}

function removeFromCart(barcode) {
    const item = cart[barcode];
    if (item) {
        const rowEl = document.getElementById('cartrow_' + barcode.replace(/\D/g,''));
        if (rowEl) rowEl.remove();
        delete cart[barcode];
        updateTotals();
    }
    if (Object.keys(cart).length === 0) {
        document.getElementById('cartBody').innerHTML = '<tr id="emptyRow"><td colspan="8"><div class="empty-state"><i class="fas fa-barcode"></i><p>Scan items to add to cart</p></div></td></tr>';
    }
}

function updateTotals() {
    let subtotal = 0, profit = 0, count = 0;
    for (const bc in cart) {
        const item = cart[bc];
        subtotal += item.qty * item.selling_price;
        profit   += item.qty * (item.selling_price - item.unit_cost);
        count    += item.qty;
    }
    document.getElementById('subtotal').textContent      = formatCurrency(subtotal);
    document.getElementById('grandTotalPos').textContent = formatCurrency(subtotal);
    document.getElementById('totalProfit').textContent   = formatCurrency(profit);
    document.getElementById('itemCount').textContent     = count + ' item(s)';
}

function submitSale() {
    if (Object.keys(cart).length === 0) {
        alert('Cart is empty. Please add items before completing the sale.');
        return;
    }
    if (confirm('Complete this sale for ' + document.getElementById('grandTotalPos').textContent + '?')) {
        document.getElementById('saleForm').submit();
    }
}

function clearCart() {
    if (Object.keys(cart).length > 0 && confirm('Clear the cart?')) {
        cart = {};
        cartIdx = 0;
        document.getElementById('cartBody').innerHTML = '<tr id="emptyRow"><td colspan="8"><div class="empty-state"><i class="fas fa-barcode"></i><p>Scan items to add to cart</p></div></td></tr>';
        updateTotals();
    }
}

// ── Receipt popup print ───────────────────────────────────────────────────
function printReceiptByInv(invNo) {
    const url = BASE_URL + 'sales.php?receipt_ajax=' + encodeURIComponent(invNo);
    fetch(url)
        .then(r => r.text())
        .then(html => {
            const win = window.open('', '_blank', 'width=360,height=600,scrollbars=no,toolbar=no,menubar=no');
            win.document.write(html);
            win.document.close();
            win.focus();
            // Wait for content to render then print
            win.onload = function() { win.print(); };
            // Fallback if onload already fired
            setTimeout(function() {
                try { win.print(); } catch(e) {}
            }, 600);
        })
        .catch(() => alert('Could not load receipt. Please try again.'));
}

function printLastReceipt() {
    if (lastInvoiceNo) {
        printReceiptByInv(lastInvoiceNo);
    }
}

// After successful sale — show print button and store invoice no
<?php if (isset($_GET['inv'])): ?>
lastInvoiceNo = '<?= addslashes($_GET['inv']) ?>';
document.getElementById('printReceiptBtn').style.display = 'flex';
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
