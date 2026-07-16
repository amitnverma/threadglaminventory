<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid or expired form token.']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'batch_update') {
    $raw = $_POST['rows'] ?? '[]';
    $rows = is_string($raw) ? json_decode($raw, true) : $raw;
    if (!is_array($rows)) {
        echo json_encode(['ok' => false, 'error' => 'Invalid rows payload.']);
        exit;
    }
    $result = updateInventorySheetRows($rows);
    echo json_encode($result);
    exit;
}

if ($action === 'delete' && !empty($_POST['id'])) {
    execute('UPDATE inventory_items SET deleted_at=NOW() WHERE id=?', [(int)$_POST['id']]);
    echo json_encode(['ok' => true, 'error' => null]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
