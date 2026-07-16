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

$currentPage = 'decor-inventory';
$pageTitle = 'Decor Inventory';
$loadDecorInventory = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Decor Inventory</h1>
        <p class="subtitle">Owned stock, spending, and selective handoff to master inventory</p>
    </div>
    <div class="flex">
        <a href="decor-events.php" class="btn btn-secondary">Event proposals</a>
        <a href="decor-inventory-form.php" class="btn btn-primary">+ Add purchase</a>
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

<form method="post" id="decor-transfer-form">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="transfer">

    <div class="card">
        <div class="decor-bulk-bar">
            <label class="checkbox-inline">
                <input type="checkbox" id="decor-select-all"> Select all with free stock
            </label>
            <span class="text-muted" id="decor-selected-count">0 selected</span>
            <button type="button" class="btn btn-primary" id="decor-open-transfer" disabled>Hand off selected…</button>
        </div>

        <?php if (empty($items)): ?>
            <div class="empty-state">
                <div class="icon">🧶</div>
                <h3>No Decor purchases yet</h3>
                <p>Record purchases to build owned Decor stock. Reserve items to events from Event proposals.</p>
                <a href="decor-inventory-form.php" class="btn btn-primary">Add first purchase</a>
            </div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table decor-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Item</th>
                        <th>Where</th>
                        <th>Date</th>
                        <th>Bought</th>
                        <th>Owned</th>
                        <th>Reserved</th>
                        <th>Free</th>
                        <th>Cost</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $row):
                    $handoffable = decorInventoryIsHandoffable($row) && (int)$row['available_qty'] > 0;
                    $returned = (int)$row['is_returned'] === 1;
                    $free = (int)$row['available_qty'];
                ?>
                    <tr class="<?= $returned ? 'decor-row-returned' : ((int)$row['quantity_on_hand'] === 0 ? 'decor-row-transferred' : '') ?>">
                        <td>
                            <?php if ($handoffable): ?>
                                <input type="checkbox" class="decor-item-check" name="ids[]" value="<?= (int)$row['id'] ?>"
                                    data-free="<?= $free ?>">
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= e($row['name']) ?></strong>
                            <?php if (!empty($row['description'])): ?>
                                <div class="text-muted decor-desc"><?= e($row['description']) ?></div>
                            <?php endif; ?>
                            <div class="hint">Markup default <?= e(number_format((float)$row['default_markup_percent'], 1)) ?>%</div>
                        </td>
                        <td><?= e($row['purchased_from'] ?: '—') ?></td>
                        <td><?= e(formatDate($row['purchase_date'])) ?></td>
                        <td><?= (int)$row['quantity'] ?></td>
                        <td><?= (int)$row['quantity_on_hand'] ?></td>
                        <td><?= (int)$row['reserved_qty'] ?></td>
                        <td><strong><?= $free ?></strong></td>
                        <td>
                            <?= e(formatMoney($row['unit_price'])) ?>
                            <div class="hint"><?= e(formatMoney($row['line_total'])) ?> total</div>
                        </td>
                        <td>
                            <?php if ($returned): ?>
                                <span class="badge badge-draft">Returned</span>
                                <div class="hint">Refund <?= e(formatMoney($row['refund_amount'])) ?></div>
                            <?php elseif ((int)$row['handed_off_qty'] > 0): ?>
                                <span class="badge badge-approved">Handoffs <?= (int)$row['handed_off_qty'] ?></span>
                                <?php if ((int)$row['quantity_on_hand'] > 0): ?>
                                    <div class="hint">Still owns <?= (int)$row['quantity_on_hand'] ?></div>
                                <?php endif; ?>
                            <?php elseif ((int)$row['quantity_on_hand'] > 0): ?>
                                <span class="badge badge-sent">In stock</span>
                            <?php else: ?>
                                <span class="badge badge-draft">Depleted</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-btns">
                                <a href="decor-inventory-form.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                <?php if (!$returned): ?>
                                <button type="button" class="btn btn-sm btn-secondary decor-return-btn"
                                    data-id="<?= (int)$row['id'] ?>"
                                    data-total="<?= e(number_format((float)$row['line_total'], 2, '.', '')) ?>">
                                    Return
                                </button>
                                <button type="submit" form="decor-delete-<?= (int)$row['id'] ?>" class="btn btn-sm btn-danger"
                                    onclick="return confirm('Delete this Decor purchase record?')">Delete</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php if ($handoffable): ?>
                    <tr class="decor-transfer-options" data-for="<?= (int)$row['id'] ?>" hidden>
                        <td colspan="11">
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
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</form>

<?php foreach ($items as $row):
    if ((int)$row['is_returned'] === 1) continue;
?>
<form method="post" id="decor-delete-<?= (int)$row['id'] ?>" style="display:none">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
</form>
<?php endforeach; ?>

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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
