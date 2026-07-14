<?php
/**
 * Copy to config.php and update for your Hostinger database.
 *
 * DATABASE NAME: threadglam  (recommended)
 * Create this in Hostinger hPanel → Databases → MySQL Databases
 * Or on VPS: CREATE DATABASE threadglam;
 */
return [
    'db_host' => 'localhost',
    'db_name' => 'threadglam',           // ← your database name from hPanel
    'db_user' => 'threadglam_user',      // ← your database username
    'db_pass' => 'YOUR_PASSWORD_HERE',   // ← your database password
    'app_name' => 'ThreadGlam Events',
    'upload_dir' => __DIR__ . '/uploads',
    // Optional: used only to seed the first admin account if the admins table is empty.
    // After that, manage logins in Admin Users. Leave empty to seed admin / admin123.
    'admin_password' => '',
];
