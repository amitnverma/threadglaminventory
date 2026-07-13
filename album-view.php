<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/album-functions.php';
requireAuth();
ensureAlbumsSchema();

$id = (int)($_GET['id'] ?? 0);
$album = albumGet($id);
if (!$album) { flash('error', 'Album not found.'); redirect('albums.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $backTab = $_POST['tab'] ?? 'all';
    $back = 'album-view.php?id=' . $id . '&tab=' . urlencode($backTab);

    if ($action === 'save_details') {
        execute(
            'UPDATE albums SET name=?, customer_id=?, event_id=?, event_type=?, event_date=?, description=?, is_template=? WHERE id=?',
            [
                trim($_POST['name'] ?? $album['name']) ?: $album['name'],
                ($_POST['customer_id'] ?? '') !== '' ? (int)$_POST['customer_id'] : null,
                ($_POST['event_id'] ?? '') !== '' ? (int)$_POST['event_id'] : null,
                trim($_POST['event_type'] ?? '') ?: null,
                trim($_POST['event_date'] ?? '') ?: null,
                trim($_POST['description'] ?? '') ?: null,
                isset($_POST['is_template']) ? 1 : 0,
                $id,
            ]
        );
        flash('success', 'Album details saved.');
        redirect('album-view.php?id=' . $id);
    }

    if ($action === 'upload' && !empty($_FILES['photos'])) {
        $ok = 0; $errs = [];
        $files = $_FILES['photos'];
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            $one = [
                'name' => $files['name'][$i], 'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i],
            ];
            $res = albumStoreUpload($id, $one);
            if (isset($res['err'])) { $errs[] = $res['err']; continue; }
            execute(
                'INSERT INTO album_photos (album_id, file_path, thumbnail_path, sort_order) VALUES (?,?,?,?)',
                [$id, $res['file_path'], $res['thumbnail_path'], albumNextOrder($id)]
            );
            $ok++;
        }
        flash($errs && !$ok ? 'error' : 'success', $ok . ' photo' . ($ok == 1 ? '' : 's') . ' added.' . ($errs ? ' ' . implode(' ', $errs) : ''));
        redirect('album-view.php?id=' . $id);
    }

    if ($action === 'photo') {
        $photo = albumPhotoGet((int)($_POST['photo_id'] ?? 0));
        if (!$photo || (int)$photo['album_id'] !== $id) { flash('error', 'Photo not found.'); redirect($back); }
        switch ($_POST['op'] ?? '') {
            case 'status':
                if (in_array($_POST['status'] ?? '', PHOTO_STATES, true)) execute('UPDATE album_photos SET status=? WHERE id=?', [$_POST['status'], $photo['id']]);
                break;
            case 'caption':
                execute('UPDATE album_photos SET caption=? WHERE id=?', [trim($_POST['caption'] ?? ''), $photo['id']]);
                flash('success', 'Caption saved.');
                break;
            case 'cover':
                execute('UPDATE albums SET cover_photo_id=? WHERE id=?', [$photo['id'], $id]);
                flash('success', 'Cover photo set.');
                break;
            case 'delete':
                albumDeletePhoto($photo); flash('success', 'Photo deleted.');
                break;
            case 'copy':
            case 'move':
                $dest = (int)($_POST['dest'] ?? 0);
                if (!albumGet($dest)) { flash('error', 'Choose a destination album.'); break; }
                $done = $_POST['op'] === 'copy' ? albumCopyPhoto($photo, $dest) : albumMovePhoto($photo, $dest);
                flash($done ? 'success' : 'error', $done ? ('Photo ' . ($_POST['op'] === 'copy' ? 'copied' : 'moved') . '.') : 'Could not process the file.');
                break;
        }
        redirect($back);
    }

    if ($action === 'bulk') {
        $ids = array_map('intval', $_POST['ids'] ?? []);
        $op = $_POST['op'] ?? '';
        $dest = (int)($_POST['dest'] ?? 0);
        $n = 0;
        foreach ($ids as $pid) {
            $photo = albumPhotoGet($pid);
            if (!$photo || (int)$photo['album_id'] !== $id) continue;
            if ($op === 'delete') { albumDeletePhoto($photo); $n++; }
            elseif ($op === 'copy' && albumGet($dest)) { $n += albumCopyPhoto($photo, $dest) ? 1 : 0; }
            elseif ($op === 'move' && albumGet($dest)) { $n += albumMovePhoto($photo, $dest) ? 1 : 0; }
            elseif (in_array($op, PHOTO_STATES, true)) { execute('UPDATE album_photos SET status=? WHERE id=?', [$op, $pid]); $n++; }
        }
        flash($n ? 'success' : 'error', $n ? ($n . ' photo' . ($n == 1 ? '' : 's') . ' updated.') : 'No photos were selected.');
        redirect($back);
    }

    if ($action === 'approve') {
        $approved = isset($_POST['design_approved']) ? 1 : 0;
        execute('UPDATE albums SET design_approved=?, approved_at=?, approved_note=? WHERE id=?',
            [$approved, $approved ? date('Y-m-d H:i:s') : null, trim($_POST['approved_note'] ?? '') ?: null, $id]);
        flash('success', $approved ? 'Final design marked as approved by the customer.' : 'Approval removed — open for discussion again.');
        redirect('album-view.php?id=' . $id . '&tab=final');
    }

    if ($action === 'share') {
        $op = $_POST['op'] ?? '';
        $scope = in_array($_POST['scope'] ?? 'all', ['all', 'final'], true) ? $_POST['scope'] : 'all';
        if ($op === 'enable') {
            $token = $album['share_token'] ?: bin2hex(random_bytes(12));
            execute('UPDATE albums SET share_token=?, share_scope=? WHERE id=?', [$token, $scope, $id]);
            flash('success', 'Share link is ready — copy it and send it to your customer.');
        } elseif ($op === 'scope') {
            execute('UPDATE albums SET share_scope=? WHERE id=?', [$scope, $id]);
            flash('success', 'Share settings updated.');
        } elseif ($op === 'revoke') {
            execute('UPDATE albums SET share_token=NULL WHERE id=?', [$id]);
            flash('success', 'Share link revoked.');
        }
        redirect('album-view.php?id=' . $id);
    }

    if ($action === 'duplicate') {
        $asTpl = isset($_POST['as_template']);
        $newName = trim($_POST['new_name'] ?? '') ?: ($album['name'] . ($asTpl ? ' (Template)' : ' (Copy)'));
        $newId = albumDuplicate($album, $newName, $asTpl);
        flash('success', $asTpl ? 'Saved as a reusable reference template.' : 'Album duplicated.');
        redirect('album-view.php?id=' . $newId);
    }
}

$album = albumGet($id);
$tab = $_GET['tab'] ?? 'all';
$statusFilter = in_array($tab, PHOTO_STATES, true) ? $tab : null;
$photos = albumPhotos($id, $statusFilter);
$all = albumPhotos($id);
$counts = ['all' => count($all)];
foreach (PHOTO_STATES as $s) $counts[$s] = 0;
foreach ($all as $p) $counts[$p['status']] = ($counts[$p['status']] ?? 0) + 1;

$others = array_values(array_filter(albumsAll([]), fn($a) => (int)$a['id'] !== $id));
$customers = query('SELECT id, name FROM customers WHERE deleted_at IS NULL ORDER BY name');
$events    = query('SELECT id, title FROM events WHERE deleted_at IS NULL ORDER BY event_date DESC');

$destOptions = function () use ($others) {
    if (!$others) return '<option value="">No other albums yet</option>';
    $out = '';
    foreach ($others as $o) {
        $label = $o['name'] . ($o['is_template'] ? ' [template]' : ($o['customer_name'] ? ' — ' . $o['customer_name'] : ''));
        $out .= '<option value="' . $o['id'] . '">' . e($label) . '</option>';
    }
    return $out;
};

$currentPage = 'albums';
$pageTitle = $album['name'];
require_once __DIR__ . '/includes/header.php';
?>
<p class="crumb"><a href="albums.php">← All albums</a></p>
<div class="page-header">
    <div>
        <h1><?= e($album['name']) ?></h1>
        <p class="subtitle">
            <?= $album['customer_name'] ? e($album['customer_name']) . ' · ' : '' ?>
            <?= $album['event_type'] ? e($album['event_type']) : ($album['is_template'] ? 'Reference template' : '') ?>
            <?= $album['event_date'] ? ' · ' . formatDate($album['event_date']) : '' ?>
            <?php if ($album['event_title']): ?> · <a href="event-view.php?id=<?= (int)$album['event_id'] ?>"><?= e($album['event_title']) ?></a><?php endif; ?>
        </p>
    </div>
    <div class="flex">
        <button type="button" class="btn btn-secondary" onclick="tg('editBox')">Edit details</button>
        <button type="button" class="btn btn-secondary" onclick="tg('shareBox')">Share with customer</button>
        <button type="button" class="btn btn-secondary" onclick="tg('dupBox')">Duplicate</button>
    </div>
</div>

<div class="card album-collapse" id="editBox">
    <h3>Edit details</h3>
    <form method="post">
        <input type="hidden" name="action" value="save_details">
        <div class="form-row">
            <div class="form-group"><label>Album name</label><input name="name" value="<?= e($album['name']) ?>"></div>
            <div class="form-group"><label>Event type</label>
                <select name="event_type"><option value="">—</option>
                    <?php foreach (getCeremonyTypes() as $ct): ?><option <?= $album['event_type'] === $ct ? 'selected' : '' ?>><?= e($ct) ?></option><?php endforeach; ?>
                </select></div>
            <div class="form-group"><label>Event date</label><input type="date" name="event_date" value="<?= e($album['event_date']) ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Customer</label>
                <select name="customer_id"><option value="">—</option>
                    <?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>" <?= (int)$album['customer_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="form-group"><label>Event</label>
                <select name="event_id"><option value="">—</option>
                    <?php foreach ($events as $ev): ?><option value="<?= $ev['id'] ?>" <?= (int)$album['event_id'] === (int)$ev['id'] ? 'selected' : '' ?>><?= e($ev['title']) ?></option><?php endforeach; ?>
                </select></div>
        </div>
        <div class="form-group"><label>Notes / description</label><textarea name="description" rows="3"><?= e($album['description']) ?></textarea></div>
        <label class="checkbox-inline"><input type="checkbox" name="is_template" <?= $album['is_template'] ? 'checked' : '' ?>> Reusable reference template</label>
        <div style="margin-top:.75rem"><button class="btn btn-primary">Save details</button></div>
    </form>
</div>

<div class="card album-collapse" id="shareBox">
    <h3>Share with customer</h3>
    <?php if ($album['share_token']): $shareUrl = albumShareUrl($album); ?>
        <p class="text-muted">Send this private link to your customer — anyone with the link can view it, no login needed.</p>
        <div class="share-row">
            <input type="text" id="shareUrl" value="<?= e($shareUrl) ?>" readonly onclick="this.select()">
            <button type="button" class="btn btn-secondary" onclick="copyShare()">Copy link</button>
            <a class="btn btn-secondary" href="<?= e($shareUrl) ?>" target="_blank">Preview ↗</a>
        </div>
        <form method="post" style="margin:.5rem 0">
            <input type="hidden" name="action" value="share"><input type="hidden" name="op" value="scope">
            <label class="checkbox-inline"><input type="radio" name="scope" value="all" <?= $album['share_scope'] !== 'final' ? 'checked' : '' ?> onchange="this.form.submit()"> Show all photos being discussed</label>
            <label class="checkbox-inline"><input type="radio" name="scope" value="final" <?= $album['share_scope'] === 'final' ? 'checked' : '' ?> onchange="this.form.submit()"> Show only the final approved design</label>
        </form>
        <form method="post" style="display:inline" onsubmit="return confirm('Revoke the link? The old link stops working.')">
            <input type="hidden" name="action" value="share"><input type="hidden" name="op" value="revoke">
            <button class="btn btn-sm btn-danger">Revoke link</button>
        </form>
    <?php else: ?>
        <p class="text-muted">Create a private link to let the customer view this album on their own device.</p>
        <form method="post">
            <input type="hidden" name="action" value="share"><input type="hidden" name="op" value="enable">
            <label class="checkbox-inline"><input type="radio" name="scope" value="all" checked> Show all photos being discussed</label>
            <label class="checkbox-inline"><input type="radio" name="scope" value="final"> Show only the final approved design</label>
            <div style="margin-top:.75rem"><button class="btn btn-primary">Create share link</button></div>
        </form>
    <?php endif; ?>
</div>

<div class="card album-collapse" id="dupBox">
    <h3>Duplicate or save as template</h3>
    <p class="text-muted">Reuse this album for another customer, or save your best sets as a reference template for future calls.</p>
    <form method="post">
        <input type="hidden" name="action" value="duplicate">
        <div class="form-group"><label>New album name</label><input name="new_name" placeholder="<?= e($album['name']) ?> (Copy)"></div>
        <label class="checkbox-inline"><input type="checkbox" name="as_template"> Save as a reference template (no customer)</label>
        <div style="margin-top:.75rem"><button class="btn btn-primary">Create copy</button></div>
    </form>
</div>

<?php if ($album['design_approved']): ?>
    <div class="alert alert-success">✓ Final design approved<?= $album['approved_at'] ? ' on ' . formatDate($album['approved_at']) : '' ?><?= $album['approved_note'] ? ' — ' . e($album['approved_note']) : '' ?>.</div>
<?php endif; ?>

<div class="card">
    <form method="post" enctype="multipart/form-data" class="album-upload">
        <input type="hidden" name="action" value="upload">
        <label class="btn btn-primary">+ Add photos
            <input type="file" name="photos[]" accept="image/*" multiple hidden onchange="this.form.submit()">
        </label>
        <span class="text-muted">Select several at once. Max 15 MB each.</span>
    </form>
</div>

<div class="tabs">
    <?php
    $ptabs = ['all' => 'All', 'shortlisted' => 'Shortlisted', 'final' => 'Final design', 'proposed' => 'Proposed', 'rejected' => 'Passed'];
    foreach ($ptabs as $k => $label):
        $c = $k === 'all' ? $counts['all'] : ($counts[$k] ?? 0); ?>
        <a href="?id=<?= $id ?>&tab=<?= $k ?>" class="<?= $tab === $k ? 'active' : '' ?>"><?= $label ?> (<?= $c ?>)</a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'final'): ?>
    <div class="card album-final-note">
        <form method="post" class="approve-form">
            <input type="hidden" name="action" value="approve">
            <label class="checkbox-inline"><input type="checkbox" name="design_approved" <?= $album['design_approved'] ? 'checked' : '' ?>> Customer has approved this final design</label>
            <input type="text" name="approved_note" value="<?= e($album['approved_note']) ?>" placeholder="Optional note (e.g. approved on call with Priya)">
            <button class="btn btn-secondary btn-sm">Save approval</button>
        </form>
        <p class="text-muted" style="margin:.5rem 0 0">Photos marked <strong>Final design</strong> appear here — the agreed set, kept separate from everything still under discussion.</p>
    </div>
<?php endif; ?>

<?php if (empty($photos)): ?>
    <div class="card"><div class="empty-state"><div class="icon">🖼️</div>
        <h3><?= $tab === 'all' ? 'No photos yet' : 'Nothing in “' . e($ptabs[$tab] ?? $tab) . '”' ?></h3>
        <p><?= $tab === 'all' ? 'Click “+ Add photos” above to build this album.' : 'Move photos here as you review them with the customer.' ?></p>
    </div></div>
<?php else: ?>
    <form method="post" id="bulkForm">
        <input type="hidden" name="action" value="bulk"><input type="hidden" name="tab" value="<?= e($tab) ?>">
        <div class="album-bulk">
            <label class="checkbox-inline"><input type="checkbox" id="selAll"> Select all</label>
            <span class="text-muted"><span id="selCount">0</span> selected</span>
            <span class="album-bulk-ops">
                <button class="btn btn-sm btn-secondary" name="op" value="shortlisted">Shortlist</button>
                <button class="btn btn-sm btn-secondary" name="op" value="final">Mark final</button>
                <button class="btn btn-sm btn-secondary" name="op" value="proposed">Proposed</button>
                <button class="btn btn-sm btn-secondary" name="op" value="rejected">Pass</button>
                <select name="dest"><?= $destOptions() ?></select>
                <button class="btn btn-sm btn-secondary" name="op" value="copy">Copy →</button>
                <button class="btn btn-sm btn-secondary" name="op" value="move">Move →</button>
                <button class="btn btn-sm btn-danger" name="op" value="delete" onclick="return confirm('Delete the selected photos?')">Delete</button>
            </span>
        </div>
    </form>

    <div class="album-photos">
        <?php foreach ($photos as $p): $isCover = (int)$album['cover_photo_id'] === (int)$p['id']; ?>
        <div class="album-photo status-<?= e($p['status']) ?>">
            <label class="ap-pick"><input type="checkbox" form="bulkForm" name="ids[]" value="<?= $p['id'] ?>" class="psel"></label>
            <div class="ap-img" style="background-image:url('<?= e(imgUrl($p['thumbnail_path'] ?: $p['file_path'])) ?>')" onclick="lb('<?= e(imgUrl($p['file_path'])) ?>')">
                <span class="ap-badge b-<?= e($p['status']) ?>"><?= e(albumStatusLabel($p['status'])) ?></span>
                <?php if ($isCover): ?><span class="ap-cover">★ Cover</span><?php endif; ?>
            </div>
            <div class="ap-body">
                <div class="ap-status">
                    <?php foreach (['shortlisted' => '♥', 'final' => '✓', 'proposed' => '↺', 'rejected' => '✕'] as $s => $ic): ?>
                        <button type="submit" form="pf<?= $p['id'] ?>" class="ap-sbtn <?= $p['status'] === $s ? 'on' : '' ?>" title="<?= e(albumStatusLabel($s)) ?>" onclick="ss('<?= $p['id'] ?>','<?= $s ?>')"><?= $ic ?></button>
                    <?php endforeach; ?>
                </div>
                <input class="ap-cap" type="text" form="pf<?= $p['id'] ?>" name="caption" value="<?= e($p['caption']) ?>" placeholder="Add a caption…" onchange="so('pf<?= $p['id'] ?>','caption');document.getElementById('pf<?= $p['id'] ?>').submit()">
                <details class="ap-more">
                    <summary>More</summary>
                    <div class="ap-more-body">
                        <button type="submit" form="pf<?= $p['id'] ?>" class="btn btn-sm btn-secondary" onclick="so('pf<?= $p['id'] ?>','cover')">Set as cover</button>
                        <div class="ap-dest">
                            <select form="pf<?= $p['id'] ?>" name="dest"><?= $destOptions() ?></select>
                            <button type="submit" form="pf<?= $p['id'] ?>" class="btn btn-sm btn-secondary" onclick="so('pf<?= $p['id'] ?>','copy')">Copy</button>
                            <button type="submit" form="pf<?= $p['id'] ?>" class="btn btn-sm btn-secondary" onclick="so('pf<?= $p['id'] ?>','move')">Move</button>
                        </div>
                        <button type="submit" form="pf<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="so('pf<?= $p['id'] ?>','delete');return confirm('Delete this photo?')">Delete</button>
                    </div>
                </details>
            </div>
        </div>
        <form method="post" id="pf<?= $p['id'] ?>" style="display:none">
            <input type="hidden" name="action" value="photo">
            <input type="hidden" name="op" value="status">
            <input type="hidden" name="status" value="<?= e($p['status']) ?>">
            <input type="hidden" name="photo_id" value="<?= $p['id'] ?>">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
        </form>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="album-lightbox" id="lightbox" onclick="this.classList.remove('open')"><img src="" alt=""></div>

<script>
function tg(id){ document.getElementById(id).classList.toggle('show'); }
function copyShare(){ var i=document.getElementById('shareUrl'); i.select(); navigator.clipboard&&navigator.clipboard.writeText(i.value); }
function lb(src){ var b=document.getElementById('lightbox'); b.querySelector('img').src=src; b.classList.add('open'); }
function so(fid,op){ document.getElementById(fid).op.value=op; }
function ss(pid,s){ var f=document.getElementById('pf'+pid); f.op.value='status'; f.status.value=s; }
(function(){
  var sel=document.querySelectorAll('.psel'), all=document.getElementById('selAll'), cnt=document.getElementById('selCount');
  function upd(){ if(cnt) cnt.textContent=[].filter.call(sel,function(c){return c.checked;}).length; }
  [].forEach.call(sel,function(c){ c.addEventListener('change',upd); });
  if(all) all.addEventListener('change',function(){ [].forEach.call(sel,function(c){c.checked=all.checked;}); upd(); });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
