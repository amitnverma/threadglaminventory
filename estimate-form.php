<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();
ensureEstimatePricingSchema();

$id = $_GET['id'] ?? null;
$customers = query('SELECT * FROM customers WHERE deleted_at IS NULL ORDER BY name');
$events = query('SELECT id, title, customer_id FROM events WHERE deleted_at IS NULL ORDER BY title');
$catalog = query('SELECT i.*, c.name as category_name FROM inventory_items i LEFT JOIN inventory_categories c ON c.id=i.category_id WHERE i.deleted_at IS NULL ORDER BY i.name');
$settings = getSettings();

$estimate = $id ? queryOne('SELECT * FROM estimates WHERE id=?', [$id]) : null;
$lines = $id ? query(
    'SELECT eli.*, i.quantity_on_hand AS inventory_available, i.unit_cost AS inventory_purchase_cost
     FROM estimate_line_items eli
     LEFT JOIN inventory_items i ON i.id=eli.inventory_item_id AND i.deleted_at IS NULL
     WHERE eli.estimate_id=?
     ORDER BY eli.sort_order',
    [$id]
) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $eventId = !empty($_POST['event_id']) ? (int)$_POST['event_id'] : null;
    $customer = $customerId
        ? queryOne('SELECT id FROM customers WHERE id=? AND deleted_at IS NULL', [$customerId])
        : null;
    if (!$customer) {
        flash('error', 'Select a valid customer.');
        redirect($id ? ('estimate-form.php?id=' . $id) : 'estimate-form.php');
    }
    if ($eventId) {
        $event = queryOne(
            'SELECT id, customer_id FROM events WHERE id=? AND deleted_at IS NULL',
            [$eventId]
        );
        if (!$event) {
            flash('error', 'Selected event was not found.');
            redirect($id ? ('estimate-form.php?id=' . $id) : 'estimate-form.php');
        }
        if ((int)$event['customer_id'] !== $customerId) {
            flash('error', 'The selected event does not belong to that customer.');
            redirect($id ? ('estimate-form.php?id=' . $id) : 'estimate-form.php');
        }
    }

    // Preserve Decor-published source links when an admin re-saves a draft estimate.
    $existingSources = [];
    if ($id) {
        foreach (query('SELECT id, sort_order, source_type, source_id FROM estimate_line_items WHERE estimate_id=? ORDER BY sort_order, id', [$id]) as $srcRow) {
            $existingSources[] = $srcRow;
        }
    }

    $labels = $_POST['line_label'] ?? [];
    $lineData = [];
    for ($i = 0; $i < count($labels); $i++) {
        if (trim($labels[$i]) === '') continue;
        $source = $existingSources[$i] ?? null;
        $lineData[] = [
            'line_type' => $_POST['line_type'][$i] ?? 'custom',
            'inventory_item_id' => $_POST['line_inventory_id'][$i] ?: null,
            'label' => $labels[$i],
            'quantity' => (float)($_POST['line_qty'][$i] ?? 1),
            'unit_price' => (float)($_POST['line_price'][$i] ?? 0),
            'unit_cost' => (float)($_POST['line_cost'][$i] ?? 0),
            'source_type' => $_POST['line_source_type'][$i] ?? ($source['source_type'] ?? null),
            'source_id' => !empty($_POST['line_source_id'][$i])
                ? (int)$_POST['line_source_id'][$i]
                : ($source['source_id'] ?? null),
        ];
    }

    $requestedInventory = [];
    foreach ($lineData as $line) {
        if ($line['quantity'] <= 0) {
            flash('error', 'Every estimate line must have a quantity greater than zero.');
            redirect($id ? ('estimate-form.php?id=' . $id) : 'estimate-form.php');
        }
        $inventoryId = (int)($line['inventory_item_id'] ?? 0);
        if ($inventoryId > 0) {
            $requestedInventory[$inventoryId] = ($requestedInventory[$inventoryId] ?? 0) + (float)$line['quantity'];
        }
    }
    if ($requestedInventory) {
        $inventoryIds = array_keys($requestedInventory);
        $placeholders = implode(',', array_fill(0, count($inventoryIds), '?'));
        $inventoryRows = query(
            "SELECT id, name, quantity_on_hand
             FROM inventory_items
             WHERE deleted_at IS NULL AND id IN ($placeholders)",
            $inventoryIds
        );
        $inventoryById = [];
        foreach ($inventoryRows as $inventoryRow) {
            $inventoryById[(int)$inventoryRow['id']] = $inventoryRow;
        }
        foreach ($requestedInventory as $inventoryId => $quantity) {
            $inventoryItem = $inventoryById[$inventoryId] ?? null;
            if (!$inventoryItem) {
                flash('error', 'An inventory item on this estimate is no longer available.');
                redirect($id ? ('estimate-form.php?id=' . $id) : 'estimate-form.php');
            }
            if ($quantity > (int)$inventoryItem['quantity_on_hand']) {
                flash(
                    'error',
                    $inventoryItem['name'] . ' has only ' . (int)$inventoryItem['quantity_on_hand']
                    . ' available in Main Inventory.'
                );
                redirect($id ? ('estimate-form.php?id=' . $id) : 'estimate-form.php');
            }
        }
    }

    $opts = [
        'tax_percent' => $_POST['tax_percent'],
        'discount_type' => $_POST['discount_type'],
        'discount_value' => $_POST['discount_value'],
        'profit_amount' => $_POST['profit_amount'] ?? 0,
    ];
    $totals = calculateEstimateTotals($lineData, $opts);

    if ($id) {
        execute('UPDATE estimates SET customer_id=?,event_id=?,title=?,status=?,subtotal=?,tax_percent=?,tax_amount=?,discount_type=?,discount_value=?,discount_amount=?,profit_amount=?,total=?,notes=?,updated_at=NOW() WHERE id=?',
            [$customerId, $eventId, $_POST['title'], $_POST['status'], $totals['subtotal'], $_POST['tax_percent'], $totals['tax_amount'], $_POST['discount_type'], $_POST['discount_value'], $totals['discount_amount'], $totals['profit_amount'], $totals['total'], $_POST['notes'] ?? null, $id]);
        execute('DELETE FROM estimate_line_items WHERE estimate_id=?', [$id]);
        $estId = $id;
    } else {
        execute('INSERT INTO estimates (customer_id,event_id,title,status,subtotal,tax_percent,tax_amount,discount_type,discount_value,discount_amount,profit_amount,total,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$customerId, $eventId, $_POST['title'], $_POST['status'] ?? 'draft', $totals['subtotal'], $_POST['tax_percent'], $totals['tax_amount'], $_POST['discount_type'], $_POST['discount_value'], $totals['discount_amount'], $totals['profit_amount'], $totals['total'], $_POST['notes'] ?? null]);
        $estId = lastId();
    }
    // Ensure source columns exist (idempotent) for Decor-published lines.
    try {
        $cols = query("SHOW COLUMNS FROM estimate_line_items LIKE 'source_type'");
        if (empty($cols)) {
            execute("ALTER TABLE estimate_line_items ADD COLUMN source_type VARCHAR(32) NULL AFTER notes");
            execute("ALTER TABLE estimate_line_items ADD COLUMN source_id INT NULL AFTER source_type");
        }
    } catch (Exception $e) {
        // ignore
    }
    foreach ($lineData as $i => $line) {
        execute(
            'INSERT INTO estimate_line_items
                (estimate_id,line_type,inventory_item_id,label,quantity,unit_price,unit_cost,sort_order,source_type,source_id)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                $estId,
                $line['line_type'],
                $line['inventory_item_id'],
                $line['label'],
                $line['quantity'],
                $line['unit_price'],
                $line['unit_cost'],
                $i,
                $line['source_type'],
                $line['source_id'],
            ]
        );
    }
    flash('success', 'Estimate saved.');
    redirect('estimate-form.php?id=' . $estId);
}

$currentPage = 'estimates';
$pageTitle = $id ? 'Edit Estimate' : 'New Estimate';
$loadEstimateBuilder = true;
$pageScripts = ['assets/js/estimate-builder.js'];
require_once __DIR__ . '/includes/header.php';
$d = $estimate ?: ['customer_id'=>$_GET['customer_id']??'','event_id'=>$_GET['event_id']??'','title'=>'New Estimate','status'=>'draft','tax_percent'=>$settings['default_tax_percent']??8.875,'discount_type'=>'percent','discount_value'=>0,'profit_amount'=>0,'notes'=>''];
$totals = calculateEstimateTotals($lines, $d);
?>

<div class="estimate-page">
    <div class="page-header estimate-page-header">
        <div>
            <h1><?= $id ? 'Edit' : 'New' ?> Estimate</h1>
            <p class="subtitle">Add from Main Inventory · adjust quantities with − / + · save on the right</p>
        </div>
        <?php if ($id): ?>
        <div class="flex">
            <a href="contract-create.php?estimate_id=<?= $id ?>" class="btn btn-secondary btn-sm">Create contract</a>
            <form method="post" action="estimates.php" onsubmit="return confirm('Delete this estimate?')">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $id ?>">
                <button class="btn btn-danger btn-sm">Delete</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <form method="post" class="estimate-form">
        <div class="card estimate-meta-bar">
            <div class="estimate-meta-grid">
                <div class="form-group">
                    <label>Title</label>
                    <input name="title" value="<?= e($d['title']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Customer *</label>
                    <select name="customer_id" required>
                        <option value="">—</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $d['customer_id']==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Event</label>
                    <select name="event_id" id="estimate_event_id">
                        <option value="">—</option>
                        <?php foreach ($events as $ev): ?>
                            <option value="<?= (int)$ev['id'] ?>"
                                data-customer="<?= (int)$ev['customer_id'] ?>"
                                <?= (int)$d['event_id'] === (int)$ev['id'] ? 'selected' : '' ?>>
                                <?= e($ev['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <?php foreach (['draft','sent','approved','rejected'] as $s): ?>
                            <option value="<?= $s ?>" <?= ($d['status']??'draft')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="estimate-layout">
            <aside class="card estimate-stock-panel">
                <div class="estimate-panel-head">
                    <h3>Main Inventory</h3>
                    <span class="hint"><?= count($catalog) ?> items</span>
                </div>
                <input type="search" id="catalog-search" placeholder="Search stock…" oninput="filterCatalog(this.value)" autocomplete="off">
                <div id="catalog-list" class="estimate-catalog-list">
                    <?php foreach ($catalog as $item): ?>
                    <div class="catalog-item"
                        data-name="<?= e(strtolower($item['name'])) ?>"
                        data-inventory-id="<?= (int)$item['id'] ?>"
                        data-stock-total="<?= (int)$item['quantity_on_hand'] ?>">
                        <div class="catalog-item-info">
                            <strong title="<?= e($item['name']) ?>"><?= e($item['name']) ?></strong>
                            <div class="catalog-item-meta">
                                <span class="is-cost"><?= formatMoney($item['unit_cost']) ?></span>
                                <span class="catalog-stock-count <?= (int)$item['quantity_on_hand'] > 0 ? 'is-available' : 'is-empty' ?>">
                                    <?= (int)$item['quantity_on_hand'] ?>
                                </span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary catalog-add-btn" title="Add" aria-label="Add <?= e($item['name']) ?>"
                            <?= (int)$item['quantity_on_hand'] < 1 ? 'disabled' : '' ?>
                            onclick='addEstimateLine(<?= json_encode([
                                "id" => (int)$item["id"],
                                "label" => $item["name"],
                                "price" => (float)$item["unit_cost"],
                                "cost" => (float)$item["unit_cost"],
                                "available" => (int)$item["quantity_on_hand"],
                                "type" => "inventory"
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'>+</button>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($catalog)): ?>
                        <div class="empty-state estimate-catalog-empty"><p>No inventory yet.</p></div>
                    <?php endif; ?>
                </div>
                <div class="estimate-catalog-actions">
                    <button type="button" class="btn btn-sm btn-secondary" onclick="addCustomLine('custom')">+ Custom</button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="addCustomLine('labor')">+ Labor</button>
                </div>
            </aside>

            <section class="card estimate-lines-panel">
                <div class="estimate-panel-head">
                    <h3>Lines</h3>
                    <span class="hint">Edit cells directly</span>
                </div>
                <div class="estimate-sheet-wrap">
                    <table class="estimate-sheet" id="estimate-lines-table">
                        <thead>
                            <tr>
                                <th class="col-item">Item</th>
                                <th class="col-qty">Qty</th>
                                <th class="col-avail">Avail</th>
                                <th class="col-money">Cost</th>
                                <th class="col-money">Rate</th>
                                <th class="col-money">Amt</th>
                                <th class="col-actions"></th>
                            </tr>
                        </thead>
                        <tbody id="estimate-lines">
                        <?php foreach ($lines as $line):
                            $isInventory = !empty($line['inventory_item_id']);
                            $available = $isInventory && $line['inventory_available'] !== null
                                ? (int)$line['inventory_available']
                                : null;
                            $purchaseCost = $isInventory && $line['inventory_purchase_cost'] !== null
                                ? (float)$line['inventory_purchase_cost']
                                : (float)$line['unit_cost'];
                            $isOverride = $isInventory && abs((float)$line['unit_price'] - $purchaseCost) > 0.0001;
                            $qtyDisplay = rtrim(rtrim(number_format((float)$line['quantity'], 2, '.', ''), '0'), '.');
                        ?>
                        <tr class="estimate-line-row" data-inventory-id="<?= (int)($line['inventory_item_id'] ?? 0) ?>"
                            data-available="<?= $available !== null ? $available : '' ?>"
                            data-source-rate="<?= e(number_format($purchaseCost, 2, '.', '')) ?>">
                            <td class="col-item">
                                <input type="hidden" name="line_inventory_id[]" value="<?= e($line['inventory_item_id']) ?>">
                                <input type="hidden" name="line_type[]" value="<?= e($line['line_type']) ?>">
                                <input type="hidden" name="line_cost[]" value="<?= e(number_format($purchaseCost, 2, '.', '')) ?>">
                                <input type="hidden" name="line_source_type[]" value="<?= e((string)($line['source_type'] ?? '')) ?>">
                                <input type="hidden" name="line_source_id[]" value="<?= e((string)($line['source_id'] ?? '')) ?>">
                                <input type="text" name="line_label[]" value="<?= e($line['label']) ?>" class="cell-input line-label" title="<?= $isInventory ? 'From inventory' : 'Custom / labor' ?>">
                            </td>
                            <td class="col-qty">
                                <div class="estimate-qty-stepper">
                                    <button type="button" class="qty-step qty-minus" onclick="changeEstimateQty(this,-1)" aria-label="Reduce quantity">−</button>
                                    <input type="number" name="line_qty[]" value="<?= e((string)$line['quantity']) ?>"
                                        min="1" step="<?= $isInventory ? '1' : '0.5' ?>"
                                        <?= $available !== null ? 'max="' . (int)$available . '"' : '' ?>
                                        class="cell-input cell-money line-qty" oninput="updateEstimateTotal()">
                                    <button type="button" class="qty-step qty-plus" onclick="changeEstimateQty(this,1)" aria-label="Increase quantity">+</button>
                                </div>
                            </td>
                            <td class="col-avail">
                                <?php if ($available !== null): ?>
                                    <div class="estimate-usage" title="Using <?= e($qtyDisplay) ?> of <?= $available ?> in stock">
                                        <span class="usage-count"><strong><?= e($qtyDisplay) ?></strong>/<?= $available ?></span>
                                        <span class="usage-track"><span></span></span>
                                    </div>
                                <?php else: ?>
                                    <span class="cell-readonly muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-money">
                                <span class="cell-readonly cell-money line-cost-display"><?= e(number_format($purchaseCost, 2, '.', '')) ?></span>
                            </td>
                            <td class="col-money">
                                <div class="estimate-rate-cell">
                                    <input type="number" step="0.01" min="0" name="line_price[]"
                                        value="<?= e(number_format((float)$line['unit_price'], 2, '.', '')) ?>"
                                        class="cell-input cell-money line-price" oninput="updateEstimateTotal()">
                                    <?php if ($isInventory): ?>
                                        <button type="button" class="rate-reset <?= $isOverride ? 'is-visible' : '' ?>"
                                            onclick="resetEstimateRate(this)" title="Reset to purchase cost">↺</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="col-money">
                                <span class="cell-readonly cell-money line-amount"><?= e(number_format((float)$line['quantity'] * (float)$line['unit_price'], 2, '.', '')) ?></span>
                            </td>
                            <td class="col-actions">
                                <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove();updateEstimateTotal()" title="Remove">×</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (empty($lines)): ?>
                    <p class="estimate-empty-hint" id="estimate-empty-hint">Add stock from the left, or + Custom / + Labor.</p>
                <?php endif; ?>
            </section>

            <aside class="card estimate-summary-panel">
                <div class="estimate-panel-head">
                    <h3>Summary</h3>
                </div>
                <div class="estimate-summary-rows">
                    <div class="estimate-summary-row">
                        <span>Subtotal</span>
                        <strong id="est-subtotal"><?= formatMoney($totals['subtotal']) ?></strong>
                    </div>
                    <div class="form-group estimate-summary-field">
                        <label>Discount</label>
                        <div class="flex">
                            <select name="discount_type" id="discount_type" onchange="updateEstimateTotal()">
                                <option value="percent" <?= $d['discount_type']==='percent'?'selected':'' ?>>%</option>
                                <option value="flat" <?= $d['discount_type']==='flat'?'selected':'' ?>>Flat</option>
                            </select>
                            <input type="number" step="0.01" name="discount_value" id="discount_value" value="<?= e((string)$d['discount_value']) ?>" oninput="updateEstimateTotal()">
                        </div>
                    </div>
                    <div class="estimate-summary-row">
                        <span>Discount</span>
                        <strong id="est-discount"><?= formatMoney($totals['discount_amount']) ?></strong>
                    </div>
                    <div class="form-group estimate-summary-field">
                        <label>Profit amount</label>
                        <input type="number" min="0" step="0.01" name="profit_amount" id="profit_amount"
                            value="<?= e(number_format((float)($d['profit_amount'] ?? 0), 2, '.', '')) ?>"
                            oninput="updateEstimateTotal()" placeholder="0.00">
                    </div>
                    <div class="estimate-summary-row">
                        <span>Profit added</span>
                        <strong id="est-profit-added"><?= formatMoney($totals['profit_amount']) ?></strong>
                    </div>
                    <div class="form-group estimate-summary-field">
                        <label>Tax %</label>
                        <input type="number" step="0.01" name="tax_percent" id="tax_percent" value="<?= e((string)$d['tax_percent']) ?>" oninput="updateEstimateTotal()">
                    </div>
                    <div class="estimate-summary-row">
                        <span>Tax</span>
                        <strong id="est-tax"><?= formatMoney($totals['tax_amount']) ?></strong>
                    </div>
                    <div class="estimate-summary-row is-total">
                        <span>Total</span>
                        <strong id="est-total"><?= formatMoney($totals['total']) ?></strong>
                    </div>
                    <div class="estimate-summary-row is-muted">
                        <span>Estimated margin</span>
                        <strong id="est-profit"><?= formatMoney($totals['profit']) ?></strong>
                    </div>
                </div>
                <div class="form-group estimate-notes">
                    <label>Notes</label>
                    <textarea name="notes" rows="3"><?= e($d['notes']) ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary estimate-save-btn">Save estimate</button>
                <?php if ($id): ?>
                    <a href="contract-create.php?estimate_id=<?= $id ?>" class="btn btn-secondary estimate-save-btn">Create contract</a>
                <?php endif; ?>
            </aside>
        </div>
    </form>
</div>

<script>
function filterEstimateEvents() {
    var customer = document.querySelector('select[name="customer_id"]');
    var eventSel = document.getElementById('estimate_event_id');
    if (!customer || !eventSel) return;
    var cid = customer.value;
    Array.prototype.forEach.call(eventSel.options, function (opt) {
        if (!opt.value) { opt.hidden = false; return; }
        var match = !cid || opt.getAttribute('data-customer') === cid;
        opt.hidden = !match;
        if (!match && opt.selected) eventSel.value = '';
    });
}
document.addEventListener('DOMContentLoaded', function () {
    var customer = document.querySelector('select[name="customer_id"]');
    if (customer) customer.addEventListener('change', filterEstimateEvents);
    filterEstimateEvents();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
