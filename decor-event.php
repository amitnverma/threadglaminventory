<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/decor-proposal-functions.php';
requireDecorOwner();
ensureDecorProposalSchema();

$eventId = (int)($_GET['id'] ?? 0);
$event = decorGetEvent($eventId);
if (!$event) {
    flash('error', 'Event not found.');
    redirect('decor-events.php');
}

$proposal = decorEnsureProposalForEvent($event);
[$rangeStart, $rangeEnd] = decorEventDateRange($event);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';
    $back = 'decor-event.php?id=' . $eventId;

    if ($action === 'add_item') {
        [$lineId, $err] = decorProposalAddItem(
            $eventId,
            (int)($_POST['decor_item_id'] ?? 0),
            (int)($_POST['quantity'] ?? 1),
            isset($_POST['unit_price']) && $_POST['unit_price'] !== '' ? (float)$_POST['unit_price'] : null,
            isset($_POST['markup_percent']) && $_POST['markup_percent'] !== '' ? (float)$_POST['markup_percent'] : null
        );
        flash($err ? 'error' : 'success', $err ?: 'Item reserved for this event.');
        redirect($back);
    }

    if ($action === 'add_custom') {
        [$lineId, $err] = decorProposalAddCustomLine(
            $eventId,
            $_POST['label'] ?? '',
            (float)($_POST['quantity'] ?? 1),
            (float)($_POST['unit_price'] ?? 0),
            (float)($_POST['unit_cost'] ?? 0),
            $_POST['line_type'] ?? 'custom'
        );
        flash($err ? 'error' : 'success', $err ?: 'Custom line added.');
        redirect($back);
    }

    if ($action === 'update_line' && !empty($_POST['line_id'])) {
        $err = decorProposalUpdateLine((int)$_POST['line_id'], $_POST);
        flash($err ? 'error' : 'success', $err ?: 'Line updated.');
        redirect($back);
    }

    if ($action === 'remove_line' && !empty($_POST['line_id'])) {
        $err = decorProposalRemoveLine((int)$_POST['line_id']);
        flash($err ? 'error' : 'success', $err ?: 'Line removed and reservation cancelled.');
        redirect($back);
    }

    if ($action === 'reservation_status' && !empty($_POST['reservation_id'])) {
        $err = decorReservationSetStatus((int)$_POST['reservation_id'], $_POST['status'] ?? '');
        flash($err ? 'error' : 'success', $err ?: 'Reservation updated.');
        redirect($back);
    }

    if ($action === 'update_header') {
        $err = decorProposalUpdateHeader((int)$proposal['id'], $_POST);
        flash($err ? 'error' : 'success', $err ?: 'Proposal settings saved.');
        redirect($back);
    }

    if ($action === 'publish') {
        decorProposalUpdateHeader((int)$proposal['id'], $_POST);
        [$estId, $err] = decorProposalPublish((int)$proposal['id']);
        if ($err) {
            flash('error', $err);
        } else {
            flash('success', 'Published to main estimate #' . $estId . '. Purchase costs were kept private.');
        }
        redirect($back);
    }

    redirect($back);
}

$proposal = decorProposalGet((int)$proposal['id']);
$lines = decorProposalLines((int)$proposal['id']);
$reservations = decorEventReservations($eventId);
$picker = decorInventoryPickerForEvent($event);
$totals = decorCalculateProposalTotals($lines, $proposal);
$availablePicker = array_values(array_filter($picker, static fn($item) => (int)$item['available_qty'] > 0));
$unavailableCount = count($picker) - count($availablePicker);

$currentPage = 'decor-events';
$pageTitle = 'Decor — ' . $event['title'];
$loadDecorInventory = true;
$loadDecorProposals = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="decor-event-page">
    <div class="page-header decor-event-header">
        <div>
            <h1><?= e($event['title']) ?></h1>
            <p class="subtitle">
                <?= e($event['customer_name']) ?>
                · <?= e(formatDate($event['event_date'])) ?>
                <?php if ($event['end_date'] && $event['end_date'] !== $event['event_date']): ?>
                    — <?= e(formatDate($event['end_date'])) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="decor-event-meta">
            <div class="decor-event-pills">
                <span class="decor-pill"><em>Total</em> <strong id="decor-prop-total"><?= e(formatMoney($totals['total'])) ?></strong></span>
                <span class="decor-pill"><em>Cost</em> <strong id="decor-prop-cost"><?= e(formatMoney($totals['private_cost_total'])) ?></strong></span>
                <span class="decor-pill"><em>Margin</em> <strong id="decor-prop-margin"><?= e(formatMoney($totals['margin'])) ?></strong></span>
                <span class="badge badge-<?= $proposal['status'] === 'published' ? 'approved' : 'sent' ?>">
                    <?= e(ucfirst($proposal['status'])) ?>
                </span>
            </div>
            <div class="flex">
                <a href="decor-events.php" class="btn btn-secondary btn-sm">All events</a>
                <?php if (!empty($proposal['estimate_id'])): ?>
                    <a href="estimate-form.php?id=<?= (int)$proposal['estimate_id'] ?>" class="btn btn-secondary btn-sm">Estimate</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card decor-custom-bar">
        <form method="post" class="decor-custom-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_custom">
            <strong class="decor-custom-label">Custom / labor</strong>
            <input name="label" required placeholder="e.g. Setup labor" class="decor-custom-name" aria-label="Label">
            <select name="line_type" aria-label="Type">
                <option value="custom">Custom</option>
                <option value="labor">Labor</option>
            </select>
            <input type="number" name="quantity" value="1" min="0.01" step="0.01" aria-label="Qty" title="Qty" placeholder="Qty">
            <input type="number" name="unit_cost" value="0" min="0" step="0.01" aria-label="Private cost" title="Private cost" placeholder="Cost">
            <input type="number" name="unit_price" value="0" min="0" step="0.01" required aria-label="Rate" title="Rate" placeholder="Rate">
            <button class="btn btn-primary btn-sm">Add line</button>
        </form>
    </div>

    <div class="decor-event-workspace">
        <aside class="card decor-stock-panel">
            <div class="decor-panel-head">
                <h3>Stock</h3>
                <span class="hint"><?= count($availablePicker) ?> avail.</span>
            </div>
            <input type="search" id="decor-picker-search" placeholder="Search stock…" autocomplete="off">
            <div class="decor-picker-list" id="decor-picker-list">
                <?php foreach ($availablePicker as $item): ?>
                <div class="decor-picker-item" data-name="<?= e(strtolower($item['name'])) ?>" data-available="1">
                    <div class="decor-picker-main">
                        <strong title="<?= e($item['name']) ?>"><?= e($item['name']) ?></strong>
                        <span class="decor-picker-stock is-available" title="In stock"><?= (int)$item['available_qty'] ?></span>
                    </div>
                    <form method="post" class="decor-add-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="add_item">
                        <input type="hidden" name="decor_item_id" value="<?= (int)$item['id'] ?>">
                        <input type="number" name="quantity" value="1" min="1" max="<?= (int)$item['available_qty'] ?>" class="decor-add-qty" aria-label="Quantity">
                        <button class="btn btn-sm btn-primary" title="Reserve">+</button>
                    </form>
                </div>
                <?php endforeach; ?>

                <?php if ($unavailableCount > 0): ?>
                <button type="button" class="decor-show-unavailable" id="decor-toggle-unavailable"
                    data-count="<?= (int)$unavailableCount ?>">
                    Show <?= (int)$unavailableCount ?> unavailable
                </button>
                <div id="decor-unavailable-list" hidden>
                    <?php foreach ($picker as $item):
                        if ((int)$item['available_qty'] > 0) continue;
                    ?>
                    <div class="decor-picker-item is-unavailable" data-name="<?= e(strtolower($item['name'])) ?>" data-available="0">
                        <div class="decor-picker-main">
                            <strong title="<?= e($item['name']) ?>"><?= e($item['name']) ?></strong>
                            <span class="decor-picker-stock is-empty">0</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (empty($picker)): ?>
                    <div class="empty-state decor-picker-empty">
                        <p>No Decor stock yet.</p>
                        <a href="decor-inventory-buy.php" class="btn btn-sm btn-primary">Buy items</a>
                    </div>
                <?php elseif (empty($availablePicker)): ?>
                    <div class="empty-state decor-picker-empty"><p>Nothing available for these dates.</p></div>
                <?php endif; ?>
            </div>
        </aside>

        <section class="decor-main-panel">
            <div class="card decor-proposal-card">
                <div class="decor-panel-head">
                    <h3>Proposal lines</h3>
                    <span class="hint"><?= count($lines) ?> line<?= count($lines) === 1 ? '' : 's' ?></span>
                </div>

                <?php if (empty($lines)): ?>
                    <div class="empty-state decor-proposal-empty">
                        <p>Reserve stock on the left, or add a custom / labor line above.</p>
                    </div>
                <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table decor-proposal-table" id="decor-proposal-lines">
                        <thead>
                            <tr>
                                <th class="col-item">Item</th>
                                <th class="col-num">Qty</th>
                                <th class="col-num">Cost</th>
                                <th class="col-num">Mk%</th>
                                <th class="col-num">Rate</th>
                                <th class="col-num">Amt</th>
                                <th class="col-status">Status</th>
                                <th class="col-actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lines as $line):
                            $amount = (float)$line['quantity'] * (float)$line['unit_price'];
                            $lid = (int)$line['id'];
                            $isCustom = in_array($line['line_type'], ['custom', 'labor'], true);
                            $statusLabel = $line['reservation_id']
                                ? ucfirst(str_replace('_', ' ', (string)$line['reservation_status']))
                                : ucfirst((string)$line['line_type']);
                            $qtyDisplay = rtrim(rtrim(number_format((float)$line['quantity'], 2, '.', ''), '0'), '.');
                        ?>
                        <tr class="decor-line-row" data-line-id="<?= $lid ?>">
                            <td class="decor-line-view col-item">
                                <strong class="decor-line-name" title="<?= e($line['label']) ?>"><?= e($line['label']) ?></strong>
                                <?php if ($line['description']): ?>
                                    <div class="hint"><?= e($line['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="decor-line-view col-num"><?= e($qtyDisplay) ?></td>
                            <td class="decor-line-view col-num"><?= e(formatMoney($line['unit_cost'])) ?></td>
                            <td class="decor-line-view col-num"><?= e(number_format((float)$line['markup_percent'], 1)) ?></td>
                            <td class="decor-line-view col-num"><?= e(formatMoney($line['unit_price'])) ?></td>
                            <td class="decor-line-view col-num"><strong><?= e(formatMoney($amount)) ?></strong></td>
                            <td class="decor-line-view col-status">
                                <span class="badge badge-<?= $line['reservation_status'] === 'checked_out' ? 'sent' : ($line['reservation_status'] === 'checked_in' ? 'approved' : 'draft') ?>">
                                    <?= e($statusLabel) ?>
                                </span>
                            </td>
                            <td class="decor-line-view col-actions">
                                <div class="action-btns">
                                    <button type="button" class="btn btn-sm btn-secondary decor-line-edit-btn">Edit</button>
                                    <button type="submit" form="remove-<?= $lid ?>" class="btn btn-sm btn-danger"
                                        onclick="return confirm('Remove this line?')" title="Remove">×</button>
                                </div>
                            </td>

                            <td class="decor-line-edit-cell" colspan="8" hidden>
                                <form method="post" class="decor-line-edit-form" id="line-<?= $lid ?>">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="update_line">
                                    <input type="hidden" name="line_id" value="<?= $lid ?>">
                                    <div class="decor-line-edit-grid">
                                        <label class="decor-edit-field">
                                            <span>Item</span>
                                            <input name="label" value="<?= e($line['label']) ?>" class="line-label" required>
                                        </label>
                                        <label class="decor-edit-field">
                                            <span>Qty</span>
                                            <input type="number" name="quantity" value="<?= e((string)$line['quantity']) ?>" min="0.01" step="0.01" class="line-qty" required>
                                        </label>
                                        <label class="decor-edit-field">
                                            <span>Cost</span>
                                            <?php if ($isCustom): ?>
                                                <input type="number" name="unit_cost" value="<?= e(number_format((float)$line['unit_cost'], 2, '.', '')) ?>" min="0" step="0.01">
                                            <?php else: ?>
                                                <input type="text" value="<?= e(formatMoney($line['unit_cost'])) ?>" disabled>
                                            <?php endif; ?>
                                        </label>
                                        <label class="decor-edit-field">
                                            <span>Mk%</span>
                                            <input type="number" name="markup_percent" value="<?= e(number_format((float)$line['markup_percent'], 2, '.', '')) ?>" min="0" step="0.01">
                                        </label>
                                        <label class="decor-edit-field">
                                            <span>Rate</span>
                                            <input type="number" name="unit_price" value="<?= e(number_format((float)$line['unit_price'], 2, '.', '')) ?>" min="0" step="0.01" class="line-price" required>
                                        </label>
                                        <div class="action-btns decor-edit-actions">
                                            <button class="btn btn-sm btn-primary">Save</button>
                                            <button type="button" class="btn btn-sm btn-secondary decor-line-cancel">Cancel</button>
                                        </div>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php foreach ($lines as $line): $lid = (int)$line['id']; ?>
                <form method="post" id="remove-<?= $lid ?>" hidden>
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="remove_line">
                    <input type="hidden" name="line_id" value="<?= $lid ?>">
                </form>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <details class="card decor-publish-card">
                <summary>
                    <span>Publish &amp; settings</span>
                    <strong><?= e(formatMoney($totals['total'])) ?></strong>
                </summary>
                <form method="post" class="decor-publish-form">
                    <?= csrfField() ?>
                    <div class="decor-publish-grid">
                        <div class="form-group">
                            <label>Title</label>
                            <input name="title" value="<?= e($proposal['title']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Discount</label>
                            <div class="flex">
                                <select name="discount_type">
                                    <option value="percent" <?= $proposal['discount_type'] === 'percent' ? 'selected' : '' ?>>%</option>
                                    <option value="flat" <?= $proposal['discount_type'] === 'flat' ? 'selected' : '' ?>>Flat</option>
                                </select>
                                <input type="number" step="0.01" min="0" name="discount_value" value="<?= e((string)$proposal['discount_value']) ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Tax %</label>
                            <input type="number" step="0.01" min="0" name="tax_percent" value="<?= e((string)$proposal['tax_percent']) ?>">
                        </div>
                        <div class="form-group decor-publish-notes">
                            <label>Notes</label>
                            <input name="notes" value="<?= e((string)($proposal['notes'] ?? '')) ?>" placeholder="Optional note on estimate">
                        </div>
                    </div>
                    <div class="decor-publish-footer">
                        <div class="hint">
                            Subtotal <?= e(formatMoney($totals['subtotal'])) ?>
                            · Discount <?= e(formatMoney($totals['discount_amount'])) ?>
                            · Tax <?= e(formatMoney($totals['tax_amount'])) ?>
                            · Costs stay private
                        </div>
                        <div class="flex">
                            <button type="submit" name="action" value="update_header" class="btn btn-secondary btn-sm">Save</button>
                            <button type="submit" name="action" value="publish" class="btn btn-primary btn-sm"
                                onclick="return confirm('Publish to the main estimate? Costs remain private.')">
                                Publish estimate
                            </button>
                        </div>
                    </div>
                </form>
            </details>

            <?php if (!empty($reservations)): ?>
            <details class="card decor-reservations-card">
                <summary>Check-out / in (<?= count($reservations) ?>)</summary>
                <div class="table-wrap">
                    <table class="data-table decor-reservations-table">
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                        <?php foreach ($reservations as $res): ?>
                        <tr>
                            <td><strong><?= e($res['item_name']) ?></strong></td>
                            <td><?= (int)$res['quantity'] ?></td>
                            <td><span class="badge badge-draft"><?= e(ucfirst(str_replace('_', ' ', $res['status']))) ?></span></td>
                            <td>
                                <div class="action-btns">
                                    <?php if ($res['status'] === 'reserved'): ?>
                                        <form method="post">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="reservation_status">
                                            <input type="hidden" name="reservation_id" value="<?= (int)$res['id'] ?>">
                                            <input type="hidden" name="status" value="checked_out">
                                            <button class="btn btn-sm btn-primary">Out</button>
                                        </form>
                                        <form method="post" onsubmit="return confirm('Cancel this reservation?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="reservation_status">
                                            <input type="hidden" name="reservation_id" value="<?= (int)$res['id'] ?>">
                                            <input type="hidden" name="status" value="cancelled">
                                            <button class="btn btn-sm btn-danger">×</button>
                                        </form>
                                    <?php elseif ($res['status'] === 'checked_out'): ?>
                                        <form method="post">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="reservation_status">
                                            <input type="hidden" name="reservation_id" value="<?= (int)$res['id'] ?>">
                                            <input type="hidden" name="status" value="checked_in">
                                            <button class="btn btn-sm btn-primary">In</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </details>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
