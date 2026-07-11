<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

$customers = query('SELECT id, name FROM customers WHERE deleted_at IS NULL ORDER BY name');
$events = query('SELECT id, title, customer_id FROM events WHERE deleted_at IS NULL ORDER BY title');
$estimates = query("SELECT id, title, customer_id, event_id FROM estimates WHERE is_template=0 AND status IN ('sent','approved') ORDER BY title");
$settings = getSettings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int)$_POST['customer_id'];
    $eventId = $_POST['event_id'] ?: null;
    $estimateId = $_POST['estimate_id'] ?: null;
    $title = trim($_POST['title']) ?: 'Event Service Agreement';

    $customer = queryOne('SELECT * FROM customers WHERE id=?', [$customerId]);
    $event = $eventId ? queryOne('SELECT * FROM events WHERE id=?', [$eventId]) : null;
    $estimate = $estimateId ? queryOne('SELECT * FROM estimates WHERE id=?', [$estimateId]) : null;

    $content = ($_POST['template'] ?? 'comprehensive') === 'comprehensive'
        ? getComprehensiveContractTemplate()
        : '<h1>Event Service Agreement</h1><p>Between <strong>{{company_name}}</strong> and <strong>{{customer_name}}</strong>.</p><h2>Event</h2><p>{{event_title}} on {{event_date}} at {{event_venue}}</p><h2>Services</h2>{{items_table}}<h2>Total: {{total}}</h2><h2>Terms</h2><p>{{contract_footer}}</p><h2>Signatures</h2><p>Client: _______________ Date: _______________</p><p>Company: _______________ Date: _______________</p>';

    $contractData = buildContractData(['id' => 0], $customer, $event, $estimate, $settings);
    $content = replaceContractPlaceholders($content, $contractData);

    execute('INSERT INTO contracts (customer_id, event_id, estimate_id, title, content, status) VALUES (?,?,?,?,?,?)',
        [$customerId, $eventId, $estimateId, $title, $content, 'draft']);

    flash('success', 'Contract created. Review and customize before sending.');
    redirect('contract-edit.php?id=' . lastId());
}

$currentPage = 'contracts';
$pageTitle = 'New Contract';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Create Contract</h1>
        <p class="subtitle">Build a comprehensive agreement with terms, payment schedule, and signature blocks</p>
    </div>
    <a href="contracts.php" class="btn btn-secondary">← Back</a>
</div>

<div class="card">
    <form method="post">
        <div class="form-row">
            <div class="form-group">
                <label>Contract Title *</label>
                <input name="title" value="Event Service Agreement" placeholder="e.g. Johnson Wedding — Service Agreement" required>
            </div>
            <div class="form-group">
                <label>Customer *</label>
                <select name="customer_id" id="contract-customer" required>
                    <option value="">Select customer</option>
                    <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Linked Event</label>
                <select name="event_id" id="contract-event">
                    <option value="">— Optional —</option>
                    <?php foreach ($events as $ev): ?>
                    <option value="<?= $ev['id'] ?>" data-customer="<?= $ev['customer_id'] ?>"><?= e($ev['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Based on Estimate</label>
                <select name="estimate_id" id="contract-estimate">
                    <option value="">— Optional —</option>
                    <?php foreach ($estimates as $est): ?>
                    <option value="<?= $est['id'] ?>" data-customer="<?= $est['customer_id'] ?>" data-event="<?= $est['event_id'] ?>"><?= e($est['title']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="hint">Selecting an estimate auto-fills items and pricing placeholders</p>
            </div>
        </div>
        <div class="form-group">
            <label>Template</label>
            <select name="template">
                <option value="comprehensive">Comprehensive Agreement (recommended)</option>
                <option value="blank">Simple blank template</option>
            </select>
        </div>
        <div class="flex">
            <button type="submit" class="btn btn-primary">Create & Edit Contract</button>
            <a href="contracts.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
document.getElementById('contract-estimate')?.addEventListener('change', function() {
    var opt = this.options[this.selectedIndex];
    if (opt.dataset.customer) document.getElementById('contract-customer').value = opt.dataset.customer;
    if (opt.dataset.event) document.getElementById('contract-event').value = opt.dataset.event;
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
