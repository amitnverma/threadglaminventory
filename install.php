<?php
/**
 * One-time database installer.
 * Run once via browser: yoursite.com/install.php
 * DELETE this file after installation!
 */
$step = $_GET['step'] ?? 'check';

if ($step === 'run') {
    if (!file_exists(__DIR__ . '/config.php')) {
        die('Copy config.example.php to config.php first and set your database credentials.');
    }
    $config = require __DIR__ . '/config.php';
    try {
        $pdo = new PDO('mysql:host=' . $config['db_host'] . ';charset=utf8mb4', $config['db_user'], $config['db_pass']);
        $pdo->exec(file_get_contents(__DIR__ . '/sql/schema.sql'));
        $pdo->exec(file_get_contents(__DIR__ . '/sql/seed.sql'));
        echo '<h2>Installation complete!</h2><p><a href="index.php">Go to app</a></p><p style="color:red"><strong>Delete install.php now!</strong></p>';
    } catch (Exception $e) {
        echo '<h2>Error</h2><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    exit;
}
?>
<!DOCTYPE html>
<html><head><title>Install ThreadGlam</title><style>body{font-family:sans-serif;max-width:500px;margin:3rem auto;padding:1rem} .btn{display:inline-block;padding:.5rem 1rem;background:#7c3aed;color:#fff;text-decoration:none;border-radius:6px}</style></head>
<body>
<h1>ThreadGlam Installer</h1>
<ol>
    <li>Copy <code>config.example.php</code> to <code>config.php</code></li>
    <li>Set your Hostinger MySQL credentials in config.php</li>
    <li>Click install below</li>
    <li>Delete install.php after setup</li>
</ol>
<a href="?step=run" class="btn">Install Database</a>
</body></html>
