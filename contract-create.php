<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

$estimateId = (int)($_GET['estimate_id'] ?? 0);
$estimate = queryOne('SELECT e.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone, c.address as customer_address, ev.title as event_title, ev.event_date, ev.venue, ev.ceremony_type FROM estimates e JOIN customers c ON c.id=e.customer_id LEFT JOIN events ev ON ev.id=e.event_id WHERE e.id=?', [$estimateId]);
if (!$estimate) { flash('error', 'Estimate not found.'); redirect('estimates.php'); }

$settings = getSettings();
$customer = ['name'=>$estimate['customer_name'],'email'=>$estimate['customer_email'],'phone'=>$estimate['customer_phone'],'address'=>$estimate['customer_address']];
$event = $estimate['event_id'] ? ['title'=>$estimate['event_title'],'event_date'=>$estimate['event_date'],'venue'=>$estimate['venue'],'ceremony_type'=>$estimate['ceremony_type']] : null;

$template = getComprehensiveContractTemplate();
$data = buildContractData(['id' => 0], $customer, $event, $estimate, $settings);
$content = replaceContractPlaceholders($template, $data);

execute('INSERT INTO contracts (customer_id, event_id, estimate_id, title, content, status) VALUES (?,?,?,?,?,?)',
    [$estimate['customer_id'], $estimate['event_id'], $estimateId, 'Service Agreement — ' . $estimate['title'], $content, 'draft']);

flash('success', 'Comprehensive contract created from estimate.');
redirect('contract-edit.php?id=' . lastId());
