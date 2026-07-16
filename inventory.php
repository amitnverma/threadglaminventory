<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
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
    if ($action === 'batch_update') {
        $raw = $_POST['rows'] ?? '[]';
        $rows = is_string($raw) ? json_decode($raw, true) : $raw;
        $result = updateInventorySheetRows(is_array($rows) ? $rows : []);
        flash($result['ok'] ? 'success' : 'error', $result['ok']
            ? ('Saved ' . $result['updated'] . ' item(s).')
            : ($result['error'] ?: 'Could not save changes.'));
        redirect('inventory.php?' . http_build_query(array_filter([
            'search' => $_GET['search'] ?? ($_POST['search'] ?? ''),
            'category' => $_GET['category'] ?? ($_POST['category'] ?? ''),
        ])));
    }
}

$currentPage = 'inventory';
$pageTitle = 'Inventory';
$loadInventorySheet = true;
$pageScripts = ['assets/js/inventory-grid.js'];
require_once __DIR__ . '/includes/header.php';

$search = $_GET['search'] ?? '';
$catId = $_GET['category'] ?? '';
$where = 'i.deleted_at IS NULL';
$params = [];
if ($search) {
    $where .= ' AND (i.name LIKE ? OR i.sku LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($catId) {
    $where .= ' AND i.category_id=?';
    $params[] = $catId;
}

$items = query(
    "SELECT i.*, c.name as category_name
     FROM inventory_items i
     LEFT JOIN inventory_categories c ON c.id=i.category_id
     WHERE $where
     ORDER BY i.name",
    $params
);
$categories = query('SELECT * FROM inventory_categories ORDER BY name');
?>

<div class="page-header inv-sheet-page">
    <div>
        <h1>Inventory</h1>
        <p class="subtitle"><?= count($items) ?> items — edit cells directly, then save</p>
    </div>
    <div class="flex">
        <a href="categories.php" class="btn btn-secondary">Categories</a>
        <a href="inventory-form.php" class="btn btn-secondary">Advanced add</a>
        <a href="inventory-buy.php" class="btn btn-primary">Buy / Restock</a>
    </div>
</div>

<div class="filter-bar">
    <form method="get" class="flex" style="width:100%;align-items:center">
        <input type="text" name="search" placeholder="Search by name or SKU..." value="<?= e($search) ?>" style="flex:2">
        <select name="category" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $catId == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
    </form>
</div>

<?php if (empty($items)): ?>
<div class="card">
    <div class="empty-state">
        <div class="icon">📦</div>
        <h3>No inventory items</h3>
        <p>Buy a receipt of items or add a single advanced item.</p>
        <a href="inventory-buy.php" class="btn btn-primary">Buy / Restock</a>
    </div>
</div>
<?php else: ?>
<div class="card inv-sheet-card">
    <div class="inv-sheet-toolbar">
        <span class="inv-sheet-hint">Click any cell to edit. Qty changes belong on Buy / Restock (keeps cost history accurate).</span>
        <span class="spacer"></span>
        <span class="inv-sheet-status" id="inv-grid-status">All saved</span>
        <button type="button" class="btn btn-primary btn-sm" id="inv-grid-save" disabled>Save changes</button>
    </div>
    <div id="inventory-edit-sheet"></div>
</div>
<?php endif; ?>

<script>
window.INV_GRID = {
    csrf: <?= json_encode(csrfToken(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
    apiUrl: 'inventory-sheet-api.php',
    categories: <?= json_encode(array_map(static fn($c) => [
        'id' => (int)$c['id'],
        'name' => $c['name'],
    ], $categories), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
    rows: <?= json_encode(array_map(static fn($i) => [
        'id' => (int)$i['id'],
        'name' => $i['name'],
        'sku' => $i['sku'],
        'category_id' => $i['category_id'] !== null ? (int)$i['category_id'] : '',
        'quantity_on_hand' => (int)$i['quantity_on_hand'],
        'reorder_level' => (int)$i['reorder_level'],
        'unit_cost' => (float)$i['unit_cost'],
        'rental_price' => (float)$i['rental_price'],
        'sale_price' => (float)$i['sale_price'],
    ], $items), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
};
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
