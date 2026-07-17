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

if ($action === 'upload_image') {
    $id = (int)($_POST['id'] ?? 0);
    $item = $id > 0
        ? queryOne('SELECT id FROM inventory_items WHERE id=? AND deleted_at IS NULL', [$id])
        : null;
    if (!$item) {
        echo json_encode(['ok' => false, 'error' => 'Inventory item not found.']);
        exit;
    }
    if (empty($_FILES['image']['name']) || !uploadImage('inventory', $id, $_FILES['image'])) {
        echo json_encode(['ok' => false, 'error' => 'Choose a JPG, PNG, GIF, or WebP image.']);
        exit;
    }
    $attachmentId = (int)lastId();
    execute(
        'UPDATE attachments SET sort_order=sort_order+1 WHERE attachable_type=? AND attachable_id=? AND id<>?',
        ['inventory', $id, $attachmentId]
    );
    execute('UPDATE attachments SET sort_order=0 WHERE id=?', [$attachmentId]);

    echo json_encode([
        'ok' => true,
        'image_url' => imgUrl(getInventoryPrimaryImage($id)),
        'error' => null,
    ]);
    exit;
}

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

if ($action === 'bulk_delete') {
    $raw = $_POST['ids'] ?? '[]';
    $ids = is_string($raw) ? json_decode($raw, true) : $raw;
    $ids = is_array($ids)
        ? array_values(array_unique(array_filter(array_map('intval', $ids))))
        : [];
    if (!$ids) {
        echo json_encode(['ok' => false, 'deleted' => 0, 'error' => 'Select at least one inventory item.']);
        exit;
    }

    try {
        dbBegin();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare(
            "UPDATE inventory_items SET deleted_at=NOW() WHERE deleted_at IS NULL AND id IN ($placeholders)"
        );
        $stmt->execute($ids);
        $deleted = $stmt->rowCount();
        dbCommit();
        echo json_encode(['ok' => true, 'deleted' => $deleted, 'error' => null]);
    } catch (Throwable $e) {
        dbRollback();
        echo json_encode(['ok' => false, 'deleted' => 0, 'error' => 'Could not delete the selected items.']);
    }
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
