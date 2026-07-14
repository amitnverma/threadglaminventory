<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/comm-functions.php';
requireAuth();
ensureCommsSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_company';
    ensureSettingsColumns();

    if ($action === 'save_company') {
        execute('UPDATE settings SET company_name=?, company_address=?, company_phone=?, company_email=?, default_tax_percent=?, currency=?, contract_footer=?, pdf_header=?, ceremony_types=?, updated_at=NOW() WHERE id=1',
            [$_POST['company_name'], $_POST['company_address'] ?? null, $_POST['company_phone'] ?? null, $_POST['company_email'] ?? null,
             $_POST['default_tax_percent'], 'USD', $_POST['contract_footer'] ?? null, $_POST['pdf_header'] ?? null, $_POST['ceremony_types'] ?? null]);
        flash('success', 'Settings saved.');
        redirect('settings.php');
    }

    if ($action === 'save_ai') {
        saveAiSettings([
            'provider' => $_POST['provider'] ?? 'openrouter',
            'api_key' => trim($_POST['api_key'] ?? ''),
            'base_url' => trim($_POST['base_url'] ?? ''),
            'model' => trim($_POST['model'] ?? ''),
            'enable_suggest' => isset($_POST['enable_suggest']) ? 1 : 0,
            'enable_summarize' => isset($_POST['enable_summarize']) ? 1 : 0,
            'max_transcript_chars' => max(1000, min(20000, (int)($_POST['max_transcript_chars'] ?? 8000))),
        ]);
        flash('success', 'AI settings saved.');
        redirect('settings.php#ai');
    }

    if ($action === 'save_template' && !empty($_POST['template_id'])) {
        $tid = (int)$_POST['template_id'];
        $name = trim($_POST['name'] ?? '');
        $ceremony = trim($_POST['ceremony_type'] ?? '');
        $raw = trim($_POST['questions_text'] ?? '');
        // One question per line → JSON array
        $questions = [];
        $i = 0;
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $questions[] = ['key' => 'q_' . $i++, 'text' => $line];
        }
        if ($name === '' || empty($questions)) {
            flash('error', 'Template needs a name and at least one question (one per line).');
            redirect('settings.php#templates');
        }
        execute(
            'UPDATE comm_templates SET name=?, ceremony_type=?, questions_json=?, updated_at=NOW() WHERE id=?',
            [$name, $ceremony, json_encode($questions, JSON_UNESCAPED_UNICODE), $tid]
        );
        flash('success', 'Question pack updated.');
        redirect('settings.php#templates');
    }

    redirect('settings.php');
}

$currentPage = 'settings';
$pageTitle = 'Settings';
require_once __DIR__ . '/includes/header.php';
$s = getSettings();
$ai = getAiSettings();
$templates = commTemplatesAll();
$editTplId = (int)($_GET['tpl'] ?? 0);
$editTpl = $editTplId ? queryOne('SELECT * FROM comm_templates WHERE id=?', [$editTplId]) : null;
$defaultCeremonyTypes = "Wedding\nReception\nBirthday\nCorporate\nAnniversary\nEngagement\nBaby Shower\nGraduation\nOther";
$keyPlaceholder = ($ai['api_key'] ?? '') !== '' ? '•••••••• (leave blank to keep current key)' : 'Paste API key';
?>

<div class="page-header">
    <div>
        <h1>Settings</h1>
        <p class="subtitle">Company profile, AI assistants, and communication question packs</p>
    </div>
</div>

<form method="post">
    <input type="hidden" name="action" value="save_company">
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

<div class="card" id="ai" style="margin-top:1.5rem">
    <h3>AI assistants (pluggable)</h3>
    <p class="text-muted">Used for follow-up question suggestions and meeting summaries. Only structured text is sent — never audio files. Keep spend low with a cheap model.</p>
    <form method="post">
        <input type="hidden" name="action" value="save_ai">
        <div class="form-row">
            <div class="form-group"><label>Provider</label>
                <select name="provider" id="aiProvider">
                    <option value="openrouter" <?= ($ai['provider'] ?? '') === 'openrouter' ? 'selected' : '' ?>>OpenRouter</option>
                    <option value="deepseek" <?= ($ai['provider'] ?? '') === 'deepseek' ? 'selected' : '' ?>>DeepSeek</option>
                    <option value="openai_compatible" <?= ($ai['provider'] ?? '') === 'openai_compatible' ? 'selected' : '' ?>>OpenAI-compatible</option>
                </select>
            </div>
            <div class="form-group"><label>Model</label>
                <input name="model" value="<?= e($ai['model'] ?? '') ?>" placeholder="Leave blank for provider default">
                <p class="hint">Examples: deepseek-chat · deepseek/deepseek-chat · gpt-4o-mini</p>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>API key</label>
                <input type="password" name="api_key" value="" placeholder="<?= e($keyPlaceholder) ?>" autocomplete="new-password">
            </div>
            <div class="form-group"><label>Base URL (optional)</label>
                <input name="base_url" value="<?= e($ai['base_url'] ?? '') ?>" placeholder="Auto by provider, or custom endpoint">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Max transcript chars to send</label>
                <input type="number" name="max_transcript_chars" value="<?= (int)($ai['max_transcript_chars'] ?? 8000) ?>" min="1000" max="20000">
                <p class="hint">Hard cap for token budget (default 8000).</p>
            </div>
            <div class="form-group" style="padding-top:1.6rem">
                <label class="checkbox-inline"><input type="checkbox" name="enable_suggest" <?= !empty($ai['enable_suggest']) ? 'checked' : '' ?>> Enable AI suggest questions</label><br>
                <label class="checkbox-inline"><input type="checkbox" name="enable_summarize" <?= !empty($ai['enable_summarize']) ? 'checked' : '' ?>> Enable AI summarize</label>
            </div>
        </div>
        <button class="btn btn-primary">Save AI settings</button>
    </form>
</div>

<div class="card" id="templates" style="margin-top:1.5rem">
    <h3>Communication question packs</h3>
    <p class="text-muted">Loaded automatically when you start an initial meeting for a ceremony type. One question per line when editing.</p>
    <div class="table-wrap">
        <table class="data-table">
            <tr><th>Name</th><th>Ceremony type</th><th>Questions</th><th></th></tr>
            <?php foreach ($templates as $t):
                $qs = json_decode($t['questions_json'] ?? '[]', true) ?: [];
                ?>
            <tr>
                <td><?= e($t['name']) ?></td>
                <td><?= e($t['ceremony_type'] !== '' ? $t['ceremony_type'] : 'General') ?></td>
                <td><?= count($qs) ?></td>
                <td><a class="btn btn-sm btn-primary" href="settings.php?tpl=<?= (int)$t['id'] ?>#templates">Edit</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <?php if ($editTpl):
        $lines = [];
        foreach (json_decode($editTpl['questions_json'] ?? '[]', true) ?: [] as $q) {
            $lines[] = $q['text'] ?? '';
        }
        ?>
    <form method="post" style="margin-top:1.25rem;border-top:1px solid var(--border);padding-top:1rem">
        <input type="hidden" name="action" value="save_template">
        <input type="hidden" name="template_id" value="<?= (int)$editTpl['id'] ?>">
        <h4>Edit: <?= e($editTpl['name']) ?></h4>
        <div class="form-row">
            <div class="form-group"><label>Pack name</label><input name="name" value="<?= e($editTpl['name']) ?>" required></div>
            <div class="form-group"><label>Ceremony type</label>
                <input name="ceremony_type" value="<?= e($editTpl['ceremony_type']) ?>" list="ceremonyList" placeholder="Wedding, or blank for General">
                <datalist id="ceremonyList">
                    <?php foreach (getCeremonyTypes() as $ct): ?><option value="<?= e($ct) ?>"><?php endforeach; ?>
                </datalist>
            </div>
        </div>
        <div class="form-group"><label>Questions (one per line)</label>
            <textarea name="questions_text" rows="14" required><?= e(implode("\n", $lines)) ?></textarea>
        </div>
        <div class="flex">
            <button class="btn btn-primary">Save pack</button>
            <a href="settings.php#templates" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
    <?php endif; ?>
</div>

<p class="text-muted mt-1">App login is managed under <a href="admins.php">Admin Users</a> — create accounts, reset passwords, or deactivate users there.</p>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
