<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    execute('UPDATE events SET deleted_at=NOW() WHERE id=?', [$_POST['id']]);
    flash('success', 'Event deleted.');
    redirect('events.php');
}

$currentPage = 'events';
$pageTitle = 'Events';
require_once __DIR__ . '/includes/header.php';

$archived = isset($_GET['archived']);
$where = 'e.deleted_at IS NULL AND e.archived=' . ($archived ? 1 : 0);
$events = query("SELECT e.*, c.name as customer_name FROM events e JOIN customers c ON c.id=e.customer_id WHERE $where ORDER BY e.event_date DESC");
?>

<div class="page-header">
    <div>
        <h1>Events</h1>
        <p class="subtitle"><?= count($events) ?> <?= $archived ? 'archived' : 'active' ?> events</p>
    </div>
    <a href="event-form.php" class="btn btn-primary">+ New Event</a>
</div>

<div class="tabs">
    <a href="events.php" class="<?= !$archived ? 'active' : '' ?>">Active Events</a>
    <a href="events.php?archived=1" class="<?= $archived ? 'active' : '' ?>">Archived</a>
</div>

<div class="card">
    <?php if (empty($events)): ?>
    <div class="empty-state"><div class="icon">📅</div><h3>No events <?= $archived ? 'archived' : 'yet' ?></h3>
    <p><?= $archived ? 'Archived events will appear here.' : 'Create your first event to get started.' ?></p>
    <?php if (!$archived): ?><a href="event-form.php" class="btn btn-primary">+ New Event</a><?php endif; ?></div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <tr><th>Event</th><th>Customer</th><th>Date</th><th>Venue</th><th>Status</th><th>Actions</th></tr>
            <?php foreach ($events as $ev): ?>
            <tr>
                <td><a href="event-view.php?id=<?= $ev['id'] ?>"><strong><?= e($ev['title']) ?></strong></a>
                    <?php if ($ev['ceremony_type']): ?><br><span class="text-muted"><?= e($ev['ceremony_type']) ?></span><?php endif; ?>
                </td>
                <td><?= e($ev['customer_name']) ?></td>
                <td><?= formatDate($ev['event_date']) ?></td>
                <td><?= e($ev['venue'] ?: '—') ?></td>
                <td><span class="badge badge-<?= e($ev['status']) ?>"><?= e(ucfirst($ev['status'])) ?></span></td>
                <td><?= actionButtons('event-form.php?id=' . $ev['id'], 'delete', $ev['id'], 'event-view.php?id=' . $ev['id']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
