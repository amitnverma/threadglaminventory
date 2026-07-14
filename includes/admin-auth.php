<?php
/**
 * Admin users — username/password login with create / edit / delete.
 * Schema is created automatically on first request (idempotent).
 */

function ensureAdminUsersSchema(): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    db()->exec(
        "CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(80) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(120) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_login_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_admin_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $count = queryOne('SELECT COUNT(*) AS n FROM admin_users');
    if ((int)($count['n'] ?? 0) === 0) {
        global $config;
        $seedPass = !empty($config['admin_password']) ? $config['admin_password'] : 'admin123';
        execute(
            'INSERT INTO admin_users (username, password_hash, display_name, is_active) VALUES (?,?,?,1)',
            ['admin', password_hash($seedPass, PASSWORD_DEFAULT), 'Administrator']
        );
    }
}

function adminUsersAll(): array
{
    return query('SELECT id, username, display_name, is_active, last_login_at, created_at, updated_at FROM admin_users ORDER BY username');
}

function adminUserGet(int $id): ?array
{
    return queryOne('SELECT * FROM admin_users WHERE id=?', [$id]);
}

function adminUserByUsername(string $username): ?array
{
    return queryOne('SELECT * FROM admin_users WHERE username=?', [$username]);
}

function adminActiveCount(): int
{
    $row = queryOne('SELECT COUNT(*) AS n FROM admin_users WHERE is_active=1');
    return (int)($row['n'] ?? 0);
}

function adminNormalizeUsername(string $username): string
{
    return strtolower(trim($username));
}

function adminValidateUsername(string $username): ?string
{
    if ($username === '' || strlen($username) < 3) return 'Username must be at least 3 characters.';
    if (strlen($username) > 80) return 'Username is too long.';
    if (!preg_match('/^[a-z0-9._-]+$/', $username)) return 'Username may only use letters, numbers, dots, dashes, and underscores.';
    return null;
}

function adminValidatePassword(string $password, bool $required = true): ?string
{
    if ($password === '') return $required ? 'Password is required.' : null;
    if (strlen($password) < 6) return 'Password must be at least 6 characters.';
    return null;
}

/** Returns error message or null on success. */
function adminCreateUser(string $username, string $password, string $displayName, bool $active = true): ?string
{
    $username = adminNormalizeUsername($username);
    $displayName = trim($displayName) ?: $username;
    if ($err = adminValidateUsername($username)) return $err;
    if ($err = adminValidatePassword($password, true)) return $err;
    if (adminUserByUsername($username)) return 'That username is already taken.';

    execute(
        'INSERT INTO admin_users (username, password_hash, display_name, is_active) VALUES (?,?,?,?)',
        [$username, password_hash($password, PASSWORD_DEFAULT), $displayName, $active ? 1 : 0]
    );
    return null;
}

/** Returns error message or null on success. */
function adminUpdateUser(int $id, string $username, string $displayName, bool $active, string $newPassword = ''): ?string
{
    $user = adminUserGet($id);
    if (!$user) return 'Admin user not found.';

    $username = adminNormalizeUsername($username);
    $displayName = trim($displayName) ?: $username;
    if ($err = adminValidateUsername($username)) return $err;
    if ($err = adminValidatePassword($newPassword, false)) return $err;

    $other = adminUserByUsername($username);
    if ($other && (int)$other['id'] !== $id) return 'That username is already taken.';

    $me = currentAdmin();
    if ($me && (int)$me['id'] === $id && !$active) {
        return 'You cannot deactivate your own account while logged in.';
    }
    if ((int)$user['is_active'] === 1 && !$active && adminActiveCount() <= 1) {
        return 'You cannot deactivate the last active admin.';
    }

    if ($newPassword !== '') {
        execute(
            'UPDATE admin_users SET username=?, display_name=?, is_active=?, password_hash=?, updated_at=NOW() WHERE id=?',
            [$username, $displayName, $active ? 1 : 0, password_hash($newPassword, PASSWORD_DEFAULT), $id]
        );
    } else {
        execute(
            'UPDATE admin_users SET username=?, display_name=?, is_active=?, updated_at=NOW() WHERE id=?',
            [$username, $displayName, $active ? 1 : 0, $id]
        );
    }

    if ($me && (int)$me['id'] === $id) {
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_name'] = $displayName;
    }
    return null;
}

/** Returns error message or null on success. */
function adminDeleteUser(int $id): ?string
{
    $user = adminUserGet($id);
    if (!$user) return 'Admin user not found.';

    $me = currentAdmin();
    if ($me && (int)$me['id'] === $id) {
        return 'You cannot delete your own account while logged in.';
    }
    if (adminActiveCount() <= 1 && (int)$user['is_active'] === 1) {
        return 'You cannot delete the last active admin.';
    }

    execute('DELETE FROM admin_users WHERE id=?', [$id]);
    return null;
}

function adminAttemptLogin(string $username, string $password): bool
{
    $user = adminUserByUsername(adminNormalizeUsername($username));
    if (!$user || !(int)$user['is_active']) return false;
    if (!password_verify($password, $user['password_hash'])) return false;

    session_regenerate_id(true);
    $_SESSION['logged_in'] = true;
    $_SESSION['admin_id'] = (int)$user['id'];
    $_SESSION['admin_username'] = $user['username'];
    $_SESSION['admin_name'] = $user['display_name'];
    execute('UPDATE admin_users SET last_login_at=NOW() WHERE id=?', [$user['id']]);
    return true;
}

function adminLogout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function currentAdmin(): ?array
{
    if (empty($_SESSION['logged_in']) || empty($_SESSION['admin_id'])) return null;
    $user = adminUserGet((int)$_SESSION['admin_id']);
    if (!$user || !(int)$user['is_active']) return null;
    return $user;
}

function isLoggedIn(): bool
{
    return currentAdmin() !== null;
}
