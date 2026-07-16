<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

$id = $_GET['id'] ?? null;
$customers = query('SELECT * FROM customers WHERE deleted_at IS NULL ORDER BY name');
$event = $id ? queryOne('SELECT * FROM events WHERE id=? AND deleted_at IS NULL', [$id]) : null;
$ceremonyTypes = getCeremonyTypes();
$preselectCustomer = (int)($_GET['customer_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['new_customer_name'])) {
        execute('INSERT INTO customers (name, email, phone) VALUES (?,?,?)', [$_POST['new_customer_name'], $_POST['new_customer_email'] ?? null, $_POST['new_customer_phone'] ?? null]);
        $_POST['customer_id'] = lastId();
    }
    $fields = [$_POST['customer_id'], $_POST['title'], $_POST['ceremony_type'] ?: null, $_POST['event_date'] ?: null,
        $_POST['end_date'] ?: null, $_POST['venue'] ?: null, $_POST['status'], $_POST['internal_notes'] ?: null, !empty($_POST['archived']) ? 1 : 0];
    if ($id) {
        execute('UPDATE events SET customer_id=?,title=?,ceremony_type=?,event_date=?,end_date=?,venue=?,status=?,internal_notes=?,archived=?,updated_at=NOW() WHERE id=?', array_merge($fields, [$id]));
        flash('success', 'Event updated.');
        redirect('event-view.php?id=' . $id);
    } else {
        execute('INSERT INTO events (customer_id,title,ceremony_type,event_date,end_date,venue,status,internal_notes,archived) VALUES (?,?,?,?,?,?,?,?,?)', $fields);
        flash('success', 'Event created.');
        redirect('event-view.php?id=' . lastId());
    }
}

$currentPage = 'events';
$pageTitle = $id ? 'Edit Event' : 'New Event';
require_once __DIR__ . '/includes/header.php';
$d = $event ?: ['customer_id'=>$preselectCustomer ?: '','title'=>'','ceremony_type'=>'','event_date'=>'','end_date'=>'','venue'=>'','status'=>'inquiry','internal_notes'=>'','archived'=>0];
$statuses = ['inquiry','estimated','confirmed','completed','cancelled'];
$backUrl = $preselectCustomer ? ('customer-view.php?id=' . $preselectCustomer . '&tab=events') : 'events.php';
?>

<div class="page-header">
    <h1><?= $id ? 'Edit' : 'New' ?> Event</h1>
    <a href="<?= e($backUrl) ?>" class="btn btn-secondary">← Back</a>
</div>

<div class="card" style="max-width:680px">
    <form method="post">
        <div class="form-group"><label>Customer *</label>
            <select name="customer_id" required><option value="">Select customer</option>
            <?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>" <?= $d['customer_id']==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
            <p class="hint"><a href="customers.php">Browse customers</a> or add inline below</p>
        </div>
        <details class="mb-1"><summary class="text-muted" style="cursor:pointer;font-weight:500">+ Add new customer inline</summary>
            <div class="form-row mt-1">
                <input name="new_customer_name" placeholder="Customer name">
                <input name="new_customer_email" placeholder="Email">
                <input name="new_customer_phone" placeholder="Phone">
            </div>
        </details>
        <div class="form-group"><label>Event Title *</label><input name="title" value="<?= e($d['title']) ?>" placeholder="e.g. Johnson Wedding Reception" required></div>
        <div class="form-row">
            <div class="form-group"><label>Event Type</label>
                <select name="ceremony_type"><option value="">— Select —</option>
                <?php foreach ($ceremonyTypes as $t): ?>
                <option value="<?= e($t) ?>" <?= $d['ceremony_type']===$t?'selected':'' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
                </select>
                <p class="hint"><a href="settings.php">Customize event types</a></p>
            </div>
            <div class="form-group"><label>Status</label>
                <select name="status"><?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= $d['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Event Date</label><input type="date" name="event_date" value="<?= e($d['event_date']) ?>"></div>
            <div class="form-group"><label>End Date</label><input type="date" name="end_date" value="<?= e($d['end_date']) ?>"></div>
        </div>
        <div class="form-group"><label>Venue</label><input name="venue" value="<?= e($d['venue']) ?>" placeholder="Venue name and address"></div>
        <div class="form-group"><label>Internal Notes</label><textarea name="internal_notes" placeholder="Theme preferences, guest count, special requirements"><?= e($d['internal_notes']) ?></textarea></div>
        <?php if ($id): ?><label><input type="checkbox" name="archived" value="1" <?= $d['archived']?'checked':'' ?>> Archive this event</label><?php endif; ?>
        <div class="flex mt-1"><button class="btn btn-primary">Save Event</button><a href="<?= e($backUrl) ?>" class="btn btn-secondary">Cancel</a></div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
