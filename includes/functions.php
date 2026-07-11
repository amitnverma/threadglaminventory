<?php

require_once __DIR__ . '/database.php';

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
    $settings = getSettings();
    $currency = $currency ?? ($settings['currency'] ?? 'INR');
    $value = (float) ($amount ?? 0);
    if ($currency === 'INR') {
        return '₹' . number_format($value, 2);
    }
    return '$' . number_format($value, 2);
}

function formatDate(?string $date): string
{
    if (!$date) return '—';
    return date('d M Y', strtotime($date));
}

function getSettings(): array
{
    ensureSettingsColumns();
    return queryOne('SELECT * FROM settings WHERE id = 1') ?: [];
}

function requireAuth(): void
{
    global $config;
    if (empty($config['admin_password'])) return;
    if (!empty($_SESSION['logged_in'])) return;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_password'])) {
        if ($_POST['login_password'] === $config['admin_password']) {
            $_SESSION['logged_in'] = true;
            redirect($_SERVER['REQUEST_URI']);
        }
        flash('error', 'Wrong password.');
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

function getCeremonyTypes(): array
{
    $settings = getSettings();
    $raw = trim($settings['ceremony_types'] ?? '');
    if ($raw) {
        return array_filter(array_map('trim', explode("\n", $raw)));
    }
    return ['Wedding', 'Reception', 'Birthday', 'Corporate', 'Anniversary', 'Engagement', 'Other'];
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
    $discountValue = (float) ($opts['discount_value'] ?? 0);
    $discountAmount = $discountType === 'percent' ? ($subtotal * $discountValue / 100) : $discountValue;
    $taxable = max(0, $subtotal - $discountAmount);
    $taxAmount = $taxable * $taxPercent / 100;
    $total = $taxable + $taxAmount;

    return [
        'subtotal' => round($subtotal, 2),
        'discount_amount' => round($discountAmount, 2),
        'tax_amount' => round($taxAmount, 2),
        'total' => round($total, 2),
        'total_cost' => round($totalCost, 2),
        'profit' => round($total - $totalCost, 2),
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
        'subtotal' => formatMoney($estimate['subtotal'] ?? 0),
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
    return ['revenue' => $revenue, 'expenses' => $costs, 'profit' => $revenue - $costs];
}

function getContractPlaceholders(): array
{
    return [
        'contract_number' => 'Contract number (auto)',
        'contract_date' => 'Today\'s date',
        'company_name' => 'Your company name',
        'company_address' => 'Company address',
        'company_phone' => 'Company phone',
        'company_email' => 'Company email',
        'customer_name' => 'Client full name',
        'customer_email' => 'Client email',
        'customer_phone' => 'Client phone',
        'customer_address' => 'Client address',
        'event_title' => 'Event title',
        'event_date' => 'Event date',
        'event_venue' => 'Venue name & address',
        'event_type' => 'Ceremony / event type',
        'items_table' => 'Line items table (auto)',
        'subtotal' => 'Estimate subtotal',
        'tax_percent' => 'Tax percentage',
        'tax_amount' => 'Tax amount',
        'discount_amount' => 'Discount amount',
        'total' => 'Grand total',
        'contract_footer' => 'Terms from Settings',
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
