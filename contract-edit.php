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
        flash('success', 'Contract updated with latest customer and event data.');
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
    flash('success', 'Professional template loaded.');
    redirect('contract-edit.php?id=' . $id);
}

$placeholderGroups = getContractPlaceholderGroups();
$placeholderLabels = getContractPlaceholders();
$loadContractEditor = true;
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
        <a href="contract-print.php?id=<?= $id ?>" class="btn btn-primary" target="_blank">📄 Preview &amp; Export PDF</a>
        <a href="?id=<?= $id ?>&load_template=1" class="btn btn-secondary" onclick="return confirm('Replace current content with the standard template?')">Reset Template</a>
    </div>
</div>

<form method="post" id="contract-form">
    <div class="form-row mb-1">
        <div class="form-group"><label>Contract Title</label><input name="title" value="<?= e($contract['title']) ?>"></div>
        <div class="form-group"><label>Status</label>
            <select name="status">
                <?php foreach (['draft'=>'Draft — still editing','sent'=>'Sent — awaiting client signature','signed'=>'Signed — completed','cancelled'=>'Cancelled'] as $s=>$label): ?>
                <option value="<?= $s ?>" <?= $contract['status']===$s?'selected':'' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="contract-editor">
        <div class="card contract-main">
            <div class="contract-toolbar-hint">
                <strong>Document Editor</strong> — Edit like a Word document. Purple tags auto-fill with real data when you export PDF.
            </div>
            <textarea name="content" id="contract-content"><?= e($contract['content']) ?></textarea>
            <div class="flex mt-1">
                <button type="submit" class="btn btn-primary">💾 Save Contract</button>
                <button type="submit" name="action" value="refresh_placeholders" class="btn btn-secondary">🔄 Fill Tags with Latest Data</button>
                <a href="contracts.php" class="btn btn-secondary">Back</a>
            </div>
        </div>

        <div class="contract-sidebar">
            <div class="card">
                <h3>Insert Auto-Fill Fields</h3>
                <p class="text-muted sidebar-hint">Click a field to insert it. These purple tags automatically fill with customer, event, and pricing data.</p>
                <?php foreach ($placeholderGroups as $group => $keys): ?>
                <div class="ph-group">
                    <div class="ph-group-title"><?= e($group) ?></div>
                    <?php foreach ($keys as $key): ?>
                    <button type="button" class="ph-btn" onclick="insertPlaceholder('<?= e($key) ?>', '<?= e($placeholderLabels[$key] ?? $key) ?>')">
                        <?= e($placeholderLabels[$key] ?? $key) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="card contract-tips">
                <h3>Quick Tips</h3>
                <ul>
                    <li>Edit text directly — no coding needed</li>
                    <li>Use <strong>Preview &amp; Export PDF</strong> to see the final document</li>
                    <li><strong>Fill Tags</strong> updates all purple fields with latest data</li>
                    <li>Change terms in <a href="settings.php">Settings</a></li>
                </ul>
            </div>
        </div>
    </div>
</form>

<script>
window.CONTRACT_PLACEHOLDER_LABELS = <?= json_encode($placeholderLabels) ?>;
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/7.6.0/tinymce.min.js"></script>
<script src="assets/js/contract.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
