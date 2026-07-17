<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

$currentPage = 'dashboard';
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$inventory = queryOne('SELECT COUNT(*) as c, COALESCE(SUM(quantity_on_hand),0) as q FROM inventory_items WHERE deleted_at IS NULL');
$events = queryOne("SELECT COUNT(*) as c FROM events WHERE deleted_at IS NULL AND status NOT IN ('completed','cancelled') AND archived=0");
$revenue = queryOne('SELECT COALESCE(SUM(total),0) as t FROM sales');
$recentEvents = query('SELECT e.*, c.name as customer_name FROM events e JOIN customers c ON c.id=e.customer_id WHERE e.deleted_at IS NULL AND e.archived=0 ORDER BY e.event_date ASC LIMIT 5');
$recentContracts = query("SELECT c.*, cu.name as customer_name FROM contracts c JOIN customers cu ON cu.id=c.customer_id WHERE c.status IN ('draft','sent') ORDER BY c.updated_at DESC LIMIT 4");
?>

<div class="page-header">
    <div>
        <h1>Dashboard</h1>
        <p class="subtitle">Welcome back — here's your business at a glance</p>
    </div>
    <div class="flex">
        <a href="event-form.php" class="btn btn-primary">+ New Event</a>
        <a href="inventory-form.php" class="btn btn-secondary">+ Add Item</a>
    </div>
</div>

<div class="stats">
    <div class="stat"><div class="label">Inventory Items</div><div class="value"><?= (int)$inventory['c'] ?></div></div>
    <div class="stat"><div class="label">Units in Stock</div><div class="value"><?= (int)$inventory['q'] ?></div></div>
    <div class="stat success"><div class="label">Active Events</div><div class="value"><?= (int)$events['c'] ?></div></div>
    <div class="stat success"><div class="label">Total Revenue</div><div class="value"><?= formatMoney($revenue['t']) ?></div></div>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Upcoming Events</h3>
        <?php if (empty($recentEvents)): ?>
            <div class="empty-state" style="padding:2rem"><p class="text-muted">No events yet.</p><a href="event-form.php" class="btn btn-primary btn-sm">Create Event</a></div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <tr><th>Event</th><th>Customer</th><th>Date</th><th>Status</th></tr>
                <?php foreach ($recentEvents as $ev): ?>
                <tr>
                    <td><a href="event-view.php?id=<?= $ev['id'] ?>"><?= e($ev['title']) ?></a></td>
                    <td><?= e($ev['customer_name']) ?></td>
                    <td><?= formatDate($ev['event_date']) ?></td>
                    <td><span class="badge badge-<?= e($ev['status']) ?>"><?= e(ucfirst($ev['status'])) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Pending Contracts</h3>
        <?php if (empty($recentContracts)): ?>
            <p class="text-muted">No pending contracts. <a href="contract-form.php">Create one</a></p>
        <?php else: ?>
        <?php foreach ($recentContracts as $ct): ?>
        <div class="flex" style="justify-content:space-between;padding:.5rem 0;border-bottom:1px solid #f3f4f6">
            <div>
                <a href="contract-edit.php?id=<?= $ct['id'] ?>"><strong><?= e($ct['title']) ?></strong></a><br>
                <span class="text-muted"><?= e($ct['customer_name']) ?></span>
            </div>
            <span class="badge badge-<?= e($ct['status']) ?>"><?= e(ucfirst($ct['status'])) ?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
