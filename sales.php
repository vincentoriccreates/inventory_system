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
        $date = $_POST['date'] ?? date('Y-m-d');
        
        foreach ($items as $row) {
            if (empty($row['barcode']) || empty($row['qty'])) continue;
            $stmt = $pdo->prepare("INSERT INTO sales (invoice_no, date, barcode, item_name, category, qty_sold, selling_price, unit_cost, cashier_id) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $invNo, $date, $row['barcode'], $row['item_name'], $row['category'],
                intval($row['qty']), floatval($row['selling_price']), floatval($row['unit_cost']), $user['id']
            ]);
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

// Sales history
$salesHistory = $pdo->query("SELECT s.*, u.name AS cashier_name FROM sales s LEFT JOIN users u ON s.cashier_id = u.id ORDER BY s.date DESC, s.id DESC LIMIT 100")->fetchAll();
$todayInv = generateReferenceNo('INV', 'sales', 'invoice_no');

// Invoice to print
$printInvoice = null;
if (isset($_GET['inv'])) {
    $stmt = $pdo->prepare("SELECT s.*, u.name AS cashier_name FROM sales s LEFT JOIN users u ON s.cashier_id = u.id WHERE s.invoice_no = ?");
    $stmt->execute([trim($_GET['inv'])]);
    $printInvoice = $stmt->fetchAll();
}

include 'includes/header.php';
?>

<?php if ($printInvoice): ?>
<!-- Invoice Print View -->
<div class="card no-print" style="max-width:400px;">
    <div class="receipt" id="receiptArea">
        <h2>🏪 MY STORE</h2>
        <div style="text-align:center;font-size:11px;margin-bottom:8px;">Barcode Inventory System</div>
        <div class="receipt-divider"></div>
        <div class="receipt-row"><span>Invoice:</span><strong><?= sanitize($printInvoice[0]['invoice_no']) ?></strong></div>
        <div class="receipt-row"><span>Date:</span><span><?= formatDate($printInvoice[0]['date']) ?></span></div>
        <div class="receipt-row"><span>Cashier:</span><span><?= sanitize($printInvoice[0]['cashier_name'] ?? '-') ?></span></div>
        <div class="receipt-divider"></div>
        <?php $total = 0; foreach ($printInvoice as $si): $total += $si['total_sales']; ?>
        <div style="margin-bottom:6px;">
            <div><?= sanitize($si['item_name']) ?></div>
            <div class="receipt-row" style="color:#666;">
                <span><?= $si['qty_sold'] ?> x <?= formatCurrency($si['selling_price']) ?></span>
                <span><?= formatCurrency($si['total_sales']) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="receipt-divider"></div>
        <div class="receipt-row" style="font-size:16px;font-weight:800;">
            <span>TOTAL</span><span><?= formatCurrency($total) ?></span>
        </div>
        <div class="receipt-divider"></div>
        <div style="text-align:center;font-size:11px;">Thank you for your purchase!</div>
    </div>
    <div style="display:flex;gap:10px;margin-top:16px;">
        <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print Receipt</button>
        <a href="sales.php" class="btn btn-outline">New Sale</a>
    </div>
</div>
<?php endif; ?>

<!-- POS Form -->
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
                        style="width:80px;text-align:center;" placeholder="Qty">
                    <button type="button" class="btn btn-primary" onclick="addFromScan()">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>
                <div id="scanFeedback" style="margin-top:8px;font-size:12px;color:var(--primary-dark);min-height:16px;"></div>
            </div>

            <form method="POST" id="saleForm">
                <input type="hidden" name="action" value="sale">
                <input type="hidden" name="invoice_no" value="<?= sanitize($todayInv) ?>">
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

    <!-- POS Summary -->
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
                <button type="button" class="btn btn-outline" onclick="clearCart()" style="justify-content:center;background:rgba(255,255,255,.05);color:var(--gray-300);border-color:rgba(255,255,255,.1);">
                    <i class="fas fa-trash"></i> Clear Cart
                </button>
            </div>

            <div id="receiptBtns" style="display:none;margin-top:12px;">
                <a id="printLink" href="#" class="btn btn-outline" style="width:100%;justify-content:center;background:rgba(255,255,255,.05);color:var(--gray-300);border-color:rgba(255,255,255,.1);">
                    <i class="fas fa-print"></i> Print Last Receipt
                </a>
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
                <tr><th>Date</th><th>Invoice</th><th>Barcode</th><th>Item</th><th>Qty</th><th>Price</th><th>Total Sales</th><th>Profit</th><th>Cashier</th><?php if (currentRole()==='admin'): ?><th></th><?php endif; ?></tr>
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
                <?php if (currentRole()==='admin'): ?>
                <td>
                    <a href="?inv=<?= urlencode($r['invoice_no']) ?>" class="btn btn-sm btn-outline btn-icon"><i class="fas fa-receipt"></i></a>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger btn-icon" data-confirm="Delete this sale?"><i class="fas fa-trash"></i></button>
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
let cart = {};
let cartIdx = 0;

const quickScan = document.getElementById('quickScan');
const feedback = document.getElementById('scanFeedback');

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
    if (e.key === 'Enter') {
        e.preventDefault();
        addFromScan();
    }
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
    // Check if already in cart
    if (cart[item.barcode]) {
        cart[item.barcode].qty += qty;
        updateCartRow(item.barcode);
    } else {
        cartIdx++;
        cart[item.barcode] = { ...item, qty: qty, idx: cartIdx };
        renderCartRow(item.barcode);
    }
    updateTotals();
    
    // Remove empty state row
    const emptyRow = document.getElementById('emptyRow');
    if (emptyRow) emptyRow.remove();
}

function renderCartRow(barcode) {
    const item = cart[barcode];
    const idx = item.idx;
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
                class="form-control" style="width:60px;text-align:center;"
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
    const idx = item.idx;
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
        const tbody = document.getElementById('cartBody');
        tbody.innerHTML = '<tr id="emptyRow"><td colspan="8"><div class="empty-state"><i class="fas fa-barcode"></i><p>Scan items to add to cart</p></div></td></tr>';
    }
}

function updateTotals() {
    let subtotal = 0, profit = 0, count = 0;
    for (const bc in cart) {
        const item = cart[bc];
        subtotal += item.qty * item.selling_price;
        profit += item.qty * (item.selling_price - item.unit_cost);
        count += item.qty;
    }
    document.getElementById('subtotal').textContent = formatCurrency(subtotal);
    document.getElementById('grandTotalPos').textContent = formatCurrency(subtotal);
    document.getElementById('totalProfit').textContent = formatCurrency(profit);
    document.getElementById('itemCount').textContent = count + ' item(s)';
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

// Show print button if returning from a sale
<?php if (isset($_GET['inv'])): ?>
document.getElementById('receiptBtns').style.display = 'block';
document.getElementById('printLink').href = '?inv=<?= urlencode($_GET['inv']) ?>';
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
