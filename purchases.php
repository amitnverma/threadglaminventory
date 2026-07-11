<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    if ($action === 'delete' && !empty($_POST['id'])) {
        execute('DELETE FROM purchase_line_items WHERE purchase_id=?', [$_POST['id']]);
        execute('DELETE FROM purchases WHERE id=?', [$_POST['id']]);
        flash('success', 'Purchase deleted.');
        redirect('purchases.php');
    }
    $labels = $_POST['line_label'] ?? [];
    $total = 0;
    execute('INSERT INTO purchases (supplier, purchase_date, total, notes) VALUES (?,?,?,?)',
        [$_POST['supplier'] ?? null, $_POST['purchase_date'], 0, $_POST['notes'] ?? null]);
    $purchaseId = lastId();
    for ($i = 0; $i < count($labels); $i++) {
        if (!trim($labels[$i])) continue;
        $qty = (int)($_POST['line_qty'][$i] ?? 1);
        $cost = (float)($_POST['line_cost'][$i] ?? 0);
        $lineTotal = $qty * $cost;
        $total += $lineTotal;
        $invId = $_POST['line_inventory_id'][$i] ?: null;
        execute('INSERT INTO purchase_line_items (purchase_id,inventory_item_id,label,quantity,unit_cost,line_total) VALUES (?,?,?,?,?,?)',
            [$purchaseId, $invId, $labels[$i], $qty, $cost, $lineTotal]);
        if ($invId) {
            $item = queryOne('SELECT quantity_on_hand, unit_cost FROM inventory_items WHERE id=?', [$invId]);
            $newQty = $item['quantity_on_hand'] + $qty;
            $avgCost = $newQty > 0 ? (($item['quantity_on_hand'] * $item['unit_cost']) + $lineTotal) / $newQty : $cost;
            execute('UPDATE inventory_items SET quantity_on_hand=?, unit_cost=?, reorder_level=? WHERE id=?', [$newQty, $avgCost, generateReorderLevel($newQty), $invId]);
        }
    }
    execute('UPDATE purchases SET total=? WHERE id=?', [$total, $purchaseId]);
    flash('success', 'Purchase recorded.');
    redirect('purchases.php');
}

$currentPage = 'purchases';
$pageTitle = 'Purchases';
require_once __DIR__ . '/includes/header.php';

$purchases = query('SELECT * FROM purchases ORDER BY purchase_date DESC');
$inventory = query('SELECT id, name, unit_cost FROM inventory_items WHERE deleted_at IS NULL ORDER BY name');
?>

<div class="page-header">
    <div>
        <h1>Purchases</h1>
        <p class="subtitle">Record supplier purchases and update inventory</p>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Record Purchase</h3>
        <form method="post">
            <div class="form-row">
                <div class="form-group"><label>Supplier</label><input name="supplier" placeholder="Vendor / supplier name"></div>
                <div class="form-group"><label>Date</label><input type="date" name="purchase_date" value="<?= date('Y-m-d') ?>" required></div>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <tr><th>Item</th><th>Qty</th><th>Unit Cost</th></tr>
                    <tr>
                        <td>
                            <select name="line_inventory_id[]" onchange="var o=this.options[this.selectedIndex];this.form.line_label[0].value=o.text;this.form.line_cost[0].value=o.dataset.cost||0">
                                <option value="">Custom item</option>
                                <?php foreach ($inventory as $inv): ?><option value="<?= $inv['id'] ?>" data-cost="<?= $inv['unit_cost'] ?>"><?= e($inv['name']) ?></option><?php endforeach; ?>
                            </select>
                            <input name="line_label[]" placeholder="Item description" class="mt-1">
                        </td>
                        <td><input type="number" name="line_qty[]" value="1" min="1"></td>
                        <td><input type="number" step="0.01" name="line_cost[]" value="0"></td>
                    </tr>
                </table>
            </div>
            <div class="form-group mt-1"><label>Notes</label><textarea name="notes" placeholder="Invoice number, delivery notes"></textarea></div>
            <button class="btn btn-primary">Save Purchase</button>
        </form>
    </div>
    <div class="card">
        <h3>Purchase History</h3>
        <?php if (empty($purchases)): ?>
            <p class="text-muted">No purchases recorded yet.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <tr><th>Date</th><th>Supplier</th><th>Total</th><th></th></tr>
                <?php foreach ($purchases as $p): ?>
                <tr>
                    <td><?= formatDate($p['purchase_date']) ?></td>
                    <td><?= e($p['supplier'] ?: '—') ?></td>
                    <td><strong><?= formatMoney($p['total']) ?></strong></td>
                    <td>
                        <form method="post" onsubmit="return confirm('Delete this purchase?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
