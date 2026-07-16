<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/album-functions.php';
requireAuth();
ensureAlbumsSchema();

$id = (int)($_GET['id'] ?? 0);
$tab = $_GET['tab'] ?? 'overview';
$editingProfile = isset($_GET['edit']);
$allowedTabs = ['overview', 'events', 'albums', 'estimates', 'contracts'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'overview';
}

$customer = queryOne('SELECT * FROM customers WHERE id=? AND deleted_at IS NULL', [$id]);
if (!$customer) {
    flash('error', 'Customer not found.');
    redirect('customers.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        execute(
            'UPDATE customers SET name=?, email=?, phone=?, address=?, notes=?, updated_at=NOW() WHERE id=?',
            [
                trim($_POST['name'] ?? ''),
                trim($_POST['email'] ?? '') ?: null,
                trim($_POST['phone'] ?? '') ?: null,
                trim($_POST['address'] ?? '') ?: null,
                trim($_POST['notes'] ?? '') ?: null,
                $id,
            ]
        );
        flash('success', 'Customer profile updated.');
        redirect('customer-view.php?id=' . $id . '&tab=overview');
    }

    if ($action === 'delete') {
        execute('UPDATE customers SET deleted_at=NOW() WHERE id=?', [$id]);
        flash('success', 'Customer removed.');
        redirect('customers.php');
    }

    redirect('customer-view.php?id=' . $id . '&tab=' . $tab);
}

$events = query(
    'SELECT e.*,
            (SELECT COUNT(*) FROM estimates est WHERE est.event_id=e.id) AS estimate_count
     FROM events e
     WHERE e.customer_id=? AND e.deleted_at IS NULL
     ORDER BY e.archived ASC, e.event_date DESC, e.id DESC',
    [$id]
);

$albums = query(
    'SELECT a.*,
            (SELECT COUNT(*) FROM album_photos ap WHERE ap.album_id=a.id) AS photo_count,
            e.title AS event_title
     FROM albums a
     LEFT JOIN events e ON e.id=a.event_id
     WHERE a.customer_id=? OR a.event_id IN (SELECT id FROM events WHERE customer_id=? AND deleted_at IS NULL)
     ORDER BY a.updated_at DESC, a.id DESC',
    [$id, $id]
);

$estimates = query(
    'SELECT est.*, ev.title AS event_title
     FROM estimates est
     LEFT JOIN events ev ON ev.id=est.event_id
     WHERE est.customer_id=?
     ORDER BY est.created_at DESC',
    [$id]
);

$contracts = query(
    'SELECT c.*, ev.title AS event_title, est.title AS estimate_title
     FROM contracts c
     LEFT JOIN events ev ON ev.id=c.event_id
     LEFT JOIN estimates est ON est.id=c.estimate_id
     WHERE c.customer_id=?
     ORDER BY c.updated_at DESC',
    [$id]
);

$stats = [
    'events' => count($events),
    'albums' => count($albums),
    'estimates' => count($estimates),
    'contracts' => count($contracts),
    'active_events' => count(array_filter($events, static fn($e) => !(int)$e['archived'])),
];

$currentPage = 'customer-view';
$pageTitle = $customer['name'];
$loadCustomerHub = true;
require_once __DIR__ . '/includes/header.php';

$tabHref = static function (string $key) use ($id): string {
    return 'customer-view.php?id=' . $id . '&tab=' . $key;
};
?>

<div class="page-header customer-hub-header">
    <div>
        <p class="crumb"><a href="customers.php">Customers</a> / <?= e($customer['name']) ?></p>
        <h1><?= e($customer['name']) ?></h1>
        <p class="subtitle">
            <?= e($customer['phone'] ?: 'No phone') ?>
            · <?= e($customer['email'] ?: 'No email') ?>
        </p>
    </div>
    <div class="flex">
        <a href="event-form.php?customer_id=<?= $id ?>" class="btn btn-primary">+ Event</a>
        <a href="estimate-form.php?customer_id=<?= $id ?>" class="btn btn-secondary">+ Estimate</a>
        <a href="customers.php" class="btn btn-secondary">All customers</a>
    </div>
</div>

<div class="customer-stat-strip">
    <div><strong><?= (int)$stats['active_events'] ?></strong><span>Active events</span></div>
    <div><strong><?= (int)$stats['albums'] ?></strong><span>Albums</span></div>
    <div><strong><?= (int)$stats['estimates'] ?></strong><span>Estimates</span></div>
    <div><strong><?= (int)$stats['contracts'] ?></strong><span>Contracts</span></div>
</div>

<div class="tabs customer-hub-tabs">
    <?php
    $tabs = [
        'overview' => 'Overview',
        'events' => 'Events (' . $stats['events'] . ')',
        'albums' => 'Albums (' . $stats['albums'] . ')',
        'estimates' => 'Estimates (' . $stats['estimates'] . ')',
        'contracts' => 'Contracts (' . $stats['contracts'] . ')',
    ];
    foreach ($tabs as $key => $label):
    ?>
        <a href="<?= e($tabHref($key)) ?>" class="<?= $tab === $key ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'overview'): ?>
<div class="grid-2 customer-hub-grid">
    <div class="card">
        <div class="customer-tab-toolbar">
            <h3>Profile</h3>
            <?php if (!$editingProfile): ?>
                <a href="customer-view.php?id=<?= $id ?>&tab=overview&edit=1" class="btn btn-secondary btn-sm">Edit</a>
            <?php else: ?>
                <a href="customer-view.php?id=<?= $id ?>&tab=overview" class="btn btn-secondary btn-sm">Cancel</a>
            <?php endif; ?>
        </div>

        <?php if (!$editingProfile): ?>
        <dl class="customer-profile-view">
            <div>
                <dt>Name</dt>
                <dd><?= e($customer['name']) ?></dd>
            </div>
            <div>
                <dt>Phone</dt>
                <dd><?= e($customer['phone'] ?: '—') ?></dd>
            </div>
            <div>
                <dt>Email</dt>
                <dd><?= e($customer['email'] ?: '—') ?></dd>
            </div>
            <div class="is-full">
                <dt>Address</dt>
                <dd><?= e($customer['address'] ?: '—') ?></dd>
            </div>
            <div class="is-full">
                <dt>Notes</dt>
                <dd><?= $customer['notes'] !== null && trim((string)$customer['notes']) !== ''
                    ? nl2br(e($customer['notes']))
                    : '—' ?></dd>
            </div>
        </dl>
        <form method="post" class="customer-delete-inline" onsubmit="return confirm('Remove this customer?')">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn btn-danger btn-sm">Delete customer</button>
        </form>
        <?php else: ?>
        <form method="post" class="customer-profile-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_profile">
            <div class="form-group">
                <label>Full name *</label>
                <input name="name" value="<?= e($customer['name']) ?>" required autofocus>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= e((string)$customer['email']) ?>">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input name="phone" value="<?= e((string)$customer['phone']) ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Address</label>
                <textarea name="address" rows="2"><?= e((string)$customer['address']) ?></textarea>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" rows="3" placeholder="Preferences, referrals, special requests"><?= e((string)$customer['notes']) ?></textarea>
            </div>
            <div class="flex">
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="customer-view.php?id=<?= $id ?>&tab=overview" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Quick actions</h3>
        <div class="customer-quick-actions">
            <a href="event-form.php?customer_id=<?= $id ?>" class="btn btn-secondary">New event</a>
            <a href="albums.php?customer_id=<?= $id ?>#newAlbum" class="btn btn-secondary">New album</a>
            <a href="estimate-form.php?customer_id=<?= $id ?>" class="btn btn-secondary">New estimate</a>
            <a href="contract-form.php?customer_id=<?= $id ?>" class="btn btn-secondary">New contract</a>
        </div>

        <?php if (!empty($events)): ?>
        <h3 class="mt-1">Recent events</h3>
        <ul class="customer-mini-list">
            <?php foreach (array_slice($events, 0, 4) as $ev): ?>
            <li>
                <a href="event-view.php?id=<?= (int)$ev['id'] ?>"><?= e($ev['title']) ?></a>
                <span><?= formatDate($ev['event_date']) ?> · <?= e(ucfirst((string)$ev['status'])) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($tab === 'events'): ?>
<div class="card">
    <div class="customer-tab-toolbar">
        <h3>Events</h3>
        <a href="event-form.php?customer_id=<?= $id ?>" class="btn btn-primary btn-sm">+ New event</a>
    </div>
    <?php if (empty($events)): ?>
        <div class="empty-state">
            <div class="icon">📅</div>
            <h3>No events yet</h3>
            <p>Create the first event for <?= e($customer['name']) ?>.</p>
            <a href="event-form.php?customer_id=<?= $id ?>" class="btn btn-primary">+ New event</a>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <tr><th>Event</th><th>Date</th><th>Status</th><th>Venue</th><th></th></tr>
            <?php foreach ($events as $ev): ?>
            <tr class="<?= (int)$ev['archived'] ? 'is-muted-row' : '' ?>">
                <td>
                    <a href="event-view.php?id=<?= (int)$ev['id'] ?>"><strong><?= e($ev['title']) ?></strong></a>
                    <?php if ((int)$ev['archived']): ?><span class="badge badge-draft">Archived</span><?php endif; ?>
                </td>
                <td><?= formatDate($ev['event_date']) ?></td>
                <td><span class="badge badge-<?= e($ev['status']) ?>"><?= e(ucfirst((string)$ev['status'])) ?></span></td>
                <td><?= e($ev['venue'] ?: '—') ?></td>
                <td>
                    <div class="action-btns">
                        <a href="event-view.php?id=<?= (int)$ev['id'] ?>" class="btn btn-sm btn-secondary">Open</a>
                        <a href="estimate-form.php?customer_id=<?= $id ?>&event_id=<?= (int)$ev['id'] ?>" class="btn btn-sm btn-primary">Estimate</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'albums'): ?>
<div class="card">
    <div class="customer-tab-toolbar">
        <h3>Albums</h3>
        <a href="albums.php?customer_id=<?= $id ?>#newAlbum" class="btn btn-primary btn-sm">+ New album</a>
    </div>
    <?php if (empty($albums)): ?>
        <div class="empty-state">
            <div class="icon">📸</div>
            <h3>No albums yet</h3>
            <p>Create a photo album for this client.</p>
            <a href="albums.php?customer_id=<?= $id ?>#newAlbum" class="btn btn-primary">+ New album</a>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <tr><th>Album</th><th>Event</th><th>Photos</th><th>Status</th><th></th></tr>
            <?php foreach ($albums as $album): ?>
            <tr>
                <td><a href="album-view.php?id=<?= (int)$album['id'] ?>"><strong><?= e($album['name']) ?></strong></a></td>
                <td><?= e($album['event_title'] ?: '—') ?></td>
                <td><?= (int)$album['photo_count'] ?></td>
                <td><span class="badge badge-<?= e($album['status'] === 'archived' ? 'draft' : 'sent') ?>"><?= e(ucfirst((string)$album['status'])) ?></span></td>
                <td><a href="album-view.php?id=<?= (int)$album['id'] ?>" class="btn btn-sm btn-secondary">Open</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'estimates'): ?>
<div class="card">
    <div class="customer-tab-toolbar">
        <h3>Estimates</h3>
        <a href="estimate-form.php?customer_id=<?= $id ?>" class="btn btn-primary btn-sm">+ New estimate</a>
    </div>
    <?php if (empty($estimates)): ?>
        <div class="empty-state">
            <div class="icon">📋</div>
            <h3>No estimates yet</h3>
            <a href="estimate-form.php?customer_id=<?= $id ?>" class="btn btn-primary">+ New estimate</a>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <tr><th>Estimate</th><th>Event</th><th>Status</th><th>Total</th><th></th></tr>
            <?php foreach ($estimates as $est): ?>
            <tr>
                <td><a href="estimate-form.php?id=<?= (int)$est['id'] ?>"><strong><?= e($est['title']) ?></strong></a></td>
                <td><?= e($est['event_title'] ?: '—') ?></td>
                <td><span class="badge badge-<?= e($est['status']) ?>"><?= e(ucfirst((string)$est['status'])) ?></span></td>
                <td><strong><?= formatMoney($est['total']) ?></strong></td>
                <td>
                    <div class="action-btns">
                        <a href="estimate-form.php?id=<?= (int)$est['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <a href="contract-create.php?estimate_id=<?= (int)$est['id'] ?>" class="btn btn-sm btn-primary">Contract</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'contracts'): ?>
<div class="card">
    <div class="customer-tab-toolbar">
        <h3>Contracts</h3>
        <a href="contract-form.php?customer_id=<?= $id ?>" class="btn btn-primary btn-sm">+ New contract</a>
    </div>
    <?php if (empty($contracts)): ?>
        <div class="empty-state">
            <div class="icon">📄</div>
            <h3>No contracts yet</h3>
            <a href="contract-form.php?customer_id=<?= $id ?>" class="btn btn-primary">+ New contract</a>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <tr><th>Contract</th><th>Event</th><th>Status</th><th>Updated</th><th></th></tr>
            <?php foreach ($contracts as $contract): ?>
            <tr>
                <td><a href="contract-edit.php?id=<?= (int)$contract['id'] ?>"><strong><?= e($contract['title']) ?></strong></a></td>
                <td><?= e($contract['event_title'] ?: '—') ?></td>
                <td><span class="badge badge-<?= e($contract['status']) ?>"><?= e(ucfirst((string)$contract['status'])) ?></span></td>
                <td><?= formatDate($contract['updated_at'] ?? null) ?></td>
                <td><a href="contract-edit.php?id=<?= (int)$contract['id'] ?>" class="btn btn-sm btn-secondary">Open</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
