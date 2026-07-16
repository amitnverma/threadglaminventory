<?php
require_once __DIR__ . '/includes/functions.php';
requireDecorOwner();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'change_password') {
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if ($new !== $confirm) {
            $err = 'New password and confirmation do not match.';
        } else {
            $err = decorOwnerChangePassword($_POST['current_password'] ?? '', $new);
        }
        flash($err ? 'error' : 'success', $err ?: 'Password updated.');
        redirect('decor-profile.php');
    }
    redirect('decor-profile.php');
}

$me = currentAdmin();
$currentPage = 'decor-profile';
$pageTitle = 'Decor Profile';
$loadDecorInventory = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Decor Profile</h1>
        <p class="subtitle">Change the Decor owner password. This account is not listed under Admin Users.</p>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Account</h3>
        <p><strong><?= e($me['display_name']) ?></strong></p>
        <p class="text-muted">@<?= e($me['username']) ?></p>
        <p class="hint">Use this password only at the Decor login page. Normal inventory admins cannot manage this account.</p>
    </div>
    <div class="card">
        <h3>Change password</h3>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="change_password">
            <div class="form-group">
                <label>Current password</label>
                <input type="password" name="current_password" required autocomplete="current-password">
            </div>
            <div class="form-group">
                <label>New password</label>
                <input type="password" name="new_password" required minlength="6" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label>Confirm new password</label>
                <input type="password" name="confirm_password" required minlength="6" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary">Update password</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
