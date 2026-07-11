<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    if ($action === 'delete' && !empty($_POST['id'])) {
        execute('DELETE FROM sale_line_items WHERE sale_id=?', [$_POST['id']]);
        execute('DELETE FROM sales WHERE id=?', [$_POST['id']]);
        flash('success', 'Sale deleted.');
        redirect('sales.php');
    }
    $labels = $_POST['line_label'] ?? [];
    $total = 0;
    execute('INSERT INTO sales (customer_id, event_id, sale_date, total, notes) VALUES (?,?,?,?,?)',
        [$_POST['customer_id'] ?: null, $_POST['event_id'] ?: null, $_POST['sale_date'], 0, $_POST['notes'] ?? null]);
    $saleId = lastId();
    for ($i = 0; $i < count($labels); $i++) {
        if (!trim($labels[$i])) continue;
        $qty = (int)($_POST['line_qty'][$i] ?? 1);
        $price = (float)($_POST['line_price'][$i] ?? 0);
        $lineTotal = $qty * $price;
        $total += $lineTotal;
        execute('INSERT INTO sale_line_items (sale_id,label,quantity,unit_price,line_total) VALUES (?,?,?,?,?)',
            [$saleId, $labels[$i], $qty, $price, $lineTotal]);
    }
    execute('UPDATE sales SET total=? WHERE id=?', [$total, $saleId]);
    flash('success', 'Sale recorded.');
    redirect('sales.php');
}

$currentPage = 'sales';
$pageTitle = 'Sales';
require_once __DIR__ . '/includes/header.php';

$sales = query('SELECT s.*, c.name as customer_name, e.title as event_title FROM sales s LEFT JOIN customers c ON c.id=s.customer_id LEFT JOIN events e ON e.id=s.event_id ORDER BY s.sale_date DESC');
$customers = query('SELECT id, name FROM customers WHERE deleted_at IS NULL ORDER BY name');
$events = query('SELECT id, title FROM events WHERE deleted_at IS NULL ORDER BY title');
?>

<div class="page-header">
    <div>
        <h1>Sales</h1>
        <p class="subtitle">Record revenue from events and rentals</p>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Record Sale</h3>
        <form method="post">
            <div class="form-row">
                <div class="form-group"><label>Customer</label>
                    <select name="customer_id"><option value="">—</option><?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select>
                </div>
                <div class="form-group"><label>Event</label>
                    <select name="event_id"><option value="">—</option><?php foreach ($events as $ev): ?><option value="<?= $ev['id'] ?>"><?= e($ev['title']) ?></option><?php endforeach; ?></select>
                </div>
            </div>
            <div class="form-group"><label>Date</label><input type="date" name="sale_date" value="<?= date('Y-m-d') ?>" required></div>
            <div class="table-wrap">
                <table class="data-table">
                    <tr><th>Description</th><th>Qty</th><th>Price</th></tr>
                    <tr><td><input name="line_label[]" placeholder="Service or item description"></td><td><input type="number" name="line_qty[]" value="1" min="1"></td><td><input type="number" step="0.01" name="line_price[]" value="0"></td></tr>
                </table>
            </div>
            <div class="form-group mt-1"><label>Notes</label><textarea name="notes" placeholder="Payment method, reference"></textarea></div>
            <button class="btn btn-primary">Save Sale</button>
        </form>
    </div>
    <div class="card">
        <h3>Sales History</h3>
        <?php if (empty($sales)): ?>
            <p class="text-muted">No sales recorded yet.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <tr><th>Date</th><th>Customer</th><th>Event</th><th>Total</th><th></th></tr>
                <?php foreach ($sales as $s): ?>
                <tr>
                    <td><?= formatDate($s['sale_date']) ?></td>
                    <td><?= e($s['customer_name'] ?: '—') ?></td>
                    <td><?= e($s['event_title'] ?: '—') ?></td>
                    <td><strong><?= formatMoney($s['total']) ?></strong></td>
                    <td>
                        <form method="post" onsubmit="return confirm('Delete this sale?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
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
