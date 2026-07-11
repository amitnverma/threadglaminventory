# ThreadGlam Event Manager

Simple PHP + JavaScript + MySQL event management app for Hostinger (or any PHP/MySQL hosting).

## Features

- **Inventory** — add, edit, track stock, upload images
- **Events** — manage ceremonies, link customers, track status
- **Estimates** — add items from inventory catalog, calculate tax/discount/profit
- **Purchases & Sales** — record costs and revenue
- **Partners** — track partner expenses (multi-row entry)
- **Contracts** — create from estimates, edit, print/save as PDF
- **Reports** — profit & loss per event
- **Settings** — company profile, tax defaults

## Requirements

- PHP 7.4+ (PHP 8.x recommended)
- MySQL 5.7+ or MariaDB
- Apache with mod_rewrite (standard on Hostinger)

## Auto-deploy (GitHub → Hostinger VPS)

Push to `main` deploys automatically. **Setup takes 3 steps:**

```bash
bash scripts/setup-deploy-key.sh
```

Then paste once on VPS (command is auto-copied to clipboard). Full guide: **[DEPLOY.md](DEPLOY.md)**

## Hostinger Setup

### 1. Upload files (or use auto-deploy)

Upload all files to your `public_html` folder (or subdomain folder) via File Manager or FTP — or use the GitHub auto-deploy above.

```
public_html/
├── index.php
├── config.php
├── includes/
├── assets/
├── uploads/        (must be writable: chmod 755)
├── sql/
└── ... other .php files
```

### 2. Create MySQL database

In Hostinger hPanel:
1. Go to **Databases → MySQL Databases**
2. Create a database (e.g. `u123456789_threadglam`)
3. Create a database user and assign it to the database
4. Note the host (usually `localhost`), database name, username, and password

### 3. Configure

```bash
cp config.example.php config.php
```

Edit `config.php`:

```php
return [
    'db_host' => 'localhost',
    'db_name' => 'u123456789_threadglam',
    'db_user' => 'u123456789_user',
    'db_pass' => 'your_password',
    'admin_password' => 'your_secret',  // optional login protection
];
```

### 4. Install database

Visit `https://yourdomain.com/inventory/install.php` in your browser.

**Database name to create in hPanel:** `threadglam`

Full step-by-step: **[DATABASE-SETUP.md](DATABASE-SETUP.md)**

**Delete `install.php` after installation.**

### 5. Set permissions

Make `uploads/` writable:

```
chmod 755 uploads/
```

## Local XAMPP Setup

1. Copy project to `htdocs/threadGlam/Untitled/`
2. Start Apache + MySQL in XAMPP
3. Edit `config.php` with `db_user=root`, `db_pass=` (empty)
4. Visit `http://localhost/threadGlam/Untitled/install.php`
5. Open `http://localhost/threadGlam/Untitled/`

## Optional Password Protection

Set `admin_password` in `config.php` to require a login before accessing the app.

## Contract PDF

Open any contract and click **Print / Save as PDF** — use your browser's print dialog to save as PDF.

## File Structure

```
index.php              Dashboard
inventory.php          Inventory list
inventory-form.php     Add/edit item
inventory-view.php     Item detail + images
events.php             Events list
event-form.php         Add/edit event
event-view.php         Event hub (estimates, expenses, images, P&L)
estimates.php          Estimates list
estimate-form.php      Estimate builder
purchases.php          Record purchases
sales.php              Record sales
partners.php           Partners & expenses
contracts.php          Contracts list
contract-edit.php      Edit contract
contract-print.php     Print-friendly contract
reports.php            P&L reports
settings.php           Company settings
includes/              PHP helpers, layout
assets/css/style.css   Styles
assets/js/app.js       Client-side JavaScript
sql/schema.sql         Database tables
sql/seed.sql           Demo data
scripts/               Deploy setup helper
```

Pure PHP app — no Node.js or build step required.
