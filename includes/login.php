<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ThreadGlam</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-box">
        <h1>ThreadGlam</h1>
        <p>Sign in with your admin account</p>
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>
        <form method="post" class="login-form">
            <div class="form-group">
                <label for="login_username">Username</label>
                <input id="login_username" type="text" name="login_username" value="<?= e($_POST['login_username'] ?? '') ?>" placeholder="admin" required autofocus autocomplete="username">
            </div>
            <div class="form-group">
                <label for="login_password">Password</label>
                <input id="login_password" type="password" name="login_password" placeholder="Password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary">Sign in</button>
        </form>
        <p class="login-hint">Default first login: <strong>admin</strong> / <strong>admin123</strong> (change this in Admin Users).</p>
    </div>
</body>
</html>
