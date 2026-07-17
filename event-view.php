<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/comm-functions.php';
requireAuth();
ensureCommsSchema();

$id = (int)($_GET['id'] ?? 0);
$tab = $_GET['tab'] ?? 'overview';

$event = queryOne('SELECT e.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email FROM events e JOIN customers c ON c.id=e.customer_id WHERE e.id=? AND e.deleted_at IS NULL', [$id]);
if (!$event) { flash('error', 'Event not found.'); redirect('events.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'expenses') {
        $partners = $_POST['exp_partner_id'] ?? [];
        $amounts = $_POST['exp_amount'] ?? [];
        $count = 0;
        for ($i = 0; $i < count($partners); $i++) {
            if ($partners[$i] && $amounts[$i]) {
                execute('INSERT INTO partner_expenses (partner_id,event_id,category,description,amount,expense_date) VALUES (?,?,?,?,?,?)',
                    [$partners[$i], $id, $_POST['exp_category'][$i] ?? null, $_POST['exp_desc'][$i] ?? null, $amounts[$i], $_POST['exp_date'][$i] ?? date('Y-m-d')]);
                $count++;
            }
        }
        flash('success', "$count expense(s) recorded.");
        redirect('event-view.php?id=' . $id . '&tab=expenses');
    }
    if ($action === 'delete_expense' && !empty($_POST['expense_id'])) {
        execute('DELETE FROM partner_expenses WHERE id=? AND event_id=?', [$_POST['expense_id'], $id]);
        flash('success', 'Expense deleted.');
        redirect('event-view.php?id=' . $id . '&tab=expenses');
    }
    if ($action === 'delete_image' && !empty($_POST['attachment_id'])) {
        deleteAttachment((int)$_POST['attachment_id']);
        flash('success', 'Image removed.');
        redirect('event-view.php?id=' . $id . '&tab=images');
    }
    if (!empty($_FILES['image']['name'])) {
        uploadImage('event', $id, $_FILES['image']);
        flash('success', 'Photo uploaded.');
        redirect('event-view.php?id=' . $id . '&tab=images');
    }
    if ($action === 'create_comm_session') {
        $type = $_POST['session_type'] ?? 'initial_meeting';
        if (!in_array($type, COMM_SESSION_TYPES, true)) $type = 'initial_meeting';
        $title = trim($_POST['title'] ?? '') ?: (commSessionTypeLabel($type) . ' — ' . ($event['customer_name'] ?? 'Customer'));
        $admin = currentAdmin();
        $sid = commCreateSession($event, $type, $title, $admin ? (int)$admin['id'] : null);
        flash('success', 'Communication session started.');
        redirect('comm-session.php?id=' . $sid);
    }
    if ($action === 'delete_comm_session' && !empty($_POST['session_id'])) {
        $sid = (int)$_POST['session_id'];
        $s = queryOne('SELECT id FROM comm_sessions WHERE id=? AND event_id=?', [$sid, $id]);
        if ($s) {
            foreach (commRecordings($sid) as $r) {
                global $config;
                $path = rtrim($config['upload_dir'], '/') . '/' . $r['file_path'];
                if (is_file($path)) @unlink($path);
            }
            execute('DELETE FROM comm_sessions WHERE id=?', [$sid]);
            flash('success', 'Session deleted.');
        }
        redirect('event-view.php?id=' . $id . '&tab=comms');
    }
}

$estimates = query('SELECT * FROM estimates WHERE event_id=? ORDER BY created_at DESC', [$id]);
$expenses = query('SELECT pe.*, p.name as partner_name FROM partner_expenses pe JOIN partners p ON p.id=pe.partner_id WHERE pe.event_id=? ORDER BY pe.expense_date DESC', [$id]);
$images = getImages('event', $id);
$contracts = query('SELECT * FROM contracts WHERE event_id=?', [$id]);
$pnl = getEventProfitLoss($id);
$partners = query('SELECT * FROM partners ORDER BY name');
$primaryImg = getPrimaryImage('event', $id);
$commSessions = ($tab === 'comms' || $tab === 'overview') ? commSessionsForEvent($id) : [];
$commDecisions = ($tab === 'comms' || $tab === 'overview') ? commDecisionsForEvent($id) : [];
$categoryCosts = $pnl['categories'] ?? [];
$topCategories = array_slice($categoryCosts, 0, 4);

$currentPage = 'events';
$pageTitle = $event['title'];
$loadEventHub = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e($event['title']) ?></h1>
        <p class="subtitle"><?= e($event['customer_name']) ?> · <?= formatDate($event['event_date']) ?></p>
    </div>
    <div class="flex">
        <a href="estimate-form.php?event_id=<?= $id ?>&customer_id=<?= $event['customer_id'] ?>" class="btn btn-primary">+ Estimate</a>
        <a href="contract-form.php" class="btn btn-secondary">+ Contract</a>
        <a href="event-form.php?id=<?= $id ?>" class="btn btn-secondary">Edit</a>
        <form method="post" action="events.php" onsubmit="return confirm('Delete this event?')">
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $id ?>">
            <button class="btn btn-danger">Delete</button>
        </form>
    </div>
</div>

<?php if ($primaryImg): ?>
<div style="margin-bottom:1.25rem;border-radius:12px;overflow:hidden;height:200px">
    <img src="<?= e(imgUrl($primaryImg)) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
</div>
<?php endif; ?>

<div class="tabs">
    <?php foreach (['overview'=>'Overview','comms'=>'Communications','estimates'=>'Estimates','expenses'=>'Expenses','images'=>'Photos','pnl'=>'Costs'] as $k=>$label): ?>
    <a href="?id=<?= $id ?>&tab=<?= $k ?>" class="<?= $tab===$k?'active':'' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'overview'): ?>
<div class="grid-2">
    <div class="card">
        <h3>Event Details</h3>
        <p><strong>Customer:</strong> <a href="customer-view.php?id=<?= (int)$event['customer_id'] ?>"><?= e($event['customer_name']) ?></a></p>
        <p><strong>Phone:</strong> <?= e($event['customer_phone'] ?: '—') ?></p>
        <p><strong>Type:</strong> <?= e($event['ceremony_type'] ?: '—') ?></p>
        <p><strong>Date:</strong> <?= formatDate($event['event_date']) ?><?php if ($event['end_date']): ?> — <?= formatDate($event['end_date']) ?><?php endif; ?></p>
        <p><strong>Venue:</strong> <?= e($event['venue'] ?: '—') ?></p>
        <p><strong>Status:</strong> <span class="badge badge-<?= e($event['status']) ?>"><?= e(ucfirst($event['status'])) ?></span></p>
    </div>
    <div class="card">
        <h3>Quick Stats</h3>
        <div class="stats" style="margin:0">
            <div class="stat success"><div class="label">Revenue</div><div class="value" style="font-size:1.25rem"><?= formatMoney($pnl['revenue']) ?></div></div>
            <div class="stat danger"><div class="label">Partner spend</div><div class="value" style="font-size:1.25rem"><?= formatMoney($pnl['expenses']) ?></div></div>
        </div>
        <?php if ($topCategories): ?>
        <div class="event-category-teaser mt-1">
            <div class="flex" style="justify-content:space-between;align-items:baseline">
                <strong style="font-size:.82rem">Top categories</strong>
                <a href="?id=<?= $id ?>&tab=pnl" class="btn btn-sm btn-secondary">Full costs</a>
            </div>
            <?php foreach ($topCategories as $i => $cat): ?>
            <div class="event-category-chip">
                <div class="label">
                    <span class="dot" style="opacity:<?= max(0.35, 1 - ($i * 0.18)) ?>"></span>
                    <span><?= e($cat['name']) ?></span>
                </div>
                <strong><?= formatMoney($cat['total']) ?></strong>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($event['internal_notes']): ?><p class="mt-1"><strong>Notes:</strong> <?= e($event['internal_notes']) ?></p><?php endif; ?>
    </div>
</div>

<?php
$approvedDecisions = array_values(array_filter($commDecisions, fn($d) => $d['status'] === 'approved'));
$latestSessions = array_slice($commSessions, 0, 3);
?>
<?php if ($approvedDecisions || $latestSessions): ?>
<div class="grid-2">
    <?php if ($latestSessions): ?>
    <div class="card">
        <h3>Recent communications</h3>
        <?php foreach ($latestSessions as $cs): ?>
            <p>
                <a href="comm-session.php?id=<?= (int)$cs['id'] ?>"><?= e($cs['title']) ?></a>
                <span class="badge badge-draft"><?= e(commSessionStatusLabel($cs['status'])) ?></span>
                <span class="text-muted"> · <?= e(formatDate($cs['held_at'] ?: $cs['created_at'])) ?></span>
            </p>
            <?php if ($cs['summary_text']): ?><p class="hint"><?= e(mb_substr($cs['summary_text'], 0, 140)) ?><?= mb_strlen($cs['summary_text']) > 140 ? '…' : '' ?></p><?php endif; ?>
        <?php endforeach; ?>
        <a href="?id=<?= $id ?>&tab=comms" class="btn btn-sm btn-secondary">All communications</a>
    </div>
    <?php endif; ?>
    <?php if ($approvedDecisions): ?>
    <div class="card">
        <h3>Approved decisions</h3>
        <ul class="comm-decision-list">
            <?php foreach (array_slice($approvedDecisions, 0, 6) as $d): ?>
            <li>
                <?= e($d['decision_text']) ?>
                <?php if ($d['related_album_id']): ?>
                    · <a href="album-view.php?id=<?= (int)$d['related_album_id'] ?>">Album</a>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($contracts): ?>
<div class="card"><h3>Contracts</h3>
    <?php foreach ($contracts as $ct): ?>
    <p><a href="contract-edit.php?id=<?= $ct['id'] ?>"><?= e($ct['title']) ?></a> <span class="badge badge-<?= e($ct['status']) ?>"><?= e(ucfirst($ct['status'])) ?></span></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php elseif ($tab === 'comms'): ?>
<div class="card">
    <div class="page-header" style="margin-bottom:1rem">
        <div>
            <h3 style="margin:0">Customer communications</h3>
            <p class="subtitle" style="margin:0">Initial discovery → discussions → design options → approval</p>
        </div>
    </div>
    <form method="post" class="comm-start-form">
        <input type="hidden" name="action" value="create_comm_session">
        <div class="form-row">
            <div class="form-group">
                <label>New session type</label>
                <select name="session_type">
                    <?php foreach (COMM_SESSION_TYPES as $t): ?>
                    <option value="<?= $t ?>"><?= e(commSessionTypeLabel($t)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Title (optional)</label>
                <input name="title" placeholder="e.g. First call with Priya">
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button class="btn btn-primary">Start session</button>
            </div>
        </div>
        <p class="hint">Initial meetings load question packs for <?= e($event['ceremony_type'] ?: 'this event type') ?> automatically.</p>
    </form>
</div>

<?php if (empty($commSessions)): ?>
<div class="card"><div class="empty-state"><div class="icon">💬</div>
    <h3>No conversations yet</h3>
    <p>Start an initial meeting to walk through discovery questions for this event.</p>
</div></div>
<?php else: ?>
<div class="comm-timeline">
    <?php foreach ($commSessions as $cs): ?>
    <div class="card comm-session-card">
        <div class="comm-session-head">
            <div>
                <a class="album-name" href="comm-session.php?id=<?= (int)$cs['id'] ?>"><?= e($cs['title']) ?></a>
                <div class="text-muted" style="font-size:.82rem">
                    <?= e(commSessionTypeLabel($cs['session_type'])) ?>
                    · <?= e(formatDate($cs['held_at'] ?: $cs['created_at'])) ?>
                    · <?= (int)$cs['answer_count'] ?> questions
                    · <?= (int)$cs['recording_count'] ?> recording<?= (int)$cs['recording_count'] === 1 ? '' : 's' ?>
                </div>
            </div>
            <div class="flex">
                <span class="badge badge-<?= $cs['status'] === 'summarized' ? 'approved' : 'draft' ?>"><?= e(commSessionStatusLabel($cs['status'])) ?></span>
                <a class="btn btn-sm btn-primary" href="comm-session.php?id=<?= (int)$cs['id'] ?>">Open</a>
                <form method="post" onsubmit="return confirm('Delete this session and its recordings?')">
                    <input type="hidden" name="action" value="delete_comm_session">
                    <input type="hidden" name="session_id" value="<?= (int)$cs['id'] ?>">
                    <button class="btn btn-sm btn-danger">Delete</button>
                </form>
            </div>
        </div>
        <?php if ($cs['summary_text']): ?>
            <p class="hint" style="margin-top:.5rem"><?= e(mb_substr($cs['summary_text'], 0, 220)) ?><?= mb_strlen($cs['summary_text']) > 220 ? '…' : '' ?></p>
        <?php elseif ((int)$cs['answer_count'] > 0 || (int)$cs['recording_count'] > 0): ?>
            <p class="hint" style="margin-top:.5rem"><a href="comm-session.php?id=<?= (int)$cs['id'] ?>">Ready to summarize</a> — answers or recordings are waiting.</p>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($commDecisions): ?>
<div class="card">
    <h3>Event decisions</h3>
    <div class="table-wrap">
        <table class="data-table">
            <tr><th>Decision</th><th>Status</th><th>From session</th><th>Album</th></tr>
            <?php foreach ($commDecisions as $d): ?>
            <tr>
                <td><?= e($d['decision_text']) ?></td>
                <td><span class="badge badge-<?= $d['status'] === 'approved' ? 'approved' : ($d['status'] === 'rejected' ? 'draft' : 'sent') ?>"><?= e(ucfirst($d['status'])) ?></span></td>
                <td><?= $d['session_id'] ? '<a href="comm-session.php?id=' . (int)$d['session_id'] . '">' . e($d['session_title'] ?: '#' . $d['session_id']) . '</a>' : '—' ?></td>
                <td><?= $d['related_album_id'] ? '<a href="album-view.php?id=' . (int)$d['related_album_id'] . '">View</a>' : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php endif; ?>

<?php elseif ($tab === 'estimates'): ?>
<div class="card">
    <?php if (empty($estimates)): ?>
    <div class="empty-state"><div class="icon">📋</div><h3>No estimates</h3><a href="estimate-form.php?event_id=<?= $id ?>&customer_id=<?= $event['customer_id'] ?>" class="btn btn-primary">Create Estimate</a></div>
    <?php else: ?>
    <div class="table-wrap"><table class="data-table"><tr><th>Title</th><th>Status</th><th>Total</th><th>Actions</th></tr>
    <?php foreach ($estimates as $est): ?><tr>
        <td><?= e($est['title']) ?></td><td><span class="badge badge-<?= e($est['status']) ?>"><?= e(ucfirst($est['status'])) ?></span></td>
        <td><?= formatMoney($est['total']) ?></td>
        <td><div class="action-btns">
            <a href="estimate-form.php?id=<?= $est['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
            <a href="contract-create.php?estimate_id=<?= $est['id'] ?>" class="btn btn-sm btn-secondary">→ Contract</a>
        </div></td>
    </tr><?php endforeach; ?></table></div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'expenses'): ?>
<div class="card">
    <h3>Add Partner Expenses</h3>
    <form method="post">
        <input type="hidden" name="action" value="expenses">
        <div id="expense-rows">
            <div class="expense-row form-row mb-1">
                <select name="exp_partner_id[]"><option value="">Partner</option><?php foreach ($partners as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select>
                <input name="exp_category[]" placeholder="Category">
                <input name="exp_desc[]" placeholder="Description">
                <input type="number" step="0.01" name="exp_amount[]" placeholder="Amount">
                <input type="date" name="exp_date[]" value="<?= date('Y-m-d') ?>">
            </div>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="addExpenseRow()">+ Add Row</button>
        <button type="submit" class="btn btn-primary">Save Expenses</button>
    </form>
    <?php if ($expenses): ?>
    <div class="table-wrap mt-1"><table class="data-table"><tr><th>Partner</th><th>Category</th><th>Amount</th><th>Date</th><th></th></tr>
    <?php foreach ($expenses as $ex): ?><tr>
        <td><?= e($ex['partner_name']) ?></td><td><?= e($ex['category']) ?></td><td><?= formatMoney($ex['amount']) ?></td><td><?= formatDate($ex['expense_date']) ?></td>
        <td><form method="post" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="delete_expense"><input type="hidden" name="expense_id" value="<?= $ex['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form></td>
    </tr><?php endforeach; ?></table></div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'images'): ?>
<div class="card">
    <h3>Event Photos</h3>
    <?php $allowDelete = true; $uploadId = 'evt-' . $id; include __DIR__ . '/includes/photo-gallery.php'; ?>
</div>

<?php elseif ($tab === 'pnl'): ?>
<?php
    $maxCategory = !empty($categoryCosts) ? max(array_column($categoryCosts, 'total')) : 0;
?>
<div class="event-cost-strip">
    <div class="is-revenue">
        <span>Revenue</span>
        <strong><?= formatMoney($pnl['revenue']) ?></strong>
    </div>
    <div class="is-expense">
        <span>Partner spend</span>
        <strong><?= formatMoney($pnl['expenses']) ?></strong>
    </div>
    <div class="is-proposal">
        <span>Proposal cost</span>
        <strong><?= formatMoney($pnl['proposal_cost'] ?? 0) ?></strong>
    </div>
    <div>
        <span>Net after partners</span>
        <strong style="color:<?= $pnl['profit'] >= 0 ? '#059669' : '#dc2626' ?>"><?= formatMoney($pnl['profit']) ?></strong>
    </div>
</div>

<div class="card event-category-card">
    <div class="event-category-head">
        <div>
            <h3>Category spend</h3>
            <p class="subtitle" style="margin:0">
                Consolidated by category for this event
                <?php if (!empty($pnl['estimate_title'])): ?>
                    · using <?= e($pnl['estimate_title']) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="event-category-legend">
            <span><i class="swatch-partner"></i>Partner (paid)</span>
            <span><i class="swatch-proposal"></i>Stock / proposal cost</span>
        </div>
    </div>

    <?php if (empty($categoryCosts)): ?>
        <div class="event-category-empty">
            No category costs yet. Add partner expenses or inventory lines on an estimate to see the breakdown.
            <div class="flex" style="justify-content:center;margin-top:.75rem;gap:.5rem">
                <a href="?id=<?= $id ?>&tab=expenses" class="btn btn-sm btn-secondary">Add expenses</a>
                <a href="estimate-form.php?event_id=<?= $id ?>&customer_id=<?= (int)$event['customer_id'] ?>" class="btn btn-sm btn-primary">Open estimate</a>
            </div>
        </div>
    <?php else: ?>
        <div class="event-category-list">
            <?php foreach ($categoryCosts as $cat):
                $width = $maxCategory > 0 ? max(4, ($cat['total'] / $maxCategory) * 100) : 0;
                $partnerShare = $cat['total'] > 0 ? ($cat['partner'] / $cat['total']) * 100 : 0;
                $proposalShare = $cat['total'] > 0 ? ($cat['proposal'] / $cat['total']) * 100 : 0;
            ?>
            <div class="event-category-row">
                <div>
                    <div class="event-category-name"><?= e($cat['name']) ?></div>
                    <span class="event-category-meta"><?= e(number_format((float)$cat['share'], 1)) ?>% of spend</span>
                </div>
                <div class="event-category-track" title="<?= e($cat['name']) ?>">
                    <div class="event-category-fill" style="width:<?= e(number_format($width, 2, '.', '')) ?>%">
                        <?php if ($partnerShare > 0): ?>
                            <span class="seg-partner" style="width:<?= e(number_format($partnerShare, 2, '.', '')) ?>%"></span>
                        <?php endif; ?>
                        <?php if ($proposalShare > 0): ?>
                            <span class="seg-proposal" style="width:<?= e(number_format($proposalShare, 2, '.', '')) ?>%"></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="event-category-amount">
                    <strong><?= formatMoney($cat['total']) ?></strong>
                    <em>
                        <?php if ($cat['partner'] > 0 && $cat['proposal'] > 0): ?>
                            <?= formatMoney($cat['partner']) ?> + <?= formatMoney($cat['proposal']) ?>
                        <?php elseif ($cat['partner'] > 0): ?>
                            Partner
                        <?php else: ?>
                            Proposal
                        <?php endif; ?>
                    </em>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="table-wrap">
            <table class="event-category-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th class="is-num">Partner</th>
                        <th class="is-num">Proposal</th>
                        <th class="is-num">Total</th>
                        <th class="is-num">Share</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($categoryCosts as $cat): ?>
                    <tr>
                        <td><strong><?= e($cat['name']) ?></strong></td>
                        <td class="is-num"><?= $cat['partner'] > 0 ? formatMoney($cat['partner']) : '—' ?></td>
                        <td class="is-num"><?= $cat['proposal'] > 0 ? formatMoney($cat['proposal']) : '—' ?></td>
                        <td class="is-num"><strong><?= formatMoney($cat['total']) ?></strong></td>
                        <td class="is-num"><?= e(number_format((float)$cat['share'], 1)) ?>%</td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td><strong>All categories</strong></td>
                    <td class="is-num"><strong><?= formatMoney($pnl['expenses']) ?></strong></td>
                    <td class="is-num"><strong><?= formatMoney($pnl['proposal_cost'] ?? 0) ?></strong></td>
                    <td class="is-num"><strong><?= formatMoney($pnl['category_total'] ?? 0) ?></strong></td>
                    <td class="is-num">100%</td>
                </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
