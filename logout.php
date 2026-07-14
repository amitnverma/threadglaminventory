<?php
require_once __DIR__ . '/includes/functions.php';
try {
    ensureAdminUsersSchema();
} catch (Exception $e) {
    // Ignore — still allow logout/clear session
}
adminLogout();
session_start();
flash('success', 'You have been signed out.');
redirect('index.php');
