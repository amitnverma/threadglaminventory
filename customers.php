<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        execute('INSERT INTO customers (name, email, phone, address, notes) VALUES (?,?,?,?,?)',
            [trim($_POST['name']), $_POST['email'] ?? null, $_POST['phone'] ?? null, $_POST['address'] ?? null, $_POST['notes'] ?? null]);
        flash('success', 'Customer added.');
    }
    if ($action === 'update' && !empty($_POST['id'])) {
        execute('UPDATE customers SET name=?, email=?, phone=?, address=?, notes=?, updated_at=NOW() WHERE id=?',
            [trim($_POST['name']), $_POST['email'] ?? null, $_POST['phone'] ?? null, $_POST['address'] ?? null, $_POST['notes'] ?? null, $_POST['id']]);
        flash('success', 'Customer updated.');
    }
    if ($action === 'delete' && !empty($_POST['id'])) {
        execute('UPDATE customers SET deleted_at=NOW() WHERE id=?', [$_POST['id']]);
        flash('success', 'Customer removed.');
    }
    redirect('customers.php');
}

$currentPage = 'customers';
$pageTitle = 'Customers';
require_once __DIR__ . '/includes/header.php';

$search = $_GET['search'] ?? '';
$where = 'deleted_at IS NULL';
$params = [];
if ($search) { $where .= ' AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)'; $params = ["%$search%", "%$search%", "%$search%"]; }
$customers = query("SELECT c.*, (SELECT COUNT(*) FROM events e WHERE e.customer_id=c.id AND e.deleted_at IS NULL) as event_count FROM customers c WHERE $where ORDER BY c.name", $params);
$editId = (int)($_GET['edit'] ?? 0);
$editCust = $editId ? queryOne('SELECT * FROM customers WHERE id=? AND deleted_at IS NULL', [$editId]) : null;
?>

<div class="page-header">
    <div>
        <h1>Customers</h1>
        <p class="subtitle">Manage your client database</p>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <h3><?= $editCust ? 'Edit Customer' : 'Add Customer' ?></h3>
        <form method="post">
            <input type="hidden" name="action" value="<?= $editCust ? 'update' : 'add' ?>">
            <?php if ($editCust): ?><input type="hidden" name="id" value="<?= $editCust['id'] ?>"><?php endif; ?>
            <div class="form-group"><label>Full Name *</label><input name="name" value="<?= e($editCust['name'] ?? '') ?>" placeholder="Client full name" required></div>
            <div class="form-row">
                <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= e($editCust['email'] ?? '') ?>" placeholder="client@email.com"></div>
                <div class="form-group"><label>Phone</label><input name="phone" value="<?= e($editCust['phone'] ?? '') ?>" placeholder="(555) 123-4567"></div>
            </div>
            <div class="form-group"><label>Address</label><textarea name="address" placeholder="Full mailing address"><?= e($editCust['address'] ?? '') ?></textarea></div>
            <div class="form-group"><label>Notes</label><textarea name="notes" placeholder="Preferences, referrals, special requests"><?= e($editCust['notes'] ?? '') ?></textarea></div>
            <div class="flex">
                <button type="submit" class="btn btn-primary"><?= $editCust ? 'Update' : 'Add Customer' ?></button>
                <?php if ($editCust): ?><a href="customers.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="filter-bar" style="margin:0 0 1rem;padding:.75rem">
            <form method="get" class="flex" style="width:100%">
                <input type="text" name="search" placeholder="Search customers..." value="<?= e($search) ?>" style="flex:1">
                <button class="btn btn-secondary btn-sm">Search</button>
            </form>
        </div>
        <?php if (empty($customers)): ?>
            <div class="empty-state"><div class="icon">👤</div><h3>No customers yet</h3><p>Add your first client to get started.</p></div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <tr><th>Name</th><th>Contact</th><th>Events</th><th>Actions</th></tr>
                <?php foreach ($customers as $c): ?>
                <tr>
                    <td><strong><?= e($c['name']) ?></strong></td>
                    <td>
                        <?php if ($c['phone']): ?><?= e($c['phone']) ?><br><?php endif; ?>
                        <span class="text-muted"><?= e($c['email'] ?: '—') ?></span>
                    </td>
                    <td><?= (int)$c['event_count'] ?></td>
                    <td><?= actionButtons('customers.php?edit=' . $c['id'], 'delete', $c['id']) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
