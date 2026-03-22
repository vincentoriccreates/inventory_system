<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');
$pageTitle = 'User Management';
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name     = trim($_POST['name']);
        $username = trim($_POST['username']);
        $role     = $_POST['role'];
        $password = $_POST['password'] ?? '';

        if ($action === 'add') {
            if (empty($password)) { header('Location: ' . BASE_URL . '/users.php?error=Password+required'); exit(); }
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (name, username, password, role) VALUES (?,?,?,?)");
            $stmt->execute([$name, $username, $hash, $role]);
            header('Location: ' . BASE_URL . '/users.php?success=User+created');
        } else {
            $id = intval($_POST['id']);
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET name=?, username=?, password=?, role=? WHERE id=?")->execute([$name, $username, $hash, $role, $id]);
            } else {
                $pdo->prepare("UPDATE users SET name=?, username=?, role=? WHERE id=?")->execute([$name, $username, $role, $id]);
            }
            header('Location: ' . BASE_URL . '/users.php?success=User+updated');
        }
        exit();
    }

    if ($action === 'toggle') {
        $id = intval($_POST['id']);
        $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ? AND id != ?")->execute([$id, $_SESSION['user_id']]);
        header('Location: ' . BASE_URL . '/users.php?success=User+status+updated');
        exit();
    }

    if ($action === 'delete') {
        $id = intval($_POST['id']);
        if ($id === $_SESSION['user_id']) { header('Location: ' . BASE_URL . '/users.php?error=Cannot+delete+yourself'); exit(); }
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
        header('Location: ' . BASE_URL . '/users.php?success=User+deleted');
        exit();
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY role, name")->fetchAll();
$editUser = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $s->execute([intval($_GET['edit'])]);
    $editUser = $s->fetch();
}

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">System Users</div>
            <div class="card-subtitle"><?= count($users) ?> registered users</div>
        </div>
        <button class="btn btn-primary" data-modal="userModal"><i class="fas fa-user-plus"></i> Add User</button>
    </div>

    <div class="table-wrapper">
        <table>
            <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div class="user-avatar" style="width:32px;height:32px;font-size:13px;"><?= strtoupper(substr($u['name'],0,1)) ?></div>
                        <strong><?= sanitize($u['name']) ?></strong>
                    </div>
                </td>
                <td><code><?= sanitize($u['username']) ?></code></td>
                <td>
                    <?php
                    $badgeClass = ['admin'=>'badge-danger','staff'=>'badge-success','cashier'=>'badge-warning'];
                    ?>
                    <span class="badge <?= $badgeClass[$u['role']] ?? 'badge-secondary' ?>"><?= ucfirst($u['role']) ?></span>
                </td>
                <td>
                    <span class="badge <?= $u['is_active'] ? 'badge-success' : 'badge-secondary' ?>">
                        <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
                <td><?= formatDate($u['created_at']) ?></td>
                <td>
                    <a href="?edit=<?= $u['id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-pen"></i></a>
                    <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                        <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-warning' : 'btn-success' ?>">
                            <i class="fas fa-<?= $u['is_active'] ? 'ban' : 'check' ?>"></i>
                        </button>
                    </form>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger" data-confirm="Delete user <?= sanitize($u['name']) ?>?">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Role Permissions Reference -->
<div class="card">
    <div class="card-header"><div class="card-title">Role Permissions</div></div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Permission</th><th>Admin</th><th>Staff</th><th>Cashier</th></tr></thead>
            <tbody>
            <?php
            $perms = [
                ['Dashboard', '✅', '✅', '✅ (limited)'],
                ['View Items', '✅', '✅', '❌'],
                ['Add/Edit Items', '✅', '❌', '❌'],
                ['Delete Items', '✅', '❌', '❌'],
                ['Stock In', '✅', '✅', '❌'],
                ['POS / Sales', '✅', '❌', '✅'],
                ['View Reports', '✅', '❌', '❌'],
                ['Manage Users', '✅', '❌', '❌'],
                ['System Settings', '✅', '❌', '❌'],
            ];
            foreach ($perms as $p): ?>
            <tr>
                <td><?= $p[0] ?></td>
                <td><?= $p[1] ?></td>
                <td><?= $p[2] ?></td>
                <td><?= $p[3] ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal-overlay <?= $editUser ? 'active' : '' ?>" id="userModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><?= $editUser ? 'Edit User' : 'Add New User' ?></h3>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="<?= $editUser ? 'edit' : 'add' ?>">
                <?php if ($editUser): ?><input type="hidden" name="id" value="<?= $editUser['id'] ?>"><?php endif; ?>

                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" class="form-control" required value="<?= sanitize($editUser['name'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" class="form-control" required value="<?= sanitize($editUser['username'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Role *</label>
                        <select name="role" class="form-control" required>
                            <?php foreach (['admin','staff','cashier'] as $r): ?>
                            <option value="<?= $r ?>" <?= ($editUser['role'] ?? '') === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Password <?= $editUser ? '(leave blank to keep current)' : '*' ?></label>
                    <input type="password" name="password" class="form-control" <?= $editUser ? '' : 'required' ?> placeholder="Enter password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save User</button>
            </div>
        </form>
    </div>
</div>

<?php if ($editUser): ?><script>document.getElementById('userModal').classList.add('active');</script><?php endif; ?>

<?php include 'includes/footer.php'; ?>
