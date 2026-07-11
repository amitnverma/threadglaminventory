<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
$tab = $_GET['tab'] ?? 'overview';

$event = queryOne('SELECT e.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email FROM events e JOIN customers c ON c.id=e.customer_id WHERE e.id=? AND e.deleted_at IS NULL', [$id]);
if (!$event) { flash('error', 'Event not found.'); redirect('events.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'expenses') {
        $partners = $_POST['exp_partner_id'] ?? [];
        $amounts = $_POST['exp_amount'] ?? [];
        $count = 0;
        for ($i = 0; $i < count($partners); $i++) {
            if ($partners[$i] && $amounts[$i]) {
                execute('INSERT INTO partner_expenses (partner_id,event_id,category,description,amount,expense_date) VALUES (?,?,?,?,?,?)',
                    [$partners[$i], $id, $_POST['exp_category'][$i] ?? null, $_POST['exp_desc'][$i] ?? null, $amounts[$i], $_POST['exp_date'][$i] ?? date('Y-m-d')]);
                $count++;
            }
        }
        flash('success', "$count expense(s) recorded.");
        redirect('event-view.php?id=' . $id . '&tab=expenses');
    }
    if ($action === 'delete_expense' && !empty($_POST['expense_id'])) {
        execute('DELETE FROM partner_expenses WHERE id=? AND event_id=?', [$_POST['expense_id'], $id]);
        flash('success', 'Expense deleted.');
        redirect('event-view.php?id=' . $id . '&tab=expenses');
    }
    if ($action === 'delete_image' && !empty($_POST['attachment_id'])) {
        deleteAttachment((int)$_POST['attachment_id']);
        flash('success', 'Image removed.');
        redirect('event-view.php?id=' . $id . '&tab=images');
    }
    if (!empty($_FILES['image']['name'])) {
        uploadImage('event', $id, $_FILES['image']);
        flash('success', 'Photo uploaded.');
        redirect('event-view.php?id=' . $id . '&tab=images');
    }
}

$estimates = query('SELECT * FROM estimates WHERE event_id=? ORDER BY created_at DESC', [$id]);
$expenses = query('SELECT pe.*, p.name as partner_name FROM partner_expenses pe JOIN partners p ON p.id=pe.partner_id WHERE pe.event_id=? ORDER BY pe.expense_date DESC', [$id]);
$images = getImages('event', $id);
$contracts = query('SELECT * FROM contracts WHERE event_id=?', [$id]);
$pnl = getEventProfitLoss($id);
$partners = query('SELECT * FROM partners ORDER BY name');
$primaryImg = getPrimaryImage('event', $id);

$currentPage = 'events';
$pageTitle = $event['title'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e($event['title']) ?></h1>
        <p class="subtitle"><?= e($event['customer_name']) ?> · <?= formatDate($event['event_date']) ?></p>
    </div>
    <div class="flex">
        <a href="estimate-form.php?event_id=<?= $id ?>&customer_id=<?= $event['customer_id'] ?>" class="btn btn-primary">+ Estimate</a>
        <a href="contract-form.php" class="btn btn-secondary">+ Contract</a>
        <a href="event-form.php?id=<?= $id ?>" class="btn btn-secondary">Edit</a>
        <form method="post" action="events.php" onsubmit="return confirm('Delete this event?')">
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $id ?>">
            <button class="btn btn-danger">Delete</button>
        </form>
    </div>
</div>

<?php if ($primaryImg): ?>
<div style="margin-bottom:1.25rem;border-radius:12px;overflow:hidden;height:200px">
    <img src="<?= e(imgUrl($primaryImg)) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
</div>
<?php endif; ?>

<div class="tabs">
    <?php foreach (['overview'=>'Overview','estimates'=>'Estimates','expenses'=>'Expenses','images'=>'Photos','pnl'=>'P&L'] as $k=>$label): ?>
    <a href="?id=<?= $id ?>&tab=<?= $k ?>" class="<?= $tab===$k?'active':'' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'overview'): ?>
<div class="grid-2">
    <div class="card">
        <h3>Event Details</h3>
        <p><strong>Customer:</strong> <a href="customers.php?edit=<?= $event['customer_id'] ?>"><?= e($event['customer_name']) ?></a></p>
        <p><strong>Phone:</strong> <?= e($event['customer_phone'] ?: '—') ?></p>
        <p><strong>Type:</strong> <?= e($event['ceremony_type'] ?: '—') ?></p>
        <p><strong>Date:</strong> <?= formatDate($event['event_date']) ?><?php if ($event['end_date']): ?> — <?= formatDate($event['end_date']) ?><?php endif; ?></p>
        <p><strong>Venue:</strong> <?= e($event['venue'] ?: '—') ?></p>
        <p><strong>Status:</strong> <span class="badge badge-<?= e($event['status']) ?>"><?= e(ucfirst($event['status'])) ?></span></p>
    </div>
    <div class="card">
        <h3>Quick Stats</h3>
        <div class="stats" style="margin:0">
            <div class="stat success"><div class="label">Revenue</div><div class="value" style="font-size:1.25rem"><?= formatMoney($pnl['revenue']) ?></div></div>
            <div class="stat danger"><div class="label">Expenses</div><div class="value" style="font-size:1.25rem"><?= formatMoney($pnl['expenses']) ?></div></div>
        </div>
        <?php if ($event['internal_notes']): ?><p class="mt-1"><strong>Notes:</strong> <?= e($event['internal_notes']) ?></p><?php endif; ?>
    </div>
</div>
<?php if ($contracts): ?>
<div class="card"><h3>Contracts</h3>
    <?php foreach ($contracts as $ct): ?>
    <p><a href="contract-edit.php?id=<?= $ct['id'] ?>"><?= e($ct['title']) ?></a> <span class="badge badge-<?= e($ct['status']) ?>"><?= e(ucfirst($ct['status'])) ?></span></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php elseif ($tab === 'estimates'): ?>
<div class="card">
    <?php if (empty($estimates)): ?>
    <div class="empty-state"><div class="icon">📋</div><h3>No estimates</h3><a href="estimate-form.php?event_id=<?= $id ?>&customer_id=<?= $event['customer_id'] ?>" class="btn btn-primary">Create Estimate</a></div>
    <?php else: ?>
    <div class="table-wrap"><table class="data-table"><tr><th>Title</th><th>Status</th><th>Total</th><th>Actions</th></tr>
    <?php foreach ($estimates as $est): ?><tr>
        <td><?= e($est['title']) ?></td><td><span class="badge badge-<?= e($est['status']) ?>"><?= e(ucfirst($est['status'])) ?></span></td>
        <td><?= formatMoney($est['total']) ?></td>
        <td><div class="action-btns">
            <a href="estimate-form.php?id=<?= $est['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
            <a href="contract-create.php?estimate_id=<?= $est['id'] ?>" class="btn btn-sm btn-secondary">→ Contract</a>
        </div></td>
    </tr><?php endforeach; ?></table></div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'expenses'): ?>
<div class="card">
    <h3>Add Partner Expenses</h3>
    <form method="post">
        <input type="hidden" name="action" value="expenses">
        <div id="expense-rows">
            <div class="expense-row form-row mb-1">
                <select name="exp_partner_id[]"><option value="">Partner</option><?php foreach ($partners as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select>
                <input name="exp_category[]" placeholder="Category">
                <input name="exp_desc[]" placeholder="Description">
                <input type="number" step="0.01" name="exp_amount[]" placeholder="Amount">
                <input type="date" name="exp_date[]" value="<?= date('Y-m-d') ?>">
            </div>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="addExpenseRow()">+ Add Row</button>
        <button type="submit" class="btn btn-primary">Save Expenses</button>
    </form>
    <?php if ($expenses): ?>
    <div class="table-wrap mt-1"><table class="data-table"><tr><th>Partner</th><th>Category</th><th>Amount</th><th>Date</th><th></th></tr>
    <?php foreach ($expenses as $ex): ?><tr>
        <td><?= e($ex['partner_name']) ?></td><td><?= e($ex['category']) ?></td><td><?= formatMoney($ex['amount']) ?></td><td><?= formatDate($ex['expense_date']) ?></td>
        <td><form method="post" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="delete_expense"><input type="hidden" name="expense_id" value="<?= $ex['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form></td>
    </tr><?php endforeach; ?></table></div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'images'): ?>
<div class="card">
    <h3>Event Photos</h3>
    <?php $allowDelete = true; $uploadId = 'evt-' . $id; include __DIR__ . '/includes/photo-gallery.php'; ?>
</div>

<?php elseif ($tab === 'pnl'): ?>
<div class="stats">
    <div class="stat success"><div class="label">Revenue</div><div class="value"><?= formatMoney($pnl['revenue']) ?></div></div>
    <div class="stat danger"><div class="label">Expenses</div><div class="value"><?= formatMoney($pnl['expenses']) ?></div></div>
    <div class="stat"><div class="label">Net Profit</div><div class="value" style="color:<?= $pnl['profit']>=0?'#059669':'#dc2626' ?>"><?= formatMoney($pnl['profit']) ?></div></div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
