<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
$contract = queryOne('SELECT c.*, cu.name as customer_name, cu.email as customer_email, cu.phone as customer_phone, cu.address as customer_address, e.title as event_title, e.event_date, e.venue, e.ceremony_type FROM contracts c JOIN customers cu ON cu.id=c.customer_id LEFT JOIN events e ON e.id=c.event_id WHERE c.id=?', [$id]);
if (!$contract) { die('Contract not found.'); }

$settings = getSettings();
$estimate = $contract['estimate_id'] ? queryOne('SELECT * FROM estimates WHERE id=?', [$contract['estimate_id']]) : null;
$customer = ['name'=>$contract['customer_name'],'email'=>$contract['customer_email'],'phone'=>$contract['customer_phone'],'address'=>$contract['customer_address']];
$event = $contract['event_id'] ? ['title'=>$contract['event_title'],'event_date'=>$contract['event_date'],'venue'=>$contract['venue'],'ceremony_type'=>$contract['ceremony_type']] : null;
$data = buildContractData($contract, $customer, $event, $estimate, $settings);
$content = replaceContractPlaceholders($contract['content'], $data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($contract['title']) ?></title>
    <style>
        @page { margin: 1.5cm; }
        body { font-family: 'Georgia', 'Times New Roman', serif; max-width: 800px; margin: 0 auto; padding: 2rem 1.5rem; line-height: 1.7; color: #1f2937; font-size: 13px; }
        h1 { color: #5b21b6; font-size: 1.6rem; }
        h2 { color: #374151; font-size: 1.1rem; margin-top: 1.5rem; border-bottom: 1px solid #e5e7eb; padding-bottom: .3rem; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { border: 1px solid #d1d5db; padding: 8px 10px; text-align: left; }
        th { background: #f9fafb; font-weight: 600; }
        .header { text-align: center; border-bottom: 3px solid #7c3aed; padding-bottom: 1.25rem; margin-bottom: 2rem; }
        .header h1 { margin: 0; font-size: 1.4rem; color: #5b21b6; }
        .header p { margin: .25rem 0; color: #6b7280; font-size: .9rem; }
        .signatures { margin-top: 3rem; display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; page-break-inside: avoid; }
        .sig-line { border-top: 2px solid #333; margin-top: 3.5rem; padding-top: .5rem; font-size: .85rem; }
        @media print { .no-print { display: none !important; } body { padding: 0; margin: 0; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()" style="position:fixed;top:1rem;right:1rem;padding:.65rem 1.25rem;background:#7c3aed;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:.9rem;box-shadow:0 4px 12px rgba(124,58,237,.3)">📄 Print / Save as PDF</button>

    <div class="header">
        <h1><?= e($settings['company_name']) ?></h1>
        <?php if ($settings['pdf_header']): ?><p><?= e($settings['pdf_header']) ?></p><?php endif; ?>
        <p><?= e($settings['company_address']) ?></p>
        <p><?= e($settings['company_phone']) ?> · <?= e($settings['company_email']) ?></p>
    </div>

    <div class="content">
        <?= $content ?>
    </div>

    <div class="signatures no-print-extra">
        <div><div class="sig-line">Client: <?= e($contract['customer_name']) ?></div></div>
        <div><div class="sig-line">Authorized Representative — <?= e($settings['company_name']) ?></div></div>
    </div>
</body>
</html>
