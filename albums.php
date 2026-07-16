<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/album-functions.php';
requireAuth();
ensureAlbumsSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { flash('error', 'Please give the album a name.'); redirect('albums.php'); }
        execute(
            'INSERT INTO albums (name, customer_id, event_id, event_type, event_date, is_template) VALUES (?,?,?,?,?,?)',
            [
                $name,
                ($_POST['customer_id'] ?? '') !== '' ? (int)$_POST['customer_id'] : null,
                ($_POST['event_id'] ?? '') !== '' ? (int)$_POST['event_id'] : null,
                trim($_POST['event_type'] ?? '') ?: null,
                trim($_POST['event_date'] ?? '') ?: null,
                isset($_POST['is_template']) ? 1 : 0,
            ]
        );
        flash('success', 'Album created — add your photos.');
        redirect('album-view.php?id=' . (int)lastId());
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'archive')  { execute('UPDATE albums SET status="archived" WHERE id=?', [$id]); flash('success', 'Album archived.'); }
    if ($action === 'restore')  { execute('UPDATE albums SET status="active" WHERE id=?', [$id]); flash('success', 'Album restored.'); }
    if ($action === 'delete') {
        foreach (albumPhotos($id) as $p) albumDeleteFiles($p);
        $dir = albumUploadDir($id);
        if (is_dir($dir)) { array_map('unlink', glob("$dir/*") ?: []); @rmdir($dir); }
        execute('DELETE FROM albums WHERE id=?', [$id]);
        flash('success', 'Album and its photos were deleted.');
    }
    redirect('albums.php' . (($_POST['view'] ?? '') ? '?view=' . urlencode($_POST['view']) : ''));
}

$view = $_GET['view'] ?? 'active';
$q    = trim($_GET['q'] ?? '');
$preselectCustomer = (int)($_GET['customer_id'] ?? 0);
$filters = ['q' => $q];
if ($view === 'templates')    { $filters['is_template'] = 1; $filters['status'] = 'active'; }
elseif ($view === 'archived') { $filters['status'] = 'archived'; }
else                          { $filters['status'] = 'active'; $filters['is_template'] = 0; }
$albums = albumsAll($filters);

$customers = query('SELECT id, name FROM customers WHERE deleted_at IS NULL ORDER BY name');
$events    = query('SELECT id, title FROM events WHERE deleted_at IS NULL ORDER BY event_date DESC');

$currentPage = 'albums';
$pageTitle = 'Albums';
require_once __DIR__ . '/includes/header.php';
?>
<div class="page-header">
    <div>
        <h1>Albums</h1>
        <p class="subtitle">Photo albums to showcase to customers during calls</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('newAlbum').classList.toggle('show')">+ New Album</button>
</div>

<div class="card album-newbox <?= $preselectCustomer ? 'show' : '' ?>" id="newAlbum">
    <h3>New album</h3>
    <form method="post">
        <input type="hidden" name="action" value="create">
        <div class="form-row">
            <div class="form-group"><label>Album name *</label>
                <input name="name" placeholder="e.g. Sharma Wedding — Mandap Décor" required></div>
            <div class="form-group"><label>Event type</label>
                <select name="event_type"><option value="">—</option>
                    <?php foreach (getCeremonyTypes() as $ct): ?><option><?= e($ct) ?></option><?php endforeach; ?>
                </select></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Customer (optional)</label>
                <select name="customer_id"><option value="">—</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $preselectCustomer === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-group"><label>Event (optional)</label>
                <select name="event_id"><option value="">—</option>
                    <?php foreach ($events as $ev): ?><option value="<?= $ev['id'] ?>"><?= e($ev['title']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="form-group"><label>Event date</label><input type="date" name="event_date"></div>
        </div>
        <label class="checkbox-inline"><input type="checkbox" name="is_template"> Save as a reusable reference template (not tied to a customer)</label>
        <div style="margin-top:.75rem"><button class="btn btn-primary">Create album</button></div>
    </form>
</div>

<div class="tabs">
    <a href="?view=active" class="<?= $view === 'active' ? 'active' : '' ?>">Active</a>
    <a href="?view=templates" class="<?= $view === 'templates' ? 'active' : '' ?>">Reference templates</a>
    <a href="?view=archived" class="<?= $view === 'archived' ? 'active' : '' ?>">Archived</a>
    <form method="get" class="album-search">
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <input name="q" value="<?= e($q) ?>" placeholder="Search name, customer, event…">
    </form>
</div>

<?php if (empty($albums)): ?>
    <div class="card"><div class="empty-state"><div class="icon">📸</div>
        <h3><?= $q !== '' ? 'No albums match your search' : 'No albums yet' ?></h3>
        <p>Create an album to start collecting photos to show your customers.</p>
    </div></div>
<?php else: ?>
    <div class="album-grid">
        <?php foreach ($albums as $a): $cover = albumCoverPath($a); ?>
        <div class="album-card">
            <a class="album-cover" href="album-view.php?id=<?= $a['id'] ?>">
                <?php if ($cover): ?><img src="<?= e(imgUrl($cover)) ?>" alt="" loading="lazy">
                <?php else: ?><span class="album-empty">No photos yet</span><?php endif; ?>
                <?php if ($a['is_template']): ?><span class="album-flag tpl">Template</span><?php endif; ?>
                <?php if ($a['design_approved']): ?><span class="album-flag ok">✓ Approved</span><?php endif; ?>
            </a>
            <div class="album-body">
                <a class="album-name" href="album-view.php?id=<?= $a['id'] ?>"><?= e($a['name']) ?></a>
                <div class="text-muted album-sub">
                    <?= $a['customer_name'] ? e($a['customer_name']) . ' · ' : '' ?>
                    <?= $a['event_type'] ? e($a['event_type']) : ($a['is_template'] ? 'Reference' : 'No event type') ?>
                    <?= $a['event_date'] ? ' · ' . formatDate($a['event_date']) : '' ?>
                </div>
                <div class="album-meta">
                    <span class="badge badge-draft"><?= (int)$a['photo_count'] ?> photo<?= $a['photo_count'] == 1 ? '' : 's' ?></span>
                    <?php if ($a['final_count']): ?><span class="badge badge-approved"><?= (int)$a['final_count'] ?> final</span><?php endif; ?>
                    <?php if ($a['share_token']): ?><span class="badge badge-sent">Shared</span><?php endif; ?>
                </div>
                <div class="album-actions">
                    <a class="btn btn-sm btn-primary" href="album-view.php?id=<?= $a['id'] ?>">Open</a>
                    <?php if ($view === 'archived'): ?>
                        <form method="post" style="display:inline"><input type="hidden" name="action" value="restore"><input type="hidden" name="id" value="<?= $a['id'] ?>"><input type="hidden" name="view" value="<?= e($view) ?>"><button class="btn btn-sm btn-secondary">Restore</button></form>
                    <?php else: ?>
                        <form method="post" style="display:inline" onsubmit="return confirm('Archive this album?')"><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?= $a['id'] ?>"><input type="hidden" name="view" value="<?= e($view) ?>"><button class="btn btn-sm btn-secondary">Archive</button></form>
                    <?php endif; ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('Permanently delete this <?= $a['is_template'] ? 'template' : 'album' ?> and all its photos? This cannot be undone.')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                        <input type="hidden" name="view" value="<?= e($view) ?>">
                        <button class="btn btn-sm btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
