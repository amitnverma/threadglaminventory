<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();
ensureAdminUsersSchema();

$me = currentAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $err = adminCreateUser(
            $_POST['username'] ?? '',
            $_POST['password'] ?? '',
            $_POST['display_name'] ?? '',
            isset($_POST['is_active'])
        );
        flash($err ? 'error' : 'success', $err ?: 'Admin user created.');
        redirect('admins.php');
    }

    if ($action === 'update' && !empty($_POST['id'])) {
        $err = adminUpdateUser(
            (int)$_POST['id'],
            $_POST['username'] ?? '',
            $_POST['display_name'] ?? '',
            isset($_POST['is_active']),
            $_POST['password'] ?? ''
        );
        flash($err ? 'error' : 'success', $err ?: 'Admin user updated.');
        redirect($err ? ('admins.php?edit=' . (int)$_POST['id']) : 'admins.php');
    }

    if ($action === 'delete' && !empty($_POST['id'])) {
        $err = adminDeleteUser((int)$_POST['id']);
        flash($err ? 'error' : 'success', $err ?: 'Admin user deleted.');
        redirect('admins.php');
    }

    redirect('admins.php');
}

$admins = adminUsersAll();
$editId = (int)($_GET['edit'] ?? 0);
$editUser = $editId ? adminUserGet($editId) : null;
if ($editUser && adminIsDecorOwnerUser($editUser)) {
    flash('error', 'This account cannot be managed here.');
    redirect('admins.php');
}

$currentPage = 'admins';
$pageTitle = 'Admin Users';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Admin Users</h1>
        <p class="subtitle">Create and manage accounts that can sign in to the inventory system</p>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <h3><?= $editUser ? 'Edit Admin' : 'Add Admin' ?></h3>
        <form method="post">
            <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'add' ?>">
            <?php if ($editUser): ?><input type="hidden" name="id" value="<?= (int)$editUser['id'] ?>"><?php endif; ?>
            <div class="form-group">
                <label>Display name</label>
                <input name="display_name" value="<?= e($editUser['display_name'] ?? '') ?>" placeholder="Full name" required>
            </div>
            <div class="form-group">
                <label>Username *</label>
                <input name="username" value="<?= e($editUser['username'] ?? '') ?>" placeholder="e.g. jane" required autocomplete="off">
                <p class="hint">Letters, numbers, dots, dashes, underscores. Min 3 characters.</p>
            </div>
            <div class="form-group">
                <label><?= $editUser ? 'New password (optional)' : 'Password *' ?></label>
                <input type="password" name="password" placeholder="<?= $editUser ? 'Leave blank to keep current password' : 'At least 6 characters' ?>" <?= $editUser ? '' : 'required' ?> autocomplete="new-password">
            </div>
            <label class="checkbox-inline">
                <input type="checkbox" name="is_active" <?= !$editUser || (int)$editUser['is_active'] ? 'checked' : '' ?>>
                Account is active (can sign in)
            </label>
            <div class="flex" style="margin-top:1rem">
                <button type="submit" class="btn btn-primary"><?= $editUser ? 'Save changes' : 'Create admin' ?></button>
                <?php if ($editUser): ?><a href="admins.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>All admins</h3>
        <?php if (empty($admins)): ?>
            <div class="empty-state"><div class="icon">🔐</div><h3>No admin users</h3><p>Create the first account to secure the app.</p></div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Status</th>
                    <th>Last login</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($admins as $a): ?>
                <tr>
                    <td>
                        <strong><?= e($a['display_name']) ?></strong>
                        <?php if ($me && (int)$me['id'] === (int)$a['id']): ?><span class="badge badge-sent">You</span><?php endif; ?>
                    </td>
                    <td><?= e($a['username']) ?></td>
                    <td>
                        <?php if ((int)$a['is_active']): ?>
                            <span class="badge badge-approved">Active</span>
                        <?php else: ?>
                            <span class="badge badge-draft">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted"><?= $a['last_login_at'] ? formatDate($a['last_login_at']) : 'Never' ?></td>
                    <td>
                        <div class="action-btns">
                            <a href="admins.php?edit=<?= (int)$a['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                            <?php if (!$me || (int)$me['id'] !== (int)$a['id']): ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('Delete this admin user permanently?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
