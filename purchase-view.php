<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
$purchase = queryOne('SELECT * FROM purchases WHERE id=?', [$id]);
if (!$purchase) { flash('error', 'Purchase not found.'); redirect('purchases.php'); }

$lines = query(
    'SELECT pli.*, i.name as item_name, i.sku, i.quantity_on_hand
     FROM purchase_line_items pli
     LEFT JOIN inventory_items i ON i.id = pli.inventory_item_id
     WHERE pli.purchase_id=? ORDER BY pli.id',
    [$id]
);

$currentPage = 'purchases';
$pageTitle = 'Purchase Details';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Purchase #<?= $id ?></h1>
        <p class="subtitle"><?= e($purchase['supplier'] ?: 'Unknown supplier') ?> · <?= formatDate($purchase['purchase_date']) ?></p>
    </div>
    <div class="flex">
        <a href="purchases.php" class="btn btn-secondary">← All Purchases</a>
        <form method="post" action="purchases.php" onsubmit="return confirm('Delete and reverse inventory stock?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $id ?>">
            <button class="btn btn-danger">Delete</button>
        </form>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Purchase Summary</h3>
        <p><strong>Supplier:</strong> <?= e($purchase['supplier'] ?: '—') ?></p>
        <p><strong>Date:</strong> <?= formatDate($purchase['purchase_date']) ?></p>
        <p><strong>Total:</strong> <span style="font-size:1.25rem;font-weight:700;color:var(--primary)"><?= formatMoney($purchase['total']) ?></span></p>
        <?php if ($purchase['notes']): ?><p><strong>Notes:</strong> <?= e($purchase['notes']) ?></p><?php endif; ?>
    </div>
    <div class="card">
        <h3>Inventory Impact</h3>
        <p class="text-muted">These items were added to inventory when this purchase was saved.</p>
        <div class="stat success" style="margin:0"><div class="label">Items Updated</div><div class="value"><?= count($lines) ?></div></div>
    </div>
</div>

<div class="card">
    <h3>Line Items</h3>
    <div class="table-wrap">
        <table class="data-table">
            <tr><th>Inventory Item</th><th>SKU</th><th>Qty Added</th><th>Unit Cost</th><th>Line Total</th><th>Current Stock</th></tr>
            <?php foreach ($lines as $line): ?>
            <tr>
                <td>
                    <?php if ($line['inventory_item_id']): ?>
                        <a href="inventory-view.php?id=<?= $line['inventory_item_id'] ?>"><strong><?= e($line['item_name'] ?: $line['label']) ?></strong></a>
                    <?php else: ?>
                        <?= e($line['label']) ?>
                    <?php endif; ?>
                </td>
                <td><code><?= e($line['sku'] ?: '—') ?></code></td>
                <td><span class="badge badge-approved">+<?= (int)$line['quantity'] ?></span></td>
                <td><?= formatMoney($line['unit_cost']) ?></td>
                <td><?= formatMoney($line['line_total']) ?></td>
                <td><?= $line['quantity_on_hand'] !== null ? (int)$line['quantity_on_hand'] . ' units' : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
