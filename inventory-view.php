<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
$item = queryOne('SELECT i.*, c.name as category_name FROM inventory_items i LEFT JOIN inventory_categories c ON c.id=i.category_id WHERE i.id=? AND i.deleted_at IS NULL', [$id]);
if (!$item) { flash('error', 'Item not found.'); redirect('inventory.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_image' && !empty($_POST['attachment_id'])) {
        deleteAttachment((int)$_POST['attachment_id']);
        flash('success', 'Image removed.');
        redirect('inventory-view.php?id=' . $id);
    }
    if (!empty($_FILES['image']['name'])) {
        uploadImage('inventory', $id, $_FILES['image']);
        flash('success', 'Photo uploaded.');
        redirect('inventory-view.php?id=' . $id);
    }
}

$images = getImages('inventory', $id);
$adjustments = query('SELECT * FROM inventory_adjustments WHERE inventory_item_id=? ORDER BY created_at DESC LIMIT 10', [$id]);
$primaryImg = getPrimaryImage('inventory', $id);

$currentPage = 'inventory';
$pageTitle = $item['name'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e($item['name']) ?></h1>
        <p class="subtitle"><code><?= e($item['sku']) ?></code><?php if ($item['category_name']): ?> · <?= e($item['category_name']) ?><?php endif; ?></p>
    </div>
    <div class="flex">
        <a href="inventory-form.php?id=<?= $id ?>" class="btn btn-primary">Edit</a>
        <form method="post" action="inventory.php" onsubmit="return confirm('Delete this item?')">
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $id ?>">
            <button class="btn btn-danger">Delete</button>
        </form>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <img src="<?= e(imgUrl($primaryImg)) ?>" alt="<?= e($item['name']) ?>" style="width:100%;max-height:320px;object-fit:cover;border-radius:10px;margin-bottom:1rem">
        <h3>Photo Gallery</h3>
        <?php $allowDelete = true; $uploadId = 'inv-' . $id; include __DIR__ . '/includes/photo-gallery.php'; ?>
    </div>
    <div>
        <div class="card">
            <h3>Details</h3>
            <div class="form-row" style="margin-top:.5rem">
                <div><span class="text-muted">Quantity</span><br><strong style="font-size:1.5rem"><?= (int)$item['quantity_on_hand'] ?></strong>
                <?php if ($item['quantity_on_hand'] <= $item['reorder_level']): ?><span class="badge badge-low">Low Stock</span><?php endif; ?>
                </div>
                <div><span class="text-muted">Reorder At</span><br><strong><?= (int)$item['reorder_level'] ?></strong></div>
                <div><span class="text-muted">Condition</span><br><strong><?= e(ucfirst($item['condition_status'])) ?></strong></div>
            </div>
            <hr style="margin:1rem 0;border:none;border-top:1px solid #e5e7eb">
            <p><strong>Unit Cost:</strong> <?= formatMoney($item['unit_cost']) ?></p>
            <p><strong>Rental Price:</strong> <?= formatMoney($item['rental_price']) ?></p>
            <p><strong>Sale Price:</strong> <?= formatMoney($item['sale_price']) ?></p>
            <?php if ($item['description']): ?><p class="mt-1"><?= e($item['description']) ?></p><?php endif; ?>
        </div>
        <div class="card">
            <h3>Adjust Stock</h3>
            <form method="post" action="inventory.php">
                <input type="hidden" name="action" value="adjust"><input type="hidden" name="id" value="<?= $id ?>">
                <div class="form-row">
                    <div class="form-group"><label>Type</label>
                        <select name="adjustment_type"><option value="add">Add</option><option value="remove">Remove</option><option value="set">Set to</option></select>
                    </div>
                    <div class="form-group"><label>Quantity</label><input type="number" name="quantity" value="1" min="0" required></div>
                </div>
                <div class="form-group"><label>Reason</label><input name="reason" placeholder="e.g. Returned from event, damaged, new purchase"></div>
                <button class="btn btn-primary">Apply Adjustment</button>
            </form>
        </div>
    </div>
</div>

<?php if ($adjustments): ?>
<div class="card">
    <h3>Recent Stock Changes</h3>
    <div class="table-wrap">
        <table class="data-table">
            <tr><th>Date</th><th>Type</th><th>Qty</th><th>Reason</th></tr>
            <?php foreach ($adjustments as $adj): ?>
            <tr>
                <td><?= formatDate($adj['created_at']) ?></td>
                <td><?= e(ucfirst($adj['adjustment_type'])) ?></td>
                <td><?= (int)$adj['quantity'] ?></td>
                <td><?= e($adj['reason'] ?: '—') ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
