<?php
/**
 * One-time database installer.
 * Visit: https://www.threadglam.com/inventory/install.php
 * DELETE this file after installation!
 */
$step = $_GET['step'] ?? 'check';
$configPath = __DIR__ . '/config.php';

function runSqlFile(PDO $pdo, string $sql): void
{
    // Remove comments and split into statements
    $sql = preg_replace('/--.*$/m', '', $sql);
    $parts = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($parts as $statement) {
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
}

if ($step === 'run') {
    if (!file_exists($configPath)) {
        die('<h2>config.php missing</h2><p>Copy <code>config.example.php</code> to <code>config.php</code> and set your database credentials first.</p><p><a href="install.php">Back</a></p>');
    }
    $config = require $configPath;
    $dbName = $config['db_name'] ?? 'threadglam';

    try {
        $pdo = new PDO(
            'mysql:host=' . $config['db_host'] . ';charset=utf8mb4',
            $config['db_user'],
            $config['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // Try to create database (works on VPS; may fail on shared hosting — create DB in panel first)
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (Exception $e) {
            // Database may already exist or user lacks CREATE privilege — continue
        }

        $pdo->exec("USE `{$dbName}`");

        $schema = str_replace('threadglam', $dbName, file_get_contents(__DIR__ . '/sql/schema.sql'));
        $seed = str_replace('threadglam', $dbName, file_get_contents(__DIR__ . '/sql/seed.sql'));

        // Strip CREATE DATABASE / USE from schema (already handled)
        $schema = preg_replace('/CREATE DATABASE[^;]+;/i', '', $schema);
        $schema = preg_replace('/USE[^;]+;/i', '', $schema);
        $seed = preg_replace('/USE[^;]+;/i', '', $seed);

        runSqlFile($pdo, $schema);
        runSqlFile($pdo, $seed);

        echo '<!DOCTYPE html><html><head><title>Installed</title><style>body{font-family:sans-serif;max-width:560px;margin:3rem auto;padding:1rem;line-height:1.6}.ok{color:#059669}.warn{color:#dc2626}</style></head><body>';
        echo '<h2 class="ok">✅ Database installed successfully!</h2>';
        echo '<p><strong>Database:</strong> ' . htmlspecialchars($dbName) . '</p>';
        echo '<p>18 tables created with demo data.</p>';
        echo '<p><a href="index.php" style="display:inline-block;padding:.6rem 1.2rem;background:#7c3aed;color:#fff;text-decoration:none;border-radius:8px">Open App →</a></p>';
        echo '<p class="warn"><strong>Important:</strong> Delete <code>install.php</code> from the server now for security.</p>';
        echo '</body></html>';
    } catch (Exception $e) {
        echo '<!DOCTYPE html><html><head><title>Install Error</title><style>body{font-family:sans-serif;max-width:560px;margin:3rem auto;padding:1rem}code{background:#f3f4f6;padding:2px 6px;border-radius:4px}</style></head><body>';
        echo '<h2>❌ Installation failed</h2>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<h3>Checklist</h3><ol>';
        echo '<li>Database <code>' . htmlspecialchars($dbName) . '</code> exists in Hostinger/hPanel or MySQL</li>';
        echo '<li><code>config.php</code> has correct host, user, password</li>';
        echo '<li>MySQL user has permission on that database</li>';
        echo '</ol>';
        echo '<p>See <strong>DATABASE-SETUP.md</strong> in the repo for full instructions.</p>';
        echo '<p><a href="install.php">← Back</a></p></body></html>';
    }
    exit;
}

$hasConfig = file_exists($configPath);
$configHint = $hasConfig ? require $configPath : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Install ThreadGlam Database</title>
    <style>
        body { font-family: -apple-system, sans-serif; max-width: 640px; margin: 2rem auto; padding: 1rem; line-height: 1.6; color: #1f2937; }
        h1 { color: #7c3aed; }
        .step { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 1rem 1.25rem; margin: 1rem 0; }
        .step h3 { margin: 0 0 .5rem; font-size: 1rem; }
        code, pre { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-size: .9rem; }
        pre { padding: 1rem; overflow-x: auto; }
        .btn { display: inline-block; padding: .75rem 1.5rem; background: #7c3aed; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; margin-top: 1rem; }
        .btn:hover { background: #6d28d9; }
        .ok { color: #059669; font-weight: 600; }
        .warn { color: #92400e; }
        table { width: 100%; border-collapse: collapse; margin: .5rem 0; font-size: .9rem; }
        td, th { border: 1px solid #e5e7eb; padding: .5rem .75rem; text-align: left; }
        th { background: #f9fafb; }
    </style>
</head>
<body>
    <h1>ThreadGlam — Database Installer</h1>
    <p>One-time setup. Creates all tables and demo data.</p>

    <div class="step">
        <h3>Recommended database name</h3>
        <table>
            <tr><th>Setting</th><th>Value</th></tr>
            <tr><td>Database name</td><td><code>threadglam</code></td></tr>
            <tr><td>Host</td><td><code>localhost</code></td></tr>
            <tr><td>Charset</td><td><code>utf8mb4</code></td></tr>
        </table>
        <p class="warn">On Hostinger shared hosting the name may be prefixed, e.g. <code>u123456789_threadglam</code> — use whatever you create in hPanel.</p>
    </div>

    <div class="step">
        <h3>Step 1 — Create database in Hostinger</h3>
        <p><strong>Option A: hPanel (easiest)</strong></p>
        <ol>
            <li>Login to <a href="https://hpanel.hostinger.com" target="_blank">Hostinger hPanel</a></li>
            <li>Go to <strong>Websites → Manage → Databases → MySQL Databases</strong></li>
            <li>Create database: <code>threadglam</code> (or any name — note it down)</li>
            <li>Create a user with a strong password</li>
            <li>Assign user to the database with <strong>All privileges</strong></li>
        </ol>
        <p><strong>Option B: VPS via SSH</strong></p>
        <pre>ssh threadglam@srv792158.hstgr.cloud
sudo mysql -u root -p

CREATE DATABASE threadglam CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'threadglam_user'@'localhost' IDENTIFIED BY 'YourStrongPassword123!';
GRANT ALL PRIVILEGES ON threadglam.* TO 'threadglam_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;</pre>
    </div>

    <div class="step">
        <h3>Step 2 — Create config.php on server</h3>
        <?php if ($hasConfig): ?>
            <p class="ok">✅ config.php found</p>
            <p>Database: <code><?= htmlspecialchars($configHint['db_name'] ?? '') ?></code> · Host: <code><?= htmlspecialchars($configHint['db_host'] ?? '') ?></code></p>
        <?php else: ?>
            <p class="warn">⚠️ config.php not found — create it first:</p>
        <?php endif; ?>
        <pre>cd ~/htdocs/www.threadglam.com/inventory
cp config.example.php config.php
nano config.php</pre>
        <p>Set these values to match your database:</p>
        <pre><?= htmlspecialchars(<<<'PHP'
return [
    'db_host' => 'localhost',
    'db_name' => 'threadglam',        // your database name from hPanel
    'db_user' => 'threadglam_user',   // your database user
    'db_pass' => 'YourPasswordHere',
    'admin_password' => 'admin123',   // optional app login
];
PHP
) ?></pre>
    </div>

    <div class="step">
        <h3>Step 3 — Click Install</h3>
        <p>Creates 18 tables + demo inventory, customer, event data.</p>
        <?php if ($hasConfig): ?>
            <a href="?step=run" class="btn">Install Database Now</a>
        <?php else: ?>
            <p class="warn">Create config.php first, then refresh this page.</p>
        <?php endif; ?>
    </div>

    <div class="step">
        <h3>Step 4 — Delete install.php</h3>
        <p>After success, remove this file from the server for security.</p>
    </div>
</body>
</html>
