<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        execute(
            'INSERT INTO customers (name, email, phone, address, notes) VALUES (?,?,?,?,?)',
            [
                trim($_POST['name'] ?? ''),
                trim($_POST['email'] ?? '') ?: null,
                trim($_POST['phone'] ?? '') ?: null,
                trim($_POST['address'] ?? '') ?: null,
                trim($_POST['notes'] ?? '') ?: null,
            ]
        );
        $newId = (int)lastId();
        flash('success', 'Customer added.');
        redirect('customer-view.php?id=' . $newId);
    }
    if ($action === 'delete' && !empty($_POST['id'])) {
        execute('UPDATE customers SET deleted_at=NOW() WHERE id=?', [(int)$_POST['id']]);
        flash('success', 'Customer removed.');
    }
    redirect('customers.php');
}

// Legacy edit links open the customer hub.
if (!empty($_GET['edit'])) {
    redirect('customer-view.php?id=' . (int)$_GET['edit'] . '&tab=overview');
}

$currentPage = 'customers';
$pageTitle = 'Customers';
$loadCustomerHub = true;
require_once __DIR__ . '/includes/header.php';

$search = trim($_GET['search'] ?? '');
$where = 'c.deleted_at IS NULL';
$params = [];
if ($search !== '') {
    $where .= ' AND (c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)';
    $params = ["%$search%", "%$search%", "%$search%"];
}

$customers = query(
    "SELECT c.*,
            (SELECT COUNT(*) FROM events e WHERE e.customer_id=c.id AND e.deleted_at IS NULL AND e.archived=0) AS event_count,
            (SELECT COUNT(*) FROM estimates est WHERE est.customer_id=c.id) AS estimate_count,
            (SELECT COUNT(*) FROM contracts ct WHERE ct.customer_id=c.id) AS contract_count
     FROM customers c
     WHERE $where
     ORDER BY c.name",
    $params
);
?>

<div class="page-header">
    <div>
        <h1>Customers</h1>
        <p class="subtitle">Open a client to manage events, albums, estimates, and contracts in one place</p>
    </div>
    <button type="button" class="btn btn-primary" id="customer-add-toggle">+ Add customer</button>
</div>

<div class="card customer-add-panel" id="customer-add-panel" hidden>
    <h3>Add customer</h3>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <div class="form-group"><label>Full name *</label><input name="name" placeholder="Client full name" required autofocus></div>
            <div class="form-group"><label>Phone</label><input name="phone" placeholder="(555) 123-4567"></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" placeholder="client@email.com"></div>
        </div>
        <div class="form-group"><label>Notes</label><input name="notes" placeholder="Preferences, referrals, special requests"></div>
        <div class="flex">
            <button type="submit" class="btn btn-primary">Save &amp; open</button>
            <button type="button" class="btn btn-secondary" id="customer-add-cancel">Cancel</button>
        </div>
    </form>
</div>

<div class="card">
    <form method="get" class="customer-search-bar">
        <input type="search" name="search" placeholder="Search by name, email, or phone…" value="<?= e($search) ?>">
        <button class="btn btn-secondary btn-sm">Search</button>
        <?php if ($search !== ''): ?><a href="customers.php" class="btn btn-secondary btn-sm">Clear</a><?php endif; ?>
    </form>

    <?php if (empty($customers)): ?>
        <div class="empty-state">
            <div class="icon">👤</div>
            <h3>No customers yet</h3>
            <p>Add a client, then manage everything from their hub.</p>
            <button type="button" class="btn btn-primary" onclick="document.getElementById('customer-add-toggle').click()">+ Add customer</button>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table customer-list-table">
            <tr>
                <th>Customer</th>
                <th>Contact</th>
                <th>Events</th>
                <th>Estimates</th>
                <th>Contracts</th>
                <th></th>
            </tr>
            <?php foreach ($customers as $c): ?>
            <tr class="customer-list-row" onclick="window.location='customer-view.php?id=<?= (int)$c['id'] ?>'">
                <td>
                    <a href="customer-view.php?id=<?= (int)$c['id'] ?>"><strong><?= e($c['name']) ?></strong></a>
                </td>
                <td>
                    <?php if ($c['phone']): ?><?= e($c['phone']) ?><br><?php endif; ?>
                    <span class="text-muted"><?= e($c['email'] ?: '—') ?></span>
                </td>
                <td><?= (int)$c['event_count'] ?></td>
                <td><?= (int)$c['estimate_count'] ?></td>
                <td><?= (int)$c['contract_count'] ?></td>
                <td onclick="event.stopPropagation()">
                    <div class="action-btns">
                        <a href="customer-view.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-primary">Open</a>
                        <form method="post" onsubmit="return confirm('Remove this customer?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var panel = document.getElementById('customer-add-panel');
    var toggle = document.getElementById('customer-add-toggle');
    var cancel = document.getElementById('customer-add-cancel');
    if (!panel || !toggle) return;
    toggle.addEventListener('click', function () {
        panel.hidden = !panel.hidden;
        if (!panel.hidden) panel.querySelector('input[name="name"]')?.focus();
    });
    cancel?.addEventListener('click', function () { panel.hidden = true; });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
