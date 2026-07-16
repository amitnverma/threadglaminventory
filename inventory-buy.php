<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $names = $_POST['line_name'] ?? [];
    $invIds = $_POST['line_inventory_id'] ?? [];
    $categoryIds = $_POST['line_category_id'] ?? [];
    $qtys = $_POST['line_qty'] ?? [];
    $costs = $_POST['line_cost'] ?? [];

    $lines = [];
    $count = max(count($names), count($invIds), count($qtys), count($costs));
    for ($i = 0; $i < $count; $i++) {
        $name = trim((string)($names[$i] ?? ''));
        $invId = (int)($invIds[$i] ?? 0);
        if ($name === '' && $invId <= 0) {
            continue;
        }
        $lines[] = [
            'mode' => $invId > 0 ? 'existing' : 'new',
            'inventory_item_id' => $invId,
            'name' => $name,
            'category_id' => ($categoryIds[$i] ?? '') !== '' ? (int)$categoryIds[$i] : null,
            'qty' => (int)($qtys[$i] ?? 1),
            'unit_cost' => (float)($costs[$i] ?? 0),
        ];
    }

    $result = savePurchaseWithLines([
        'supplier' => $_POST['supplier'] ?? '',
        'purchase_date' => $_POST['purchase_date'] ?? date('Y-m-d'),
        'notes' => $_POST['notes'] ?? '',
    ], $lines);

    if (!$result['ok']) {
        flash('error', $result['error'] ?: 'Could not save purchase.');
        redirect('inventory-buy.php');
    }

    flash('success', "Saved. {$result['items_updated']} item(s) updated in inventory.");
    redirect('inventory.php');
}

$inventory = query(
    'SELECT i.id, i.name, i.sku, i.quantity_on_hand, i.unit_cost, c.name as category_name
     FROM inventory_items i LEFT JOIN inventory_categories c ON c.id=i.category_id
     WHERE i.deleted_at IS NULL ORDER BY i.name'
);
$categories = query('SELECT id, name FROM inventory_categories ORDER BY name');
$preselectItem = (int)($_GET['item'] ?? 0);
$preselect = $preselectItem
    ? queryOne('SELECT id, name, unit_cost, quantity_on_hand FROM inventory_items WHERE id=? AND deleted_at IS NULL', [$preselectItem])
    : null;

$currentPage = 'inventory-buy';
$pageTitle = 'Buy / Restock';
$loadInventorySheet = true;
$pageScripts = ['assets/js/inventory-buy.js'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header inv-sheet-page">
    <div>
        <h1>Buy / Restock</h1>
        <p class="subtitle">Enter a vendor receipt like a spreadsheet — new items and restocks in one save</p>
    </div>
    <div class="flex">
        <a href="purchases.php" class="btn btn-secondary">Purchase history</a>
        <a href="inventory.php" class="btn btn-secondary">Back to inventory</a>
    </div>
</div>

<form method="post" id="inventory-buy-form" class="card inv-sheet-card">
    <?= csrfField() ?>

    <div class="inv-sheet-header-fields">
        <div class="form-group">
            <label>Vendor / store</label>
            <input name="supplier" placeholder="e.g. Michaels, Amazon, wholesale" autofocus>
        </div>
        <div class="form-group">
            <label>Date</label>
            <input type="date" name="purchase_date" value="<?= e(date('Y-m-d')) ?>" required>
        </div>
        <div class="form-group">
            <label>Notes <span class="text-muted">(optional)</span></label>
            <input name="notes" placeholder="Invoice #, PO, delivery notes">
        </div>
    </div>

    <div class="inv-sheet-toolbar">
        <span class="inv-sheet-hint">Type an item name to restock or create. Tab / Enter moves cells. Paste from Excel works.</span>
        <span class="spacer"></span>
        <button type="button" class="btn btn-secondary btn-sm" id="inv-buy-add-row">+ Row</button>
    </div>

    <div id="inventory-buy-sheet"></div>

    <div class="inv-sheet-footer">
        <div class="inv-sheet-total">Receipt total: $<span id="inv-buy-total">0.00</span></div>
        <span class="inv-sheet-status" id="inv-buy-count">0 lines</span>
        <span class="spacer"></span>
        <button type="submit" class="btn btn-primary">Save all &amp; update stock</button>
    </div>
</form>

<script>
window.INV_BUY = {
    items: <?= json_encode(array_map(static fn($i) => [
        'id' => (int)$i['id'],
        'name' => $i['name'],
        'sku' => $i['sku'],
        'qty' => (int)$i['quantity_on_hand'],
        'cost' => (float)$i['unit_cost'],
        'category_name' => $i['category_name'],
    ], $inventory), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
    categories: <?= json_encode(array_map(static fn($c) => [
        'id' => (int)$c['id'],
        'name' => $c['name'],
    ], $categories), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
    preselect: <?= $preselect ? json_encode([
        'id' => (int)$preselect['id'],
        'name' => $preselect['name'],
        'cost' => (float)$preselect['unit_cost'],
        'qty' => (int)$preselect['quantity_on_hand'],
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) : 'null' ?>
};
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
