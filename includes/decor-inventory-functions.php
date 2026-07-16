<?php
/**
 * Decor purchase ledger / owned stock — track spending separately from master
 * inventory, reserve stock to events, and selectively hand off quantities.
 */

require_once __DIR__ . '/functions.php';

function ensureDecorInventorySchema(): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    db()->exec(
        "CREATE TABLE IF NOT EXISTS decor_inventory_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT NULL,
            purchased_from VARCHAR(255) NULL,
            purchase_date DATE NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            quantity_on_hand INT NOT NULL DEFAULT 0,
            unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            default_markup_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
            is_returned TINYINT(1) NOT NULL DEFAULT 0,
            returned_at DATE NULL,
            refund_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            notes TEXT NULL,
            created_by INT NULL,
            inventory_item_id INT NULL,
            transfer_mode VARCHAR(32) NULL,
            transferred_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_decor_purchase_date (purchase_date),
            INDEX idx_decor_returned (is_returned),
            INDEX idx_decor_transferred (transferred_at),
            INDEX idx_decor_inventory_item (inventory_item_id),
            INDEX idx_decor_qty_on_hand (quantity_on_hand)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    decorEnsureColumn('decor_inventory_items', 'quantity_on_hand', "INT NOT NULL DEFAULT 0 AFTER quantity");
    decorEnsureColumn('decor_inventory_items', 'default_markup_percent', "DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER line_total");

    backfillDecorInventoryCategories();

    db()->exec(
        "CREATE TABLE IF NOT EXISTS decor_inventory_handoffs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            decor_inventory_item_id INT NOT NULL,
            inventory_item_id INT NOT NULL,
            quantity INT NOT NULL,
            unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
            transfer_mode VARCHAR(32) NOT NULL,
            notes VARCHAR(255) NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_decor_handoff_item (decor_inventory_item_id),
            INDEX idx_decor_handoff_inventory (inventory_item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // One-time migration for legacy rows that never had quantity_on_hand populated.
    try {
        $needs = queryOne(
            "SELECT COUNT(*) AS n FROM decor_inventory_items
             WHERE quantity_on_hand = 0
               AND quantity > 0
               AND is_returned = 0
               AND transferred_at IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM decor_inventory_handoffs h WHERE h.decor_inventory_item_id = decor_inventory_items.id
               )"
        );
        // Legacy open rows: treat purchased qty as owned stock when never handed off.
        execute(
            "UPDATE decor_inventory_items
             SET quantity_on_hand = quantity
             WHERE quantity_on_hand = 0
               AND quantity > 0
               AND is_returned = 0
               AND transferred_at IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM decor_inventory_handoffs h WHERE h.decor_inventory_item_id = decor_inventory_items.id
               )"
        );

        // Legacy fully transferred rows → on_hand already 0; seed handoff audit once.
        $legacy = query(
            "SELECT d.* FROM decor_inventory_items d
             WHERE d.transferred_at IS NOT NULL
               AND d.inventory_item_id IS NOT NULL
               AND NOT EXISTS (
                   SELECT 1 FROM decor_inventory_handoffs h WHERE h.decor_inventory_item_id = d.id
               )"
        );
        foreach ($legacy as $row) {
            execute(
                "INSERT INTO decor_inventory_handoffs
                    (decor_inventory_item_id, inventory_item_id, quantity, unit_cost, transfer_mode, notes, created_by)
                 VALUES (?,?,?,?,?,?,?)",
                [
                    (int)$row['id'],
                    (int)$row['inventory_item_id'],
                    (int)$row['quantity'],
                    (float)$row['unit_price'],
                    $row['transfer_mode'] ?: 'legacy',
                    'Migrated from legacy full transfer',
                    $row['created_by'] !== null ? (int)$row['created_by'] : null,
                ]
            );
            execute(
                'UPDATE decor_inventory_items SET quantity_on_hand=0 WHERE id=?',
                [(int)$row['id']]
            );
        }

        // Returned with leftover on-hand (shouldn't happen) → force zero.
        execute(
            'UPDATE decor_inventory_items SET quantity_on_hand=0 WHERE is_returned=1 AND quantity_on_hand<>0'
        );
    } catch (Exception $e) {
        // ignore migration failures on incomplete installs
    }

    unset($needs);
}

function decorEnsureColumn(string $table, string $column, string $definition): void
{
    try {
        $safeTable = preg_replace('/[^a-z0-9_]/i', '', $table);
        $safeColumn = preg_replace('/[^a-z0-9_]/i', '', $column);
        $cols = query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        if (empty($cols)) {
            execute("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$definition}");
        }
    } catch (Exception $e) {
        // ignore
    }
}

function decorInventoryGet(int $id): ?array
{
    return queryOne('SELECT * FROM decor_inventory_items WHERE id=?', [$id]);
}

/**
 * Active reservation quantity for an item overlapping [start, end] (inclusive dates).
 * Optionally exclude a reservation id (when editing) or only for a given event.
 */
function decorReservedQuantity(
    int $itemId,
    ?string $startDate = null,
    ?string $endDate = null,
    ?int $excludeReservationId = null
): int {
    // Table may not exist until proposal schema is bootstrapped.
    try {
        $tables = query("SHOW TABLES LIKE 'decor_inventory_reservations'");
        if (empty($tables)) return 0;
    } catch (Exception $e) {
        return 0;
    }

    $where = ["r.decor_inventory_item_id = ?", "r.status IN ('reserved','checked_out')"];
    $params = [$itemId];

    if ($startDate && $endDate) {
        $where[] = 'r.start_date <= ? AND r.end_date >= ?';
        $params[] = $endDate;
        $params[] = $startDate;
    }

    if ($excludeReservationId) {
        $where[] = 'r.id <> ?';
        $params[] = $excludeReservationId;
    }

    $sql = 'SELECT COALESCE(SUM(r.quantity), 0) AS qty FROM decor_inventory_reservations r WHERE '
        . implode(' AND ', $where);
    $row = queryOne($sql, $params);
    return (int)($row['qty'] ?? 0);
}

function decorAvailableQuantity(array $item, ?string $startDate = null, ?string $endDate = null, ?int $excludeReservationId = null): int
{
    $owned = (int)($item['quantity_on_hand'] ?? 0);
    if ($owned <= 0) return 0;
    $reserved = decorReservedQuantity((int)$item['id'], $startDate, $endDate, $excludeReservationId);
    return max(0, $owned - $reserved);
}

function decorSuggestedRate(float $unitCost, float $markupPercent): float
{
    return round($unitCost * (1 + ($markupPercent / 100)), 2);
}

/**
 * @return array{sql:string,params:array}
 */
function decorInventoryListFilters(array $filters = []): array
{
    $where = ['1=1'];
    $params = [];

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(d.name LIKE ? OR d.purchased_from LIKE ? OR d.description LIKE ? OR d.notes LIKE ?)';
        $like = '%' . $search . '%';
        $params = array_merge($params, [$like, $like, $like, $like]);
    }

    $status = (string)($filters['status'] ?? '');
    if ($status === 'available') {
        $where[] = 'd.is_returned=0 AND d.quantity_on_hand > 0';
    } elseif ($status === 'returned') {
        $where[] = 'd.is_returned=1';
    } elseif ($status === 'handed_off') {
        $where[] = 'EXISTS (SELECT 1 FROM decor_inventory_handoffs h WHERE h.decor_inventory_item_id=d.id)';
    } elseif ($status === 'depleted') {
        $where[] = 'd.is_returned=0 AND d.quantity_on_hand=0';
    } elseif ($status === 'open' || $status === 'transferable') {
        $where[] = 'd.is_returned=0 AND d.quantity_on_hand > 0';
    }

    return ['sql' => implode(' AND ', $where), 'params' => $params];
}

function decorInventoryList(array $filters = []): array
{
    $f = decorInventoryListFilters($filters);
    $rows = query(
        "SELECT d.*,
                (SELECT COALESCE(SUM(h.quantity),0) FROM decor_inventory_handoffs h WHERE h.decor_inventory_item_id=d.id) AS handed_off_qty
         FROM decor_inventory_items d
         WHERE {$f['sql']}
         ORDER BY d.purchase_date DESC, d.id DESC",
        $f['params']
    );

    foreach ($rows as &$row) {
        $row['reserved_qty'] = decorReservedQuantity((int)$row['id']);
        $row['available_qty'] = max(0, (int)$row['quantity_on_hand'] - (int)$row['reserved_qty']);
    }
    unset($row);
    return $rows;
}

function decorInventorySummary(array $filters = []): array
{
    $f = decorInventoryListFilters($filters);
    $row = queryOne(
        "SELECT
            COALESCE(SUM(d.line_total), 0) AS purchased_total,
            COALESCE(SUM(CASE WHEN d.is_returned=1 THEN d.refund_amount ELSE 0 END), 0) AS refunded_total,
            COALESCE(SUM(d.quantity_on_hand * d.unit_price), 0) AS owned_value,
            COALESCE(SUM(d.quantity_on_hand), 0) AS owned_units,
            COUNT(*) AS item_count
         FROM decor_inventory_items d
         WHERE {$f['sql']}",
        $f['params']
    ) ?: [];

    $purchased = (float)($row['purchased_total'] ?? 0);
    $refunded = (float)($row['refunded_total'] ?? 0);
    return [
        'purchased_total' => $purchased,
        'refunded_total' => $refunded,
        'net_total' => $purchased - $refunded,
        'owned_value' => (float)($row['owned_value'] ?? 0),
        'owned_units' => (int)($row['owned_units'] ?? 0),
        'item_count' => (int)($row['item_count'] ?? 0),
    ];
}

function decorInventoryIsEditable(array $item): bool
{
    // Editable unless fully returned; handed-off history does not lock the row.
    return true;
}

function decorInventoryIsHandoffable(array $item): bool
{
    return !(int)($item['is_returned'] ?? 0) && (int)($item['quantity_on_hand'] ?? 0) > 0;
}

/**
 * Validate create/update payload. Returns [data|null, error|null].
 */
function decorInventoryValidateInput(array $input, ?array $existing = null): array
{
    $name = trim((string)($input['name'] ?? ''));
    if ($name === '') return [null, 'Item name is required.'];
    if (strlen($name) > 255) return [null, 'Item name is too long.'];

    $purchaseDate = trim((string)($input['purchase_date'] ?? ''));
    if ($purchaseDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchaseDate)) {
        return [null, 'Purchase date is required.'];
    }

    $qty = (int)($input['quantity'] ?? 0);
    if ($qty < 1) return [null, 'Purchased quantity must be at least 1.'];

    $unitPrice = round((float)($input['unit_price'] ?? 0), 2);
    if ($unitPrice < 0) return [null, 'Unit price cannot be negative.'];

    $markup = round((float)($input['default_markup_percent'] ?? 0), 2);
    if ($markup < 0) return [null, 'Markup percent cannot be negative.'];

    $isReturned = !empty($input['is_returned']) ? 1 : 0;
    $returnedAt = trim((string)($input['returned_at'] ?? ''));
    $refundAmount = round((float)($input['refund_amount'] ?? 0), 2);

    if ($isReturned) {
        if ($returnedAt === '') {
            $returnedAt = $purchaseDate;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $returnedAt)) {
            return [null, 'Return date is invalid.'];
        }
        if ($refundAmount < 0) return [null, 'Refund amount cannot be negative.'];
        $maxRefund = round($qty * $unitPrice, 2);
        if ($refundAmount > $maxRefund) {
            return [null, 'Refund amount cannot exceed the purchase total.'];
        }
    } else {
        $returnedAt = null;
        $refundAmount = 0;
    }

    // Quantity on hand: for create, equals purchased qty (or 0 if returned).
    // For update, adjust on_hand by purchased-qty delta, but never below active reservations / existing handoffs.
    if ($existing) {
        $oldPurchased = (int)$existing['quantity'];
        $oldOnHand = (int)$existing['quantity_on_hand'];
        $delta = $qty - $oldPurchased;
        $onHand = $oldOnHand + $delta;
        if ($isReturned) {
            $onHand = 0;
        }
        if ($onHand < 0) {
            return [null, 'Cannot reduce purchased quantity below what is already handed off or used.'];
        }
        $reserved = decorReservedQuantity((int)$existing['id']);
        if ($onHand < $reserved) {
            return [null, 'Cannot set owned quantity below currently reserved units (' . $reserved . ').'];
        }
    } else {
        $onHand = $isReturned ? 0 : $qty;
    }

    $data = [
        'name' => $name,
        'description' => trim((string)($input['description'] ?? '')) ?: null,
        'purchased_from' => trim((string)($input['purchased_from'] ?? '')) ?: null,
        'purchase_date' => $purchaseDate,
        'quantity' => $qty,
        'quantity_on_hand' => $onHand,
        'unit_price' => $unitPrice,
        'line_total' => round($qty * $unitPrice, 2),
        'default_markup_percent' => $markup,
        'is_returned' => $isReturned,
        'returned_at' => $returnedAt,
        'refund_amount' => $refundAmount,
        'notes' => trim((string)($input['notes'] ?? '')) ?: null,
    ];

    return [$data, null];
}

/** Returns error message or null on success. */
function decorInventoryCreate(array $input): ?string
{
    [$data, $err] = decorInventoryValidateInput($input);
    if ($err) return $err;

    $me = currentAdmin();
    execute(
        'INSERT INTO decor_inventory_items
            (name, description, purchased_from, purchase_date, quantity, quantity_on_hand, unit_price, line_total,
             default_markup_percent, is_returned, returned_at, refund_amount, notes, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            $data['name'], $data['description'], $data['purchased_from'], $data['purchase_date'],
            $data['quantity'], $data['quantity_on_hand'], $data['unit_price'], $data['line_total'],
            $data['default_markup_percent'],
            $data['is_returned'], $data['returned_at'], $data['refund_amount'], $data['notes'],
            $me ? (int)$me['id'] : null,
        ]
    );
    return null;
}

/**
 * Create many Decor purchase/stock rows sharing vendor + date (one receipt).
 *
 * @param array{purchased_from?:?string,purchase_date?:string,notes?:?string} $header
 * @param list<array{name?:string,quantity?:int,unit_price?:float,default_markup_percent?:float,description?:?string}> $lines
 * @return array{ok:bool,created:int,error:?string}
 */
function decorInventoryCreateMany(array $header, array $lines): array
{
    $purchaseDate = trim((string)($header['purchase_date'] ?? date('Y-m-d')));
    $purchasedFrom = trim((string)($header['purchased_from'] ?? '')) ?: null;
    $notes = trim((string)($header['notes'] ?? '')) ?: null;

    $normalized = [];
    foreach ($lines as $line) {
        $name = trim((string)($line['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $normalized[] = [
            'name' => $name,
            'description' => $line['description'] ?? null,
            'purchased_from' => $purchasedFrom,
            'purchase_date' => $purchaseDate,
            'quantity' => (int)($line['quantity'] ?? 1),
            'unit_price' => (float)($line['unit_price'] ?? 0),
            'default_markup_percent' => (float)($line['default_markup_percent'] ?? 0),
            'notes' => $notes,
            'is_returned' => 0,
        ];
    }

    if (empty($normalized)) {
        return ['ok' => false, 'created' => 0, 'error' => 'Add at least one item row.'];
    }

    try {
        dbBegin();
        $created = 0;
        foreach ($normalized as $input) {
            $err = decorInventoryCreate($input);
            if ($err) {
                dbRollback();
                return ['ok' => false, 'created' => 0, 'error' => $err];
            }
            $created++;
        }
        dbCommit();
        return ['ok' => true, 'created' => $created, 'error' => null];
    } catch (Throwable $e) {
        dbRollback();
        return ['ok' => false, 'created' => 0, 'error' => 'Could not save Decor purchases.'];
    }
}

/**
 * Inline grid field updates for Decor inventory.
 *
 * @param list<array{id:int,name?:string,purchased_from?:?string,purchase_date?:string,quantity?:int,unit_price?:float,default_markup_percent?:float}> $rows
 * @return array{ok:bool,updated:int,error:?string}
 */
function decorInventoryUpdateFields(array $rows): array
{
    $updated = 0;
    try {
        dbBegin();
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $item = decorInventoryGet($id);
            if (!$item) {
                continue;
            }

            $payload = [
                'name' => $row['name'] ?? $item['name'],
                'description' => $item['description'],
                'purchased_from' => array_key_exists('purchased_from', $row) ? $row['purchased_from'] : $item['purchased_from'],
                'purchase_date' => $row['purchase_date'] ?? $item['purchase_date'],
                'quantity' => $row['quantity'] ?? $item['quantity'],
                'unit_price' => $row['unit_price'] ?? $item['unit_price'],
                'default_markup_percent' => $row['default_markup_percent'] ?? $item['default_markup_percent'],
                'is_returned' => (int)$item['is_returned'] === 1,
                'returned_at' => $item['returned_at'],
                'refund_amount' => $item['refund_amount'],
                'notes' => $item['notes'],
            ];

            $err = decorInventoryUpdate($id, $payload);
            if ($err) {
                dbRollback();
                return ['ok' => false, 'updated' => 0, 'error' => $err];
            }
            $updated++;
        }
        dbCommit();
        return ['ok' => true, 'updated' => $updated, 'error' => null];
    } catch (Throwable $e) {
        dbRollback();
        return ['ok' => false, 'updated' => 0, 'error' => 'Could not save Decor changes.'];
    }
}

/** Returns error message or null on success. */
function decorInventoryUpdate(int $id, array $input): ?string
{
    $item = decorInventoryGet($id);
    if (!$item) return 'Decor item not found.';

    [$data, $err] = decorInventoryValidateInput($input, $item);
    if ($err) return $err;

    execute(
        'UPDATE decor_inventory_items SET
            name=?, description=?, purchased_from=?, purchase_date=?, quantity=?, quantity_on_hand=?,
            unit_price=?, line_total=?, default_markup_percent=?,
            is_returned=?, returned_at=?, refund_amount=?, notes=?, updated_at=NOW()
         WHERE id=?',
        [
            $data['name'], $data['description'], $data['purchased_from'], $data['purchase_date'],
            $data['quantity'], $data['quantity_on_hand'],
            $data['unit_price'], $data['line_total'], $data['default_markup_percent'],
            $data['is_returned'], $data['returned_at'], $data['refund_amount'], $data['notes'],
            $id,
        ]
    );
    return null;
}

/** Returns error message or null on success. */
function decorInventoryMarkReturned(int $id, string $returnedAt, float $refundAmount): ?string
{
    $item = decorInventoryGet($id);
    if (!$item) return 'Decor item not found.';
    if ((int)$item['is_returned'] === 1) return 'Item is already marked returned.';

    $reserved = decorReservedQuantity($id);
    if ($reserved > 0) {
        return 'Cancel or check in active event reservations before marking returned.';
    }
    if ($returnedAt === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $returnedAt)) {
        return 'Return date is invalid.';
    }
    $refundAmount = round($refundAmount, 2);
    if ($refundAmount < 0) return 'Refund amount cannot be negative.';
    if ($refundAmount > (float)$item['line_total']) {
        return 'Refund amount cannot exceed the purchase total.';
    }

    execute(
        'UPDATE decor_inventory_items
         SET is_returned=1, returned_at=?, refund_amount=?, quantity_on_hand=0, updated_at=NOW()
         WHERE id=?',
        [$returnedAt, $refundAmount, $id]
    );
    return null;
}

/** Returns the reason an item cannot be deleted, or null when deletion is allowed. */
function decorInventoryDeleteError(int $id): ?string
{
    $item = decorInventoryGet($id);
    if (!$item) return 'Decor item not found.';

    $handoffs = queryOne(
        'SELECT COUNT(*) AS n FROM decor_inventory_handoffs WHERE decor_inventory_item_id=?',
        [$id]
    );
    if ((int)($handoffs['n'] ?? 0) > 0 || !empty($item['transferred_at'])) {
        return 'Items with master-inventory handoff history cannot be deleted.';
    }

    $reserved = decorReservedQuantity($id);
    if ($reserved > 0) {
        return 'Cancel event reservations before deleting this item.';
    }

    return null;
}

/** Returns error message or null on success. */
function decorInventoryDelete(int $id): ?string
{
    $error = decorInventoryDeleteError($id);
    if ($error) return $error;

    execute('DELETE FROM decor_inventory_items WHERE id=?', [$id]);
    return null;
}

/**
 * Partial or full handoff of Decor-owned quantity into master inventory.
 *
 * $map: ['mode'=>'new'|'existing', 'category_id'=>?, 'inventory_item_id'=>?, 'quantity'=>int]
 * Returns [inventoryItemId|null, error|null]
 */
function decorInventoryHandoff(int $id, array $map): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM decor_inventory_items WHERE id=? FOR UPDATE');
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            throw new RuntimeException('Decor item not found.');
        }
        if ((int)$item['is_returned'] === 1) {
            throw new RuntimeException('Returned items cannot be transferred.');
        }

        $qty = (int)($map['quantity'] ?? 0);
        if ($qty < 1) {
            throw new RuntimeException('Transfer quantity must be at least 1.');
        }

        $owned = (int)$item['quantity_on_hand'];
        $reserved = decorReservedQuantity($id);
        $free = max(0, $owned - $reserved);
        if ($qty > $free) {
            throw new RuntimeException(
                '"' . $item['name'] . '" has only ' . $free . ' unit(s) free to transfer'
                . ($reserved ? " ({$reserved} reserved for events)" : '') . '.'
            );
        }

        $mode = $map['mode'] ?? '';
        $unitCost = (float)$item['unit_price'];
        $reason = 'Decor handoff — ' . $item['name'] . ' (decor #' . $id . ', qty ' . $qty . ')';
        $inventoryId = null;

        if ($mode === 'new') {
            $categoryId = !empty($map['category_id'])
                ? (int)$map['category_id']
                : getOrCreateInventoryCategoryId('Decor');
            if (!empty($map['category_id'])) {
                $cat = queryOne('SELECT id FROM inventory_categories WHERE id=?', [$categoryId]);
                if (!$cat) {
                    throw new RuntimeException('Invalid category.');
                }
            }
            $inventoryId = createInventoryFromPurchase(
                $item['name'],
                $qty,
                $unitCost,
                $categoryId,
                $reason
            );
            if (!empty($item['description'])) {
                execute('UPDATE inventory_items SET description=? WHERE id=?', [$item['description'], $inventoryId]);
            }
        } elseif ($mode === 'existing') {
            $inventoryId = (int)($map['inventory_item_id'] ?? 0);
            $target = queryOne(
                'SELECT id FROM inventory_items WHERE id=? AND deleted_at IS NULL',
                [$inventoryId]
            );
            if (!$target) {
                throw new RuntimeException('Select a valid inventory item.');
            }
            addInventoryStock($inventoryId, $qty, $unitCost, $reason);
        } else {
            throw new RuntimeException('Choose create new or add to existing.');
        }

        $newOwned = $owned - $qty;
        $me = currentAdmin();
        execute(
            'UPDATE decor_inventory_items
             SET quantity_on_hand=?,
                 inventory_item_id=COALESCE(inventory_item_id, ?),
                 transfer_mode=?,
                 transferred_at=CASE WHEN ?=0 THEN NOW() ELSE transferred_at END,
                 updated_at=NOW()
             WHERE id=?',
            [$newOwned, $inventoryId, $mode, $newOwned, $id]
        );

        execute(
            'INSERT INTO decor_inventory_handoffs
                (decor_inventory_item_id, inventory_item_id, quantity, unit_cost, transfer_mode, notes, created_by)
             VALUES (?,?,?,?,?,?,?)',
            [
                $id,
                $inventoryId,
                $qty,
                $unitCost,
                $mode,
                null,
                $me ? (int)$me['id'] : null,
            ]
        );

        $pdo->commit();
        return [$inventoryId, null];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [null, $e->getMessage()];
    }
}

/**
 * Batch handoff. $mappings keyed by decor item id.
 * Returns [count, error|null]
 */
function decorInventoryTransferBatch(array $ids, array $mappings): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return [0, 'No items selected.'];

    $count = 0;
    foreach ($ids as $id) {
        $map = $mappings[$id] ?? $mappings[(string)$id] ?? null;
        if (!$map) return [0, 'Choose a transfer option for each selected item.'];
        if (empty($map['quantity'])) {
            $item = decorInventoryGet($id);
            $map['quantity'] = $item ? (int)$item['quantity_on_hand'] : 0;
        }
        [, $err] = decorInventoryHandoff($id, $map);
        if ($err) return [$count, $err];
        $count++;
    }
    return [$count, null];
}

function decorInventoryHandoffs(int $itemId): array
{
    return query(
        "SELECT h.*, i.name AS inventory_name, i.sku AS inventory_sku
         FROM decor_inventory_handoffs h
         LEFT JOIN inventory_items i ON i.id = h.inventory_item_id
         WHERE h.decor_inventory_item_id=?
         ORDER BY h.created_at DESC, h.id DESC",
        [$itemId]
    );
}
