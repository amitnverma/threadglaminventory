<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/admin-auth.php';

$config = require __DIR__ . '/../config.php';

session_start();

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function formatMoney($amount, ?string $currency = null): string
{
    $value = (float) ($amount ?? 0);
    return '$' . number_format($value, 2);
}

function formatDate(?string $date): string
{
    if (!$date) return '—';
    return date('M j, Y', strtotime($date));
}

function getSettings(): array
{
    ensureSettingsColumns();
    return queryOne('SELECT * FROM settings WHERE id = 1') ?: [];
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrf(?string $token = null): bool
{
    $token = $token ?? ($_POST['csrf_token'] ?? '');
    $session = $_SESSION['csrf_token'] ?? '';
    return is_string($token) && $session !== '' && hash_equals($session, $token);
}

function requireCsrf(): void
{
    if (!verifyCsrf()) {
        flash('error', 'Invalid or expired form token. Please try again.');
        $back = $_SERVER['HTTP_REFERER'] ?? 'index.php';
        redirect($back);
    }
}

function requireAuth(): void
{
    try {
        ensureAdminUsersSchema();
    } catch (Exception $e) {
        // DB may not be installed yet — install.php / share pages handle their own access.
        http_response_code(503);
        echo '<!DOCTYPE html><html><body style="font-family:sans-serif;max-width:520px;margin:3rem auto;padding:1rem">';
        echo '<h2>Database not ready</h2><p>Run <a href="install.php">install.php</a> first, or check <code>config.php</code>.</p>';
        echo '<p class="text-muted">' . htmlspecialchars($e->getMessage()) . '</p></body></html>';
        exit;
    }

    if (isLoggedIn()) return;

    // Old sessions (password-only) without admin_id must sign in again.
    unset($_SESSION['logged_in']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_username'], $_POST['login_password'])) {
        if (adminAttemptLogin($_POST['login_username'], $_POST['login_password'], 'admin')) {
            redirect($_SERVER['REQUEST_URI']);
        }
        flash('error', 'Invalid username or password.');
    }
    include __DIR__ . '/login.php';
    exit;
}

function imgUrl(?string $path): string
{
    if (!$path) return 'assets/img/no-image.svg';
    return 'uploads/' . ltrim($path, '/');
}

function getPrimaryImage(string $type, int $id): ?string
{
    $row = queryOne(
        'SELECT file_path, thumbnail_path FROM attachments WHERE attachable_type=? AND attachable_id=? ORDER BY sort_order LIMIT 1',
        [$type, $id]
    );
    if (!$row) return null;
    return $row['thumbnail_path'] ?: $row['file_path'];
}

function createThumbnail(string $source, string $dest, int $maxSize = 400): bool
{
    if (!function_exists('imagecreatefromjpeg')) return false;
    $info = @getimagesize($source);
    if (!$info) return false;

    switch ($info[2]) {
        case IMAGETYPE_JPEG: $src = imagecreatefromjpeg($source); break;
        case IMAGETYPE_PNG: $src = imagecreatefrompng($source); break;
        case IMAGETYPE_GIF: $src = imagecreatefromgif($source); break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
                $src = imagecreatefromwebp($source);
            } else return false;
            break;
        default: return false;
    }
    if (!$src) return false;

    $w = imagesx($src);
    $h = imagesy($src);
    $ratio = min($maxSize / $w, $maxSize / $h, 1);
    $nw = (int)($w * $ratio);
    $nh = (int)($h * $ratio);

    $thumb = imagecreatetruecolor($nw, $nh);
    if ($info[2] === IMAGETYPE_PNG) {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
    }
    imagecopyresampled($thumb, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $ok = imagejpeg($thumb, $dest, 85);
    imagedestroy($src);
    imagedestroy($thumb);
    return $ok;
}

function uploadImage(string $type, int $id, array $file): ?string
{
    global $config;
    if ($file['error'] !== UPLOAD_ERR_OK) return null;

    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed)) return null;

    $dir = $config['upload_dir'] . '/' . $type;
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid('img_') . '.' . $ext;
    $path = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $path)) return null;

    $relative = $type . '/' . $filename;
    $thumbRelative = null;
    $thumbPath = $dir . '/thumb_' . $filename;
    if (createThumbnail($path, $thumbPath)) {
        $thumbRelative = $type . '/thumb_' . $filename;
    }

    $maxOrder = queryOne('SELECT COALESCE(MAX(sort_order), -1) as m FROM attachments WHERE attachable_type=? AND attachable_id=?', [$type, $id]);
    execute(
        'INSERT INTO attachments (attachable_type, attachable_id, file_path, thumbnail_path, sort_order) VALUES (?,?,?,?,?)',
        [$type, $id, $relative, $thumbRelative, ($maxOrder['m'] ?? -1) + 1]
    );
    return $relative;
}

function deleteAttachment(int $attachmentId): void
{
    global $config;
    $att = queryOne('SELECT * FROM attachments WHERE id=?', [$attachmentId]);
    if (!$att) return;
    $base = $config['upload_dir'] . '/';
    if ($att['file_path'] && file_exists($base . $att['file_path'])) unlink($base . $att['file_path']);
    if ($att['thumbnail_path'] && file_exists($base . $att['thumbnail_path'])) unlink($base . $att['thumbnail_path']);
    execute('DELETE FROM attachments WHERE id=?', [$attachmentId]);
}

function getImages(string $type, int $id): array
{
    return query('SELECT * FROM attachments WHERE attachable_type=? AND attachable_id=? ORDER BY sort_order', [$type, $id]);
}

function generateSku(?int $categoryId): string
{
    $prefix = 'ITM';
    if ($categoryId) {
        $cat = queryOne('SELECT name FROM inventory_categories WHERE id=?', [$categoryId]);
        if ($cat) {
            $clean = preg_replace('/[^A-Za-z]/', '', $cat['name']);
            $prefix = strtoupper(substr($clean ?: 'ITM', 0, 3));
        }
    }
    $row = queryOne('SELECT COUNT(*) as c FROM inventory_items WHERE sku LIKE ?', [$prefix . '-%']);
    return $prefix . '-' . str_pad((string)(($row['c'] ?? 0) + 1), 3, '0', STR_PAD_LEFT);
}

function generateReorderLevel(int $quantity): int
{
    if ($quantity <= 0) return 5;
    return max(3, (int)ceil($quantity * 0.2));
}

/**
 * Find or create an inventory category by name (case-insensitive).
 */
function getOrCreateInventoryCategoryId(string $name): int
{
    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Category name is required.');
    }

    $existing = queryOne(
        'SELECT id FROM inventory_categories WHERE LOWER(name)=LOWER(?) ORDER BY id ASC LIMIT 1',
        [$name]
    );
    if ($existing) {
        return (int)$existing['id'];
    }

    execute(
        'INSERT INTO inventory_categories (name, description) VALUES (?,?)',
        [$name, null]
    );
    return (int)lastId();
}

/**
 * Assign the Decor category to main-inventory items that came from Decor and have none.
 */
function backfillDecorInventoryCategories(): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        $categoryId = getOrCreateInventoryCategoryId('Decor');
    } catch (Throwable $e) {
        return;
    }

    execute(
        'UPDATE inventory_items i
         INNER JOIN decor_inventory_items d ON d.inventory_item_id = i.id
         SET i.category_id = ?, i.updated_at = NOW()
         WHERE i.deleted_at IS NULL AND i.category_id IS NULL',
        [$categoryId]
    );

    execute(
        "UPDATE inventory_items i
         SET i.category_id = ?, i.updated_at = NOW()
         WHERE i.deleted_at IS NULL
           AND i.category_id IS NULL
           AND EXISTS (
               SELECT 1 FROM inventory_adjustments a
               WHERE a.inventory_item_id = i.id
                 AND (a.reason LIKE 'Decor publish%' OR a.reason LIKE 'Decor handoff%')
           )",
        [$categoryId]
    );
}

function addInventoryStock(int $itemId, int $qty, float $unitCost, string $reason): void
{
    $item = queryOne('SELECT quantity_on_hand, unit_cost FROM inventory_items WHERE id=? AND deleted_at IS NULL', [$itemId]);
    if (!$item || $qty <= 0) return;

    $lineTotal = $qty * $unitCost;
    $newQty = (int)$item['quantity_on_hand'] + $qty;
    $avgCost = $newQty > 0
        ? (((int)$item['quantity_on_hand'] * (float)$item['unit_cost']) + $lineTotal) / $newQty
        : $unitCost;

    execute(
        'UPDATE inventory_items SET quantity_on_hand=?, unit_cost=?, reorder_level=?, updated_at=NOW() WHERE id=?',
        [$newQty, $avgCost, generateReorderLevel($newQty), $itemId]
    );
    execute(
        'INSERT INTO inventory_adjustments (inventory_item_id, adjustment_type, quantity, reason) VALUES (?,?,?,?)',
        [$itemId, 'add', $qty, $reason]
    );
}

function createInventoryFromPurchase(string $name, int $qty, float $unitCost, ?int $categoryId, string $reason): int
{
    $sku = generateSku($categoryId);
    $reorder = generateReorderLevel($qty);
    execute(
        'INSERT INTO inventory_items (category_id,name,sku,quantity_on_hand,unit_cost,reorder_level) VALUES (?,?,?,?,?,?)',
        [$categoryId, trim($name), $sku, $qty, $unitCost, $reorder]
    );
    $id = (int)lastId();
    execute(
        'INSERT INTO inventory_adjustments (inventory_item_id, adjustment_type, quantity, reason) VALUES (?,?,?,?)',
        [$id, 'add', $qty, $reason]
    );
    return $id;
}

function reversePurchaseInventory(int $purchaseId): void
{
    $lines = query('SELECT * FROM purchase_line_items WHERE purchase_id=? AND inventory_item_id IS NOT NULL', [$purchaseId]);
    foreach ($lines as $line) {
        $item = queryOne('SELECT quantity_on_hand FROM inventory_items WHERE id=? AND deleted_at IS NULL', [$line['inventory_item_id']]);
        if (!$item) continue;
        $qty = (int)$line['quantity'];
        $newQty = max(0, (int)$item['quantity_on_hand'] - $qty);
        execute(
            'UPDATE inventory_items SET quantity_on_hand=?, reorder_level=?, updated_at=NOW() WHERE id=?',
            [$newQty, generateReorderLevel($newQty), $line['inventory_item_id']]
        );
        execute(
            'INSERT INTO inventory_adjustments (inventory_item_id, adjustment_type, quantity, reason) VALUES (?,?,?,?)',
            [$line['inventory_item_id'], 'remove', $qty, 'Reversed — purchase #' . $purchaseId . ' deleted']
        );
    }
}

/**
 * Save a multi-line purchase and update inventory atomically.
 *
 * @param array{supplier?:?string,purchase_date?:string,notes?:?string} $header
 * @param list<array{mode?:string,inventory_item_id?:int,name?:string,category_id?:?int,qty?:int,unit_cost?:float}> $lines
 * @return array{ok:bool,purchase_id:?int,items_updated:int,error:?string}
 */
function savePurchaseWithLines(array $header, array $lines): array
{
    $supplier = trim((string)($header['supplier'] ?? ''));
    $purchaseDate = trim((string)($header['purchase_date'] ?? date('Y-m-d')));
    if ($purchaseDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchaseDate)) {
        return ['ok' => false, 'purchase_id' => null, 'items_updated' => 0, 'error' => 'Purchase date is required.'];
    }
    $notes = trim((string)($header['notes'] ?? '')) ?: null;
    $reasonSupplier = $supplier !== '' ? $supplier : 'Supplier';
    $reason = 'Purchase from ' . $reasonSupplier . ' on ' . $purchaseDate;

    $normalized = [];
    foreach ($lines as $line) {
        $qty = max(1, (int)($line['qty'] ?? 1));
        $cost = max(0, (float)($line['unit_cost'] ?? 0));
        $mode = ($line['mode'] ?? '') === 'existing' || !empty($line['inventory_item_id']) ? 'existing' : 'new';
        $invId = (int)($line['inventory_item_id'] ?? 0);
        $name = trim((string)($line['name'] ?? ''));
        $catId = isset($line['category_id']) && $line['category_id'] !== '' && $line['category_id'] !== null
            ? (int)$line['category_id']
            : null;
        if ($catId !== null && $catId <= 0) {
            $catId = null;
        }

        if ($mode === 'existing') {
            if ($invId <= 0 && $name !== '') {
                $match = queryOne(
                    'SELECT id FROM inventory_items WHERE deleted_at IS NULL AND LOWER(name)=LOWER(?) LIMIT 1',
                    [$name]
                );
                $invId = $match ? (int)$match['id'] : 0;
            }
            if ($invId <= 0) {
                continue;
            }
            $normalized[] = [
                'mode' => 'existing',
                'inventory_item_id' => $invId,
                'name' => $name,
                'category_id' => $catId,
                'qty' => $qty,
                'unit_cost' => $cost,
            ];
        } else {
            if ($name === '') {
                continue;
            }
            $normalized[] = [
                'mode' => 'new',
                'inventory_item_id' => 0,
                'name' => $name,
                'category_id' => $catId,
                'qty' => $qty,
                'unit_cost' => $cost,
            ];
        }
    }

    if (empty($normalized)) {
        return ['ok' => false, 'purchase_id' => null, 'items_updated' => 0, 'error' => 'Add at least one inventory item to the purchase.'];
    }

    try {
        dbBegin();

        execute(
            'INSERT INTO purchases (supplier, purchase_date, total, notes) VALUES (?,?,?,?)',
            [$supplier !== '' ? $supplier : null, $purchaseDate, 0, $notes]
        );
        $purchaseId = (int)lastId();
        $total = 0.0;
        $itemsUpdated = 0;

        foreach ($normalized as $line) {
            $qty = $line['qty'];
            $cost = $line['unit_cost'];
            $lineTotal = $qty * $cost;
            $invId = null;
            $label = '';

            if ($line['mode'] === 'new') {
                $invId = createInventoryFromPurchase($line['name'], $qty, $cost, $line['category_id'], $reason);
                $label = $line['name'];
                $itemsUpdated++;
            } else {
                $invId = (int)$line['inventory_item_id'];
                $item = queryOne('SELECT name FROM inventory_items WHERE id=? AND deleted_at IS NULL', [$invId]);
                if (!$item) {
                    continue;
                }
                $label = $item['name'];
                addInventoryStock($invId, $qty, $cost, $reason);
                $itemsUpdated++;
            }

            $total += $lineTotal;
            execute(
                'INSERT INTO purchase_line_items (purchase_id,inventory_item_id,label,quantity,unit_cost,line_total) VALUES (?,?,?,?,?,?)',
                [$purchaseId, $invId, $label, $qty, $cost, $lineTotal]
            );
        }

        if ($itemsUpdated === 0) {
            dbRollback();
            return ['ok' => false, 'purchase_id' => null, 'items_updated' => 0, 'error' => 'Add at least one inventory item to the purchase.'];
        }

        execute('UPDATE purchases SET total=? WHERE id=?', [$total, $purchaseId]);
        dbCommit();

        return ['ok' => true, 'purchase_id' => $purchaseId, 'items_updated' => $itemsUpdated, 'error' => null];
    } catch (Throwable $e) {
        dbRollback();
        return ['ok' => false, 'purchase_id' => null, 'items_updated' => 0, 'error' => 'Could not save purchase. Please try again.'];
    }
}

/**
 * Batch-update inventory catalog fields (not stock qty — use adjustments/purchases for that).
 *
 * @param list<array{id:int,name?:string,category_id?:?int,unit_cost?:float,rental_price?:float,sale_price?:float}> $rows
 * @return array{ok:bool,updated:int,error:?string}
 */
function updateInventorySheetRows(array $rows): array
{
    $updated = 0;
    try {
        dbBegin();
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $existing = queryOne(
                'SELECT id, category_id, unit_cost, rental_price, sale_price FROM inventory_items WHERE id=? AND deleted_at IS NULL',
                [$id]
            );
            if (!$existing) {
                continue;
            }

            $name = trim((string)($row['name'] ?? ''));
            if ($name === '') {
                dbRollback();
                return ['ok' => false, 'updated' => 0, 'error' => 'Item name cannot be empty.'];
            }

            $catId = array_key_exists('category_id', $row)
                ? (($row['category_id'] === '' || $row['category_id'] === null) ? null : (int)$row['category_id'])
                : ($existing['category_id'] !== null ? (int)$existing['category_id'] : null);
            if ($catId !== null && $catId <= 0) {
                $catId = null;
            }

            $unitCost = array_key_exists('unit_cost', $row)
                ? max(0, (float)$row['unit_cost'])
                : (float)$existing['unit_cost'];
            $rental = array_key_exists('rental_price', $row)
                ? max(0, (float)$row['rental_price'])
                : (float)$existing['rental_price'];
            $sale = array_key_exists('sale_price', $row)
                ? max(0, (float)$row['sale_price'])
                : (float)$existing['sale_price'];

            execute(
                'UPDATE inventory_items SET name=?, category_id=?, unit_cost=?, rental_price=?, sale_price=?, updated_at=NOW() WHERE id=?',
                [$name, $catId, $unitCost, $rental, $sale, $id]
            );
            $updated++;
        }
        dbCommit();
        return ['ok' => true, 'updated' => $updated, 'error' => null];
    } catch (Throwable $e) {
        dbRollback();
        return ['ok' => false, 'updated' => 0, 'error' => 'Could not save inventory changes.'];
    }
}

function getCeremonyTypes(): array
{
    $settings = getSettings();
    $raw = trim($settings['ceremony_types'] ?? '');
    if ($raw) {
        return array_filter(array_map('trim', explode("\n", $raw)));
    }
    return ['Wedding', 'Reception', 'Birthday', 'Corporate', 'Anniversary', 'Engagement', 'Baby Shower', 'Graduation', 'Other'];
}

function ensureEstimatePricingSchema(): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    $column = query("SHOW COLUMNS FROM estimates LIKE 'profit_amount'");
    if (empty($column)) {
        execute(
            'ALTER TABLE estimates ADD COLUMN profit_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER discount_amount'
        );
    }
}

function calculateEstimateTotals(array $lines, array $opts = []): array
{
    $subtotal = 0;
    $totalCost = 0;
    foreach ($lines as $line) {
        if (($line['line_type'] ?? '') === 'discount') continue;
        $qty = (float) ($line['quantity'] ?? 0);
        $price = (float) ($line['unit_price'] ?? 0);
        $cost = (float) ($line['unit_cost'] ?? 0);
        $subtotal += $qty * $price;
        $totalCost += $qty * $cost;
    }

    $taxPercent = (float) ($opts['tax_percent'] ?? 0);
    $discountType = $opts['discount_type'] ?? 'percent';
    $discountValue = max(0, (float) ($opts['discount_value'] ?? 0));
    $discountAmount = $discountType === 'percent' ? ($subtotal * $discountValue / 100) : $discountValue;
    $discountAmount = max(0, min($subtotal, $discountAmount));
    $profitAmount = max(0, (float) ($opts['profit_amount'] ?? 0));
    $taxable = max(0, $subtotal - $discountAmount + $profitAmount);
    $taxAmount = $taxable * $taxPercent / 100;
    $total = $taxable + $taxAmount;

    return [
        'subtotal' => round($subtotal, 2),
        'discount_amount' => round($discountAmount, 2),
        'profit_amount' => round($profitAmount, 2),
        'tax_amount' => round($taxAmount, 2),
        'total' => round($total, 2),
        'total_cost' => round($totalCost, 2),
        'profit' => round($taxable - $totalCost, 2),
    ];
}

function replaceContractPlaceholders(string $content, array $data): string
{
    foreach ($data as $key => $value) {
        $content = str_replace('{{' . $key . '}}', (string)($value ?? ''), $content);
    }
    return $content;
}

function buildItemsTableHtml(array $lines): string
{
    if (empty($lines)) return '<p><em>No items listed.</em></p>';
    $html = '<table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:14px;">';
    $html .= '<thead><tr style="background:#f3f4f6;"><th style="border:1px solid #ddd;padding:10px;text-align:left;">Item / Service</th>';
    $html .= '<th style="border:1px solid #ddd;padding:10px;">Qty</th>';
    $html .= '<th style="border:1px solid #ddd;padding:10px;text-align:right;">Rate</th>';
    $html .= '<th style="border:1px solid #ddd;padding:10px;text-align:right;">Amount</th></tr></thead><tbody>';
    foreach ($lines as $line) {
        if (($line['line_type'] ?? '') === 'discount') continue;
        $amt = (float)$line['quantity'] * (float)$line['unit_price'];
        $html .= '<tr>';
        $html .= '<td style="border:1px solid #ddd;padding:10px;">' . e($line['label']) . '</td>';
        $html .= '<td style="border:1px solid #ddd;padding:10px;text-align:center;">' . e($line['quantity']) . '</td>';
        $html .= '<td style="border:1px solid #ddd;padding:10px;text-align:right;">' . formatMoney($line['unit_price']) . '</td>';
        $html .= '<td style="border:1px solid #ddd;padding:10px;text-align:right;">' . formatMoney($amt) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    return $html;
}

function getComprehensiveContractTemplate(): string
{
    return <<<'HTML'
<div class="contract-doc">
<h1 style="text-align:center;color:#5b21b6;">EVENT SERVICE AGREEMENT</h1>
<p style="text-align:center;color:#666;">Agreement No: {{contract_number}} &nbsp;|&nbsp; Date: {{contract_date}}</p>

<h2>1. Parties</h2>
<p>This Event Service Agreement ("Agreement") is made between:</p>
<p><strong>Service Provider:</strong> {{company_name}}<br>
Address: {{company_address}}<br>
Phone: {{company_phone}} &nbsp;|&nbsp; Email: {{company_email}}</p>
<p><strong>Client:</strong> {{customer_name}}<br>
Phone: {{customer_phone}} &nbsp;|&nbsp; Email: {{customer_email}}<br>
Address: {{customer_address}}</p>

<h2>2. Event Details</h2>
<table style="width:100%;border-collapse:collapse;">
<tr><td style="padding:8px;border:1px solid #ddd;"><strong>Event Title</strong></td><td style="padding:8px;border:1px solid #ddd;">{{event_title}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;"><strong>Event Date</strong></td><td style="padding:8px;border:1px solid #ddd;">{{event_date}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;"><strong>Venue</strong></td><td style="padding:8px;border:1px solid #ddd;">{{event_venue}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;"><strong>Event Type</strong></td><td style="padding:8px;border:1px solid #ddd;">{{event_type}}</td></tr>
</table>

<h2>3. Services &amp; Items</h2>
<p>The Service Provider agrees to supply the following items and services:</p>
{{items_table}}

<h2>4. Payment Terms</h2>
<table style="width:100%;border-collapse:collapse;max-width:400px;">
<tr><td style="padding:8px;border:1px solid #ddd;">Subtotal</td><td style="padding:8px;border:1px solid #ddd;text-align:right;">{{subtotal}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;">Tax ({{tax_percent}}%)</td><td style="padding:8px;border:1px solid #ddd;text-align:right;">{{tax_amount}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;">Discount</td><td style="padding:8px;border:1px solid #ddd;text-align:right;">{{discount_amount}}</td></tr>
<tr style="background:#f5f3ff;font-weight:bold;"><td style="padding:10px;border:1px solid #ddd;">TOTAL AMOUNT</td><td style="padding:10px;border:1px solid #ddd;text-align:right;">{{total}}</td></tr>
</table>
<p><strong>Payment Schedule:</strong></p>
<ul>
<li>50% advance payment upon signing this agreement</li>
<li>50% balance payment on or before the event date</li>
</ul>

<h2>5. Terms &amp; Conditions</h2>
<div style="background:#f9fafb;padding:16px;border-radius:8px;border:1px solid #e5e7eb;">
{{contract_footer}}
</div>

<h2>6. Cancellation Policy</h2>
<ul>
<li>Cancellation 30+ days before event: Full refund of advance minus 10% admin fee</li>
<li>Cancellation 15-30 days before event: 50% of advance forfeited</li>
<li>Cancellation less than 15 days: No refund</li>
</ul>

<h2>7. Liability</h2>
<p>The Service Provider shall not be liable for delays caused by weather, venue restrictions, or circumstances beyond reasonable control. Client is responsible for venue access and necessary permits.</p>

<h2>8. Signatures</h2>
<p>By signing below, both parties agree to the terms of this Agreement.</p>
<table style="width:100%;margin-top:40px;">
<tr>
<td style="width:50%;padding-top:60px;border-top:2px solid #333;">
<strong>Client Signature</strong><br>{{customer_name}}<br>Date: _______________
</td>
<td style="width:50%;padding-top:60px;border-top:2px solid #333;">
<strong>Authorized Representative</strong><br>{{company_name}}<br>Date: _______________
</td>
</tr>
</table>
</div>
HTML;
}

function buildContractData(?array $contract, ?array $customer, ?array $event, ?array $estimate, ?array $settings): array
{
    $lines = [];
    if ($estimate) {
        $lines = query('SELECT * FROM estimate_line_items WHERE estimate_id=? ORDER BY sort_order', [$estimate['id']]);
    }
    return [
        'contract_number' => 'CTR-' . str_pad((string)($contract['id'] ?? time()), 5, '0', STR_PAD_LEFT),
        'contract_date' => date('d M Y'),
        'company_name' => $settings['company_name'] ?? '',
        'company_address' => $settings['company_address'] ?? '',
        'company_phone' => $settings['company_phone'] ?? '',
        'company_email' => $settings['company_email'] ?? '',
        'customer_name' => $customer['name'] ?? '',
        'customer_email' => $customer['email'] ?? '',
        'customer_phone' => $customer['phone'] ?? '',
        'customer_address' => $customer['address'] ?? '',
        'event_title' => $event['title'] ?? ($estimate['title'] ?? ''),
        'event_date' => isset($event['event_date']) ? formatDate($event['event_date']) : '',
        'event_venue' => $event['venue'] ?? '',
        'event_type' => $event['ceremony_type'] ?? '',
        // Profit is internal; customer-facing subtotal includes it without exposing margin.
        'subtotal' => formatMoney(
            (float)($estimate['subtotal'] ?? 0) + (float)($estimate['profit_amount'] ?? 0)
        ),
        'tax_percent' => $estimate['tax_percent'] ?? ($settings['default_tax_percent'] ?? 0),
        'tax_amount' => formatMoney($estimate['tax_amount'] ?? 0),
        'discount_amount' => formatMoney($estimate['discount_amount'] ?? 0),
        'total' => formatMoney($estimate['total'] ?? 0),
        'contract_footer' => nl2br(e($settings['contract_footer'] ?? '')),
        'items_table' => buildItemsTableHtml($lines),
    ];
}

function getEventProfitLoss(int $eventId): array
{
    $sales = queryOne('SELECT COALESCE(SUM(total),0) as t FROM sales WHERE event_id=?', [$eventId]);
    $estimates = queryOne("SELECT COALESCE(SUM(total),0) as t FROM estimates WHERE event_id=? AND status='approved'", [$eventId]);
    $expenses = queryOne('SELECT COALESCE(SUM(amount),0) as t FROM partner_expenses WHERE event_id=?', [$eventId]);
    $revenue = (float)$sales['t'] + (float)$estimates['t'];
    $costs = (float)$expenses['t'];
    $breakdown = getEventCategoryExpenses($eventId);
    return [
        'revenue' => $revenue,
        'expenses' => $costs,
        'proposal_cost' => $breakdown['proposal_total'],
        'category_total' => $breakdown['total'],
        'profit' => $revenue - $costs,
        'categories' => $breakdown['categories'],
        'estimate_id' => $breakdown['estimate_id'],
        'estimate_title' => $breakdown['estimate_title'],
    ];
}

/**
 * Consolidated category-wise spend for an event.
 * Partner expenses (actual) + estimate/decor line purchase costs (proposal COGS),
 * merged by category name without double-counting published Decor lines.
 *
 * @return array{
 *   categories: list<array{name:string,partner:float,proposal:float,total:float,share:float}>,
 *   total: float,
 *   partner_total: float,
 *   proposal_total: float,
 *   estimate_id: ?int,
 *   estimate_title: ?string
 * }
 */
function getEventCategoryExpenses(int $eventId): array
{
    $buckets = [];

    $add = static function (string $name, float $amount, string $kind) use (&$buckets): void {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return;
        }
        $name = trim($name) !== '' ? trim($name) : 'Uncategorized';
        $key = strtolower($name);
        if (!isset($buckets[$key])) {
            $buckets[$key] = [
                'name' => $name,
                'partner' => 0.0,
                'proposal' => 0.0,
                'total' => 0.0,
            ];
        }
        $buckets[$key][$kind] = round($buckets[$key][$kind] + $amount, 2);
        $buckets[$key]['total'] = round($buckets[$key]['partner'] + $buckets[$key]['proposal'], 2);
    };

    foreach (query(
        "SELECT COALESCE(NULLIF(TRIM(category), ''), 'Uncategorized') AS category_name,
                SUM(amount) AS amount
         FROM partner_expenses
         WHERE event_id=?
         GROUP BY category_name",
        [$eventId]
    ) as $row) {
        $add((string)$row['category_name'], (float)$row['amount'], 'partner');
    }

    $primaryEstimate = queryOne(
        "SELECT id, title, status
         FROM estimates
         WHERE event_id=?
         ORDER BY
            CASE status
                WHEN 'approved' THEN 1
                WHEN 'sent' THEN 2
                WHEN 'draft' THEN 3
                ELSE 4
            END,
            updated_at DESC,
            id DESC
         LIMIT 1",
        [$eventId]
    );

    $decorCoveredByEstimate = false;
    if ($primaryEstimate) {
        $estimateLines = query(
            "SELECT eli.line_type, eli.source_type, eli.quantity, eli.unit_cost, ic.name AS category_name
             FROM estimate_line_items eli
             LEFT JOIN inventory_items i ON i.id = eli.inventory_item_id AND i.deleted_at IS NULL
             LEFT JOIN inventory_categories ic ON ic.id = i.category_id
             WHERE eli.estimate_id=?",
            [(int)$primaryEstimate['id']]
        );
        foreach ($estimateLines as $line) {
            if (($line['source_type'] ?? '') === 'decor_proposal') {
                $decorCoveredByEstimate = true;
            }
            $cost = (float)$line['quantity'] * (float)$line['unit_cost'];
            if ($cost <= 0) {
                continue;
            }
            $cat = trim((string)($line['category_name'] ?? ''));
            if ($cat === '') {
                $type = (string)($line['line_type'] ?? 'custom');
                $cat = $type === 'labor' ? 'Labor' : ($type === 'inventory' ? 'Uncategorized' : 'Custom');
            }
            $add($cat, $cost, 'proposal');
        }
    }

    if (!$decorCoveredByEstimate) {
        try {
            $decorExists = query("SHOW TABLES LIKE 'decor_proposal_lines'");
            if (!empty($decorExists)) {
                $decorLines = query(
                    "SELECT dpl.line_type, dpl.quantity, dpl.unit_cost, ic.name AS category_name
                     FROM decor_proposals dp
                     JOIN decor_proposal_lines dpl ON dpl.proposal_id = dp.id
                     LEFT JOIN decor_inventory_items di ON di.id = dpl.decor_inventory_item_id
                     LEFT JOIN inventory_items i ON i.id = di.inventory_item_id AND i.deleted_at IS NULL
                     LEFT JOIN inventory_categories ic ON ic.id = i.category_id
                     WHERE dp.event_id=?",
                    [$eventId]
                );
                foreach ($decorLines as $line) {
                    $cost = (float)$line['quantity'] * (float)$line['unit_cost'];
                    if ($cost <= 0) {
                        continue;
                    }
                    $cat = trim((string)($line['category_name'] ?? ''));
                    if ($cat === '') {
                        $type = (string)($line['line_type'] ?? 'decor');
                        $cat = $type === 'labor' ? 'Labor' : ($type === 'custom' ? 'Custom' : 'Decor');
                    }
                    $add($cat, $cost, 'proposal');
                }
            }
        } catch (Throwable $e) {
            // Decor schema may not exist yet on older installs.
        }
    }

    $categories = array_values($buckets);
    usort($categories, static fn($a, $b) => $b['total'] <=> $a['total']);

    $partnerTotal = 0.0;
    $proposalTotal = 0.0;
    $total = 0.0;
    foreach ($categories as &$cat) {
        $partnerTotal += $cat['partner'];
        $proposalTotal += $cat['proposal'];
        $total += $cat['total'];
    }
    unset($cat);

    foreach ($categories as &$cat) {
        $cat['share'] = $total > 0 ? round(($cat['total'] / $total) * 100, 1) : 0.0;
    }
    unset($cat);

    return [
        'categories' => $categories,
        'total' => round($total, 2),
        'partner_total' => round($partnerTotal, 2),
        'proposal_total' => round($proposalTotal, 2),
        'estimate_id' => $primaryEstimate ? (int)$primaryEstimate['id'] : null,
        'estimate_title' => $primaryEstimate['title'] ?? null,
    ];
}

function getContractPlaceholders(): array
{
    return [
        'contract_number' => 'Contract Number',
        'contract_date' => 'Today\'s Date',
        'company_name' => 'Your Company Name',
        'company_address' => 'Company Address',
        'company_phone' => 'Company Phone',
        'company_email' => 'Company Email',
        'customer_name' => 'Client Name',
        'customer_email' => 'Client Email',
        'customer_phone' => 'Client Phone',
        'customer_address' => 'Client Address',
        'event_title' => 'Event Title',
        'event_date' => 'Event Date',
        'event_venue' => 'Venue',
        'event_type' => 'Event Type',
        'items_table' => 'Items & Services Table',
        'subtotal' => 'Subtotal Amount',
        'tax_percent' => 'Tax Rate (%)',
        'tax_amount' => 'Tax Amount',
        'discount_amount' => 'Discount Amount',
        'total' => 'Grand Total',
        'contract_footer' => 'Terms & Conditions',
    ];
}

function getContractPlaceholderGroups(): array
{
    return [
        'Agreement' => ['contract_number', 'contract_date'],
        'Your Company' => ['company_name', 'company_address', 'company_phone', 'company_email'],
        'Client Details' => ['customer_name', 'customer_email', 'customer_phone', 'customer_address'],
        'Event Details' => ['event_title', 'event_date', 'event_venue', 'event_type'],
        'Pricing' => ['items_table', 'subtotal', 'tax_percent', 'tax_amount', 'discount_amount', 'total'],
        'Terms' => ['contract_footer'],
    ];
}

function ensureSettingsColumns(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $cols = query("SHOW COLUMNS FROM settings LIKE 'ceremony_types'");
        if (empty($cols)) {
            execute('ALTER TABLE settings ADD COLUMN ceremony_types TEXT AFTER pdf_header');
        }
    } catch (Exception $e) {
        // ignore if cannot alter
    }
    try {
        $cols = query("SHOW COLUMNS FROM settings LIKE 'ai_settings'");
        if (empty($cols)) {
            execute('ALTER TABLE settings ADD COLUMN ai_settings LONGTEXT NULL AFTER ceremony_types');
        }
    } catch (Exception $e) {
        // ignore
    }
}

function actionButtons(string $editUrl, ?string $deleteAction = null, ?int $deleteId = null, ?string $viewUrl = null): string
{
    $html = '<div class="action-btns">';
    if ($viewUrl) $html .= '<a href="' . e($viewUrl) . '" class="btn btn-sm btn-secondary">View</a>';
    $html .= '<a href="' . e($editUrl) . '" class="btn btn-sm btn-primary">Edit</a>';
    if ($deleteAction && $deleteId) {
        $html .= '<form method="post" style="display:inline" onsubmit="return confirm(\'Are you sure you want to delete this?\')">';
        $html .= '<input type="hidden" name="action" value="' . e($deleteAction) . '">';
        $html .= '<input type="hidden" name="id" value="' . $deleteId . '">';
        $html .= '<button type="submit" class="btn btn-sm btn-danger">Delete</button></form>';
    }
    $html .= '</div>';
    return $html;
}
