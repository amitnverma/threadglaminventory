<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    execute('DELETE FROM estimate_line_items WHERE estimate_id=?', [$_POST['id']]);
    execute('DELETE FROM estimates WHERE id=?', [$_POST['id']]);
    flash('success', 'Estimate deleted.');
    redirect('estimates.php');
}

$currentPage = 'estimates';
$pageTitle = 'Estimates';
require_once __DIR__ . '/includes/header.php';

$estimates = query('SELECT e.*, c.name as customer_name, ev.title as event_title FROM estimates e LEFT JOIN customers c ON c.id=e.customer_id LEFT JOIN events ev ON ev.id=e.event_id WHERE e.is_template=0 ORDER BY e.updated_at DESC');
?>

<div class="page-header">
    <div>
        <h1>Estimates</h1>
        <p class="subtitle">Build quotes and convert to contracts</p>
    </div>
    <a href="estimate-form.php" class="btn btn-primary">+ New Estimate</a>
</div>

<div class="card">
    <?php if (empty($estimates)): ?>
    <div class="empty-state"><div class="icon">📋</div><h3>No estimates yet</h3><p>Create your first quote for a customer.</p>
    <a href="estimate-form.php" class="btn btn-primary">+ New Estimate</a></div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <tr><th>Title</th><th>Customer</th><th>Event</th><th>Status</th><th>Total</th><th>Actions</th></tr>
            <?php foreach ($estimates as $est): ?>
            <tr>
                <td><strong><?= e($est['title']) ?></strong></td>
                <td><a href="customer-view.php?id=<?= (int)$est['customer_id'] ?>"><?= e($est['customer_name']) ?></a></td>
                <td><?= e($est['event_title'] ?: '—') ?></td>
                <td><span class="badge badge-<?= e($est['status']) ?>"><?= e(ucfirst($est['status'])) ?></span></td>
                <td><strong><?= formatMoney($est['total']) ?></strong></td>
                <td>
                    <div class="action-btns">
                        <a href="estimate-form.php?id=<?= $est['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                        <a href="contract-create.php?estimate_id=<?= $est['id'] ?>" class="btn btn-sm btn-secondary">→ Contract</a>
                        <form method="post" onsubmit="return confirm('Delete this estimate?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $est['id'] ?>">
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
