<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        execute('INSERT INTO inventory_categories (name, description) VALUES (?,?)',
            [trim($_POST['name']), $_POST['description'] ?? null]);
        flash('success', 'Category added.');
    }
    if ($action === 'update' && !empty($_POST['id'])) {
        execute('UPDATE inventory_categories SET name=?, description=? WHERE id=?',
            [trim($_POST['name']), $_POST['description'] ?? null, $_POST['id']]);
        flash('success', 'Category updated.');
    }
    if ($action === 'delete' && !empty($_POST['id'])) {
        $count = queryOne('SELECT COUNT(*) as c FROM inventory_items WHERE category_id=? AND deleted_at IS NULL', [$_POST['id']]);
        if (($count['c'] ?? 0) > 0) {
            flash('error', 'Cannot delete — category has inventory items. Reassign items first.');
        } else {
            execute('DELETE FROM inventory_categories WHERE id=?', [$_POST['id']]);
            flash('success', 'Category deleted.');
        }
    }
    redirect('categories.php');
}

$currentPage = 'categories';
$pageTitle = 'Categories';
require_once __DIR__ . '/includes/header.php';

$categories = query('SELECT c.*, (SELECT COUNT(*) FROM inventory_items i WHERE i.category_id=c.id AND i.deleted_at IS NULL) as item_count FROM inventory_categories c ORDER BY c.name');
$editId = (int)($_GET['edit'] ?? 0);
$editCat = $editId ? queryOne('SELECT * FROM inventory_categories WHERE id=?', [$editId]) : null;
?>

<div class="page-header">
    <div>
        <h1>Inventory Categories</h1>
        <p class="subtitle">Fully customizable — add, edit, or remove categories anytime</p>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <h3><?= $editCat ? 'Edit Category' : 'Add Category' ?></h3>
        <form method="post">
            <input type="hidden" name="action" value="<?= $editCat ? 'update' : 'add' ?>">
            <?php if ($editCat): ?><input type="hidden" name="id" value="<?= $editCat['id'] ?>"><?php endif; ?>
            <div class="form-group">
                <label>Category Name *</label>
                <input name="name" value="<?= e($editCat['name'] ?? '') ?>" placeholder="e.g. Decor, Lighting, Furniture" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" placeholder="Brief description of this category"><?= e($editCat['description'] ?? '') ?></textarea>
            </div>
            <div class="flex">
                <button type="submit" class="btn btn-primary"><?= $editCat ? 'Update' : 'Add Category' ?></button>
                <?php if ($editCat): ?><a href="categories.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>All Categories (<?= count($categories) ?>)</h3>
        <?php if (empty($categories)): ?>
            <div class="empty-state">
                <div class="icon">🏷️</div>
                <h3>No categories yet</h3>
                <p>Create your first category to organize inventory.</p>
            </div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <tr><th>Name</th><th>Items</th><th>Actions</th></tr>
                <?php foreach ($categories as $cat): ?>
                <tr>
                    <td>
                        <strong><?= e($cat['name']) ?></strong>
                        <?php if ($cat['description']): ?><br><span class="text-muted"><?= e($cat['description']) ?></span><?php endif; ?>
                    </td>
                    <td><?= (int)$cat['item_count'] ?></td>
                    <td><?= actionButtons('categories.php?edit=' . $cat['id'], 'delete', $cat['id']) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
