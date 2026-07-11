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
        <p>Enter password to continue</p>
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="password" name="login_password" placeholder="Password" required autofocus>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
    </div>
</body>
</html>
