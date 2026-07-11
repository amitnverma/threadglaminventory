<?php
require_once __DIR__ . '/functions.php';
requireAuth();
$settings = getSettings();
$currentPage = $currentPage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> - <?= e($settings['company_name'] ?? 'ThreadGlam') ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <?php if (!empty($loadContractEditor)): ?>
    <link rel="stylesheet" href="assets/css/contract-editor.css">
    <?php endif; ?>
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="brand">
            <strong><?= e($settings['company_name'] ?? 'ThreadGlam') ?></strong>
            <small>Event Manager</small>
        </div>
        <nav>
            <a href="index.php" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a>
            <a href="inventory.php" class="<?= $currentPage === 'inventory' ? 'active' : '' ?>">📦 Inventory</a>
            <a href="categories.php" class="<?= $currentPage === 'categories' ? 'active' : '' ?>">🏷️ Categories</a>
            <a href="customers.php" class="<?= $currentPage === 'customers' ? 'active' : '' ?>">👤 Customers</a>
            <a href="events.php" class="<?= $currentPage === 'events' ? 'active' : '' ?>">📅 Events</a>
            <a href="estimates.php" class="<?= $currentPage === 'estimates' ? 'active' : '' ?>">📋 Estimates</a>
            <a href="purchases.php" class="<?= $currentPage === 'purchases' ? 'active' : '' ?>">🛒 Purchases</a>
            <a href="sales.php" class="<?= $currentPage === 'sales' ? 'active' : '' ?>">💰 Sales</a>
            <a href="partners.php" class="<?= $currentPage === 'partners' ? 'active' : '' ?>">🤝 Partners</a>
            <a href="contracts.php" class="<?= $currentPage === 'contracts' ? 'active' : '' ?>">📄 Contracts</a>
            <a href="reports.php" class="<?= $currentPage === 'reports' ? 'active' : '' ?>">📈 Reports</a>
            <a href="settings.php" class="<?= $currentPage === 'settings' ? 'active' : '' ?>">⚙️ Settings</a>
        </nav>
    </aside>
    <main class="content">
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>
