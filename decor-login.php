<?php
/**
 * Dedicated login for the hidden Decor owner account.
 * Normal inventory admins cannot authenticate here.
 */
require_once __DIR__ . '/includes/functions.php';

try {
    ensureAdminUsersSchema();
} catch (Exception $e) {
    http_response_code(503);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;max-width:520px;margin:3rem auto;padding:1rem">';
    echo '<h2>Database not ready</h2><p>Run <a href="install.php">install.php</a> first, or check <code>config.php</code>.</p>';
    echo '</body></html>';
    exit;
}

if (isLoggedIn()) {
    redirect(isDecorOwner() ? 'decor-inventory.php' : 'index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $user = trim($_POST['login_username'] ?? '');
    $pass = $_POST['login_password'] ?? '';
    if (adminAttemptLogin($user, $pass, 'decor')) {
        redirect('decor-inventory.php');
    }
    flash('error', 'Invalid Decor username or password.');
    redirect('decor-login.php');
}

$pageTitle = 'Decor Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Decor Login - ThreadGlam</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-box">
        <h1>Decor Portal</h1>
        <p>Sign in with the Decor owner account</p>
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>
        <form method="post" class="login-form">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="login_username">Username</label>
                <input id="login_username" type="text" name="login_username" value="<?= e($_POST['login_username'] ?? '') ?>" required autofocus autocomplete="username">
            </div>
            <div class="form-group">
                <label for="login_password">Password</label>
                <input id="login_password" type="password" name="login_password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary">Sign in to Decor</button>
        </form>
        <p class="login-hint"><a href="index.php">Inventory admin login</a></p>
    </div>
</body>
</html>
