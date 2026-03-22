<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');
$pageTitle = 'Suppliers';
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $data = [trim($_POST['name']), trim($_POST['contact']), trim($_POST['address']), trim($_POST['email'])];
        if ($action === 'add') {
            $pdo->prepare("INSERT INTO suppliers (name,contact,address,email) VALUES (?,?,?,?)")->execute($data);
            header('Location: ' . BASE_URL . '/suppliers.php?success=Supplier+added'); exit();
        } else {
            $pdo->prepare("UPDATE suppliers SET name=?,contact=?,address=?,email=? WHERE id=?")->execute([...$data, intval($_POST['id'])]);
            header('Location: ' . BASE_URL . '/suppliers.php?success=Supplier+updated'); exit();
        }
    }
    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM suppliers WHERE id=?")->execute([intval($_POST['id'])]);
        header('Location: ' . BASE_URL . '/suppliers.php?success=Supplier+deleted'); exit();
    }
}

$suppliers = $pdo->query("SELECT s.*, COUNT(i.id) AS item_count FROM suppliers s LEFT JOIN items i ON i.supplier = s.name WHERE i.is_active=1 OR i.id IS NULL GROUP BY s.id ORDER BY s.name")->fetchAll();
$editSupplier = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM suppliers WHERE id=?");
    $s->execute([intval($_GET['edit'])]);
    $editSupplier = $s->fetch();
}

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div><div class="card-title">Supplier Directory</div><div class="card-subtitle"><?= count($suppliers) ?> suppliers</div></div>
        <button class="btn btn-primary" data-modal="supModal"><i class="fas fa-plus"></i> Add Supplier</button>
    </div>
    <div class="search-bar">
        <div class="search-input-wrap"><i class="fas fa-magnifying-glass"></i>
            <input type="text" id="tableSearch" class="form-control" placeholder="Search suppliers..."></div>
    </div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Name</th><th>Contact</th><th>Email</th><th>Address</th><th>Items</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($suppliers as $s): ?>
            <tr>
                <td><strong><?= sanitize($s['name']) ?></strong></td>
                <td><?= sanitize($s['contact']) ?></td>
                <td><?= sanitize($s['email']) ?></td>
                <td><?= sanitize($s['address']) ?></td>
                <td><span class="badge badge-info"><?= $s['item_count'] ?></span></td>
                <td>
                    <a href="?edit=<?= $s['id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-pen"></i></a>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger" data-confirm="Delete supplier?"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($suppliers)): ?>
            <tr><td colspan="6"><div class="empty-state"><i class="fas fa-truck"></i><p>No suppliers added yet.</p></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay <?= $editSupplier ? 'active' : '' ?>" id="supModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><?= $editSupplier ? 'Edit Supplier' : 'Add Supplier' ?></h3>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="<?= $editSupplier ? 'edit' : 'add' ?>">
                <?php if ($editSupplier): ?><input type="hidden" name="id" value="<?= $editSupplier['id'] ?>"><?php endif; ?>
                <div class="form-group"><label>Supplier Name *</label>
                    <input type="text" name="name" class="form-control" required value="<?= sanitize($editSupplier['name'] ?? '') ?>"></div>
                <div class="form-row">
                    <div class="form-group"><label>Contact No.</label>
                        <input type="text" name="contact" class="form-control" value="<?= sanitize($editSupplier['contact'] ?? '') ?>"></div>
                    <div class="form-group"><label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?= sanitize($editSupplier['email'] ?? '') ?>"></div>
                </div>
                <div class="form-group"><label>Address</label>
                    <input type="text" name="address" class="form-control" value="<?= sanitize($editSupplier['address'] ?? '') ?>"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<?php if ($editSupplier): ?><script>document.getElementById('supModal').classList.add('active');</script><?php endif; ?>
<?php include 'includes/footer.php'; ?>
