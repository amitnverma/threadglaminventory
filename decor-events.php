<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/decor-proposal-functions.php';
requireDecorOwner();
ensureDecorProposalSchema();

$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'status' => trim($_GET['status'] ?? ''),
];
$events = decorEventsForWorkspace($filters);

$currentPage = 'decor-events';
$pageTitle = 'Decor Events';
$loadDecorInventory = true;
$loadDecorProposals = true;
require_once __DIR__ . '/includes/header.php';

$grouped = [];
foreach ($events as $ev) {
    $cid = (int)$ev['customer_id'];
    if (!isset($grouped[$cid])) {
        $grouped[$cid] = [
            'customer_name' => $ev['customer_name'],
            'events' => [],
        ];
    }
    $grouped[$cid]['events'][] = $ev;
}
?>

<div class="page-header">
    <div>
        <h1>Decor Events</h1>
        <p class="subtitle">Reserve Decor stock to customer events and build private decoration estimates</p>
    </div>
    <a href="decor-inventory.php" class="btn btn-secondary">Decor Inventory</a>
</div>

<form method="get" class="card decor-filters">
    <div class="flex decor-filter-row">
        <div class="form-group" style="flex:1;margin:0">
            <label>Search</label>
            <input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Customer, event, venue…">
        </div>
        <div class="form-group" style="margin:0;min-width:180px">
            <label>Event status</label>
            <select name="status">
                <option value="">All</option>
                <?php foreach (['inquiry','estimated','confirmed','completed','cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;align-self:flex-end">
            <button type="submit" class="btn btn-secondary">Filter</button>
            <a href="decor-events.php" class="btn btn-secondary">Reset</a>
        </div>
    </div>
</form>

<?php if (empty($grouped)): ?>
<div class="card">
    <div class="empty-state">
        <div class="icon">📅</div>
        <h3>No events found</h3>
        <p>Events created in the main app appear here for Decor planning.</p>
    </div>
</div>
<?php else: ?>
    <?php foreach ($grouped as $group): ?>
    <div class="card decor-customer-group">
        <h3><?= e($group['customer_name']) ?></h3>
        <div class="table-wrap">
            <table class="data-table">
                <tr>
                    <th>Event</th>
                    <th>Dates</th>
                    <th>Status</th>
                    <th>Proposal</th>
                    <th>Reservations</th>
                    <th></th>
                </tr>
                <?php foreach ($group['events'] as $ev): ?>
                <tr>
                    <td>
                        <strong><?= e($ev['title']) ?></strong>
                        <?php if ($ev['venue']): ?><div class="hint"><?= e($ev['venue']) ?></div><?php endif; ?>
                    </td>
                    <td>
                        <?= e(formatDate($ev['event_date'])) ?>
                        <?php if (!empty($ev['end_date']) && $ev['end_date'] !== $ev['event_date']): ?>
                            — <?= e(formatDate($ev['end_date'])) ?>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-<?= e($ev['status']) ?>"><?= e(ucfirst($ev['status'])) ?></span></td>
                    <td>
                        <?php if ($ev['proposal_id']): ?>
                            <span class="badge badge-<?= $ev['proposal_status'] === 'published' ? 'approved' : 'sent' ?>">
                                <?= e(ucfirst($ev['proposal_status'] ?: 'draft')) ?>
                            </span>
                            <div class="hint">
                                Price <?= e(formatMoney($ev['proposal_total'])) ?>
                                · Cost <?= e(formatMoney($ev['private_cost_total'])) ?>
                            </div>
                        <?php else: ?>
                            <span class="text-muted">Not started</span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int)$ev['active_reservations'] ?></td>
                    <td><a class="btn btn-sm btn-primary" href="decor-event.php?id=<?= (int)$ev['id'] ?>">Open</a></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
