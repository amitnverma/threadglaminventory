<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/decor-inventory-functions.php';
require_once __DIR__ . '/includes/decor-proposal-functions.php';
requireDecorOwner();
ensureDecorInventorySchema();
ensureDecorProposalSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $names = $_POST['line_name'] ?? [];
    $qtys = $_POST['line_qty'] ?? [];
    $prices = $_POST['line_price'] ?? [];
    $markups = $_POST['line_markup'] ?? [];

    $lines = [];
    $count = count($names);
    for ($i = 0; $i < $count; $i++) {
        $name = trim((string)($names[$i] ?? ''));
        if ($name === '') {
            continue;
        }
        $lines[] = [
            'name' => $name,
            'quantity' => (int)($qtys[$i] ?? 1),
            'unit_price' => (float)($prices[$i] ?? 0),
            'default_markup_percent' => (float)($markups[$i] ?? 0),
        ];
    }

    $result = decorInventoryCreateMany([
        'purchased_from' => $_POST['purchased_from'] ?? '',
        'purchase_date' => $_POST['purchase_date'] ?? date('Y-m-d'),
        'notes' => $_POST['notes'] ?? '',
    ], $lines);

    if (!$result['ok']) {
        flash('error', $result['error'] ?: 'Could not save purchases.');
        redirect('decor-inventory-buy.php');
    }

    flash('success', "Saved {$result['created']} Decor item(s).");
    redirect('decor-inventory.php');
}

$currentPage = 'decor-inventory';
$pageTitle = 'Buy Decor Items';
$loadDecorInventory = true;
$loadInventorySheet = true;
$pageScripts = ['assets/js/decor-inventory-buy.js'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header inv-sheet-page">
    <div>
        <h1>Buy Decor Items</h1>
        <p class="subtitle">One vendor trip — enter every item as spreadsheet rows</p>
    </div>
    <a href="decor-inventory.php" class="btn btn-secondary">Back to Decor Inventory</a>
</div>

<form method="post" id="decor-buy-form" class="card inv-sheet-card">
    <?= csrfField() ?>

    <div class="inv-sheet-header-fields">
        <div class="form-group">
            <label>Store / vendor</label>
            <input name="purchased_from" placeholder="e.g. Michaels, Amazon, Hobby Lobby" autofocus>
        </div>
        <div class="form-group">
            <label>Date</label>
            <input type="date" name="purchase_date" value="<?= e(date('Y-m-d')) ?>" required>
        </div>
        <div class="form-group">
            <label>Notes <span class="text-muted">(optional)</span></label>
            <input name="notes" placeholder="Receipt #, trip notes">
        </div>
    </div>

    <div class="inv-sheet-toolbar">
        <span class="inv-sheet-hint">Tab through cells. Paste from Excel. Each row becomes Decor-owned stock.</span>
        <span class="spacer"></span>
        <button type="button" class="btn btn-secondary btn-sm" id="decor-buy-add-row">+ Row</button>
    </div>

    <div id="decor-buy-sheet"></div>

    <div class="inv-sheet-footer">
        <div class="inv-sheet-total">Receipt total: $<span id="decor-buy-total">0.00</span></div>
        <span class="inv-sheet-status" id="decor-buy-count">0 lines</span>
        <span class="spacer"></span>
        <button type="submit" class="btn btn-primary">Save all</button>
    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
