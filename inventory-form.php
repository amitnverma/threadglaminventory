<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

$id = $_GET['id'] ?? null;
$categories = query('SELECT * FROM inventory_categories ORDER BY name');
$item = $id ? queryOne('SELECT * FROM inventory_items WHERE id=? AND deleted_at IS NULL', [$id]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryId = $_POST['category_id'] ?: null;
    $qty = (int)($_POST['quantity_on_hand'] ?? 0);
    $sku = $id ? ($item['sku'] ?: generateSku($categoryId)) : generateSku($categoryId);
    $reorder = generateReorderLevel($qty);

    if ($id) {
        execute('UPDATE inventory_items SET category_id=?,name=?,description=?,unit_cost=?,rental_price=?,sale_price=?,condition_status=?,reorder_level=?,updated_at=NOW() WHERE id=?',
            [$categoryId, trim($_POST['name']), $_POST['description'] ?: null, (float)$_POST['unit_cost'], (float)$_POST['rental_price'],
             (float)$_POST['sale_price'], $_POST['condition_status'], $reorder, $id]);
        if (!empty($_FILES['image']['name'])) uploadImage('inventory', $id, $_FILES['image']);
        flash('success', 'Item updated.');
        redirect('inventory-view.php?id=' . $id);
    } else {
        execute('INSERT INTO inventory_items (category_id,name,sku,description,quantity_on_hand,unit_cost,rental_price,sale_price,condition_status,reorder_level) VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$categoryId, trim($_POST['name']), $sku, $_POST['description'] ?: null, $qty, (float)$_POST['unit_cost'],
             (float)$_POST['rental_price'], (float)$_POST['sale_price'], $_POST['condition_status'], $reorder]);
        $newId = lastId();
        if (!empty($_FILES['image']['name'])) uploadImage('inventory', $newId, $_FILES['image']);
        flash('success', 'Item created. SKU: ' . $sku);
        redirect('inventory-view.php?id=' . $newId);
    }
}

$currentPage = 'inventory';
$pageTitle = $id ? 'Edit Item' : 'New Item';
require_once __DIR__ . '/includes/header.php';
$d = $item ?: ['name'=>'','sku'=>'','category_id'=>'','description'=>'','quantity_on_hand'=>1,'unit_cost'=>0,'rental_price'=>0,'sale_price'=>0,'condition_status'=>'good','reorder_level'=>5];
$previewSku = $id ? ($d['sku'] ?: '—') : generateSku($d['category_id'] ?: null);
$previewReorder = generateReorderLevel((int)$d['quantity_on_hand']);
?>

<div class="page-header">
    <h1><?= $id ? 'Edit' : 'New' ?> Inventory Item</h1>
    <a href="inventory.php" class="btn btn-secondary">← Back</a>
</div>

<div class="grid-2">
    <div class="card">
        <form method="post" enctype="multipart/form-data">
            <div class="form-group"><label>Item Name *</label><input name="name" value="<?= e($d['name']) ?>" placeholder="e.g. Gold Chiavari Chair" required></div>
            <div class="form-row">
                <div class="form-group">
                    <label>SKU</label>
                    <input value="<?= e($previewSku) ?>" class="readonly-field" readonly>
                    <p class="hint">Auto-generated from category</p>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id">
                        <option value="">— Select —</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $d['category_id']==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="hint"><a href="categories.php">+ Add new category</a></p>
                </div>
            </div>
            <div class="form-group"><label>Description</label><textarea name="description" placeholder="Size, color, material, usage notes"><?= e($d['description']) ?></textarea></div>
            <?php if (!$id): ?>
            <div class="form-group"><label>Initial Quantity</label><input type="number" name="quantity_on_hand" value="<?= (int)$d['quantity_on_hand'] ?>" min="0"></div>
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group"><label>Unit Cost (₹)</label><input type="number" step="0.01" name="unit_cost" value="<?= $d['unit_cost'] ?>" placeholder="0.00"></div>
                <div class="form-group"><label>Rental Price (₹)</label><input type="number" step="0.01" name="rental_price" value="<?= $d['rental_price'] ?>" placeholder="0.00"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Sale Price (₹)</label><input type="number" step="0.01" name="sale_price" value="<?= $d['sale_price'] ?>" placeholder="0.00"></div>
                <div class="form-group">
                    <label>Condition</label>
                    <select name="condition_status">
                        <?php foreach (['excellent','good','fair','poor'] as $cond): ?>
                        <option value="<?= $cond ?>" <?= $d['condition_status']===$cond?'selected':'' ?>><?= ucfirst($cond) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Reorder Level</label>
                <input value="<?= $previewReorder ?>" class="readonly-field" readonly>
                <p class="hint">Auto-calculated at 20% of quantity (min 3)</p>
            </div>
            <div class="form-group">
                <label>Photo</label>
                <input type="file" name="image" accept="image/*">
                <p class="hint">Upload a clear product photo for catalog display</p>
            </div>
            <div class="flex">
                <button type="submit" class="btn btn-primary">Save Item</button>
                <a href="inventory.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <?php if ($id):
        $img = getPrimaryImage('inventory', $id);
    ?>
    <div class="card">
        <h3>Current Photo</h3>
        <img src="<?= e(imgUrl($img)) ?>" alt="" style="width:100%;max-height:280px;object-fit:cover;border-radius:10px">
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
