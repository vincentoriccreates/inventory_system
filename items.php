<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
canAccess('items') || header('Location: ' . BASE_URL . '/dashboard.php?error=Access+denied') && exit();
$pageTitle = 'Item Master';
$pdo = getDB();
$isAdmin = currentRole() === 'admin';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $fields = [
            'barcode'       => trim($_POST['barcode']),
            'item_id'       => trim($_POST['item_id']),
            'item_name'     => trim($_POST['item_name']),
            'category'      => trim($_POST['category']),
            'unit'          => trim($_POST['unit']),
            'supplier'      => trim($_POST['supplier']),
            'unit_cost'     => floatval($_POST['unit_cost']),
            'selling_price' => floatval($_POST['selling_price']),
            'reorder_level' => intval($_POST['reorder_level']),
        ];
        
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO items (barcode,item_id,item_name,category,unit,supplier,unit_cost,selling_price,reorder_level) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute(array_values($fields));
            header('Location: ' . BASE_URL . '/items.php?success=Item+added+successfully');
        } else {
            $id = intval($_POST['id']);
            $stmt = $pdo->prepare("UPDATE items SET barcode=?,item_id=?,item_name=?,category=?,unit=?,supplier=?,unit_cost=?,selling_price=?,reorder_level=? WHERE id=?");
            $stmt->execute([...array_values($fields), $id]);
            header('Location: ' . BASE_URL . '/items.php?success=Item+updated+successfully');
        }
        exit();
    }
    
    if ($action === 'delete' && isset($_POST['id'])) {
        $pdo->prepare("UPDATE items SET is_active=0 WHERE id=?")->execute([intval($_POST['id'])]);
        header('Location: ' . BASE_URL . '/items.php?success=Item+deleted');
        exit();
    }
}

// Fetch items
$search = trim($_GET['q'] ?? '');
$category = trim($_GET['cat'] ?? '');
$sql = "SELECT * FROM items WHERE is_active=1";
$params = [];
if ($search) { $sql .= " AND (item_name LIKE ? OR barcode LIKE ? OR item_id LIKE ?)"; $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]); }
if ($category) { $sql .= " AND category = ?"; $params[] = $category; }
$sql .= " ORDER BY category, item_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();
$categories = $pdo->query("SELECT DISTINCT category FROM items WHERE is_active=1 ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Edit mode
$editItem = null;
if (isset($_GET['edit'])) {
    $editItem = $pdo->prepare("SELECT * FROM items WHERE id=?")->execute([intval($_GET['edit'])]) ? 
        $pdo->prepare("SELECT * FROM items WHERE id=?")->fetch() : null;
    $stmt2 = $pdo->prepare("SELECT * FROM items WHERE id=?");
    $stmt2->execute([intval($_GET['edit'])]);
    $editItem = $stmt2->fetch();
}

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">Item Master Database</div>
            <div class="card-subtitle"><?= count($items) ?> items registered</div>
        </div>
        <?php if ($isAdmin): ?>
        <button class="btn btn-primary" data-modal="addModal"><i class="fas fa-plus"></i> Add Item</button>
        <?php endif; ?>
    </div>

    <div class="search-bar">
        <div class="search-input-wrap">
            <i class="fas fa-magnifying-glass"></i>
            <input type="text" id="tableSearch" class="form-control" placeholder="Search items, barcode..." value="<?= sanitize($search) ?>">
        </div>
        <select class="form-control" style="width:180px" onchange="window.location='items.php?cat='+this.value">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?= sanitize($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= sanitize($cat) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Barcode</th><th>ID</th><th>Item Name</th><th>Category</th>
                    <th>Unit</th><th>Supplier</th><th>Cost (₱)</th><th>Price (₱)</th>
                    <th>Margin</th><th>Reorder</th>
                    <?php if ($isAdmin): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($items)): ?>
            <tr><td colspan="11"><div class="empty-state"><i class="fas fa-tag"></i><p>No items found.</p></div></td></tr>
            <?php else: foreach ($items as $item):
                $margin = $item['selling_price'] > 0 ? (($item['selling_price'] - $item['unit_cost']) / $item['selling_price']) * 100 : 0;
            ?>
            <tr>
                <td><code><?= sanitize($item['barcode']) ?></code></td>
                <td><?= sanitize($item['item_id']) ?></td>
                <td><strong><?= sanitize($item['item_name']) ?></strong></td>
                <td><span class="badge badge-info"><?= sanitize($item['category']) ?></span></td>
                <td><?= sanitize($item['unit']) ?></td>
                <td><?= sanitize($item['supplier']) ?></td>
                <td><?= number_format($item['unit_cost'], 2) ?></td>
                <td><?= number_format($item['selling_price'], 2) ?></td>
                <td><?= number_format($margin, 1) ?>%</td>
                <td><?= $item['reorder_level'] ?></td>
                <?php if ($isAdmin): ?>
                <td>
                    <a href="?edit=<?= $item['id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-pen"></i></a>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger" data-confirm="Delete <?= sanitize($item['item_name']) ?>?">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- Add/Edit Modal -->
<div class="modal-overlay <?= $editItem ? 'active' : '' ?>" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><?= $editItem ? 'Edit Item' : 'Add New Item' ?></h3>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="<?= $editItem ? 'edit' : 'add' ?>">
                <?php if ($editItem): ?><input type="hidden" name="id" value="<?= $editItem['id'] ?>"><?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Barcode *</label>
                        <input type="text" name="barcode" class="form-control barcode-input" required 
                            value="<?= sanitize($editItem['barcode'] ?? '') ?>" placeholder="Scan or type barcode">
                    </div>
                    <div class="form-group">
                        <label>Item ID</label>
                        <input type="text" name="item_id" class="form-control" 
                            value="<?= sanitize($editItem['item_id'] ?? '') ?>" placeholder="ITM-001">
                    </div>
                </div>
                <div class="form-group">
                    <label>Item Name *</label>
                    <input type="text" name="item_name" class="form-control" required 
                        value="<?= sanitize($editItem['item_name'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Category *</label>
                        <input type="text" name="category" class="form-control" required list="catList"
                            value="<?= sanitize($editItem['category'] ?? '') ?>">
                        <datalist id="catList">
                            <?php foreach ($categories as $cat): ?><option value="<?= sanitize($cat) ?>"><?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label>Unit *</label>
                        <input type="text" name="unit" class="form-control" required 
                            value="<?= sanitize($editItem['unit'] ?? '') ?>" placeholder="pcs, kg, box...">
                    </div>
                </div>
                <div class="form-group">
                    <label>Supplier</label>
                    <input type="text" name="supplier" class="form-control" 
                        value="<?= sanitize($editItem['supplier'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Unit Cost (₱) *</label>
                        <input type="number" name="unit_cost" class="form-control" step="0.01" min="0" required 
                            value="<?= $editItem['unit_cost'] ?? '' ?>" id="unitCostF">
                    </div>
                    <div class="form-group">
                        <label>Selling Price (₱) *</label>
                        <input type="number" name="selling_price" class="form-control" step="0.01" min="0" required 
                            value="<?= $editItem['selling_price'] ?? '' ?>" id="sellPriceF">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Reorder Level</label>
                        <input type="number" name="reorder_level" class="form-control" min="0" 
                            value="<?= $editItem['reorder_level'] ?? 0 ?>">
                    </div>
                    <div class="form-group">
                        <label>Profit Margin</label>
                        <input type="text" id="marginDisplay" class="form-control" readonly placeholder="Auto-calculated">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Item</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// Auto-calculate margin
['unitCostF','sellPriceF'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', calcMargin);
});
function calcMargin() {
    const cost = parseFloat(document.getElementById('unitCostF')?.value || 0);
    const price = parseFloat(document.getElementById('sellPriceF')?.value || 0);
    const m = document.getElementById('marginDisplay');
    if (m && price > 0) m.value = ((price - cost) / price * 100).toFixed(1) + '%';
}
<?php if ($editItem): ?>
document.getElementById('addModal').classList.add('active');
<?php endif; ?>
calcMargin();
</script>

<?php include 'includes/footer.php'; ?>
