<?php
require_once __DIR__ . '/includes/functions.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ensureSettingsColumns();
    execute('UPDATE settings SET company_name=?, company_address=?, company_phone=?, company_email=?, default_tax_percent=?, currency=?, contract_footer=?, pdf_header=?, ceremony_types=?, updated_at=NOW() WHERE id=1',
        [$_POST['company_name'], $_POST['company_address'] ?? null, $_POST['company_phone'] ?? null, $_POST['company_email'] ?? null,
         $_POST['default_tax_percent'], 'USD', $_POST['contract_footer'] ?? null, $_POST['pdf_header'] ?? null, $_POST['ceremony_types'] ?? null]);
    flash('success', 'Settings saved.');
    redirect('settings.php');
}

$currentPage = 'settings';
$pageTitle = 'Settings';
require_once __DIR__ . '/includes/header.php';
$s = getSettings();
$defaultCeremonyTypes = "Wedding\nReception\nBirthday\nCorporate\nAnniversary\nEngagement\nBaby Shower\nGraduation\nOther";
?>

<div class="page-header">
    <div>
        <h1>Settings</h1>
        <p class="subtitle">Company profile, defaults, and contract terms</p>
    </div>
</div>

<form method="post">
    <div class="grid-2">
        <div class="card">
            <h3>Company Profile</h3>
            <div class="form-group"><label>Company Name</label><input name="company_name" value="<?= e($s['company_name']) ?>" placeholder="Your business name"></div>
            <div class="form-group"><label>Address</label><textarea name="company_address" placeholder="123 Main St, Suite 200, New York, NY 10001"><?= e($s['company_address']) ?></textarea></div>
            <div class="form-row">
                <div class="form-group"><label>Phone</label><input name="company_phone" value="<?= e($s['company_phone']) ?>" placeholder="(555) 123-4567"></div>
                <div class="form-group"><label>Email</label><input name="company_email" value="<?= e($s['company_email']) ?>" placeholder="hello@yourcompany.com"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Default Sales Tax %</label><input type="number" step="0.001" name="default_tax_percent" value="<?= $s['default_tax_percent'] ?>" placeholder="e.g. 8.875"></div>
                <div class="form-group"><label>Currency</label>
                    <input value="USD ($)" class="readonly-field" readonly>
                    <p class="hint">All amounts displayed in US Dollars</p>
                </div>
            </div>
            <div class="form-group"><label>PDF Header Text</label><input name="pdf_header" value="<?= e($s['pdf_header']) ?>" placeholder="Tagline shown on contract PDF header"></div>
        </div>
        <div class="card">
            <h3>Event Types & Contract Terms</h3>
            <div class="form-group">
                <label>Ceremony / Event Types</label>
                <textarea name="ceremony_types" rows="6" placeholder="One type per line"><?= e($s['ceremony_types'] ?? $defaultCeremonyTypes) ?></textarea>
                <p class="hint">One per line — used in event forms. Fully customizable.</p>
            </div>
            <div class="form-group">
                <label>Contract Terms & Conditions</label>
                <textarea name="contract_footer" rows="10" placeholder="Default terms inserted via {{contract_footer}} placeholder"><?= e($s['contract_footer']) ?></textarea>
                <p class="hint">These appear in Section 5 of every contract. Include payment terms, delivery, liability, etc.</p>
            </div>
        </div>
    </div>
    <button class="btn btn-primary">Save All Settings</button>
</form>

<p class="text-muted mt-1">App login is managed under <a href="admins.php">Admin Users</a> — create accounts, reset passwords, or deactivate users there.</p>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
