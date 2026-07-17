<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/decor-inventory-functions.php';
require_once __DIR__ . '/includes/decor-proposal-functions.php';
requireDecorOwner();
ensureDecorInventorySchema();
ensureDecorProposalSchema();

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
    $item = $id > 0 ? decorInventoryGet($id) : null;
    if (!$item) {
        echo json_encode(['ok' => false, 'error' => 'Decor item not found.']);
        exit;
    }
    if (empty($_FILES['image']['name']) || !uploadImage('decor_inventory', $id, $_FILES['image'])) {
        echo json_encode(['ok' => false, 'error' => 'Choose a JPG, PNG, GIF, or WebP image.']);
        exit;
    }
    $attachmentId = (int)lastId();
    execute(
        'UPDATE attachments SET sort_order=sort_order+1 WHERE attachable_type=? AND attachable_id=? AND id<>?',
        ['decor_inventory', $id, $attachmentId]
    );
    execute('UPDATE attachments SET sort_order=0 WHERE id=?', [$attachmentId]);

    echo json_encode([
        'ok' => true,
        'image_url' => imgUrl(getDecorInventoryPrimaryImage(
            $id,
            !empty($item['inventory_item_id']) ? (int)$item['inventory_item_id'] : null
        )),
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
    echo json_encode(decorInventoryUpdateFields($rows));
    exit;
}

if ($action === 'delete' && !empty($_POST['id'])) {
    $err = decorInventoryDelete((int)$_POST['id']);
    echo json_encode(['ok' => !$err, 'error' => $err]);
    exit;
}

if ($action === 'bulk_delete') {
    $raw = $_POST['ids'] ?? '[]';
    $ids = is_string($raw) ? json_decode($raw, true) : $raw;
    $ids = is_array($ids)
        ? array_values(array_unique(array_filter(array_map('intval', $ids))))
        : [];
    if (!$ids) {
        echo json_encode(['ok' => false, 'deleted' => 0, 'error' => 'Select at least one Decor item.']);
        exit;
    }

    try {
        dbBegin();
        foreach ($ids as $id) {
            $item = decorInventoryGet($id);
            $error = decorInventoryDeleteError($id);
            if ($error) {
                $label = $item ? ('"' . $item['name'] . '": ') : '';
                throw new RuntimeException($label . $error);
            }
        }
        foreach ($ids as $id) {
            execute('DELETE FROM decor_inventory_items WHERE id=?', [$id]);
        }
        dbCommit();
        echo json_encode(['ok' => true, 'deleted' => count($ids), 'error' => null]);
    } catch (Throwable $e) {
        dbRollback();
        echo json_encode(['ok' => false, 'deleted' => 0, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
