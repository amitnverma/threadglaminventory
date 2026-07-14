<?php
/**
 * JSON API for communications: upload recording, save transcript, AI suggest/summarize.
 */
require_once __DIR__ . '/includes/comm-functions.php';
require_once __DIR__ . '/includes/ai-client.php';
requireAuth();
ensureCommsSchema();

header('Content-Type: application/json; charset=utf-8');

function commApiJson(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function commApiSessionOwned(int $sessionId): array
{
    $session = commSessionGet($sessionId);
    if (!$session) commApiJson(['ok' => false, 'err' => 'Session not found.'], 404);
    return $session;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'upload_recording') {
    $sessionId = (int)($_POST['session_id'] ?? 0);
    $session = commApiSessionOwned($sessionId);
    if (empty($_FILES['audio'])) {
        commApiJson(['ok' => false, 'err' => 'No audio file.'], 400);
    }
    $duration = isset($_POST['duration_sec']) ? (int)$_POST['duration_sec'] : null;
    $res = commStoreRecording($sessionId, (int)$session['event_id'], $_FILES['audio'], $duration);
    if (isset($res['err'])) commApiJson(['ok' => false, 'err' => $res['err']], 400);
    execute('UPDATE comm_sessions SET status=IF(status="draft","in_progress",status), updated_at=NOW() WHERE id=?', [$sessionId]);
    commApiJson(['ok' => true, 'recording' => $res, 'url' => imgUrl($res['file_path'])]);
}

if ($action === 'save_transcript') {
    $recId = (int)($_POST['recording_id'] ?? 0);
    $sessionId = (int)($_POST['session_id'] ?? 0);
    $session = commApiSessionOwned($sessionId);
    $rec = queryOne('SELECT * FROM comm_recordings WHERE id=? AND session_id=?', [$recId, $sessionId]);
    if (!$rec) commApiJson(['ok' => false, 'err' => 'Recording not found.'], 404);
    $text = trim((string)($_POST['transcript_text'] ?? ''));
    execute(
        'UPDATE comm_recordings SET transcript_text=?, transcribe_status=? WHERE id=?',
        [$text !== '' ? $text : null, $text !== '' ? 'done' : 'none', $recId]
    );
    commApiJson(['ok' => true]);
}

if ($action === 'suggest_questions') {
    $sessionId = (int)($_POST['session_id'] ?? 0);
    $session = commApiSessionOwned($sessionId);
    $payload = buildCommSummaryPayload($session, commAnswers($sessionId), commRecordings($sessionId));
    $res = aiSuggestQuestions($payload);
    if (!$res['ok']) commApiJson($res, 400);
    commApiJson($res);
}

if ($action === 'summarize') {
    $sessionId = (int)($_POST['session_id'] ?? 0);
    $session = commApiSessionOwned($sessionId);
    $answers = commAnswers($sessionId);
    $recordings = commRecordings($sessionId);
    $payload = buildCommSummaryPayload($session, $answers, $recordings);
    $res = aiSummarizeSession($payload);
    if (!$res['ok']) commApiJson($res, 400);

    $summary = $res['summary'];
    $summaryText = trim((string)($summary['summary'] ?? ''));
    execute(
        'UPDATE comm_sessions SET summary_text=?, summary_json=?, summarized_at=NOW(), summary_model=?, status="summarized", updated_at=NOW() WHERE id=?',
        [$summaryText, json_encode($summary, JSON_UNESCAPED_UNICODE), $res['model'] ?? null, $sessionId]
    );

    // Persist suggested decisions as proposed (avoid duplicates by exact text)
    foreach (($summary['decisions'] ?? []) as $dText) {
        $dText = trim((string)$dText);
        if ($dText === '') continue;
        $exists = queryOne(
            'SELECT id FROM comm_decisions WHERE event_id=? AND session_id=? AND decision_text=? LIMIT 1',
            [(int)$session['event_id'], $sessionId, $dText]
        );
        if ($exists) continue;
        execute(
            'INSERT INTO comm_decisions (event_id, session_id, decision_text, status) VALUES (?,?,?,?)',
            [(int)$session['event_id'], $sessionId, mb_substr($dText, 0, 500), 'proposed']
        );
    }

    commApiJson(['ok' => true, 'summary' => $summary, 'model' => $res['model'] ?? null]);
}

commApiJson(['ok' => false, 'err' => 'Unknown action.'], 400);
