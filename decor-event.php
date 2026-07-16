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

$currentPage = 'decor-events';
$pageTitle = 'Decor — ' . $event['title'];
$loadDecorInventory = true;
$loadDecorProposals = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e($event['title']) ?></h1>
        <p class="subtitle">
            <?= e($event['customer_name']) ?>
            · <?= e(formatDate($event['event_date'])) ?>
            <?php if ($event['end_date'] && $event['end_date'] !== $event['event_date']): ?>
                — <?= e(formatDate($event['end_date'])) ?>
            <?php endif; ?>
            · Reservation window <?= e($rangeStart) ?> → <?= e($rangeEnd) ?>
        </p>
    </div>
    <div class="flex">
        <a href="decor-events.php" class="btn btn-secondary">All events</a>
        <?php if (!empty($proposal['estimate_id'])): ?>
            <a href="estimate-form.php?id=<?= (int)$proposal['estimate_id'] ?>" class="btn btn-secondary">Open published estimate</a>
        <?php endif; ?>
    </div>
</div>

<div class="decor-summary-grid">
    <div class="card decor-stat">
        <div class="decor-stat-label">Proposed total</div>
        <div class="decor-stat-value" id="decor-prop-total"><?= e(formatMoney($totals['total'])) ?></div>
    </div>
    <div class="card decor-stat">
        <div class="decor-stat-label">Private cost</div>
        <div class="decor-stat-value" id="decor-prop-cost"><?= e(formatMoney($totals['private_cost_total'])) ?></div>
    </div>
    <div class="card decor-stat">
        <div class="decor-stat-label">Projected margin</div>
        <div class="decor-stat-value" id="decor-prop-margin"><?= e(formatMoney($totals['margin'])) ?></div>
    </div>
    <div class="card decor-stat">
        <div class="decor-stat-label">Status</div>
        <div class="decor-stat-value" style="font-size:1rem">
            <span class="badge badge-<?= $proposal['status'] === 'published' ? 'approved' : 'sent' ?>">
                <?= e(ucfirst($proposal['status'])) ?>
            </span>
        </div>
        <?php if ($proposal['published_at']): ?>
            <div class="hint">Published <?= e(formatDate($proposal['published_at'])) ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="decor-event-layout">
    <div class="card">
        <h3>Add from Decor inventory</h3>
        <p class="hint">Available = owned − overlapping reservations for these event dates.</p>
        <input type="search" id="decor-picker-search" class="mb-1" placeholder="Search stock…">
        <div class="decor-picker-list" id="decor-picker-list">
            <?php foreach ($picker as $item): ?>
            <div class="decor-picker-item" data-name="<?= e(strtolower($item['name'])) ?>">
                <div>
                    <strong><?= e($item['name']) ?></strong>
                    <div class="hint">
                        Owned <?= (int)$item['quantity_on_hand'] ?>
                        · Reserved <?= (int)$item['reserved_qty'] ?>
                        · <strong>Available <?= (int)$item['available_qty'] ?></strong>
                    </div>
                    <div class="hint">
                        Cost <?= e(formatMoney($item['unit_price'])) ?>
                        · Suggest <?= e(formatMoney($item['suggested_rate'])) ?>
                        (<?= e(number_format((float)$item['default_markup_percent'], 1)) ?>%)
                    </div>
                </div>
                <?php if ((int)$item['available_qty'] > 0): ?>
                <form method="post" class="decor-add-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_item">
                    <input type="hidden" name="decor_item_id" value="<?= (int)$item['id'] ?>">
                    <input type="number" name="quantity" value="1" min="1" max="<?= (int)$item['available_qty'] ?>" class="decor-add-qty">
                    <button class="btn btn-sm btn-primary">Reserve</button>
                </form>
                <?php else: ?>
                    <span class="badge badge-draft">Unavailable</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (empty($picker)): ?>
                <div class="empty-state"><p>No Decor stock with owned quantity. <a href="decor-inventory-form.php">Add a purchase</a>.</p></div>
            <?php endif; ?>
        </div>

        <hr>
        <h3>Custom / labor line</h3>
        <form method="post" class="grid-2">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_custom">
            <div class="form-group">
                <label>Label</label>
                <input name="label" required placeholder="e.g. Setup labor">
            </div>
            <div class="form-group">
                <label>Type</label>
                <select name="line_type">
                    <option value="custom">Custom</option>
                    <option value="labor">Labor</option>
                </select>
            </div>
            <div class="form-group">
                <label>Qty</label>
                <input type="number" name="quantity" value="1" min="0.01" step="0.01" required>
            </div>
            <div class="form-group">
                <label>Private cost</label>
                <input type="number" name="unit_cost" value="0" min="0" step="0.01">
            </div>
            <div class="form-group">
                <label>Proposed rate</label>
                <input type="number" name="unit_price" value="0" min="0" step="0.01" required>
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button class="btn btn-secondary">Add line</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>Decoration proposal lines</h3>
        <?php if (empty($lines)): ?>
            <div class="empty-state"><p>Reserve inventory or add custom lines to build the Decor estimate.</p></div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table" id="decor-proposal-lines">
                <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Cost</th>
                    <th>Markup%</th>
                    <th>Rate</th>
                    <th>Amount</th>
                    <th>Reservation</th>
                    <th></th>
                </tr>
                <?php foreach ($lines as $line):
                    $amount = (float)$line['quantity'] * (float)$line['unit_price'];
                    $lid = (int)$line['id'];
                ?>
                <tr>
                    <td>
                        <input form="line-<?= $lid ?>" name="label" value="<?= e($line['label']) ?>" class="line-label">
                        <?php if ($line['description']): ?>
                            <div class="hint"><?= e($line['description']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><input form="line-<?= $lid ?>" type="number" name="quantity" value="<?= e((string)$line['quantity']) ?>" min="0.01" step="0.01" class="line-qty"></td>
                    <td>
                        <?php if (in_array($line['line_type'], ['custom', 'labor'], true)): ?>
                            <input form="line-<?= $lid ?>" type="number" name="unit_cost" value="<?= e(number_format((float)$line['unit_cost'], 2, '.', '')) ?>" min="0" step="0.01">
                        <?php else: ?>
                            <?= e(formatMoney($line['unit_cost'])) ?>
                        <?php endif; ?>
                    </td>
                    <td><input form="line-<?= $lid ?>" type="number" name="markup_percent" value="<?= e(number_format((float)$line['markup_percent'], 2, '.', '')) ?>" min="0" step="0.01"></td>
                    <td><input form="line-<?= $lid ?>" type="number" name="unit_price" value="<?= e(number_format((float)$line['unit_price'], 2, '.', '')) ?>" min="0" step="0.01" class="line-price"></td>
                    <td class="text-right"><?= e(formatMoney($amount)) ?></td>
                    <td>
                        <?php if ($line['reservation_id']): ?>
                            <span class="badge badge-<?= $line['reservation_status'] === 'checked_out' ? 'sent' : ($line['reservation_status'] === 'checked_in' ? 'approved' : 'draft') ?>">
                                <?= e(ucfirst(str_replace('_', ' ', (string)$line['reservation_status']))) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted"><?= e($line['line_type']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-btns">
                            <button form="line-<?= $lid ?>" class="btn btn-sm btn-primary">Save</button>
                            <button type="submit" form="remove-<?= $lid ?>" class="btn btn-sm btn-danger"
                                onclick="return confirm('Remove this line from the event?')">×</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php foreach ($lines as $line): $lid = (int)$line['id']; ?>
        <form method="post" id="line-<?= $lid ?>" style="display:none">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_line">
            <input type="hidden" name="line_id" value="<?= $lid ?>">
        </form>
        <form method="post" id="remove-<?= $lid ?>" style="display:none">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="remove_line">
            <input type="hidden" name="line_id" value="<?= $lid ?>">
        </form>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Publish settings</h3>
        <form method="post">
            <?= csrfField() ?>
            <div class="form-group">
                <label>Proposal title</label>
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
            <div class="form-group">
                <label>Notes (shared on estimate)</label>
                <textarea name="notes" rows="3"><?= e((string)($proposal['notes'] ?? '')) ?></textarea>
            </div>
            <p>Subtotal: <strong><?= e(formatMoney($totals['subtotal'])) ?></strong></p>
            <p>Discount: <strong><?= e(formatMoney($totals['discount_amount'])) ?></strong></p>
            <p>Tax: <strong><?= e(formatMoney($totals['tax_amount'])) ?></strong></p>
            <p style="font-size:1.15rem">Total: <strong><?= e(formatMoney($totals['total'])) ?></strong></p>
            <p class="hint">Publishing writes customer-facing rates only. Private cost/markup stay in Decor.</p>
            <div class="flex" style="flex-direction:column;gap:.5rem">
                <button type="submit" name="action" value="update_header" class="btn btn-secondary">Save settings</button>
                <button type="submit" name="action" value="publish" class="btn btn-primary"
                    onclick="return confirm('Publish this Decor proposal into the main estimate? Costs will remain private.')">
                    Publish to main estimate
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <h3>Reservations / check-out</h3>
    <?php if (empty($reservations)): ?>
        <p class="text-muted">No reservations for this event yet.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Dates</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($reservations as $res): ?>
            <tr>
                <td>
                    <strong><?= e($res['item_name']) ?></strong>
                    <div class="hint">Cost <?= e(formatMoney($res['unit_price'])) ?></div>
                </td>
                <td><?= (int)$res['quantity'] ?></td>
                <td><?= e(formatDate($res['start_date'])) ?> — <?= e(formatDate($res['end_date'])) ?></td>
                <td><span class="badge badge-draft"><?= e(ucfirst(str_replace('_', ' ', $res['status']))) ?></span></td>
                <td>
                    <div class="action-btns">
                        <?php if ($res['status'] === 'reserved'): ?>
                            <form method="post" style="display:inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="reservation_status">
                                <input type="hidden" name="reservation_id" value="<?= (int)$res['id'] ?>">
                                <input type="hidden" name="status" value="checked_out">
                                <button class="btn btn-sm btn-primary">Check out</button>
                            </form>
                            <form method="post" style="display:inline" onsubmit="return confirm('Cancel this reservation?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="reservation_status">
                                <input type="hidden" name="reservation_id" value="<?= (int)$res['id'] ?>">
                                <input type="hidden" name="status" value="cancelled">
                                <button class="btn btn-sm btn-danger">Cancel</button>
                            </form>
                        <?php elseif ($res['status'] === 'checked_out'): ?>
                            <form method="post" style="display:inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="reservation_status">
                                <input type="hidden" name="reservation_id" value="<?= (int)$res['id'] ?>">
                                <input type="hidden" name="status" value="checked_in">
                                <button class="btn btn-sm btn-primary">Check in</button>
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
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
