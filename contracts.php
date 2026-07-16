<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    execute('DELETE FROM contracts WHERE id=?', [$_POST['id']]);
    flash('success', 'Contract deleted.');
    redirect('contracts.php');
}

$currentPage = 'contracts';
$pageTitle = 'Contracts';
require_once __DIR__ . '/includes/header.php';

$contracts = query('SELECT c.*, cu.name as customer_name, e.title as event_title FROM contracts c JOIN customers cu ON cu.id=c.customer_id LEFT JOIN events e ON e.id=c.event_id ORDER BY c.updated_at DESC');
?>

<div class="page-header">
    <div>
        <h1>Contracts</h1>
        <p class="subtitle">Comprehensive agreements with terms, signatures & PDF export</p>
    </div>
    <a href="contract-form.php" class="btn btn-primary">+ New Contract</a>
</div>

<div class="card">
    <?php if (empty($contracts)): ?>
    <div class="empty-state">
        <div class="icon">📄</div>
        <h3>No contracts yet</h3>
        <p>Create a contract from scratch or from an approved estimate.</p>
        <div class="flex" style="justify-content:center">
            <a href="contract-form.php" class="btn btn-primary">+ New Contract</a>
            <a href="estimates.php" class="btn btn-secondary">From Estimate</a>
        </div>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <tr><th>Title</th><th>Customer</th><th>Event</th><th>Status</th><th>Updated</th><th>Actions</th></tr>
            <?php foreach ($contracts as $c): ?>
            <tr>
                <td><strong><?= e($c['title']) ?></strong></td>
                <td><a href="customer-view.php?id=<?= (int)$c['customer_id'] ?>"><?= e($c['customer_name']) ?></a></td>
                <td><?= e($c['event_title'] ?: '—') ?></td>
                <td><span class="badge badge-<?= e($c['status']) ?>"><?= e(ucfirst($c['status'])) ?></span></td>
                <td><?= formatDate($c['updated_at']) ?></td>
                <td>
                    <div class="action-btns">
                        <a href="contract-edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                        <a href="contract-print.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-secondary" target="_blank">PDF</a>
                        <form method="post" onsubmit="return confirm('Delete this contract?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
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
