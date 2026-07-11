<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

$currentPage = 'reports';
$pageTitle = 'Reports';
require_once __DIR__ . '/includes/header.php';

$salesTotal = queryOne('SELECT COALESCE(SUM(total),0) as t FROM sales');
$purchaseTotal = queryOne('SELECT COALESCE(SUM(total),0) as t FROM purchases');
$expenseTotal = queryOne('SELECT COALESCE(SUM(amount),0) as t FROM partner_expenses');

$events = query('SELECT e.id, e.title, e.status, c.name as customer_name FROM events e JOIN customers c ON c.id=e.customer_id WHERE e.deleted_at IS NULL ORDER BY e.event_date DESC LIMIT 20');
?>

<div class="page-header"><h1>Reports</h1></div>

<div class="stats">
    <div class="stat"><div class="label">Total Sales</div><div class="value" style="color:#059669"><?= formatMoney($salesTotal['t']) ?></div></div>
    <div class="stat"><div class="label">Total Purchases</div><div class="value" style="color:#dc2626"><?= formatMoney($purchaseTotal['t']) ?></div></div>
    <div class="stat"><div class="label">Partner Expenses</div><div class="value" style="color:#dc2626"><?= formatMoney($expenseTotal['t']) ?></div></div>
    <div class="stat"><div class="label">Net (Sales - Costs)</div><div class="value"><?= formatMoney($salesTotal['t'] - $purchaseTotal['t'] - $expenseTotal['t']) ?></div></div>
</div>

<div class="card">
    <h3 class="mb-1">Event Profit & Loss</h3>
    <table>
        <tr><th>Event</th><th>Customer</th><th>Status</th><th>Revenue</th><th>Expenses</th><th>Profit</th></tr>
        <?php foreach ($events as $ev):
            $pnl = getEventProfitLoss($ev['id']);
        ?>
        <tr>
            <td><a href="event-view.php?id=<?= $ev['id'] ?>"><?= e($ev['title']) ?></a></td>
            <td><?= e($ev['customer_name']) ?></td>
            <td><?= e(ucfirst($ev['status'])) ?></td>
            <td><?= formatMoney($pnl['revenue']) ?></td>
            <td><?= formatMoney($pnl['expenses']) ?></td>
            <td style="color:<?= $pnl['profit']>=0?'#059669':'#dc2626' ?>"><?= formatMoney($pnl['profit']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
