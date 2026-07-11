<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_partner' && !empty($_POST['id'])) {
        execute('DELETE FROM partners WHERE id=?', [$_POST['id']]);
        flash('success', 'Partner deleted.');
    }
    if ($action === 'delete_expense' && !empty($_POST['id'])) {
        execute('DELETE FROM partner_expenses WHERE id=?', [$_POST['id']]);
        flash('success', 'Expense deleted.');
    }
    if ($action === 'add_expenses') {
        $count = 0;
        foreach ($_POST['exp_partner_id'] ?? [] as $i => $pid) {
            if ($pid && !empty($_POST['exp_amount'][$i])) {
                execute('INSERT INTO partner_expenses (partner_id,event_id,category,description,amount,expense_date) VALUES (?,?,?,?,?,?)',
                    [$pid, $_POST['exp_event_id'][$i] ?: null, $_POST['exp_category'][$i] ?? null, $_POST['exp_desc'][$i] ?? null, $_POST['exp_amount'][$i], $_POST['exp_date'][$i] ?? date('Y-m-d')]);
                $count++;
            }
        }
        flash('success', "$count expense(s) saved.");
    }
    redirect('partners.php');
}

$currentPage = 'partners';
$pageTitle = 'Partners';
require_once __DIR__ . '/includes/header.php';

$partners = query('SELECT p.*, (SELECT COALESCE(SUM(amount),0) FROM partner_expenses pe WHERE pe.partner_id=p.id) as total_expenses FROM partners p ORDER BY p.name');
$expenses = query('SELECT pe.*, p.name as partner_name, e.title as event_title FROM partner_expenses pe JOIN partners p ON p.id=pe.partner_id LEFT JOIN events e ON e.id=pe.event_id ORDER BY pe.expense_date DESC LIMIT 50');
$events = query('SELECT id, title FROM events WHERE deleted_at IS NULL ORDER BY title');
?>

<div class="page-header">
    <div>
        <h1>Partners & Expenses</h1>
        <p class="subtitle">Manage vendors, collaborators, and shared costs</p>
    </div>
    <a href="partner-form.php" class="btn btn-primary">+ Add Partner</a>
</div>

<div class="card">
    <h3>Partners (<?= count($partners) ?>)</h3>
    <?php if (empty($partners)): ?>
        <div class="empty-state"><div class="icon">🤝</div><h3>No partners yet</h3><p>Add decorators, audio vendors, and other collaborators.</p>
        <a href="partner-form.php" class="btn btn-primary">+ Add Partner</a></div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <tr><th>Partner</th><th>Contact</th><th>Split %</th><th>Total Expenses</th><th>Actions</th></tr>
            <?php foreach ($partners as $p): ?>
            <tr>
                <td><strong><?= e($p['name']) ?></strong>
                    <?php if ($p['notes']): ?><br><span class="text-muted"><?= e(mb_strimwidth($p['notes'], 0, 60, '...')) ?></span><?php endif; ?>
                </td>
                <td>
                    <?= e($p['phone'] ?: '—') ?><br>
                    <span class="text-muted"><?= e($p['email'] ?: '') ?></span>
                </td>
                <td><?= $p['default_split_percent'] ?>%</td>
                <td><?= formatMoney($p['total_expenses']) ?></td>
                <td><?= actionButtons('partner-form.php?id=' . $p['id'], 'delete_partner', $p['id']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Record Expenses</h3>
        <form method="post">
            <input type="hidden" name="action" value="add_expenses">
            <div id="expense-rows">
                <div class="expense-row form-row mb-1">
                    <select name="exp_partner_id[]" required><option value="">Partner *</option><?php foreach ($partners as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select>
                    <select name="exp_event_id[]"><option value="">Event</option><?php foreach ($events as $ev): ?><option value="<?= $ev['id'] ?>"><?= e($ev['title']) ?></option><?php endforeach; ?></select>
                    <input name="exp_category[]" placeholder="Category">
                    <input name="exp_desc[]" placeholder="Description">
                    <input type="number" step="0.01" name="exp_amount[]" placeholder="Amount *" required>
                    <input type="date" name="exp_date[]" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="flex mt-1">
                <button type="button" class="btn btn-sm btn-secondary" onclick="addExpenseRow()">+ Add Row</button>
                <button class="btn btn-primary">Save Expenses</button>
            </div>
        </form>
    </div>
    <div class="card">
        <h3>Expense Summary</h3>
        <?php $totalExpRow = queryOne('SELECT COALESCE(SUM(amount),0) as t FROM partner_expenses'); ?>
        <div class="stat" style="margin:0"><div class="label">Total Recorded</div><div class="value"><?= formatMoney($totalExpRow['t']) ?></div></div>
    </div>
</div>

<div class="card">
    <h3>Recent Expenses</h3>
    <?php if (empty($expenses)): ?>
        <p class="text-muted">No expenses recorded yet.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <tr><th>Date</th><th>Partner</th><th>Event</th><th>Category</th><th>Description</th><th>Amount</th><th></th></tr>
            <?php foreach ($expenses as $ex): ?>
            <tr>
                <td><?= formatDate($ex['expense_date']) ?></td>
                <td><?= e($ex['partner_name']) ?></td>
                <td><?= e($ex['event_title'] ?: '—') ?></td>
                <td><?= e($ex['category'] ?: '—') ?></td>
                <td><?= e($ex['description'] ?: '—') ?></td>
                <td><?= formatMoney($ex['amount']) ?></td>
                <td>
                    <form method="post" onsubmit="return confirm('Delete this expense?')">
                        <input type="hidden" name="action" value="delete_expense">
                        <input type="hidden" name="id" value="<?= $ex['id'] ?>">
                        <button class="btn btn-sm btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
