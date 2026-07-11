<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete' && !empty($_POST['id'])) {
        reversePurchaseInventory((int)$_POST['id']);
        execute('DELETE FROM purchase_line_items WHERE purchase_id=?', [$_POST['id']]);
        execute('DELETE FROM purchases WHERE id=?', [$_POST['id']]);
        flash('success', 'Purchase deleted and inventory stock reversed.');
        redirect('purchases.php');
    }

    $modes = $_POST['line_mode'] ?? [];
    $invIds = $_POST['line_inventory_id'] ?? [];
    $newNames = $_POST['line_new_name'] ?? [];
    $categoryIds = $_POST['line_category_id'] ?? [];
    $qtys = $_POST['line_qty'] ?? [];
    $costs = $_POST['line_cost'] ?? [];
    $supplier = trim($_POST['supplier'] ?? '') ?: 'Supplier';
    $purchaseDate = $_POST['purchase_date'] ?? date('Y-m-d');
    $reason = 'Purchase from ' . $supplier . ' on ' . $purchaseDate;

    execute('INSERT INTO purchases (supplier, purchase_date, total, notes) VALUES (?,?,?,?)',
        [$_POST['supplier'] ?? null, $purchaseDate, 0, $_POST['notes'] ?? null]);
    $purchaseId = (int)lastId();

    $total = 0;
    $itemsUpdated = 0;

    for ($i = 0; $i < count($modes); $i++) {
        $qty = max(1, (int)($qtys[$i] ?? 1));
        $cost = (float)($costs[$i] ?? 0);
        $lineTotal = $qty * $cost;
        $mode = $modes[$i] ?? 'existing';
        $invId = null;
        $label = '';

        if ($mode === 'new') {
            $name = trim($newNames[$i] ?? '');
            if ($name === '') continue;
            $catId = ($categoryIds[$i] ?? '') ?: null;
            $invId = createInventoryFromPurchase($name, $qty, $cost, $catId, $reason);
            $label = $name;
            $itemsUpdated++;
        } else {
            $invId = (int)($invIds[$i] ?? 0);
            if ($invId <= 0) continue;
            $item = queryOne('SELECT name FROM inventory_items WHERE id=? AND deleted_at IS NULL', [$invId]);
            if (!$item) continue;
            $label = $item['name'];
            addInventoryStock($invId, $qty, $cost, $reason);
            $itemsUpdated++;
        }

        $total += $lineTotal;
        execute(
            'INSERT INTO purchase_line_items (purchase_id,inventory_item_id,label,quantity,unit_cost,line_total) VALUES (?,?,?,?,?,?)',
            [$purchaseId, $invId, $label, $qty, $cost, $lineTotal]
        );
    }

    execute('UPDATE purchases SET total=? WHERE id=?', [$total, $purchaseId]);

    if ($itemsUpdated === 0) {
        execute('DELETE FROM purchase_line_items WHERE purchase_id=?', [$purchaseId]);
        execute('DELETE FROM purchases WHERE id=?', [$purchaseId]);
        flash('error', 'Add at least one inventory item to the purchase.');
        redirect('purchases.php');
    }

    flash('success', "Purchase saved. {$itemsUpdated} inventory item(s) updated.");
    redirect('purchase-view.php?id=' . $purchaseId);
}

$currentPage = 'purchases';
$pageTitle = 'Purchases';
require_once __DIR__ . '/includes/header.php';

$purchases = query(
    'SELECT p.*, COUNT(pli.id) as line_count,
     GROUP_CONCAT(CONCAT(pli.label, " (+", pli.quantity, ")") ORDER BY pli.id SEPARATOR ", ") as items_summary
     FROM purchases p
     LEFT JOIN purchase_line_items pli ON pli.purchase_id = p.id
     GROUP BY p.id ORDER BY p.purchase_date DESC'
);
$inventory = query(
    'SELECT i.id, i.name, i.sku, i.quantity_on_hand, i.unit_cost, c.name as category_name
     FROM inventory_items i LEFT JOIN inventory_categories c ON c.id=i.category_id
     WHERE i.deleted_at IS NULL ORDER BY i.name'
);
$categories = query('SELECT id, name FROM inventory_categories ORDER BY name');
$preselectItem = (int)($_GET['item'] ?? 0);
?>

<div class="page-header">
    <div>
        <h1>Purchases</h1>
        <p class="subtitle">Every purchase automatically adds stock to inventory</p>
    </div>
    <a href="inventory.php" class="btn btn-secondary">View Inventory</a>
</div>

<div class="card">
    <h3>Record Purchase</h3>
    <p class="text-muted mb-1">Select an existing inventory item to restock, or create a new item. Stock quantities update automatically on save.</p>
    <form method="post" id="purchase-form">
        <div class="form-row">
            <div class="form-group"><label>Supplier</label><input name="supplier" placeholder="Vendor / supplier name"></div>
            <div class="form-group"><label>Purchase Date</label><input type="date" name="purchase_date" value="<?= date('Y-m-d') ?>" required></div>
        </div>

        <div class="table-wrap">
            <table class="data-table" id="purchase-lines-table">
                <thead>
                    <tr>
                        <th style="width:140px">Action</th>
                        <th>Inventory Item</th>
                        <th style="width:80px">Qty</th>
                        <th style="width:110px">Unit Cost ($)</th>
                        <th style="width:120px">Stock After</th>
                        <th style="width:50px"></th>
                    </tr>
                </thead>
                <tbody id="purchase-lines">
                    <tr class="purchase-row">
                        <td>
                            <select name="line_mode[]" class="line-mode" onchange="togglePurchaseRow(this)">
                                <option value="existing">Restock existing</option>
                                <option value="new">Create new item</option>
                            </select>
                        </td>
                        <td>
                            <div class="existing-fields">
                                <select name="line_inventory_id[]" class="line-inventory" onchange="updatePurchaseRow(this)" required>
                                    <option value="">— Select inventory item —</option>
                                    <?php foreach ($inventory as $inv): ?>
                                    <option value="<?= $inv['id'] ?>" data-qty="<?= (int)$inv['quantity_on_hand'] ?>" data-cost="<?= $inv['unit_cost'] ?>" data-name="<?= e($inv['name']) ?>">
                                        <?= e($inv['name']) ?> (<?= e($inv['sku']) ?>) — <?= (int)$inv['quantity_on_hand'] ?> in stock
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="new-fields" style="display:none">
                                <input type="text" name="line_new_name[]" placeholder="New item name" class="mb-1">
                                <select name="line_category_id[]">
                                    <option value="">Category (optional)</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </td>
                        <td><input type="number" name="line_qty[]" value="1" min="1" class="line-qty" onchange="updatePurchaseRow(this)" oninput="updatePurchaseRow(this)"></td>
                        <td><input type="number" step="0.01" name="line_cost[]" value="0" min="0" class="line-cost" onchange="updatePurchaseRow(this)" oninput="updatePurchaseRow(this)"></td>
                        <td class="stock-after text-muted">—</td>
                        <td><button type="button" class="btn btn-sm btn-danger" onclick="removePurchaseRow(this)" title="Remove row">×</button></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex mt-1">
            <button type="button" class="btn btn-secondary btn-sm" onclick="addPurchaseRow()">+ Add Another Item</button>
        </div>
        <div class="form-group mt-1"><label>Notes</label><textarea name="notes" placeholder="Invoice #, PO number, delivery notes"></textarea></div>
        <button type="submit" class="btn btn-primary">Save Purchase &amp; Update Inventory</button>
    </form>
</div>

<div class="card">
    <h3>Purchase History</h3>
    <?php if (empty($purchases)): ?>
        <p class="text-muted">No purchases recorded yet.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <tr><th>Date</th><th>Supplier</th><th>Items Added to Inventory</th><th>Total</th><th></th></tr>
            <?php foreach ($purchases as $p): ?>
            <tr>
                <td><?= formatDate($p['purchase_date']) ?></td>
                <td><?= e($p['supplier'] ?: '—') ?></td>
                <td>
                    <?php if ($p['items_summary']): ?>
                        <span class="text-muted" style="font-size:.85rem"><?= e($p['items_summary']) ?></span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td><strong><?= formatMoney($p['total']) ?></strong></td>
                <td>
                    <div class="action-btns">
                        <a href="purchase-view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-secondary">View</a>
                        <form method="post" onsubmit="return confirm('Delete this purchase and reverse inventory stock?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
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
window.PURCHASE_INVENTORY = <?= json_encode(array_map(fn($i) => [
    'id' => $i['id'], 'name' => $i['name'], 'qty' => (int)$i['quantity_on_hand'], 'cost' => (float)$i['unit_cost'], 'sku' => $i['sku']
], $inventory)) ?>;
window.PURCHASE_CATEGORIES = <?= json_encode($categories) ?>;
window.PURCHASE_PRESELECT = <?= $preselectItem ?>;
</script>
<script src="assets/js/purchases.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
