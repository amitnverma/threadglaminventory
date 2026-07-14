<?php
require_once __DIR__ . '/includes/comm-functions.php';
require_once __DIR__ . '/includes/album-functions.php';
requireAuth();
ensureCommsSchema();
ensureAlbumsSchema();

$id = (int)($_GET['id'] ?? 0);
$session = commSessionGet($id);
if (!$session) {
    flash('error', 'Communication session not found.');
    redirect('events.php');
}

$eventId = (int)$session['event_id'];
$admin = currentAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_answers') {
        $ids = $_POST['answer_id'] ?? [];
        $texts = $_POST['answer_text'] ?? [];
        foreach ($ids as $i => $aid) {
            $aid = (int)$aid;
            if ($aid <= 0) continue;
            $row = queryOne('SELECT id FROM comm_answers WHERE id=? AND session_id=?', [$aid, $id]);
            if (!$row) continue;
            execute('UPDATE comm_answers SET answer_text=?, updated_at=NOW() WHERE id=?', [trim((string)($texts[$i] ?? '')), $aid]);
        }
        if (($session['status'] ?? '') === 'draft') {
            execute('UPDATE comm_sessions SET status="in_progress", updated_at=NOW() WHERE id=?', [$id]);
        } else {
            execute('UPDATE comm_sessions SET updated_at=NOW() WHERE id=?', [$id]);
        }
        flash('success', 'Answers saved.');
        redirect('comm-session.php?id=' . $id);
    }

    if ($action === 'add_question') {
        $q = trim($_POST['question_text'] ?? '');
        if ($q === '') {
            flash('error', 'Enter a question.');
            redirect('comm-session.php?id=' . $id);
        }
        $source = in_array($_POST['source'] ?? 'manual', COMM_ANSWER_SOURCES, true) ? $_POST['source'] : 'manual';
        $key = 'manual_' . time() . '_' . mt_rand(100, 999);
        execute(
            'INSERT INTO comm_answers (session_id, question_key, question_text, answer_text, sort_order, source) VALUES (?,?,?,?,?,?)',
            [$id, $key, mb_substr($q, 0, 500), '', commNextAnswerOrder($id), $source]
        );
        flash('success', 'Question added.');
        redirect('comm-session.php?id=' . $id . '#q' . lastId());
    }

    if ($action === 'delete_answer' && !empty($_POST['answer_id'])) {
        execute('DELETE FROM comm_answers WHERE id=? AND session_id=?', [(int)$_POST['answer_id'], $id]);
        flash('success', 'Question removed.');
        redirect('comm-session.php?id=' . $id);
    }

    if ($action === 'save_meta') {
        $title = trim($_POST['title'] ?? $session['title']) ?: $session['title'];
        $type = $_POST['session_type'] ?? $session['session_type'];
        if (!in_array($type, COMM_SESSION_TYPES, true)) $type = $session['session_type'];
        $status = $_POST['status'] ?? $session['status'];
        if (!in_array($status, COMM_SESSION_STATUSES, true)) $status = $session['status'];
        $held = trim($_POST['held_at'] ?? '');
        $heldSql = $held !== '' ? date('Y-m-d H:i:s', strtotime($held)) : null;
        $albumId = ($_POST['album_id'] ?? '') !== '' ? (int)$_POST['album_id'] : null;
        execute(
            'UPDATE comm_sessions SET title=?, session_type=?, status=?, held_at=?, album_id=?, updated_at=NOW() WHERE id=?',
            [$title, $type, $status, $heldSql, $albumId, $id]
        );
        flash('success', 'Session details saved.');
        redirect('comm-session.php?id=' . $id);
    }

    if ($action === 'save_manual_summary') {
        $text = trim($_POST['summary_text'] ?? '');
        if ($text === '') {
            execute('UPDATE comm_sessions SET summary_text=NULL, summarized_at=NULL, status="in_progress", updated_at=NOW() WHERE id=?', [$id]);
        } else {
            execute('UPDATE comm_sessions SET summary_text=?, status="summarized", summarized_at=NOW(), updated_at=NOW() WHERE id=?', [$text, $id]);
        }
        flash('success', 'Summary saved.');
        redirect('comm-session.php?id=' . $id);
    }

    if ($action === 'add_decision') {
        $text = trim($_POST['decision_text'] ?? '');
        if ($text === '') {
            flash('error', 'Enter a decision.');
            redirect('comm-session.php?id=' . $id);
        }
        $st = $_POST['decision_status'] ?? 'proposed';
        if (!in_array($st, COMM_DECISION_STATUSES, true)) $st = 'proposed';
        $albumId = ($_POST['related_album_id'] ?? '') !== '' ? (int)$_POST['related_album_id'] : null;
        execute(
            'INSERT INTO comm_decisions (event_id, session_id, decision_text, status, related_album_id) VALUES (?,?,?,?,?)',
            [$eventId, $id, mb_substr($text, 0, 500), $st, $albumId]
        );
        if ($st === 'approved' && $albumId) {
            execute('UPDATE albums SET design_approved=1, approved_at=NOW(), approved_note=? WHERE id=? AND event_id=?',
                ['Approved via communication session #' . $id, $albumId, $eventId]);
            execute('UPDATE comm_sessions SET album_id=? WHERE id=?', [$albumId, $id]);
        }
        flash('success', 'Decision recorded.');
        redirect('comm-session.php?id=' . $id);
    }

    if ($action === 'update_decision' && !empty($_POST['decision_id'])) {
        $did = (int)$_POST['decision_id'];
        $st = $_POST['decision_status'] ?? 'proposed';
        if (!in_array($st, COMM_DECISION_STATUSES, true)) $st = 'proposed';
        $albumId = ($_POST['related_album_id'] ?? '') !== '' ? (int)$_POST['related_album_id'] : null;
        execute(
            'UPDATE comm_decisions SET status=?, related_album_id=?, updated_at=NOW() WHERE id=? AND event_id=?',
            [$st, $albumId, $did, $eventId]
        );
        if ($st === 'approved' && $albumId) {
            execute('UPDATE albums SET design_approved=1, approved_at=NOW(), approved_note=? WHERE id=? AND event_id=?',
                ['Approved via communication session #' . $id, $albumId, $eventId]);
            execute('UPDATE comm_sessions SET album_id=? WHERE id=?', [$albumId, $id]);
        }
        flash('success', 'Decision updated.');
        redirect('comm-session.php?id=' . $id);
    }

    if ($action === 'delete_decision' && !empty($_POST['decision_id'])) {
        execute('DELETE FROM comm_decisions WHERE id=? AND event_id=?', [(int)$_POST['decision_id'], $eventId]);
        flash('success', 'Decision removed.');
        redirect('comm-session.php?id=' . $id);
    }

    if ($action === 'delete_recording' && !empty($_POST['recording_id'])) {
        $rec = queryOne('SELECT * FROM comm_recordings WHERE id=? AND session_id=?', [(int)$_POST['recording_id'], $id]);
        if ($rec) {
            global $config;
            $path = rtrim($config['upload_dir'], '/') . '/' . $rec['file_path'];
            if (is_file($path)) @unlink($path);
            execute('DELETE FROM comm_recordings WHERE id=?', [$rec['id']]);
            flash('success', 'Recording deleted.');
        }
        redirect('comm-session.php?id=' . $id);
    }

    if ($action === 'upload_file_recording' && !empty($_FILES['audio_file']['name'])) {
        $res = commStoreRecording($id, $eventId, $_FILES['audio_file']);
        flash(isset($res['err']) ? 'error' : 'success', $res['err'] ?? 'Recording uploaded.');
        redirect('comm-session.php?id=' . $id);
    }

    redirect('comm-session.php?id=' . $id);
}

$session = commSessionGet($id);
$answers = commAnswers($id);
$recordings = commRecordings($id);
$decisions = commDecisionsForSession($id);
$albums = albumsAll(['event_id' => $eventId]);
$ai = getAiSettings();
$summaryObj = $session['summary_json'] ? (json_decode($session['summary_json'], true) ?: null) : null;

$currentPage = 'events';
$pageTitle = $session['title'];
require_once __DIR__ . '/includes/header.php';
?>

<p class="crumb"><a href="event-view.php?id=<?= $eventId ?>&tab=comms">← Communications</a></p>
<div class="page-header">
    <div>
        <h1><?= e($session['title']) ?></h1>
        <p class="subtitle">
            <?= e($session['customer_name']) ?> · <?= e($session['event_title']) ?>
            · <?= e(commSessionTypeLabel($session['session_type'])) ?>
            · <span class="badge badge-draft"><?= e(commSessionStatusLabel($session['status'])) ?></span>
        </p>
    </div>
</div>

<nav class="section-jump" aria-label="Session sections">
    <a href="#sec-questions">Questions (<?= count($answers) ?>)</a>
    <a href="#sec-recording">Recording (<?= count($recordings) ?>)</a>
    <a href="#sec-summary">Summary</a>
    <a href="#sec-decisions">Decisions (<?= count($decisions) ?>)</a>
    <a href="#sec-details">Details</a>
</nav>

<details class="panel" id="sec-questions" open>
    <summary>
        <span>Discovery questions</span>
        <span class="panel-meta"><?= count($answers) ?> item<?= count($answers) === 1 ? '' : 's' ?></span>
    </summary>
    <div class="panel-body">
        <div class="flex" style="justify-content:flex-end;margin-bottom:.75rem">
            <button type="button" class="btn btn-secondary btn-sm" id="btnSuggest" <?= empty($ai['api_key']) || empty($ai['enable_suggest']) ? 'disabled title="Configure AI in Settings"' : '' ?>>AI suggest follow-ups</button>
        </div>
        <div id="suggestBox" class="comm-suggest" hidden></div>
        <form method="post">
            <input type="hidden" name="action" value="save_answers">
            <?php if (empty($answers)): ?>
                <p class="text-muted">No questions yet — add one below.</p>
            <?php else: ?>
                <?php foreach ($answers as $a): ?>
                <div class="comm-qa" id="q<?= (int)$a['id'] ?>">
                    <input type="hidden" name="answer_id[]" value="<?= (int)$a['id'] ?>">
                    <div class="comm-q-head">
                        <strong><?= e($a['question_text']) ?></strong>
                        <span class="badge badge-draft"><?= e($a['source']) ?></span>
                        <button type="submit" form="delAns<?= (int)$a['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Remove this question?')">×</button>
                    </div>
                    <textarea name="answer_text[]" rows="2" placeholder="Answer / notes…"><?= e($a['answer_text']) ?></textarea>
                </div>
                <?php endforeach; ?>
                <button class="btn btn-primary">Save answers</button>
            <?php endif; ?>
        </form>
        <?php foreach ($answers as $a): ?>
        <form method="post" id="delAns<?= (int)$a['id'] ?>" style="display:none">
            <input type="hidden" name="action" value="delete_answer">
            <input type="hidden" name="answer_id" value="<?= (int)$a['id'] ?>">
        </form>
        <?php endforeach; ?>

        <form method="post" class="comm-add-q" style="margin-top:1.25rem">
            <input type="hidden" name="action" value="add_question">
            <input type="hidden" name="source" value="manual">
            <div class="form-row">
                <div class="form-group" style="flex:1"><label>Add a question during the discussion</label>
                    <input name="question_text" id="newQuestionInput" placeholder="e.g. Do you want aisle florals?" required>
                </div>
                <div class="form-group" style="align-self:flex-end"><button class="btn btn-secondary">Add question</button></div>
            </div>
        </form>
    </div>
</details>

<details class="panel" id="sec-recording" open>
    <summary>
        <span>Recording</span>
        <span class="panel-meta"><?= count($recordings) ?> file<?= count($recordings) === 1 ? '' : 's' ?></span>
    </summary>
    <div class="panel-body" id="recordingCard"
         data-session-id="<?= (int)$id ?>"
         data-api="comm-api.php">
        <p class="text-muted" style="margin-bottom:.75rem">
            Record in the browser — saved on the server. For Zoom/Meet in Chrome, use <strong>Record call / tab</strong> and enable <strong>Share tab audio</strong>.
        </p>
        <div class="comm-recorder">
            <button type="button" class="btn btn-primary" id="btnRecMic">● Record microphone</button>
            <button type="button" class="btn btn-primary" id="btnRecCall">● Record call / tab</button>
            <button type="button" class="btn btn-danger" id="btnRecStop" disabled>Stop &amp; save</button>
            <span id="recTimer" class="text-muted">00:00</span>
            <span id="recStatus" class="hint"></span>
        </div>
        <form method="post" enctype="multipart/form-data" style="margin-top:.75rem" class="comm-upload-file">
            <input type="hidden" name="action" value="upload_file_recording">
            <label class="btn btn-secondary btn-sm">Upload audio file instead
                <input type="file" name="audio_file" accept="audio/*,video/webm" hidden onchange="this.form.submit()">
            </label>
        </form>

        <?php if ($recordings): ?>
        <div class="comm-rec-list">
            <?php foreach ($recordings as $r): ?>
            <div class="comm-rec-item" data-recording-id="<?= (int)$r['id'] ?>">
                <audio controls src="<?= e(imgUrl($r['file_path'])) ?>"></audio>
                <div class="comm-rec-meta">
                    <span class="text-muted"><?= e(formatDate($r['created_at'])) ?><?= $r['duration_sec'] ? ' · ' . (int)$r['duration_sec'] . 's' : '' ?></span>
                    <form method="post" style="display:inline" onsubmit="return confirm('Delete this recording?')">
                        <input type="hidden" name="action" value="delete_recording">
                        <input type="hidden" name="recording_id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-sm btn-danger">Delete</button>
                    </form>
                </div>
                <label class="hint">Transcript (paste, or use Live dictate)</label>
                <textarea class="rec-transcript" rows="3" placeholder="Transcript text…"><?= e($r['transcript_text']) ?></textarea>
                <div class="flex" style="gap:.4rem;margin-top:.35rem;flex-wrap:wrap">
                    <button type="button" class="btn btn-sm btn-secondary btn-save-transcript">Save transcript</button>
                    <button type="button" class="btn btn-sm btn-secondary btn-dictate">Live dictate (browser)</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</details>

<details class="panel" id="sec-summary" <?= ($session['summary_text'] || $summaryObj) ? 'open' : '' ?>>
    <summary>
        <span>Summary</span>
        <span class="panel-meta"><?= ($session['summary_text'] || $summaryObj) ? 'Ready' : 'Not yet' ?></span>
    </summary>
    <div class="panel-body">
        <?php if ($summaryObj): ?>
            <p><?= e($summaryObj['summary'] ?? $session['summary_text'] ?? '') ?></p>
            <?php if (!empty($summaryObj['open_questions'])): ?>
                <p class="hint"><strong>Open questions:</strong> <?= e(implode(' · ', $summaryObj['open_questions'])) ?></p>
            <?php endif; ?>
            <?php if (!empty($summaryObj['next_actions'])): ?>
                <p class="hint"><strong>Next actions:</strong> <?= e(implode(' · ', $summaryObj['next_actions'])) ?></p>
            <?php endif; ?>
            <?php if ($session['summary_model']): ?><p class="text-muted" style="font-size:.78rem">Model: <?= e($session['summary_model']) ?><?= $session['summarized_at'] ? ' · ' . e(formatDate($session['summarized_at'])) : '' ?></p><?php endif; ?>
        <?php elseif ($session['summary_text']): ?>
            <p><?= nl2br(e($session['summary_text'])) ?></p>
        <?php else: ?>
            <p class="text-muted">No summary yet. Save answers/recording, then summarize with AI or write one manually.</p>
        <?php endif; ?>
        <div class="flex" style="margin-top:.75rem;flex-wrap:wrap;gap:.5rem">
            <button type="button" class="btn btn-primary btn-sm" id="btnSummarize" <?= empty($ai['api_key']) || empty($ai['enable_summarize']) ? 'disabled title="Configure AI in Settings"' : '' ?>>Summarize with AI</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('manualSummary').classList.toggle('show')">Edit summary manually</button>
        </div>
        <div id="aiStatus" class="hint" style="margin-top:.5rem"></div>
        <div class="album-collapse" id="manualSummary" style="margin-top:.75rem">
            <form method="post">
                <input type="hidden" name="action" value="save_manual_summary">
                <textarea name="summary_text" rows="4" placeholder="Meeting summary…"><?= e($session['summary_text'] ?? '') ?></textarea>
                <button class="btn btn-secondary btn-sm" style="margin-top:.5rem">Save summary</button>
            </form>
        </div>
    </div>
</details>

<details class="panel" id="sec-decisions" <?= $decisions ? 'open' : '' ?>>
    <summary>
        <span>Decisions</span>
        <span class="panel-meta"><?= count($decisions) ?></span>
    </summary>
    <div class="panel-body">
        <form method="post" class="comm-decision-form">
            <input type="hidden" name="action" value="add_decision">
            <div class="form-row">
                <div class="form-group" style="flex:2"><label>Decision</label>
                    <input name="decision_text" placeholder="e.g. Approved blush + gold mandap concept" required>
                </div>
                <div class="form-group"><label>Status</label>
                    <select name="decision_status">
                        <?php foreach (COMM_DECISION_STATUSES as $ds): ?>
                        <option value="<?= $ds ?>"><?= e(ucfirst($ds)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Link album</label>
                    <select name="related_album_id">
                        <option value="">—</option>
                        <?php foreach ($albums as $a): ?>
                        <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button class="btn btn-primary btn-sm">Add decision</button>
        </form>

        <?php if ($decisions): ?>
        <div class="table-wrap mt-1">
            <table class="data-table">
                <tr><th>Decision</th><th>Status</th><th>Album</th><th></th></tr>
                <?php foreach ($decisions as $d): ?>
                <tr>
                    <td><?= e($d['decision_text']) ?></td>
                    <td>
                        <form method="post" class="inline-flex">
                            <input type="hidden" name="action" value="update_decision">
                            <input type="hidden" name="decision_id" value="<?= (int)$d['id'] ?>">
                            <select name="decision_status" onchange="this.form.submit()">
                                <?php foreach (COMM_DECISION_STATUSES as $ds): ?>
                                <option value="<?= $ds ?>" <?= $d['status'] === $ds ? 'selected' : '' ?>><?= e(ucfirst($ds)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="related_album_id" onchange="this.form.submit()" style="max-width:140px">
                                <option value="">Album…</option>
                                <?php foreach ($albums as $a): ?>
                                <option value="<?= (int)$a['id'] ?>" <?= (int)$d['related_album_id'] === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                    <td>
                        <?php if ($d['related_album_id']): ?>
                            <a href="album-view.php?id=<?= (int)$d['related_album_id'] ?>">Open album</a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td>
                        <form method="post" onsubmit="return confirm('Delete decision?')">
                            <input type="hidden" name="action" value="delete_decision">
                            <input type="hidden" name="decision_id" value="<?= (int)$d['id'] ?>">
                            <button class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php else: ?>
            <p class="text-muted mt-1">No decisions yet. AI summarize can propose some from the conversation.</p>
        <?php endif; ?>
    </div>
</details>

<details class="panel" id="sec-details">
    <summary>
        <span>Session details</span>
        <span class="panel-meta"><?= e(commSessionTypeLabel($session['session_type'])) ?></span>
    </summary>
    <div class="panel-body">
        <form method="post">
            <input type="hidden" name="action" value="save_meta">
            <div class="form-group"><label>Title</label><input name="title" value="<?= e($session['title']) ?>"></div>
            <div class="form-row">
                <div class="form-group"><label>Type</label>
                    <select name="session_type">
                        <?php foreach (COMM_SESSION_TYPES as $t): ?>
                        <option value="<?= $t ?>" <?= $session['session_type'] === $t ? 'selected' : '' ?>><?= e(commSessionTypeLabel($t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Status</label>
                    <select name="status">
                        <?php foreach (COMM_SESSION_STATUSES as $st): ?>
                        <option value="<?= $st ?>" <?= $session['status'] === $st ? 'selected' : '' ?>><?= e(commSessionStatusLabel($st)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Held at</label>
                    <input type="datetime-local" name="held_at" value="<?= $session['held_at'] ? e(date('Y-m-d\TH:i', strtotime($session['held_at']))) : '' ?>">
                </div>
                <div class="form-group"><label>Linked album (design)</label>
                    <select name="album_id">
                        <option value="">—</option>
                        <?php foreach ($albums as $a): ?>
                        <option value="<?= (int)$a['id'] ?>" <?= (int)$session['album_id'] === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button class="btn btn-primary btn-sm">Save details</button>
        </form>
    </div>
</details>

<script src="assets/js/comms.js?v=3"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
