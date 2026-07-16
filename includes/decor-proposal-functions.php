<?php
/**
 * Decor event proposals — reserve stock to customer events, price lines,
 * and publish into the main estimate + inventory system.
 */

require_once __DIR__ . '/decor-inventory-functions.php';

function ensureDecorProposalSchema(): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    ensureDecorInventorySchema();

    db()->exec(
        "CREATE TABLE IF NOT EXISTS decor_proposals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            customer_id INT NOT NULL,
            estimate_id INT NULL,
            title VARCHAR(255) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'draft',
            subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
            tax_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
            tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            discount_type ENUM('percent','flat') NOT NULL DEFAULT 'percent',
            discount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
            discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            total DECIMAL(12,2) NOT NULL DEFAULT 0,
            private_cost_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            notes TEXT NULL,
            published_at DATETIME NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_decor_proposal_event (event_id),
            INDEX idx_decor_proposal_customer (customer_id),
            INDEX idx_decor_proposal_estimate (estimate_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    db()->exec(
        "CREATE TABLE IF NOT EXISTS decor_proposal_lines (
            id INT AUTO_INCREMENT PRIMARY KEY,
            proposal_id INT NOT NULL,
            line_type VARCHAR(32) NOT NULL DEFAULT 'decor',
            decor_inventory_item_id INT NULL,
            reservation_id INT NULL,
            label VARCHAR(255) NOT NULL,
            description TEXT NULL,
            quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
            unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
            markup_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
            unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_decor_proposal_lines_proposal (proposal_id),
            INDEX idx_decor_proposal_lines_item (decor_inventory_item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    db()->exec(
        "CREATE TABLE IF NOT EXISTS decor_inventory_reservations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            decor_inventory_item_id INT NOT NULL,
            event_id INT NOT NULL,
            proposal_id INT NULL,
            proposal_line_id INT NULL,
            quantity INT NOT NULL DEFAULT 1,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'reserved',
            checked_out_at DATETIME NULL,
            checked_in_at DATETIME NULL,
            notes TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_decor_res_item (decor_inventory_item_id),
            INDEX idx_decor_res_event (event_id),
            INDEX idx_decor_res_status (status),
            INDEX idx_decor_res_dates (start_date, end_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    decorEnsureColumn('estimate_line_items', 'source_type', "VARCHAR(32) NULL AFTER notes");
    decorEnsureColumn('estimate_line_items', 'source_id', "INT NULL AFTER source_type");
}

function decorEventDateRange(array $event): array
{
    $start = $event['event_date'] ?: date('Y-m-d');
    $end = $event['end_date'] ?: $start;
    if ($end < $start) {
        $end = $start;
    }
    return [$start, $end];
}

function decorGetEvent(int $eventId): ?array
{
    return queryOne(
        "SELECT e.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email
         FROM events e
         JOIN customers c ON c.id = e.customer_id
         WHERE e.id=? AND e.deleted_at IS NULL AND c.deleted_at IS NULL",
        [$eventId]
    );
}

function decorEventsForWorkspace(array $filters = []): array
{
    $where = ['e.deleted_at IS NULL', 'c.deleted_at IS NULL'];
    $params = [];

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(e.title LIKE ? OR c.name LIKE ? OR e.venue LIKE ?)';
        $like = '%' . $search . '%';
        $params = array_merge($params, [$like, $like, $like]);
    }

    $status = trim((string)($filters['status'] ?? ''));
    if ($status !== '') {
        $where[] = 'e.status=?';
        $params[] = $status;
    }

    $sql = 'SELECT e.*, c.name AS customer_name,
                   p.id AS proposal_id, p.status AS proposal_status, p.total AS proposal_total,
                   p.private_cost_total, p.estimate_id, p.published_at,
                   (SELECT COUNT(*) FROM decor_inventory_reservations r
                     WHERE r.event_id=e.id AND r.status IN (\'reserved\',\'checked_out\')) AS active_reservations
            FROM events e
            JOIN customers c ON c.id=e.customer_id
            LEFT JOIN decor_proposals p ON p.event_id=e.id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY COALESCE(e.event_date, e.created_at) DESC, e.id DESC';

    return query($sql, $params);
}

function decorProposalForEvent(int $eventId): ?array
{
    return queryOne('SELECT * FROM decor_proposals WHERE event_id=?', [$eventId]);
}

function decorProposalGet(int $id): ?array
{
    return queryOne('SELECT * FROM decor_proposals WHERE id=?', [$id]);
}

function decorProposalLines(int $proposalId): array
{
    return query(
        "SELECT l.*,
                i.name AS decor_item_name,
                i.quantity_on_hand,
                r.status AS reservation_status,
                r.start_date AS reservation_start,
                r.end_date AS reservation_end,
                r.quantity AS reservation_qty
         FROM decor_proposal_lines l
         LEFT JOIN decor_inventory_items i ON i.id = l.decor_inventory_item_id
         LEFT JOIN decor_inventory_reservations r ON r.id = l.reservation_id
         WHERE l.proposal_id=?
         ORDER BY l.sort_order, l.id",
        [$proposalId]
    );
}

function decorEventReservations(int $eventId): array
{
    return query(
        "SELECT r.*, i.name AS item_name, i.unit_price, i.purchased_from
         FROM decor_inventory_reservations r
         JOIN decor_inventory_items i ON i.id = r.decor_inventory_item_id
         WHERE r.event_id=?
         ORDER BY FIELD(r.status,'checked_out','reserved','checked_in','cancelled'), r.start_date, r.id",
        [$eventId]
    );
}

/**
 * Ensure a draft proposal exists for the event. Creates one if missing.
 */
function decorEnsureProposalForEvent(array $event): array
{
    $existing = decorProposalForEvent((int)$event['id']);
    if ($existing) return $existing;

    $settings = getSettings();
    $me = currentAdmin();
    $title = 'Decor proposal — ' . ($event['title'] ?: 'Event');
    execute(
        "INSERT INTO decor_proposals
            (event_id, customer_id, title, status, tax_percent, created_by)
         VALUES (?,?,?,'draft',?,?)",
        [
            (int)$event['id'],
            (int)$event['customer_id'],
            $title,
            (float)($settings['default_tax_percent'] ?? 0),
            $me ? (int)$me['id'] : null,
        ]
    );
    return decorProposalGet((int)lastId());
}

function decorCalculateProposalTotals(array $lines, array $opts = []): array
{
    $subtotal = 0.0;
    $privateCost = 0.0;
    foreach ($lines as $line) {
        $qty = (float)($line['quantity'] ?? 0);
        $price = (float)($line['unit_price'] ?? 0);
        $cost = (float)($line['unit_cost'] ?? 0);
        $subtotal += $qty * $price;
        $privateCost += $qty * $cost;
    }

    $discountType = $opts['discount_type'] ?? 'percent';
    $discountValue = (float)($opts['discount_value'] ?? 0);
    $discountAmount = $discountType === 'flat'
        ? $discountValue
        : $subtotal * $discountValue / 100;
    $discountAmount = max(0, min($subtotal, $discountAmount));

    $taxPercent = (float)($opts['tax_percent'] ?? 0);
    $taxable = max(0, $subtotal - $discountAmount);
    $taxAmount = $taxable * $taxPercent / 100;
    $total = $taxable + $taxAmount;

    return [
        'subtotal' => round($subtotal, 2),
        'discount_amount' => round($discountAmount, 2),
        'tax_amount' => round($taxAmount, 2),
        'total' => round($total, 2),
        'private_cost_total' => round($privateCost, 2),
        'margin' => round($subtotal - $privateCost, 2),
    ];
}

function decorRecalcProposal(int $proposalId): void
{
    $proposal = decorProposalGet($proposalId);
    if (!$proposal) return;
    $lines = decorProposalLines($proposalId);
    $totals = decorCalculateProposalTotals($lines, $proposal);
    execute(
        'UPDATE decor_proposals SET subtotal=?, tax_amount=?, discount_amount=?, total=?, private_cost_total=?, updated_at=NOW() WHERE id=?',
        [
            $totals['subtotal'],
            $totals['tax_amount'],
            $totals['discount_amount'],
            $totals['total'],
            $totals['private_cost_total'],
            $proposalId,
        ]
    );
}

/**
 * Add a Decor inventory item to an event proposal (creates reservation).
 * Returns [lineId|null, error|null]
 */
function decorProposalAddItem(int $eventId, int $itemId, int $quantity, ?float $unitPrice = null, ?float $markup = null): array
{
    $event = decorGetEvent($eventId);
    if (!$event) return [null, 'Event not found.'];
    if ($quantity < 1) return [null, 'Quantity must be at least 1.'];

    [$start, $end] = decorEventDateRange($event);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM decor_inventory_items WHERE id=? FOR UPDATE');
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            throw new RuntimeException('Decor item not found.');
        }
        if ((int)$item['is_returned'] === 1) {
            throw new RuntimeException('Returned items cannot be reserved.');
        }

        $available = decorAvailableQuantity($item, $start, $end);
        if ($quantity > $available) {
            throw new RuntimeException(
                'Only ' . $available . ' unit(s) available for these event dates.'
            );
        }

        $proposal = decorEnsureProposalForEvent($event);
        $markupPct = $markup !== null ? round($markup, 2) : (float)$item['default_markup_percent'];
        $cost = (float)$item['unit_price'];
        $rate = $unitPrice !== null ? round($unitPrice, 2) : decorSuggestedRate($cost, $markupPct);

        $me = currentAdmin();
        execute(
            "INSERT INTO decor_inventory_reservations
                (decor_inventory_item_id, event_id, proposal_id, quantity, start_date, end_date, status, created_by)
             VALUES (?,?,?,?,?,?,'reserved',?)",
            [$itemId, $eventId, (int)$proposal['id'], $quantity, $start, $end, $me ? (int)$me['id'] : null]
        );
        $reservationId = (int)lastId();

        $sort = queryOne('SELECT COALESCE(MAX(sort_order),-1) AS m FROM decor_proposal_lines WHERE proposal_id=?', [(int)$proposal['id']]);
        execute(
            "INSERT INTO decor_proposal_lines
                (proposal_id, line_type, decor_inventory_item_id, reservation_id, label, description,
                 quantity, unit_cost, markup_percent, unit_price, sort_order)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [
                (int)$proposal['id'],
                'decor',
                $itemId,
                $reservationId,
                $item['name'],
                $item['description'],
                $quantity,
                $cost,
                $markupPct,
                $rate,
                ((int)($sort['m'] ?? -1)) + 1,
            ]
        );
        $lineId = (int)lastId();

        execute(
            'UPDATE decor_inventory_reservations SET proposal_line_id=? WHERE id=?',
            [$lineId, $reservationId]
        );

        decorRecalcProposal((int)$proposal['id']);
        $pdo->commit();
        return [$lineId, null];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [null, $e->getMessage()];
    }
}

/**
 * Add a custom/labor line (no reservation).
 */
function decorProposalAddCustomLine(int $eventId, string $label, float $qty, float $unitPrice, float $unitCost = 0, string $lineType = 'custom'): array
{
    $event = decorGetEvent($eventId);
    if (!$event) return [null, 'Event not found.'];
    $label = trim($label);
    if ($label === '') return [null, 'Label is required.'];
    if ($qty <= 0) return [null, 'Quantity must be positive.'];
    if (!in_array($lineType, ['custom', 'labor'], true)) $lineType = 'custom';

    $proposal = decorEnsureProposalForEvent($event);
    $sort = queryOne('SELECT COALESCE(MAX(sort_order),-1) AS m FROM decor_proposal_lines WHERE proposal_id=?', [(int)$proposal['id']]);
    execute(
        "INSERT INTO decor_proposal_lines
            (proposal_id, line_type, label, quantity, unit_cost, markup_percent, unit_price, sort_order)
         VALUES (?,?,?,?,?,?,?,?)",
        [
            (int)$proposal['id'],
            $lineType,
            $label,
            $qty,
            round($unitCost, 2),
            0,
            round($unitPrice, 2),
            ((int)($sort['m'] ?? -1)) + 1,
        ]
    );
    $lineId = (int)lastId();
    decorRecalcProposal((int)$proposal['id']);
    return [$lineId, null];
}

/** Update a proposal line quantity/rate/markup. Returns error or null. */
function decorProposalUpdateLine(int $lineId, array $input): ?string
{
    $line = queryOne('SELECT * FROM decor_proposal_lines WHERE id=?', [$lineId]);
    if (!$line) return 'Line not found.';
    $proposal = decorProposalGet((int)$line['proposal_id']);
    if (!$proposal) return 'Proposal not found.';
    $event = decorGetEvent((int)$proposal['event_id']);
    if (!$event) return 'Event not found.';

    $qty = (float)($input['quantity'] ?? $line['quantity']);
    if ($qty <= 0) return 'Quantity must be positive.';

    $markup = isset($input['markup_percent'])
        ? round((float)$input['markup_percent'], 2)
        : (float)$line['markup_percent'];
    if ($markup < 0) return 'Markup cannot be negative.';

    $unitCost = (float)$line['unit_cost'];
    if (array_key_exists('unit_cost', $input) && in_array($line['line_type'], ['custom', 'labor'], true)) {
        $unitCost = round((float)$input['unit_cost'], 2);
        if ($unitCost < 0) return 'Cost cannot be negative.';
    }

    if (isset($input['unit_price']) && $input['unit_price'] !== '') {
        $unitPrice = round((float)$input['unit_price'], 2);
    } else {
        $unitPrice = decorSuggestedRate($unitCost, $markup);
    }
    if ($unitPrice < 0) return 'Rate cannot be negative.';

    $label = trim((string)($input['label'] ?? $line['label']));
    if ($label === '') return 'Label is required.';
    $description = array_key_exists('description', $input)
        ? (trim((string)$input['description']) ?: null)
        : $line['description'];

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($line['decor_inventory_item_id'] && $line['reservation_id']) {
            $intQty = (int)ceil($qty);
            [$start, $end] = decorEventDateRange($event);

            $stmt = $pdo->prepare('SELECT * FROM decor_inventory_items WHERE id=? FOR UPDATE');
            $stmt->execute([(int)$line['decor_inventory_item_id']]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) throw new RuntimeException('Decor item not found.');

            $available = decorAvailableQuantity(
                $item,
                $start,
                $end,
                (int)$line['reservation_id']
            );
            if ($intQty > $available) {
                throw new RuntimeException('Only ' . $available . ' unit(s) available for these event dates.');
            }

            $res = queryOne('SELECT * FROM decor_inventory_reservations WHERE id=? FOR UPDATE', [(int)$line['reservation_id']]);
            if (!$res) throw new RuntimeException('Reservation missing.');
            if (!in_array($res['status'], ['reserved', 'checked_out'], true)) {
                throw new RuntimeException('Cannot change a cancelled or checked-in reservation.');
            }

            execute(
                'UPDATE decor_inventory_reservations SET quantity=?, start_date=?, end_date=?, updated_at=NOW() WHERE id=?',
                [$intQty, $start, $end, (int)$line['reservation_id']]
            );
            $qty = $intQty;
        }

        execute(
            'UPDATE decor_proposal_lines
             SET label=?, description=?, quantity=?, unit_cost=?, markup_percent=?, unit_price=?, updated_at=NOW()
             WHERE id=?',
            [$label, $description, $qty, $unitCost, $markup, $unitPrice, $lineId]
        );

        decorRecalcProposal((int)$proposal['id']);
        $pdo->commit();
        return null;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return $e->getMessage();
    }
}

/** Remove a line and cancel its reservation. */
function decorProposalRemoveLine(int $lineId): ?string
{
    $line = queryOne('SELECT * FROM decor_proposal_lines WHERE id=?', [$lineId]);
    if (!$line) return 'Line not found.';
    $proposalId = (int)$line['proposal_id'];

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($line['reservation_id']) {
            $res = queryOne('SELECT * FROM decor_inventory_reservations WHERE id=? FOR UPDATE', [(int)$line['reservation_id']]);
            if ($res && $res['status'] === 'checked_out') {
                throw new RuntimeException('Check the item back in before removing it from the event.');
            }
            if ($res && in_array($res['status'], ['reserved', 'checked_out'], true)) {
                execute(
                    "UPDATE decor_inventory_reservations SET status='cancelled', updated_at=NOW() WHERE id=?",
                    [(int)$line['reservation_id']]
                );
            }
        }
        execute('DELETE FROM decor_proposal_lines WHERE id=?', [$lineId]);
        decorRecalcProposal($proposalId);
        $pdo->commit();
        return null;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return $e->getMessage();
    }
}

function decorReservationSetStatus(int $reservationId, string $status): ?string
{
    if (!in_array($status, ['reserved', 'checked_out', 'checked_in', 'cancelled'], true)) {
        return 'Invalid status.';
    }
    $res = queryOne('SELECT * FROM decor_inventory_reservations WHERE id=?', [$reservationId]);
    if (!$res) return 'Reservation not found.';

    $from = $res['status'];
    $ok = [
        'reserved' => ['checked_out', 'cancelled'],
        'checked_out' => ['checked_in'],
        'checked_in' => [],
        'cancelled' => [],
    ];
    if (!in_array($status, $ok[$from] ?? [], true)) {
        return 'Cannot change reservation from ' . $from . ' to ' . $status . '.';
    }

    $fields = "status=?, updated_at=NOW()";
    $params = [$status];
    if ($status === 'checked_out') {
        $fields .= ', checked_out_at=NOW()';
    }
    if ($status === 'checked_in') {
        $fields .= ', checked_in_at=NOW()';
    }
    $params[] = $reservationId;
    execute("UPDATE decor_inventory_reservations SET {$fields} WHERE id=?", $params);
    return null;
}

/** Update proposal header options (tax/discount/notes/title). */
function decorProposalUpdateHeader(int $proposalId, array $input): ?string
{
    $proposal = decorProposalGet($proposalId);
    if (!$proposal) return 'Proposal not found.';

    $title = trim((string)($input['title'] ?? $proposal['title']));
    if ($title === '') return 'Title is required.';
    $tax = round((float)($input['tax_percent'] ?? $proposal['tax_percent']), 2);
    $discountType = ($input['discount_type'] ?? $proposal['discount_type']) === 'flat' ? 'flat' : 'percent';
    $discountValue = round((float)($input['discount_value'] ?? $proposal['discount_value']), 2);
    if ($tax < 0 || $discountValue < 0) return 'Tax/discount cannot be negative.';
    $notes = trim((string)($input['notes'] ?? $proposal['notes'] ?? '')) ?: null;

    execute(
        'UPDATE decor_proposals SET title=?, tax_percent=?, discount_type=?, discount_value=?, notes=?, updated_at=NOW() WHERE id=?',
        [$title, $tax, $discountType, $discountValue, $notes, $proposalId]
    );
    decorRecalcProposal($proposalId);
    return null;
}

/**
 * Ensure a main inventory row exists for a Decor stock item.
 * Custom/labor lines are skipped by the caller.
 * Returns inventory_item_id.
 */
function decorEnsureMainInventoryItem(array $decorItem, int $qty, float $unitCost): int
{
    $qty = max(1, $qty);
    $unitCost = max(0, $unitCost);
    $name = trim((string)($decorItem['name'] ?? ''));
    if ($name === '') {
        throw new RuntimeException('Decor item is missing a name.');
    }

    $decorCategoryId = getOrCreateInventoryCategoryId('Decor');

    $linkedId = (int)($decorItem['inventory_item_id'] ?? 0);
    if ($linkedId > 0) {
        $linked = queryOne(
            'SELECT id, category_id FROM inventory_items WHERE id=? AND deleted_at IS NULL',
            [$linkedId]
        );
        if ($linked) {
            if ($linked['category_id'] === null || $linked['category_id'] === '') {
                execute(
                    'UPDATE inventory_items SET category_id=?, updated_at=NOW() WHERE id=?',
                    [$decorCategoryId, $linkedId]
                );
            }
            return $linkedId;
        }
    }

    $existing = queryOne(
        'SELECT id, category_id FROM inventory_items WHERE deleted_at IS NULL AND LOWER(name)=LOWER(?) LIMIT 1',
        [$name]
    );
    if ($existing) {
        $inventoryId = (int)$existing['id'];
        if ($existing['category_id'] === null || $existing['category_id'] === '') {
            execute(
                'UPDATE inventory_items SET category_id=?, updated_at=NOW() WHERE id=?',
                [$decorCategoryId, $inventoryId]
            );
        }
        execute(
            'UPDATE decor_inventory_items SET inventory_item_id=COALESCE(inventory_item_id, ?), updated_at=NOW() WHERE id=?',
            [$inventoryId, (int)$decorItem['id']]
        );
        return $inventoryId;
    }

    $reason = 'Decor publish — ' . $name . ' (decor #' . (int)$decorItem['id'] . ')';
    $inventoryId = createInventoryFromPurchase($name, $qty, $unitCost, $decorCategoryId, $reason);
    if (!empty($decorItem['description'])) {
        execute('UPDATE inventory_items SET description=? WHERE id=?', [$decorItem['description'], $inventoryId]);
    }
    execute(
        'UPDATE decor_inventory_items SET inventory_item_id=?, updated_at=NOW() WHERE id=?',
        [$inventoryId, (int)$decorItem['id']]
    );
    return $inventoryId;
}

/**
 * Publish proposal into the main estimates system.
 * Costs are copied as-is. Stock lines are linked into main inventory;
 * custom/labor lines stay as non-inventory estimate lines.
 * Returns [estimateId|null, error|null]
 */
function decorProposalPublish(int $proposalId): array
{
    $proposal = decorProposalGet($proposalId);
    if (!$proposal) return [null, 'Proposal not found.'];
    $event = decorGetEvent((int)$proposal['event_id']);
    if (!$event) return [null, 'Event not found.'];
    if ((int)$event['customer_id'] !== (int)$proposal['customer_id']) {
        return [null, 'Customer/event mismatch.'];
    }

    $lines = decorProposalLines($proposalId);
    if (!$lines) return [null, 'Add at least one line before publishing.'];

    $totals = decorCalculateProposalTotals($lines, $proposal);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $estimateId = $proposal['estimate_id'] ? (int)$proposal['estimate_id'] : null;
        $existing = $estimateId ? queryOne('SELECT * FROM estimates WHERE id=? FOR UPDATE', [$estimateId]) : null;

        $createNew = true;
        $parentId = null;
        $version = 1;

        if ($existing) {
            if (($existing['status'] ?? '') === 'draft') {
                $createNew = false;
                $version = (int)($existing['version'] ?? 1);
                $parentId = $existing['parent_estimate_id'] ? (int)$existing['parent_estimate_id'] : null;
            } else {
                $parentId = $existing['parent_estimate_id']
                    ? (int)$existing['parent_estimate_id']
                    : (int)$existing['id'];
                $maxVer = queryOne(
                    'SELECT MAX(version) AS v FROM estimates WHERE id=? OR parent_estimate_id=?',
                    [$parentId, $parentId]
                );
                $version = ((int)($maxVer['v'] ?? 1)) + 1;
                $createNew = true;
            }
        }

        $title = $proposal['title'] ?: ('Decor — ' . $event['title']);

        if ($createNew) {
            execute(
                'INSERT INTO estimates
                    (customer_id, event_id, parent_estimate_id, title, status, version,
                     subtotal, tax_percent, tax_amount, discount_type, discount_value, discount_amount, total, notes)
                 VALUES (?,?,?,?,\'draft\',?,?,?,?,?,?,?,?,?)',
                [
                    (int)$proposal['customer_id'],
                    (int)$proposal['event_id'],
                    $parentId,
                    $title,
                    $version,
                    $totals['subtotal'],
                    (float)$proposal['tax_percent'],
                    $totals['tax_amount'],
                    $proposal['discount_type'],
                    (float)$proposal['discount_value'],
                    $totals['discount_amount'],
                    $totals['total'],
                    $proposal['notes'],
                ]
            );
            $estimateId = (int)lastId();
        } else {
            execute(
                'UPDATE estimates SET
                    customer_id=?, event_id=?, title=?,
                    subtotal=?, tax_percent=?, tax_amount=?,
                    discount_type=?, discount_value=?, discount_amount=?, total=?, notes=?,
                    updated_at=NOW()
                 WHERE id=?',
                [
                    (int)$proposal['customer_id'],
                    (int)$proposal['event_id'],
                    $title,
                    $totals['subtotal'],
                    (float)$proposal['tax_percent'],
                    $totals['tax_amount'],
                    $proposal['discount_type'],
                    (float)$proposal['discount_value'],
                    $totals['discount_amount'],
                    $totals['total'],
                    $proposal['notes'],
                    $estimateId,
                ]
            );
            // Remove previous Decor-sourced lines for this proposal, keep any admin-added lines.
            execute(
                "DELETE FROM estimate_line_items WHERE estimate_id=? AND source_type='decor_proposal' AND source_id=?",
                [$estimateId, $proposalId]
            );
        }

        $sort = 0;
        if (!$createNew) {
            $maxSort = queryOne(
                'SELECT COALESCE(MAX(sort_order),-1) AS m FROM estimate_line_items WHERE estimate_id=?',
                [$estimateId]
            );
            $sort = ((int)($maxSort['m'] ?? -1)) + 1;
        }

        foreach ($lines as $line) {
            $isCustom = in_array($line['line_type'], ['custom', 'labor'], true);
            $inventoryId = null;
            $estLineType = $line['line_type'] === 'labor' ? 'labor' : ($isCustom ? 'custom' : 'inventory');
            $unitCost = round((float)$line['unit_cost'], 2);

            if (!$isCustom && !empty($line['decor_inventory_item_id'])) {
                $decorItem = queryOne(
                    'SELECT * FROM decor_inventory_items WHERE id=?',
                    [(int)$line['decor_inventory_item_id']]
                );
                if (!$decorItem) {
                    throw new RuntimeException('Decor stock item missing for "' . $line['label'] . '".');
                }
                $inventoryId = decorEnsureMainInventoryItem(
                    $decorItem,
                    max(1, (int)ceil((float)$line['quantity'])),
                    $unitCost
                );
            }

            execute(
                'INSERT INTO estimate_line_items
                    (estimate_id, line_type, inventory_item_id, label, description, quantity,
                     unit_price, unit_cost, sort_order, source_type, source_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $estimateId,
                    $estLineType,
                    $inventoryId,
                    $line['label'],
                    $line['description'],
                    $line['quantity'],
                    $line['unit_price'],
                    $unitCost,
                    $sort++,
                    'decor_proposal',
                    $proposalId,
                ]
            );
        }

        execute(
            "UPDATE decor_proposals
             SET estimate_id=?, status='published', published_at=NOW(),
                 subtotal=?, tax_amount=?, discount_amount=?, total=?, private_cost_total=?, updated_at=NOW()
             WHERE id=?",
            [
                $estimateId,
                $totals['subtotal'],
                $totals['tax_amount'],
                $totals['discount_amount'],
                $totals['total'],
                $totals['private_cost_total'],
                $proposalId,
            ]
        );

        $pdo->commit();
        return [$estimateId, null];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [null, $e->getMessage()];
    }
}

/** Available Decor stock picker rows for an event date range. */
function decorInventoryPickerForEvent(array $event): array
{
    [$start, $end] = decorEventDateRange($event);
    $items = query(
        "SELECT * FROM decor_inventory_items
         WHERE is_returned=0 AND quantity_on_hand > 0
         ORDER BY name"
    );
    foreach ($items as &$item) {
        $item['reserved_qty'] = decorReservedQuantity((int)$item['id'], $start, $end);
        $item['available_qty'] = max(0, (int)$item['quantity_on_hand'] - (int)$item['reserved_qty']);
        $item['suggested_rate'] = decorSuggestedRate(
            (float)$item['unit_price'],
            (float)$item['default_markup_percent']
        );
    }
    unset($item);
    return $items;
}
