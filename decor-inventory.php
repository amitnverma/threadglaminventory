<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/decor-inventory-functions.php';
require_once __DIR__ . '/includes/decor-proposal-functions.php';
requireDecorOwner();
ensureDecorInventorySchema();
ensureDecorProposalSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete' && !empty($_POST['id'])) {
        $err = decorInventoryDelete((int)$_POST['id']);
        flash($err ? 'error' : 'success', $err ?: 'Decor item deleted.');
        redirect('decor-inventory.php');
    }

    if ($action === 'mark_returned' && !empty($_POST['id'])) {
        $err = decorInventoryMarkReturned(
            (int)$_POST['id'],
            trim($_POST['returned_at'] ?? date('Y-m-d')),
            (float)($_POST['refund_amount'] ?? 0)
        );
        flash($err ? 'error' : 'success', $err ?: 'Marked as returned.');
        redirect('decor-inventory.php');
    }

    if ($action === 'transfer') {
        $ids = array_map('intval', $_POST['ids'] ?? []);
        $mappings = [];
        foreach ($ids as $id) {
            $mode = $_POST['transfer_mode'][$id] ?? '';
            $mappings[$id] = [
                'mode' => $mode,
                'category_id' => (int)($_POST['transfer_category_id'][$id] ?? 0),
                'inventory_item_id' => (int)($_POST['transfer_inventory_id'][$id] ?? 0),
                'quantity' => (int)($_POST['transfer_qty'][$id] ?? 0),
            ];
        }
        [$n, $err] = decorInventoryTransferBatch($ids, $mappings);
        if ($err) {
            flash('error', $err);
        } else {
            flash('success', $n . ' item' . ($n === 1 ? '' : 's') . ' handed off to master inventory.');
        }
        redirect('decor-inventory.php');
    }

    redirect('decor-inventory.php');
}

$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'status' => $_GET['status'] ?? '',
];
$items = decorInventoryList($filters);
$summary = decorInventorySummary($filters);
$categories = query('SELECT id, name FROM inventory_categories ORDER BY name');
$inventoryOptions = query(
    'SELECT id, name, sku, quantity_on_hand FROM inventory_items WHERE deleted_at IS NULL ORDER BY name'
);
$handoffableItems = array_values(array_filter($items, static function ($row) {
    return decorInventoryIsHandoffable($row) && (int)$row['available_qty'] > 0;
}));

$gridRows = array_map(static function ($row) {
    $returned = (int)$row['is_returned'] === 1;
    if ($returned) {
        $status = '<span class="badge badge-draft">Returned</span>';
    } elseif ((int)$row['handed_off_qty'] > 0) {
        $status = '<span class="badge badge-approved">Handoffs ' . (int)$row['handed_off_qty'] . '</span>';
    } elseif ((int)$row['quantity_on_hand'] > 0) {
        $status = '<span class="badge badge-sent">In stock</span>';
    } else {
        $status = '<span class="badge badge-draft">Depleted</span>';
    }

    return [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'purchased_from' => $row['purchased_from'] ?? '',
        'purchase_date' => $row['purchase_date'],
        'quantity' => (int)$row['quantity'],
        'quantity_on_hand' => (int)$row['quantity_on_hand'],
        'available_qty' => (int)$row['available_qty'],
        'unit_price' => (float)$row['unit_price'],
        'default_markup_percent' => (float)$row['default_markup_percent'],
        'is_returned' => $returned ? 1 : 0,
        'status_label' => $status,
    ];
}, $items);

$currentPage = 'decor-inventory';
$pageTitle = 'Decor Inventory';
$loadDecorInventory = true;
$loadInventorySheet = true;
$pageScripts = ['assets/js/decor-inventory-grid.js'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header inv-sheet-page">
    <div>
        <h1>Decor Inventory</h1>
        <p class="subtitle">Editable stock sheet — buy many items at once, hand off when ready</p>
    </div>
    <div class="flex">
        <a href="decor-events.php" class="btn btn-secondary">Event proposals</a>
        <a href="decor-inventory-form.php" class="btn btn-secondary">Advanced add</a>
        <a href="decor-inventory-buy.php" class="btn btn-primary">Buy items</a>
    </div>
</div>

<div class="decor-summary-grid">
    <div class="card decor-stat">
        <div class="decor-stat-label">Purchased</div>
        <div class="decor-stat-value"><?= e(formatMoney($summary['purchased_total'])) ?></div>
    </div>
    <div class="card decor-stat">
        <div class="decor-stat-label">Refunded</div>
        <div class="decor-stat-value"><?= e(formatMoney($summary['refunded_total'])) ?></div>
    </div>
    <div class="card decor-stat">
        <div class="decor-stat-label">Net spend</div>
        <div class="decor-stat-value"><?= e(formatMoney($summary['net_total'])) ?></div>
    </div>
    <div class="card decor-stat">
        <div class="decor-stat-label">Owned stock value</div>
        <div class="decor-stat-value"><?= e(formatMoney($summary['owned_value'])) ?></div>
        <div class="hint"><?= (int)$summary['owned_units'] ?> units on hand</div>
    </div>
</div>

<form method="get" class="card decor-filters">
    <div class="flex decor-filter-row">
        <div class="form-group" style="flex:1;margin:0">
            <label>Search</label>
            <input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Name, store, notes…">
        </div>
        <div class="form-group" style="margin:0;min-width:180px">
            <label>Status</label>
            <select name="status">
                <option value="">All</option>
                <option value="available" <?= $filters['status'] === 'available' ? 'selected' : '' ?>>Available stock</option>
                <option value="depleted" <?= $filters['status'] === 'depleted' ? 'selected' : '' ?>>No owned stock</option>
                <option value="handed_off" <?= $filters['status'] === 'handed_off' ? 'selected' : '' ?>>Has handoffs</option>
                <option value="returned" <?= $filters['status'] === 'returned' ? 'selected' : '' ?>>Returned</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;align-self:flex-end">
            <button type="submit" class="btn btn-secondary">Filter</button>
            <a href="decor-inventory.php" class="btn btn-secondary">Reset</a>
        </div>
    </div>
</form>

<?php if (empty($items)): ?>
<div class="card">
    <div class="empty-state">
        <div class="icon">🧶</div>
        <h3>No Decor purchases yet</h3>
        <p>Record a multi-item receipt to build owned Decor stock.</p>
        <a href="decor-inventory-buy.php" class="btn btn-primary">Buy items</a>
    </div>
</div>
<?php else: ?>
<div class="card inv-sheet-card">
    <div class="inv-sheet-toolbar">
        <span class="inv-sheet-hint">Edit cells inline. Use ⋯ for notes/returns details. Save when done.</span>
        <span class="spacer"></span>
        <span class="inv-sheet-status" id="decor-grid-selected">0 selected</span>
        <button type="button" class="btn btn-danger btn-sm" id="decor-grid-delete-selected" disabled>Delete selected</button>
        <span class="inv-sheet-status" id="decor-grid-status">All saved</span>
        <button type="button" class="btn btn-primary btn-sm" id="decor-grid-save" disabled>Save changes</button>
    </div>
    <div id="decor-edit-sheet"></div>
</div>
<?php endif; ?>

<?php if (!empty($handoffableItems)): ?>
<form method="post" id="decor-transfer-form">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="transfer">

    <div class="card">
        <h3>Hand off to master inventory</h3>
        <p class="text-muted mb-1">Select free Decor stock to create or restock master inventory items.</p>
        <div class="decor-bulk-bar">
            <label class="checkbox-inline">
                <input type="checkbox" id="decor-select-all"> Select all with free stock
            </label>
            <span class="text-muted" id="decor-selected-count">0 selected</span>
            <button type="button" class="btn btn-primary" id="decor-open-transfer" disabled>Hand off selected…</button>
        </div>

        <div class="table-wrap">
            <table class="data-table decor-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Item</th>
                        <th>Free</th>
                        <th>Unit cost</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($handoffableItems as $row):
                    $free = (int)$row['available_qty'];
                ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="decor-item-check" name="ids[]" value="<?= (int)$row['id'] ?>"
                                data-free="<?= $free ?>">
                        </td>
                        <td><strong><?= e($row['name']) ?></strong></td>
                        <td><strong><?= $free ?></strong></td>
                        <td><?= e(formatMoney($row['unit_price'])) ?></td>
                    </tr>
                    <tr class="decor-transfer-options" data-for="<?= (int)$row['id'] ?>" hidden>
                        <td colspan="4">
                            <div class="decor-transfer-map">
                                <strong>Handoff mapping for <?= e($row['name']) ?></strong>
                                <div class="grid-3">
                                    <div class="form-group">
                                        <label>Quantity to transfer (max <?= $free ?>)</label>
                                        <input type="number" name="transfer_qty[<?= (int)$row['id'] ?>]" class="decor-transfer-qty"
                                            data-id="<?= (int)$row['id'] ?>" value="<?= $free ?>" min="1" max="<?= $free ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Action</label>
                                        <select name="transfer_mode[<?= (int)$row['id'] ?>]" class="decor-transfer-mode" data-id="<?= (int)$row['id'] ?>">
                                            <option value="">Choose…</option>
                                            <option value="new">Create new inventory item</option>
                                            <option value="existing">Add to existing inventory item</option>
                                        </select>
                                    </div>
                                    <div class="form-group decor-transfer-new" data-id="<?= (int)$row['id'] ?>" hidden>
                                        <label>Category</label>
                                        <select name="transfer_category_id[<?= (int)$row['id'] ?>]">
                                            <option value="">— Optional —</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group decor-transfer-existing" data-id="<?= (int)$row['id'] ?>" hidden>
                                        <label>Existing item</label>
                                        <select name="transfer_inventory_id[<?= (int)$row['id'] ?>]">
                                            <option value="">Choose item…</option>
                                            <?php foreach ($inventoryOptions as $inv): ?>
                                                <option value="<?= (int)$inv['id'] ?>">
                                                    <?= e($inv['name']) ?> (<?= e($inv['sku'] ?: ('#' . $inv['id'])) ?>, stock <?= (int)$inv['quantity_on_hand'] ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>
<?php endif; ?>

<dialog id="decor-return-dialog" class="decor-dialog">
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="mark_returned">
        <input type="hidden" name="id" id="decor_return_id" value="">
        <h3>Mark as returned</h3>
        <p class="hint">This sets owned stock to zero. Cancel event reservations first if any are active.</p>
        <div class="form-group">
            <label>Return date</label>
            <input type="date" name="returned_at" id="decor_return_date" value="<?= e(date('Y-m-d')) ?>" required>
        </div>
        <div class="form-group">
            <label>Refund amount</label>
            <input type="number" name="refund_amount" id="decor_return_refund" min="0" step="0.01" required>
        </div>
        <div class="flex">
            <button type="submit" class="btn btn-primary">Save return</button>
            <button type="button" class="btn btn-secondary" id="decor-return-cancel">Cancel</button>
        </div>
    </form>
</dialog>

<script>
window.DECOR_GRID = {
    csrf: <?= json_encode(csrfToken(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
    apiUrl: 'decor-inventory-api.php',
    rows: <?= json_encode($gridRows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
};
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
