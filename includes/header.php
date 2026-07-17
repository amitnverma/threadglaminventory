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
    <?php if (!empty($loadDecorInventory) || !empty($loadDecorProposals)): ?>
    <link rel="stylesheet" href="assets/css/decor-inventory.css">
    <?php endif; ?>
    <?php if (!empty($loadInventorySheet)): ?>
    <link rel="stylesheet" href="assets/css/inventory-sheet.css">
    <?php endif; ?>
    <?php if (!empty($loadEstimateBuilder)): ?>
    <link rel="stylesheet" href="assets/css/estimate-builder.css">
    <?php endif; ?>
    <?php if (!empty($loadCustomerHub)): ?>
    <link rel="stylesheet" href="assets/css/customer-hub.css">
    <?php endif; ?>
    <?php if (!empty($loadEventHub)): ?>
    <link rel="stylesheet" href="assets/css/event-hub.css">
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
            <?php
            $navAdmin = currentAdmin();
            $navIsDecorOwner = $navAdmin && adminIsDecorOwnerUser($navAdmin);
            $navActive = [
                'dashboard' => $currentPage === 'dashboard',
                'inventory' => in_array($currentPage, ['inventory', 'inventory-buy', 'categories'], true),
                'decor'     => in_array($currentPage, ['decor-inventory', 'decor-inventory-buy', 'decor-profile', 'decor-events'], true),
                'clients'   => in_array($currentPage, ['customers', 'customer-view', 'events', 'albums'], true),
                'sales'     => in_array($currentPage, ['estimates', 'purchases', 'sales', 'partners', 'contracts'], true),
                'insights'  => $currentPage === 'reports',
                'admin'     => in_array($currentPage, ['settings', 'admins'], true),
            ];
            ?>
            <div class="nav-group">
                <a href="index.php" class="nav-link <?= $navActive['dashboard'] ? 'active' : '' ?>">📊 Dashboard</a>
            </div>

            <?php if ($navIsDecorOwner): ?>
            <details class="nav-group" <?= $navActive['decor'] ? 'open' : '' ?>>
                <summary>Decor</summary>
                <a href="decor-inventory.php" class="nav-link <?= $currentPage === 'decor-inventory' ? 'active' : '' ?>">🧵 Decor Inventory</a>
                <a href="decor-events.php" class="nav-link <?= $currentPage === 'decor-events' ? 'active' : '' ?>">🎀 Event Proposals</a>
                <a href="decor-profile.php" class="nav-link <?= $currentPage === 'decor-profile' ? 'active' : '' ?>">🔑 Decor Password</a>
            </details>
            <?php endif; ?>

            <details class="nav-group" <?= $navActive['inventory'] ? 'open' : '' ?>>
                <summary>Stock</summary>
                <a href="inventory.php" class="nav-link <?= $currentPage === 'inventory' ? 'active' : '' ?>">📦 Inventory</a>
                <a href="inventory-buy.php" class="nav-link <?= $currentPage === 'inventory-buy' ? 'active' : '' ?>">🛒 Buy / Restock</a>
                <a href="categories.php" class="nav-link <?= $currentPage === 'categories' ? 'active' : '' ?>">🏷️ Categories</a>
            </details>

            <details class="nav-group" <?= $navActive['clients'] ? 'open' : '' ?>>
                <summary>Clients</summary>
                <a href="customers.php" class="nav-link <?= in_array($currentPage, ['customers', 'customer-view'], true) ? 'active' : '' ?>">👤 Customers</a>
                <a href="events.php" class="nav-link <?= $currentPage === 'events' ? 'active' : '' ?>">📅 All events</a>
                <a href="albums.php" class="nav-link <?= $currentPage === 'albums' ? 'active' : '' ?>">📸 All albums</a>
            </details>

            <details class="nav-group" <?= $navActive['sales'] ? 'open' : '' ?>>
                <summary>Sales &amp; ops</summary>
                <a href="estimates.php" class="nav-link <?= $currentPage === 'estimates' ? 'active' : '' ?>">📋 All estimates</a>
                <a href="sales.php" class="nav-link <?= $currentPage === 'sales' ? 'active' : '' ?>">💰 Sales</a>
                <a href="partners.php" class="nav-link <?= $currentPage === 'partners' ? 'active' : '' ?>">🤝 Partners</a>
                <a href="contracts.php" class="nav-link <?= $currentPage === 'contracts' ? 'active' : '' ?>">📄 All contracts</a>
            </details>

            <details class="nav-group" <?= $navActive['insights'] ? 'open' : '' ?>>
                <summary>Insights</summary>
                <a href="reports.php" class="nav-link <?= $currentPage === 'reports' ? 'active' : '' ?>">📈 Reports</a>
            </details>

            <details class="nav-group" <?= $navActive['admin'] ? 'open' : '' ?>>
                <summary>Admin</summary>
                <a href="settings.php" class="nav-link <?= $currentPage === 'settings' ? 'active' : '' ?>">⚙️ Settings</a>
                <a href="admins.php" class="nav-link <?= $currentPage === 'admins' ? 'active' : '' ?>">🔐 Admin Users</a>
            </details>
        </nav>
        <?php if ($navAdmin): ?>
        <div class="sidebar-user">
            <div class="sidebar-user-name"><?= e($navAdmin['display_name']) ?></div>
            <div class="sidebar-user-meta">@<?= e($navAdmin['username']) ?></div>
            <a href="logout.php" class="btn btn-sm btn-secondary sidebar-logout">Sign out</a>
        </div>
        <?php endif; ?>
    </aside>
    <main class="content">
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>
