<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/decor-inventory-functions.php';
require_once __DIR__ . '/includes/decor-proposal-functions.php';
requireDecorOwner();
ensureDecorInventorySchema();
ensureDecorProposalSchema();

$id = (int)($_GET['id'] ?? 0);
$item = $id ? decorInventoryGet($id) : null;

if ($id && !$item) {
    flash('error', 'Decor item not found.');
    redirect('decor-inventory.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $payload = $_POST;
    $payload['is_returned'] = isset($_POST['is_returned']);

    if ($item) {
        $err = decorInventoryUpdate($id, $payload);
        flash($err ? 'error' : 'success', $err ?: 'Decor item updated.');
        redirect($err ? ('decor-inventory-form.php?id=' . $id) : 'decor-inventory.php');
    }

    $err = decorInventoryCreate($payload);
    flash($err ? 'error' : 'success', $err ?: 'Decor purchase recorded.');
    redirect($err ? 'decor-inventory-form.php' : 'decor-inventory.php');
}

$handoffs = $item ? decorInventoryHandoffs((int)$item['id']) : [];
$reserved = $item ? decorReservedQuantity((int)$item['id']) : 0;

$currentPage = 'decor-inventory';
$pageTitle = $item ? 'Edit Decor Item' : 'Add Decor Purchase';
$loadDecorInventory = true;
require_once __DIR__ . '/includes/header.php';

$values = [
    'name' => $item['name'] ?? ($_POST['name'] ?? ''),
    'description' => $item['description'] ?? ($_POST['description'] ?? ''),
    'purchased_from' => $item['purchased_from'] ?? ($_POST['purchased_from'] ?? ''),
    'purchase_date' => $item['purchase_date'] ?? ($_POST['purchase_date'] ?? date('Y-m-d')),
    'quantity' => $item['quantity'] ?? ($_POST['quantity'] ?? 1),
    'unit_price' => $item['unit_price'] ?? ($_POST['unit_price'] ?? '0.00'),
    'default_markup_percent' => $item['default_markup_percent'] ?? ($_POST['default_markup_percent'] ?? '0'),
    'is_returned' => isset($item) ? (int)$item['is_returned'] : !empty($_POST['is_returned']),
    'returned_at' => $item['returned_at'] ?? ($_POST['returned_at'] ?? ''),
    'refund_amount' => $item['refund_amount'] ?? ($_POST['refund_amount'] ?? '0.00'),
    'notes' => $item['notes'] ?? ($_POST['notes'] ?? ''),
];
?>

<div class="page-header">
    <div>
        <h1><?= $item ? 'Edit Decor Item' : 'Add Decor Purchase' ?></h1>
        <p class="subtitle">Purchases become Decor-owned stock you can reserve to events or hand off to master inventory</p>
    </div>
    <div class="flex">
        <a href="decor-inventory-buy.php" class="btn btn-secondary">Buy many items</a>
        <a href="decor-inventory.php" class="btn btn-secondary">Back to Decor Inventory</a>
    </div>
</div>

<?php if ($item): ?>
<div class="decor-summary-grid" style="grid-template-columns:repeat(4,1fr)">
    <div class="card decor-stat">
        <div class="decor-stat-label">Purchased</div>
        <div class="decor-stat-value"><?= (int)$item['quantity'] ?></div>
    </div>
    <div class="card decor-stat">
        <div class="decor-stat-label">Owned</div>
        <div class="decor-stat-value"><?= (int)$item['quantity_on_hand'] ?></div>
    </div>
    <div class="card decor-stat">
        <div class="decor-stat-label">Reserved</div>
        <div class="decor-stat-value"><?= (int)$reserved ?></div>
    </div>
    <div class="card decor-stat">
        <div class="decor-stat-label">Free</div>
        <div class="decor-stat-value"><?= max(0, (int)$item['quantity_on_hand'] - (int)$reserved) ?></div>
    </div>
</div>
<?php endif; ?>

<div class="card decor-form-card">
    <form method="post" id="decor-item-form">
        <?= csrfField() ?>
        <div class="grid-2">
            <div class="form-group">
                <label>Item name *</label>
                <input name="name" value="<?= e((string)$values['name']) ?>" required maxlength="255">
            </div>
            <div class="form-group">
                <label>Purchased from / store</label>
                <input name="purchased_from" value="<?= e((string)$values['purchased_from']) ?>" maxlength="255" placeholder="e.g. Amazon, Michaels">
            </div>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="3"><?= e((string)$values['description']) ?></textarea>
        </div>

        <div class="grid-3">
            <div class="form-group">
                <label>Purchase date *</label>
                <input type="date" name="purchase_date" value="<?= e((string)$values['purchase_date']) ?>" required>
            </div>
            <div class="form-group">
                <label>Purchased quantity *</label>
                <input type="number" name="quantity" id="decor_quantity" value="<?= e((string)$values['quantity']) ?>" min="1" step="1" required>
            </div>
            <div class="form-group">
                <label>Unit cost *</label>
                <input type="number" name="unit_price" id="decor_unit_price" value="<?= e(number_format((float)$values['unit_price'], 2, '.', '')) ?>" min="0" step="0.01" required>
            </div>
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label>Default markup %</label>
                <input type="number" name="default_markup_percent" id="decor_markup" value="<?= e(number_format((float)$values['default_markup_percent'], 2, '.', '')) ?>" min="0" step="0.01">
                <p class="hint">Suggested event rate: <strong id="decor_suggested_rate">$0.00</strong></p>
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <p class="hint">Line total: <strong id="decor_line_total">$0.00</strong></p>
            </div>
        </div>

        <div class="card decor-return-box">
            <label class="checkbox-inline">
                <input type="checkbox" name="is_returned" id="decor_is_returned" <?= $values['is_returned'] ? 'checked' : '' ?>>
                Item was returned
            </label>
            <div class="grid-2 decor-return-fields" id="decor_return_fields" style="<?= $values['is_returned'] ? '' : 'display:none' ?>">
                <div class="form-group">
                    <label>Return date</label>
                    <input type="date" name="returned_at" value="<?= e((string)$values['returned_at']) ?>">
                </div>
                <div class="form-group">
                    <label>Refund amount</label>
                    <input type="number" name="refund_amount" value="<?= e(number_format((float)$values['refund_amount'], 2, '.', '')) ?>" min="0" step="0.01">
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" rows="2"><?= e((string)$values['notes']) ?></textarea>
        </div>

        <div class="flex" style="margin-top:1rem">
            <button type="submit" class="btn btn-primary"><?= $item ? 'Save changes' : 'Save purchase' ?></button>
            <a href="decor-inventory.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php if ($handoffs): ?>
<div class="card">
    <h3>Master inventory handoffs</h3>
    <div class="table-wrap">
        <table class="data-table">
            <tr><th>When</th><th>Qty</th><th>Cost</th><th>Mode</th><th>Inventory item</th></tr>
            <?php foreach ($handoffs as $h): ?>
            <tr>
                <td><?= e(formatDate($h['created_at'])) ?></td>
                <td><?= (int)$h['quantity'] ?></td>
                <td><?= e(formatMoney($h['unit_cost'])) ?></td>
                <td><?= e($h['transfer_mode']) ?></td>
                <td>
                    <?php if ($h['inventory_item_id']): ?>
                        <a href="inventory-view.php?id=<?= (int)$h['inventory_item_id'] ?>">
                            <?= e($h['inventory_name'] ?: ('#' . $h['inventory_item_id'])) ?>
                            <?= $h['inventory_sku'] ? ' (' . e($h['inventory_sku']) . ')' : '' ?>
                        </a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
