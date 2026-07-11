<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
$contract = queryOne('SELECT c.*, cu.name as customer_name, cu.email as customer_email, cu.phone as customer_phone, cu.address as customer_address, e.title as event_title, e.event_date, e.venue, e.ceremony_type FROM contracts c JOIN customers cu ON cu.id=c.customer_id LEFT JOIN events e ON e.id=c.event_id WHERE c.id=?', [$id]);
if (!$contract) { flash('error', 'Contract not found.'); redirect('contracts.php'); }

$estimate = $contract['estimate_id'] ? queryOne('SELECT * FROM estimates WHERE id=?', [$contract['estimate_id']]) : null;
$settings = getSettings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    if ($action === 'refresh_placeholders') {
        $data = buildContractData($contract, [
            'name' => $contract['customer_name'], 'email' => $contract['customer_email'],
            'phone' => $contract['customer_phone'], 'address' => $contract['customer_address'],
        ], $contract['event_id'] ? ['title'=>$contract['event_title'],'event_date'=>$contract['event_date'],'venue'=>$contract['venue'],'ceremony_type'=>$contract['ceremony_type']] : null, $estimate, $settings);
        $content = replaceContractPlaceholders($_POST['content'], $data);
        execute('UPDATE contracts SET content=?, updated_at=NOW() WHERE id=?', [$content, $id]);
        flash('success', 'Placeholders refreshed with latest data.');
        redirect('contract-edit.php?id=' . $id);
    }
    execute('UPDATE contracts SET title=?, content=?, status=?, updated_at=NOW() WHERE id=?',
        [$_POST['title'], $_POST['content'], $_POST['status'], $id]);
    if ($_POST['status'] === 'signed') execute('UPDATE contracts SET signed_at=NOW() WHERE id=?', [$id]);
    flash('success', 'Contract saved.');
    redirect('contract-edit.php?id=' . $id);
}

if (isset($_GET['load_template'])) {
    $content = getComprehensiveContractTemplate();
    $data = buildContractData($contract, [
        'name' => $contract['customer_name'], 'email' => $contract['customer_email'],
        'phone' => $contract['customer_phone'], 'address' => $contract['customer_address'],
    ], $contract['event_id'] ? ['title'=>$contract['event_title'],'event_date'=>$contract['event_date'],'venue'=>$contract['venue'],'ceremony_type'=>$contract['ceremony_type']] : null, $estimate, $settings);
    $content = replaceContractPlaceholders($content, $data);
    execute('UPDATE contracts SET content=?, updated_at=NOW() WHERE id=?', [$content, $id]);
    flash('success', 'Comprehensive template loaded.');
    redirect('contract-edit.php?id=' . $id);
}

$placeholders = getContractPlaceholders();
$currentPage = 'contracts';
$pageTitle = 'Edit Contract';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Edit Contract</h1>
        <p class="subtitle"><?= e($contract['customer_name']) ?><?php if ($contract['event_title']): ?> · <?= e($contract['event_title']) ?><?php endif; ?></p>
    </div>
    <div class="flex no-print">
        <a href="contract-print.php?id=<?= $id ?>" class="btn btn-primary" target="_blank">📄 Export PDF</a>
        <a href="?id=<?= $id ?>&load_template=1" class="btn btn-secondary" onclick="return confirm('Replace content with comprehensive template?')">Load Template</a>
    </div>
</div>

<form method="post" id="contract-form">
    <div class="form-row mb-1">
        <div class="form-group"><label>Contract Title</label><input name="title" value="<?= e($contract['title']) ?>"></div>
        <div class="form-group"><label>Status</label>
            <select name="status">
                <?php foreach (['draft'=>'Draft — editing','sent'=>'Sent — awaiting signature','signed'=>'Signed — completed','cancelled'=>'Cancelled'] as $s=>$label): ?>
                <option value="<?= $s ?>" <?= $contract['status']===$s?'selected':'' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="contract-editor">
        <div class="card">
            <h3>Contract Content</h3>
            <p class="text-muted mb-1">Edit all sections — payment terms, cancellation policy, liability, and signature blocks. Use placeholders for dynamic data.</p>
            <textarea name="content" id="contract-content" rows="28" style="font-family:monospace;font-size:.8rem"><?= e($contract['content']) ?></textarea>
            <div class="flex mt-1">
                <button type="submit" class="btn btn-primary">Save Contract</button>
                <button type="submit" name="action" value="refresh_placeholders" class="btn btn-secondary">Refresh Placeholders</button>
                <a href="contracts.php" class="btn btn-secondary">Back</a>
            </div>
        </div>
        <div>
            <div class="card">
                <h3>Insert Placeholder</h3>
                <p class="text-muted" style="font-size:.8rem">Click to insert at cursor. Filled automatically on save/print.</p>
                <ul class="placeholder-list">
                    <?php foreach ($placeholders as $key => $desc): ?>
                    <li><button type="button" onclick="insertPlaceholder('{{<?= $key ?>}}')" title="<?= e($desc) ?>">{{<?= $key ?>}}</button></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="card">
                <h3>Live Preview</h3>
                <div class="contract-preview" id="contract-preview" style="font-size:.8rem;max-height:500px;overflow-y:auto"></div>
            </div>
        </div>
    </div>
</form>

<script src="assets/js/contract.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
