<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

$id = $_GET['id'] ?? null;
$customers = query('SELECT * FROM customers WHERE deleted_at IS NULL ORDER BY name');
$events = query('SELECT id, title FROM events WHERE deleted_at IS NULL ORDER BY title');
$catalog = query('SELECT i.*, c.name as category_name FROM inventory_items i LEFT JOIN inventory_categories c ON c.id=i.category_id WHERE i.deleted_at IS NULL ORDER BY i.name');
$settings = getSettings();

$estimate = $id ? queryOne('SELECT * FROM estimates WHERE id=?', [$id]) : null;
$lines = $id ? query('SELECT * FROM estimate_line_items WHERE estimate_id=? ORDER BY sort_order', [$id]) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $labels = $_POST['line_label'] ?? [];
    $lineData = [];
    for ($i = 0; $i < count($labels); $i++) {
        if (trim($labels[$i]) === '') continue;
        $lineData[] = [
            'line_type' => $_POST['line_type'][$i] ?? 'custom',
            'inventory_item_id' => $_POST['line_inventory_id'][$i] ?: null,
            'label' => $labels[$i],
            'quantity' => (float)($_POST['line_qty'][$i] ?? 1),
            'unit_price' => (float)($_POST['line_price'][$i] ?? 0),
            'unit_cost' => (float)($_POST['line_cost'][$i] ?? 0),
        ];
    }
    $opts = ['tax_percent' => $_POST['tax_percent'], 'discount_type' => $_POST['discount_type'], 'discount_value' => $_POST['discount_value']];
    $totals = calculateEstimateTotals($lineData, $opts);

    if ($id) {
        execute('UPDATE estimates SET customer_id=?,event_id=?,title=?,status=?,subtotal=?,tax_percent=?,tax_amount=?,discount_type=?,discount_value=?,discount_amount=?,total=?,notes=?,updated_at=NOW() WHERE id=?',
            [$_POST['customer_id'], $_POST['event_id'] ?: null, $_POST['title'], $_POST['status'], $totals['subtotal'], $_POST['tax_percent'], $totals['tax_amount'], $_POST['discount_type'], $_POST['discount_value'], $totals['discount_amount'], $totals['total'], $_POST['notes'] ?? null, $id]);
        execute('DELETE FROM estimate_line_items WHERE estimate_id=?', [$id]);
        $estId = $id;
    } else {
        execute('INSERT INTO estimates (customer_id,event_id,title,status,subtotal,tax_percent,tax_amount,discount_type,discount_value,discount_amount,total,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$_POST['customer_id'], $_POST['event_id'] ?: null, $_POST['title'], $_POST['status'] ?? 'draft', $totals['subtotal'], $_POST['tax_percent'], $totals['tax_amount'], $_POST['discount_type'], $_POST['discount_value'], $totals['discount_amount'], $totals['total'], $_POST['notes'] ?? null]);
        $estId = lastId();
    }
    foreach ($lineData as $i => $line) {
        execute('INSERT INTO estimate_line_items (estimate_id,line_type,inventory_item_id,label,quantity,unit_price,unit_cost,sort_order) VALUES (?,?,?,?,?,?,?,?)',
            [$estId, $line['line_type'], $line['inventory_item_id'], $line['label'], $line['quantity'], $line['unit_price'], $line['unit_cost'], $i]);
    }
    flash('success', 'Estimate saved.');
    redirect('estimate-form.php?id=' . $estId);
}

$currentPage = 'estimates';
$pageTitle = $id ? 'Edit Estimate' : 'New Estimate';
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
            <select name="event_id"><option value="">—</option>
            <?php foreach ($events as $ev): ?><option value="<?= $ev['id'] ?>" <?= $d['event_id']==$ev['id']?'selected':'' ?>><?= e($ev['title']) ?></option><?php endforeach; ?>
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
                    <small class="text-muted"><?= formatMoney($item['rental_price']) ?></small>
                </div>
                <button type="button" class="btn btn-sm btn-primary" onclick='addEstimateLine(<?= json_encode(["id"=>$item["id"],"label"=>$item["name"],"price"=>$item["rental_price"],"cost"=>$item["unit_cost"],"type"=>"inventory"]) ?>)'>+</button>
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
            <table class="data-table">
                <tr><th>Item</th><th>Qty</th><th>Rate</th><th>Amount</th><th></th></tr>
                <tbody id="estimate-lines">
                <?php foreach ($lines as $line): ?>
                <tr>
                    <td><input type="hidden" name="line_inventory_id[]" value="<?= e($line['inventory_item_id']) ?>">
                        <input type="hidden" name="line_type[]" value="<?= e($line['line_type']) ?>">
                        <input type="hidden" name="line_cost[]" value="<?= $line['unit_cost'] ?>">
                        <input type="text" name="line_label[]" value="<?= e($line['label']) ?>" class="line-label"></td>
                    <td><input type="number" name="line_qty[]" value="<?= $line['quantity'] ?>" class="line-qty" onchange="updateEstimateTotal()"></td>
                    <td><input type="number" step="0.01" name="line_price[]" value="<?= $line['unit_price'] ?>" class="line-price" onchange="updateEstimateTotal()"></td>
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
function filterCatalog(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#catalog-list .catalog-item').forEach(function(el) {
        el.style.display = el.dataset.name.includes(q) ? '' : 'none';
    });
}
document.addEventListener('DOMContentLoaded', updateEstimateTotal);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
