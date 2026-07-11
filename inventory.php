<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete' && !empty($_POST['id'])) {
        execute('UPDATE inventory_items SET deleted_at=NOW() WHERE id=?', [$_POST['id']]);
        flash('success', 'Item deleted.');
        redirect('inventory.php');
    }
    if ($action === 'adjust' && !empty($_POST['id'])) {
        $item = queryOne('SELECT quantity_on_hand FROM inventory_items WHERE id=?', [$_POST['id']]);
        $qty = (int)$_POST['quantity'];
        $type = $_POST['adjustment_type'];
        $newQty = $type === 'add' ? $item['quantity_on_hand'] + $qty : ($type === 'remove' ? $item['quantity_on_hand'] - $qty : $qty);
        execute('UPDATE inventory_items SET quantity_on_hand=?, reorder_level=? WHERE id=?', [$newQty, generateReorderLevel($newQty), $_POST['id']]);
        execute('INSERT INTO inventory_adjustments (inventory_item_id, adjustment_type, quantity, reason) VALUES (?,?,?,?)',
            [$_POST['id'], $type, $qty, $_POST['reason'] ?? '']);
        flash('success', 'Stock updated.');
        redirect('inventory-view.php?id=' . $_POST['id']);
    }
}

$currentPage = 'inventory';
$pageTitle = 'Inventory';
require_once __DIR__ . '/includes/header.php';

$search = $_GET['search'] ?? '';
$catId = $_GET['category'] ?? '';
$view = $_GET['view'] ?? 'grid';
$where = 'i.deleted_at IS NULL';
$params = [];
if ($search) { $where .= ' AND (i.name LIKE ? OR i.sku LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($catId) { $where .= ' AND i.category_id=?'; $params[] = $catId; }

$items = query("SELECT i.*, c.name as category_name FROM inventory_items i LEFT JOIN inventory_categories c ON c.id=i.category_id WHERE $where ORDER BY i.name", $params);
$categories = query('SELECT * FROM inventory_categories ORDER BY name');
?>

<div class="page-header">
    <div>
        <h1>Inventory</h1>
        <p class="subtitle"><?= count($items) ?> items in stock</p>
    </div>
    <div class="flex">
        <a href="categories.php" class="btn btn-secondary">Manage Categories</a>
        <a href="inventory-form.php" class="btn btn-primary">+ Add Item</a>
    </div>
</div>

<div class="filter-bar">
    <form method="get" class="flex" style="width:100%;align-items:center">
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <input type="text" name="search" placeholder="Search by name or SKU..." value="<?= e($search) ?>" style="flex:2">
        <select name="category" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $catId == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        <div class="view-toggle">
            <a href="?view=grid&search=<?= urlencode($search) ?>&category=<?= urlencode($catId) ?>" class="<?= $view==='grid'?'active':'' ?>">Grid</a>
            <a href="?view=list&search=<?= urlencode($search) ?>&category=<?= urlencode($catId) ?>" class="<?= $view==='list'?'active':'' ?>">List</a>
        </div>
    </form>
</div>

<?php if (empty($items)): ?>
<div class="card">
    <div class="empty-state">
        <div class="icon">📦</div>
        <h3>No inventory items</h3>
        <p>Add your first item to start building your catalog.</p>
        <a href="inventory-form.php" class="btn btn-primary">+ Add Item</a>
    </div>
</div>
<?php elseif ($view === 'list'): ?>
<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <tr><th>Item</th><th>SKU</th><th>Category</th><th>Qty</th><th>Rental</th><th>Actions</th></tr>
            <?php foreach ($items as $item):
                $img = getPrimaryImage('inventory', $item['id']);
            ?>
            <tr>
                <td>
                    <div class="flex" style="align-items:center">
                        <img src="<?= e(imgUrl($img)) ?>" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:6px">
                        <a href="inventory-view.php?id=<?= $item['id'] ?>"><?= e($item['name']) ?></a>
                        <?php if ($item['quantity_on_hand'] <= $item['reorder_level']): ?><span class="badge badge-low">Low</span><?php endif; ?>
                    </div>
                </td>
                <td><code><?= e($item['sku']) ?></code></td>
                <td><?= e($item['category_name'] ?: '—') ?></td>
                <td><?= (int)$item['quantity_on_hand'] ?></td>
                <td><?= formatMoney($item['rental_price']) ?></td>
                <td><?= actionButtons('inventory-form.php?id=' . $item['id'], 'delete', $item['id'], 'inventory-view.php?id=' . $item['id']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php else: ?>
<div class="grid-4">
    <?php foreach ($items as $item):
        $img = getPrimaryImage('inventory', $item['id']);
    ?>
    <div class="item-card">
        <a href="inventory-view.php?id=<?= $item['id'] ?>">
            <img src="<?= e(imgUrl($img)) ?>" alt="<?= e($item['name']) ?>" class="item-card-img" loading="lazy">
        </a>
        <div class="item-card-body">
            <h4><a href="inventory-view.php?id=<?= $item['id'] ?>"><?= e($item['name']) ?></a></h4>
            <div class="item-card-meta">
                <code><?= e($item['sku']) ?></code>
                <?php if ($item['category_name']): ?> · <?= e($item['category_name']) ?><?php endif; ?>
            </div>
            <div class="flex" style="justify-content:space-between;margin-top:.5rem">
                <span>Qty: <strong><?= (int)$item['quantity_on_hand'] ?></strong>
                <?php if ($item['quantity_on_hand'] <= $item['reorder_level']): ?><span class="badge badge-low">Low</span><?php endif; ?>
                </span>
                <span class="item-card-price"><?= formatMoney($item['rental_price']) ?></span>
            </div>
        </div>
        <div class="item-card-actions">
            <a href="inventory-form.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
            <a href="inventory-view.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-secondary">View</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
