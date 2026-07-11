<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
$partner = $id ? queryOne('SELECT * FROM partners WHERE id=?', [$id]) : null;
if ($id && !$partner) { flash('error', 'Partner not found.'); redirect('partners.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [trim($_POST['name']), $_POST['phone'] ?? null, $_POST['email'] ?? null, (float)($_POST['default_split_percent'] ?? 0), $_POST['notes'] ?? null];
    if ($id) {
        execute('UPDATE partners SET name=?, phone=?, email=?, default_split_percent=?, notes=?, updated_at=NOW() WHERE id=?', array_merge($data, [$id]));
        flash('success', 'Partner updated.');
        redirect('partners.php');
    } else {
        execute('INSERT INTO partners (name, phone, email, default_split_percent, notes) VALUES (?,?,?,?,?)', $data);
        flash('success', 'Partner added.');
        redirect('partners.php');
    }
}

$currentPage = 'partners';
$pageTitle = $id ? 'Edit Partner' : 'New Partner';
require_once __DIR__ . '/includes/header.php';
$d = $partner ?: ['name'=>'','phone'=>'','email'=>'','default_split_percent'=>0,'notes'=>''];
?>

<div class="page-header">
    <h1><?= $id ? 'Edit' : 'Add' ?> Partner</h1>
    <a href="partners.php" class="btn btn-secondary">← Back</a>
</div>

<div class="card" style="max-width:560px">
    <form method="post">
        <div class="form-group"><label>Partner Name *</label><input name="name" value="<?= e($d['name']) ?>" placeholder="Business or individual name" required></div>
        <div class="form-row">
            <div class="form-group"><label>Phone</label><input name="phone" value="<?= e($d['phone']) ?>" placeholder="+91 98765 43210"></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= e($d['email']) ?>" placeholder="partner@email.com"></div>
        </div>
        <div class="form-group">
            <label>Default Profit Split %</label>
            <input type="number" step="0.01" name="default_split_percent" value="<?= e($d['default_split_percent']) ?>" placeholder="e.g. 30">
            <p class="hint">Percentage of profit allocated to this partner by default</p>
        </div>
        <div class="form-group"><label>Notes</label><textarea name="notes" placeholder="Services provided, payment terms, etc."><?= e($d['notes']) ?></textarea></div>
        <div class="flex">
            <button type="submit" class="btn btn-primary">Save Partner</button>
            <a href="partners.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
