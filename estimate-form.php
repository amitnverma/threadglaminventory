<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

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
            'source_type' => $source['source_type'] ?? null,
            'source_id' => $source['source_id'] ?? null,
        ];
    }
    $opts = ['tax_percent' => $_POST['tax_percent'], 'discount_type' => $_POST['discount_type'], 'discount_value' => $_POST['discount_value']];
    $totals = calculateEstimateTotals($lineData, $opts);

    if ($id) {
        execute('UPDATE estimates SET customer_id=?,event_id=?,title=?,status=?,subtotal=?,tax_percent=?,tax_amount=?,discount_type=?,discount_value=?,discount_amount=?,total=?,notes=?,updated_at=NOW() WHERE id=?',
            [$customerId, $eventId, $_POST['title'], $_POST['status'], $totals['subtotal'], $_POST['tax_percent'], $totals['tax_amount'], $_POST['discount_type'], $_POST['discount_value'], $totals['discount_amount'], $totals['total'], $_POST['notes'] ?? null, $id]);
        execute('DELETE FROM estimate_line_items WHERE estimate_id=?', [$id]);
        $estId = $id;
    } else {
        execute('INSERT INTO estimates (customer_id,event_id,title,status,subtotal,tax_percent,tax_amount,discount_type,discount_value,discount_amount,total,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$customerId, $eventId, $_POST['title'], $_POST['status'] ?? 'draft', $totals['subtotal'], $_POST['tax_percent'], $totals['tax_amount'], $_POST['discount_type'], $_POST['discount_value'], $totals['discount_amount'], $totals['total'], $_POST['notes'] ?? null]);
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
$d = $estimate ?: ['customer_id'=>$_GET['customer_id']??'','event_id'=>$_GET['event_id']??'','title'=>'New Estimate','status'=>'draft','tax_percent'=>$settings['default_tax_percent']??8.875,'discount_type'=>'percent','discount_value'=>0,'notes'=>''];
$totals = calculateEstimateTotals($lines, $d);
?>

<div class="page-header"><h1><?= $id ? 'Edit' : 'New' ?> Estimate</h1>
<?php if ($id): ?>
<div class="flex">
    <a href="contract-create.php?estimate_id=<?= $id ?>" class="btn btn-secondary">→ Create Contract</a>
    <form method="post" action="estimates.php" onsubmit="return confirm('Delete this estimate?')">
        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn btn-danger">Delete</button>
    </form>
</div>
<?php endif; ?>
</div>

<form method="post">
    <div class="form-row mb-1">
        <div class="form-group"><label>Title</label><input name="title" value="<?= e($d['title']) ?>" required></div>
        <div class="form-group"><label>Customer *</label>
            <select name="customer_id" required><option value="">—</option>
            <?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>" <?= $d['customer_id']==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>Event</label>
            <select name="event_id" id="estimate_event_id"><option value="">—</option>
            <?php foreach ($events as $ev): ?>
                <option value="<?= (int)$ev['id'] ?>"
                    data-customer="<?= (int)$ev['customer_id'] ?>"
                    <?= (int)$d['event_id'] === (int)$ev['id'] ? 'selected' : '' ?>>
                    <?= e($ev['title']) ?>
                </option>
            <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>Status</label>
            <select name="status"><?php foreach (['draft','sent','approved','rejected'] as $s): ?><option value="<?= $s ?>" <?= ($d['status']??'draft')===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select>
        </div>
    </div>

    <div class="estimate-layout">
        <div class="card">
            <h3 class="mb-1">Inventory</h3>
            <input type="text" id="catalog-search" placeholder="Search..." oninput="filterCatalog(this.value)" class="mb-1">
            <div id="catalog-list" style="max-height:400px;overflow-y:auto">
            <?php foreach ($catalog as $item):
                $thumb = getPrimaryImage('inventory', $item['id']);
            ?>
            <div class="catalog-item" data-name="<?= e(strtolower($item['name'])) ?>">
                <img src="<?= e(imgUrl($thumb)) ?>" alt="">
                <div class="catalog-item-info">
                    <strong><?= e($item['name']) ?></strong>
                    <div class="catalog-item-meta">
                        <span class="is-cost">Cost <?= formatMoney($item['unit_cost']) ?></span>
                        <span class="<?= (int)$item['quantity_on_hand'] > 0 ? 'is-available' : 'is-empty' ?>">
                            <?= (int)$item['quantity_on_hand'] ?> available
                        </span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-primary" aria-label="Add <?= e($item['name']) ?>"
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
            </div>
            <div class="mt-1">
                <button type="button" class="btn btn-sm btn-secondary" onclick="addCustomLine('custom')">+ Custom</button>
                <button type="button" class="btn btn-sm btn-secondary" onclick="addCustomLine('labor')">+ Labor</button>
            </div>
        </div>

        <div class="card">
            <h3 class="mb-1">Line Items</h3>
            <div class="table-wrap">
            <table class="data-table estimate-lines-table">
                <thead><tr><th>Item</th><th>Use / Available</th><th>Contract Rate</th><th>Amount</th><th></th></tr></thead>
                <tbody id="estimate-lines">
                <?php foreach ($lines as $line): ?>
                <?php
                    $isInventory = !empty($line['inventory_item_id']);
                    $available = $isInventory && $line['inventory_available'] !== null
                        ? (int)$line['inventory_available']
                        : null;
                    $purchaseCost = $isInventory && $line['inventory_purchase_cost'] !== null
                        ? (float)$line['inventory_purchase_cost']
                        : (float)$line['unit_cost'];
                    $isOverride = $isInventory && abs((float)$line['unit_price'] - $purchaseCost) > 0.0001;
                ?>
                <tr class="estimate-line-row" data-inventory-id="<?= (int)($line['inventory_item_id'] ?? 0) ?>"
                    data-available="<?= $available !== null ? $available : '' ?>"
                    data-source-rate="<?= e(number_format($purchaseCost, 2, '.', '')) ?>">
                    <td><input type="hidden" name="line_inventory_id[]" value="<?= e($line['inventory_item_id']) ?>">
                        <input type="hidden" name="line_type[]" value="<?= e($line['line_type']) ?>">
                        <input type="hidden" name="line_cost[]" value="<?= e(number_format($purchaseCost, 2, '.', '')) ?>">
                        <input type="text" name="line_label[]" value="<?= e($line['label']) ?>" class="line-label">
                        <?php if ($isInventory): ?>
                            <small class="estimate-source-note">From inventory · purchase cost <?= formatMoney($purchaseCost) ?></small>
                        <?php else: ?>
                            <small class="estimate-source-note">Custom contract line</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="estimate-usage">
                            <input type="number" name="line_qty[]" value="<?= e((string)$line['quantity']) ?>"
                                min="0" step="0.5" class="line-qty" oninput="updateEstimateTotal()">
                            <?php if ($available !== null): ?>
                                <span class="usage-count"><strong><?= e((string)$line['quantity']) ?></strong> of <?= $available ?></span>
                                <span class="usage-track"><span></span></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div class="estimate-rate-field">
                            <input type="number" step="0.01" min="0" name="line_price[]"
                                value="<?= e(number_format((float)$line['unit_price'], 2, '.', '')) ?>"
                                class="line-price" oninput="updateEstimateTotal()">
                            <?php if ($isInventory): ?>
                                <div class="rate-source">
                                    <span>Purchase <?= formatMoney($purchaseCost) ?></span>
                                    <button type="button" class="rate-reset" onclick="resetEstimateRate(this)">Reset</button>
                                </div>
                                <span class="rate-status <?= $isOverride ? 'is-overridden' : '' ?>">
                                    <?= $isOverride ? 'Overridden for contract' : 'Using purchase cost' ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="line-amount text-right"><?= formatMoney($line['quantity'] * $line['unit_price']) ?></td>
                    <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove();updateEstimateTotal()">×</button></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>

        <div class="card">
            <h3 class="mb-1">Summary</h3>
            <p>Subtotal: <strong id="est-subtotal"><?= formatMoney($totals['subtotal']) ?></strong></p>
            <div class="form-group"><label>Discount</label>
                <div class="flex">
                    <select name="discount_type" id="discount_type" onchange="updateEstimateTotal()"><option value="percent" <?= $d['discount_type']==='percent'?'selected':'' ?>>%</option><option value="flat" <?= $d['discount_type']==='flat'?'selected':'' ?>>Flat</option></select>
                    <input type="number" step="0.01" name="discount_value" id="discount_value" value="<?= $d['discount_value'] ?>" onchange="updateEstimateTotal()">
                </div>
            </div>
            <p>Discount: <strong id="est-discount"><?= formatMoney($totals['discount_amount']) ?></strong></p>
            <div class="form-group"><label>Tax %</label><input type="number" step="0.01" name="tax_percent" id="tax_percent" value="<?= $d['tax_percent'] ?>" onchange="updateEstimateTotal()"></div>
            <p>Tax: <strong id="est-tax"><?= formatMoney($totals['tax_amount']) ?></strong></p>
            <p style="font-size:1.2rem">Total: <strong id="est-total"><?= formatMoney($totals['total']) ?></strong></p>
            <p class="text-muted">Profit est: <?= formatMoney($totals['profit']) ?></p>
            <div class="form-group mt-1"><label>Notes</label><textarea name="notes"><?= e($d['notes']) ?></textarea></div>
            <button type="submit" class="btn btn-primary" style="width:100%">Save Estimate</button>
            <?php if ($id): ?><a href="contract-create.php?estimate_id=<?= $id ?>" class="btn btn-secondary mt-1" style="width:100%;text-align:center;display:block">Create Contract</a><?php endif; ?>
        </div>
    </div>
</form>

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
