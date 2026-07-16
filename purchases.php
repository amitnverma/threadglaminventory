<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete' && !empty($_POST['id'])) {
        reversePurchaseInventory((int)$_POST['id']);
        execute('DELETE FROM purchase_line_items WHERE purchase_id=?', [$_POST['id']]);
        execute('DELETE FROM purchases WHERE id=?', [$_POST['id']]);
        flash('success', 'Purchase deleted and inventory stock reversed.');
        redirect('purchases.php');
    }

    redirect('purchases.php');
}

// Legacy bookmark: old "new purchase" entry pointed here with ?item=
if (isset($_GET['item']) || isset($_GET['new'])) {
    $qs = !empty($_GET['item']) ? ('?item=' . (int)$_GET['item']) : '';
    redirect('inventory-buy.php' . $qs);
}

$currentPage = 'purchases';
$pageTitle = 'Purchase History';
require_once __DIR__ . '/includes/header.php';

$purchases = query(
    'SELECT p.*, COUNT(pli.id) as line_count,
     GROUP_CONCAT(CONCAT(pli.label, " (+", pli.quantity, ")") ORDER BY pli.id SEPARATOR ", ") as items_summary
     FROM purchases p
     LEFT JOIN purchase_line_items pli ON pli.purchase_id = p.id
     GROUP BY p.id ORDER BY p.purchase_date DESC'
);
?>

<div class="page-header">
    <div>
        <h1>Purchase History</h1>
        <p class="subtitle">Past vendor receipts — new buys go through Buy / Restock</p>
    </div>
    <div class="flex">
        <a href="inventory.php" class="btn btn-secondary">Inventory</a>
        <a href="inventory-buy.php" class="btn btn-primary">Buy / Restock</a>
    </div>
</div>

<div class="card">
    <?php if (empty($purchases)): ?>
        <div class="empty-state">
            <div class="icon">🛒</div>
            <h3>No purchases yet</h3>
            <p>Record a vendor receipt to add stock in one step.</p>
            <a href="inventory-buy.php" class="btn btn-primary">Buy / Restock</a>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <tr><th>Date</th><th>Supplier</th><th>Items</th><th>Total</th><th></th></tr>
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
                            <?= csrfField() ?>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
