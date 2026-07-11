# Database Setup — Hostinger VPS

Your app URL: **https://www.threadglam.com/inventory/**

## Quick answer

| What | Value |
|------|--------|
| **Database name** | `threadglam` |
| **Host** | `localhost` |
| **Tables** | 18 (auto-created by install.php) |

---

## Step-by-step (copy-paste ready)

### 1. Create the database

#### If you use Hostinger hPanel (recommended)

1. Open [hpanel.hostinger.com](https://hpanel.hostinger.com)
2. **Websites** → your site → **Databases** → **MySQL Databases**
3. Click **Create new database**
   - Name: `threadglam` (Hostinger may prefix it like `u123_threadglam` — that's fine, use the full name shown)
4. Click **Create new user**
   - Username: `threadglam_user` (or any name)
   - Password: choose a strong password — **save it**
5. **Add user to database** → select user + database → **All Privileges** → Save

Write down these 3 values:
```
Database name:  threadglam  (or u123_threadglam)
Username:       threadglam_user
Password:       (what you set)
```

#### If you use VPS SSH (MySQL on server)

Login and paste this block (change the password):

```bash
ssh threadglam@srv792158.hstgr.cloud
```

```sql
sudo mysql -u root -p
```

Then paste inside MySQL:

```sql
CREATE DATABASE threadglam CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'threadglam_user'@'localhost' IDENTIFIED BY 'YourStrongPassword123!';
GRANT ALL PRIVILEGES ON threadglam.* TO 'threadglam_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

### 2. Create config.php on the server

SSH to VPS and paste:

```bash
cd ~/htdocs/www.threadglam.com/inventory
cp config.example.php config.php
nano config.php
```

Edit these 4 lines to match your database (from Step 1):

```php
'db_host' => 'localhost',
'db_name' => 'threadglam',           // exact name from hPanel
'db_user' => 'threadglam_user',      // exact username from hPanel
'db_pass' => 'YourStrongPassword123!',
```

Save: **Ctrl+O** → Enter → **Ctrl+X**

---

### 3. Run the installer in your browser

Open:

```
https://www.threadglam.com/inventory/install.php
```

Click **Install Database Now**

You should see: ✅ Database installed successfully!

---

### 4. Open the app

```
https://www.threadglam.com/inventory/
```

Demo login data is seeded automatically (inventory items, 1 customer, 1 event).

---

### 5. Delete install.php (security)

On VPS:

```bash
cd ~/htdocs/www.threadglam.com/inventory
rm install.php
```

---

## config.php example (full)

```php
<?php
return [
    'db_host' => 'localhost',
    'db_name' => 'threadglam',
    'db_user' => 'threadglam_user',
    'db_pass' => 'YourStrongPassword123!',
    'app_name' => 'ThreadGlam Events',
    'upload_dir' => __DIR__ . '/uploads',
    'admin_password' => 'admin123',
];
```

---

## Troubleshooting

| Error | Fix |
|-------|-----|
| `Access denied for user` | Wrong username/password in config.php |
| `Unknown database 'threadglam'` | Create the database in hPanel first |
| `config.php missing` | Run `cp config.example.php config.php` on server |
| Blank page | Check PHP error log on VPS |
| Install works but app errors | Ensure `db_name` in config matches exactly what hPanel shows |

---

## What gets created

The installer runs `sql/schema.sql` + `sql/seed.sql` and creates:

- settings, customers, inventory_categories, inventory_items
- events, estimates, partners, purchases, sales
- contracts, budgets, attachments, and more

Demo data: 5 inventory items, 1 customer, 1 wedding event, 2 partners.
