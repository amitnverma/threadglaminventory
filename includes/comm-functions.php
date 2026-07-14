<?php
/**
 * Event planning communications — sessions, Q&A templates, recordings, decisions.
 */

require_once __DIR__ . '/functions.php';

const COMM_SESSION_TYPES = ['initial_meeting', 'follow_up', 'design_review', 'approval', 'other'];
const COMM_SESSION_STATUSES = ['draft', 'in_progress', 'summarized', 'closed'];
const COMM_ANSWER_SOURCES = ['template', 'manual', 'ai'];
const COMM_DECISION_STATUSES = ['proposed', 'approved', 'rejected'];

function ensureCommsSchema(): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    ensureSettingsColumns();

    db()->exec(
        "CREATE TABLE IF NOT EXISTS comm_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ceremony_type VARCHAR(100) NOT NULL DEFAULT '',
            name VARCHAR(255) NOT NULL,
            questions_json LONGTEXT NOT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_ceremony (ceremony_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    db()->exec(
        "CREATE TABLE IF NOT EXISTS comm_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            customer_id INT NOT NULL,
            session_type ENUM('initial_meeting','follow_up','design_review','approval','other') NOT NULL DEFAULT 'initial_meeting',
            title VARCHAR(255) NOT NULL,
            status ENUM('draft','in_progress','summarized','closed') NOT NULL DEFAULT 'draft',
            held_at DATETIME NULL,
            summary_text TEXT NULL,
            summary_json LONGTEXT NULL,
            summarized_at DATETIME NULL,
            summary_model VARCHAR(120) NULL,
            album_id INT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_event (event_id),
            INDEX idx_customer (customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    db()->exec(
        "CREATE TABLE IF NOT EXISTS comm_answers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            question_key VARCHAR(80) NOT NULL,
            question_text VARCHAR(500) NOT NULL,
            answer_text TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            source ENUM('template','manual','ai') NOT NULL DEFAULT 'template',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_session (session_id),
            FOREIGN KEY (session_id) REFERENCES comm_sessions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    db()->exec(
        "CREATE TABLE IF NOT EXISTS comm_recordings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            duration_sec INT NULL,
            transcript_text LONGTEXT NULL,
            transcribe_status ENUM('none','pending','done','failed') NOT NULL DEFAULT 'none',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session (session_id),
            FOREIGN KEY (session_id) REFERENCES comm_sessions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    db()->exec(
        "CREATE TABLE IF NOT EXISTS comm_decisions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            session_id INT NULL,
            decision_text VARCHAR(500) NOT NULL,
            status ENUM('proposed','approved','rejected') NOT NULL DEFAULT 'proposed',
            related_album_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_event (event_id),
            INDEX idx_session (session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    seedDefaultCommTemplates();
}

function defaultCommQuestionPacks(): array
{
    $common = [
        ['key' => 'guest_count', 'text' => 'Approximate guest count?'],
        ['key' => 'budget_range', 'text' => 'Budget range for décor / setup?'],
        ['key' => 'color_theme', 'text' => 'Preferred colors or theme?'],
        ['key' => 'must_haves', 'text' => 'Must-have elements (mandap, backdrop, florals, lighting…)?'],
        ['key' => 'avoid', 'text' => 'Anything to avoid?'],
        ['key' => 'timeline', 'text' => 'Key times (setup access, ceremony, reception)?'],
        ['key' => 'decision_makers', 'text' => 'Who makes the final design decisions?'],
    ];

    $venue = [
        ['key' => 'venue_name', 'text' => 'Venue name and address?'],
        ['key' => 'venue_rules', 'text' => 'Venue rules / restrictions (open flame, pinning, load-in)?'],
        ['key' => 'venue_layout', 'text' => 'Indoor / outdoor / mixed? Ceiling height / space limits?'],
        ['key' => 'power_access', 'text' => 'Power / water / dressing room access?'],
    ];

    $byType = [
        'Wedding' => array_merge($common, $venue, [
            ['key' => 'ceremony_style', 'text' => 'Ceremony style (traditional, modern, fusion)?'],
            ['key' => 'mandap_prefs', 'text' => 'Mandap / altar preferences?'],
            ['key' => 'bride_groom_colors', 'text' => 'Bride / groom color preferences?'],
            ['key' => 'cultural_elements', 'text' => 'Cultural or religious elements to include?'],
        ]),
        'Reception' => array_merge($common, $venue, [
            ['key' => 'stage_needs', 'text' => 'Stage / sweetheart table needs?'],
            ['key' => 'dance_floor', 'text' => 'Dance floor décor?'],
            ['key' => 'entrance_moment', 'text' => 'Entrance / reveal moment?'],
        ]),
        'Birthday' => array_merge($common, $venue, [
            ['key' => 'honoree', 'text' => 'Whose birthday and age milestone?'],
            ['key' => 'party_vibe', 'text' => 'Party vibe (elegant, fun, kids, surprise)?'],
            ['key' => 'cake_table', 'text' => 'Cake / dessert table décor?'],
        ]),
        'Corporate' => array_merge($common, $venue, [
            ['key' => 'brand_guidelines', 'text' => 'Brand colors / logo guidelines?'],
            ['key' => 'stage_av', 'text' => 'Stage / AV / branding backdrop needs?'],
            ['key' => 'audience_size', 'text' => 'Seating / audience size?'],
        ]),
        'Anniversary' => array_merge($common, $venue, [
            ['key' => 'years', 'text' => 'Which anniversary year?'],
            ['key' => 'story_elements', 'text' => 'Personal story elements to reflect?'],
        ]),
        'Engagement' => array_merge($common, $venue, [
            ['key' => 'proposal_or_party', 'text' => 'Proposal setup or engagement party?'],
            ['key' => 'intimacy', 'text' => 'Intimate vs large gathering?'],
        ]),
        'Baby Shower' => array_merge($common, $venue, [
            ['key' => 'gender_theme', 'text' => 'Gender reveal / theme colors?'],
            ['key' => 'photo_backdrop', 'text' => 'Photo backdrop preferences?'],
        ]),
        'Graduation' => array_merge($common, $venue, [
            ['key' => 'school_colors', 'text' => 'School colors / mascot?'],
            ['key' => 'honoree_prefs', 'text' => 'Graduate preferences?'],
        ]),
        '' => array_merge($common, $venue, [
            ['key' => 'event_goal', 'text' => 'What should guests feel / remember?'],
            ['key' => 'inspiration', 'text' => 'Any inspiration photos or Pinterest links?'],
        ]),
        'Other' => array_merge($common, $venue, [
            ['key' => 'event_goal', 'text' => 'What should guests feel / remember?'],
            ['key' => 'inspiration', 'text' => 'Any inspiration photos or Pinterest links?'],
        ]),
    ];

    return $byType;
}

function seedDefaultCommTemplates(): void
{
    $count = queryOne('SELECT COUNT(*) AS n FROM comm_templates');
    if ((int)($count['n'] ?? 0) > 0) return;

    foreach (defaultCommQuestionPacks() as $type => $questions) {
        $name = $type === '' ? 'General / Other' : ($type . ' discovery');
        execute(
            'INSERT INTO comm_templates (ceremony_type, name, questions_json, is_default) VALUES (?,?,?,1)',
            [$type, $name, json_encode($questions, JSON_UNESCAPED_UNICODE)]
        );
    }
}

function commTemplateForCeremony(?string $ceremonyType): ?array
{
    $ceremonyType = trim((string)$ceremonyType);
    $tpl = null;
    if ($ceremonyType !== '') {
        $tpl = queryOne('SELECT * FROM comm_templates WHERE ceremony_type=? ORDER BY is_default DESC, id LIMIT 1', [$ceremonyType]);
    }
    if (!$tpl) {
        $tpl = queryOne('SELECT * FROM comm_templates WHERE ceremony_type IN ("","Other") ORDER BY is_default DESC, id LIMIT 1');
    }
    if (!$tpl) {
        $tpl = queryOne('SELECT * FROM comm_templates ORDER BY id LIMIT 1');
    }
    return $tpl;
}

function commTemplatesAll(): array
{
    return query('SELECT * FROM comm_templates ORDER BY ceremony_type, name');
}

function commSessionGet(int $id): ?array
{
    return queryOne(
        'SELECT s.*, e.title AS event_title, e.ceremony_type, e.venue, c.name AS customer_name
         FROM comm_sessions s
         JOIN events e ON e.id=s.event_id
         JOIN customers c ON c.id=s.customer_id
         WHERE s.id=?',
        [$id]
    );
}

function commSessionsForEvent(int $eventId): array
{
    return query(
        'SELECT s.*,
                (SELECT COUNT(*) FROM comm_answers a WHERE a.session_id=s.id) AS answer_count,
                (SELECT COUNT(*) FROM comm_recordings r WHERE r.session_id=s.id) AS recording_count
         FROM comm_sessions s WHERE s.event_id=? ORDER BY COALESCE(s.held_at, s.created_at) DESC, s.id DESC',
        [$eventId]
    );
}

function commAnswers(int $sessionId): array
{
    return query('SELECT * FROM comm_answers WHERE session_id=? ORDER BY sort_order, id', [$sessionId]);
}

function commRecordings(int $sessionId): array
{
    return query('SELECT * FROM comm_recordings WHERE session_id=? ORDER BY id DESC', [$sessionId]);
}

function commDecisionsForEvent(int $eventId): array
{
    return query(
        'SELECT d.*, s.title AS session_title FROM comm_decisions d
         LEFT JOIN comm_sessions s ON s.id=d.session_id
         WHERE d.event_id=? ORDER BY d.created_at DESC, d.id DESC',
        [$eventId]
    );
}

function commDecisionsForSession(int $sessionId): array
{
    return query('SELECT * FROM comm_decisions WHERE session_id=? ORDER BY id', [$sessionId]);
}

function commSessionTypeLabel(string $type): string
{
    return [
        'initial_meeting' => 'Initial meeting',
        'follow_up' => 'Follow-up',
        'design_review' => 'Design review',
        'approval' => 'Approval',
        'other' => 'Other',
    ][$type] ?? ucfirst(str_replace('_', ' ', $type));
}

function commSessionStatusLabel(string $status): string
{
    return [
        'draft' => 'Draft',
        'in_progress' => 'In progress',
        'summarized' => 'Summarized',
        'closed' => 'Closed',
    ][$status] ?? ucfirst($status);
}

function commNextAnswerOrder(int $sessionId): int
{
    $r = queryOne('SELECT COALESCE(MAX(sort_order),-1)+1 AS n FROM comm_answers WHERE session_id=?', [$sessionId]);
    return (int)($r['n'] ?? 0);
}

function commCreateSession(array $event, string $sessionType, string $title, ?int $adminId): int
{
    if (!in_array($sessionType, COMM_SESSION_TYPES, true)) $sessionType = 'other';
    execute(
        'INSERT INTO comm_sessions (event_id, customer_id, session_type, title, status, held_at, created_by)
         VALUES (?,?,?,?,?,?,?)',
        [
            (int)$event['id'],
            (int)$event['customer_id'],
            $sessionType,
            $title !== '' ? $title : commSessionTypeLabel($sessionType),
            'in_progress',
            date('Y-m-d H:i:s'),
            $adminId,
        ]
    );
    $sid = (int)lastId();

    if ($sessionType === 'initial_meeting') {
        $tpl = commTemplateForCeremony($event['ceremony_type'] ?? '');
        if ($tpl) {
            $questions = json_decode($tpl['questions_json'] ?? '[]', true) ?: [];
            $order = 0;
            foreach ($questions as $q) {
                $text = trim((string)($q['text'] ?? ''));
                if ($text === '') continue;
                $key = trim((string)($q['key'] ?? ('q_' . $order)));
                execute(
                    'INSERT INTO comm_answers (session_id, question_key, question_text, answer_text, sort_order, source) VALUES (?,?,?,?,?,?)',
                    [$sid, $key, $text, '', $order++, 'template']
                );
            }
        }
    }
    return $sid;
}

function commUploadDir(int $eventId): string
{
    global $config;
    return rtrim($config['upload_dir'], '/') . '/comms/' . $eventId;
}

/** Ensure uploads/comms[/event_id] exists and is writable by the web server. */
function commEnsureUploadDir(int $eventId): array
{
    global $config;
    $base = rtrim($config['upload_dir'] ?? '', '/');
    if ($base === '' || !is_dir($base)) {
        return ['err' => 'Upload directory is missing. Check config.php upload_dir.'];
    }
    if (!is_writable($base)) {
        return ['err' => 'uploads/ is not writable. Run: chmod 777 uploads'];
    }

    $comms = $base . '/comms';
    if (!is_dir($comms)) {
        if (!@mkdir($comms, 0777, true)) {
            return ['err' => 'Could not create uploads/comms. On the server run: mkdir -p uploads/comms && chmod 777 uploads/comms'];
        }
        @chmod($comms, 0777);
    } elseif (!is_writable($comms)) {
        @chmod($comms, 0777);
        if (!is_writable($comms)) {
            return ['err' => 'uploads/comms is not writable. Run: chmod 777 uploads/comms'];
        }
    }

    $dir = $comms . '/' . $eventId;
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0777, true)) {
            $last = error_get_last();
            $hint = $last['message'] ?? 'permission denied';
            return ['err' => 'Could not create recording folder (' . $hint . '). Run: chmod -R 777 uploads/comms'];
        }
        @chmod($dir, 0777);
    } elseif (!is_writable($dir)) {
        @chmod($dir, 0777);
        if (!is_writable($dir)) {
            return ['err' => 'Recording folder is not writable. Run: chmod -R 777 uploads/comms'];
        }
    }

    return ['dir' => $dir];
}

function commStoreRecording(int $sessionId, int $eventId, array $file, ?int $durationSec = null): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $code = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        return ['err' => 'Upload failed (code ' . $code . '). Check PHP upload_max_filesize.'];
    }
    if (($file['size'] ?? 0) > 80 * 1024 * 1024) {
        return ['err' => 'Recording too large (max 80 MB).'];
    }

    $mime = $file['type'] ?? '';
    $allowed = ['audio/webm', 'audio/wav', 'audio/mpeg', 'audio/mp4', 'audio/ogg', 'video/webm', 'application/octet-stream', ''];
    $ext = strtolower(pathinfo($file['name'] ?? 'recording.webm', PATHINFO_EXTENSION)) ?: 'webm';
    $okExt = in_array($ext, ['webm', 'wav', 'mp3', 'm4a', 'ogg', 'mp4'], true);
    if (!in_array($mime, $allowed, true) && !$okExt) {
        return ['err' => 'Unsupported audio format.'];
    }

    $ensured = commEnsureUploadDir($eventId);
    if (isset($ensured['err'])) return $ensured;
    $dir = $ensured['dir'];

    $name = uniqid('rec_') . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) {
        // Blob uploads sometimes need rename fallback
        if (!@rename($file['tmp_name'], "$dir/$name") && !@copy($file['tmp_name'], "$dir/$name")) {
            return ['err' => 'Could not save recording file. Check folder permissions.'];
        }
        @unlink($file['tmp_name']);
    }
    @chmod("$dir/$name", 0666);

    $rel = 'comms/' . $eventId . '/' . $name;
    execute(
        'INSERT INTO comm_recordings (session_id, file_path, duration_sec, transcribe_status) VALUES (?,?,?,?)',
        [$sessionId, $rel, $durationSec, 'none']
    );
    return ['id' => (int)lastId(), 'file_path' => $rel];
}

function getAiSettings(): array
{
    ensureSettingsColumns();
    $s = getSettings();
    $raw = $s['ai_settings'] ?? '';
    $decoded = is_string($raw) && $raw !== '' ? (json_decode($raw, true) ?: []) : [];
    return array_merge([
        'provider' => 'openrouter',
        'api_key' => '',
        'base_url' => '',
        'model' => '',
        'enable_suggest' => 1,
        'enable_summarize' => 1,
        'max_transcript_chars' => 8000,
    ], $decoded);
}

function saveAiSettings(array $data): void
{
    ensureSettingsColumns();
    $current = getAiSettings();
    $merged = array_merge($current, $data);
    if (($merged['api_key'] ?? '') === '' && ($current['api_key'] ?? '') !== '') {
        $merged['api_key'] = $current['api_key']; // keep existing if left blank
    }
    execute('UPDATE settings SET ai_settings=?, updated_at=NOW() WHERE id=1', [json_encode($merged, JSON_UNESCAPED_UNICODE)]);
}

function buildCommSummaryPayload(array $session, array $answers, array $recordings): array
{
    $qa = [];
    foreach ($answers as $a) {
        $qa[] = [
            'q' => $a['question_text'],
            'a' => trim((string)$a['answer_text']),
        ];
    }
    $transcript = '';
    foreach ($recordings as $r) {
        $t = trim((string)($r['transcript_text'] ?? ''));
        if ($t !== '') $transcript .= ($transcript === '' ? '' : "\n\n") . $t;
    }
    $ai = getAiSettings();
    $max = max(1000, (int)($ai['max_transcript_chars'] ?? 8000));
    if (mb_strlen($transcript) > $max) {
        $transcript = mb_substr($transcript, 0, $max) . "\n…[truncated]";
    }
    return [
        'event_title' => $session['event_title'] ?? '',
        'ceremony_type' => $session['ceremony_type'] ?? '',
        'venue' => $session['venue'] ?? '',
        'customer' => $session['customer_name'] ?? '',
        'session_type' => $session['session_type'] ?? '',
        'session_title' => $session['title'] ?? '',
        'qa' => $qa,
        'transcript' => $transcript,
    ];
}
