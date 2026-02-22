<?php
/**
 * Admin — Site Settings
 * Runtime configuration persisted to storage/settings.json.
 */
require_once __DIR__ . '/../../includes/Config.php';
Config::load();
require_once __DIR__ . '/../../includes/AdminAuth.php';
AdminAuth::check();
require_once __DIR__ . '/../../includes/SiteSettings.php';
SiteSettings::load();

$flash     = '';
$flashType = 'success';

// ── POST: save settings ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {

    $new = [
        'offline' => [
            'enabled' => isset($_POST['offline_enabled']),
            'message' => trim($_POST['offline_message'] ?? ''),
            'eta'     => trim($_POST['offline_eta']     ?? ''),
        ],
        'cache' => [
            'ttl_hours' => max(1, (int)($_POST['cache_ttl_hours'] ?? 24)),
        ],
        'scraper' => [
            'timeout'    => max(5, min(120, (int)($_POST['scraper_timeout']   ?? 10))),
            'min_delay'  => max(0, min(30,  (int)($_POST['scraper_min_delay'] ?? 2))),
            'ssl_verify' => isset($_POST['scraper_ssl_verify']),
        ],
        'rate_limit' => [
            'enabled'  => isset($_POST['rate_limit_enabled']),
            'requests' => max(1, min(1000, (int)($_POST['rate_limit_requests'] ?? 10))),
            'period'   => max(10, min(3600, (int)($_POST['rate_limit_period']  ?? 60))),
        ],
    ];

    if (SiteSettings::save($new)) {
        $flash = 'Settings saved successfully.';
    } else {
        $flash     = 'Failed to write settings file — check storage/ directory permissions.';
        $flashType = 'error';
    }
}

// Re-read current values for the form (picks up just-saved values)
$s = SiteSettings::all();

$pageTitle = 'Settings';
$activeNav = 'settings';
require __DIR__ . '/_header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flashType === 'error' ? 'error' : 'success' ?>">
        <?= htmlspecialchars($flash) ?>
    </div>
<?php endif; ?>

<form method="POST" action="/admin/settings.php">
    <input type="hidden" name="action" value="save">

    <!-- ── Site Status ──────────────────────────────────────────────────────── -->
    <div class="settings-section">
        <div class="settings-section-header">
            <h2>Site Status</h2>
            <p>Put the public homepage into maintenance mode. The admin panel remains accessible.</p>
        </div>

        <div class="settings-group">
            <label class="toggle-label">
                <input type="checkbox" name="offline_enabled" value="1"
                    <?= !empty($s['offline']['enabled']) ? 'checked' : '' ?>>
                <span class="toggle-track"><span class="toggle-thumb"></span></span>
                <span class="toggle-text">Offline / Maintenance mode</span>
            </label>
            <?php if (!empty($s['offline']['enabled'])): ?>
                <div class="settings-badge-online settings-badge-offline">Site is currently OFFLINE</div>
            <?php else: ?>
                <div class="settings-badge-online">Site is currently ONLINE</div>
            <?php endif; ?>
        </div>

        <div class="settings-group">
            <label for="offline_message">Maintenance message shown to visitors</label>
            <textarea id="offline_message" name="offline_message"
                      rows="3" class="settings-textarea"><?= htmlspecialchars($s['offline']['message'] ?? '') ?></textarea>
        </div>

        <div class="settings-group">
            <label for="offline_eta">Expected return time <span class="label-hint">(optional, shown to visitors)</span></label>
            <input type="text" id="offline_eta" name="offline_eta"
                   class="settings-input" placeholder="e.g. Friday 21 Feb at 18:00 UTC"
                   value="<?= htmlspecialchars($s['offline']['eta'] ?? '') ?>">
        </div>
    </div>

    <!-- ── Extraction Cache ─────────────────────────────────────────────────── -->
    <div class="settings-section">
        <div class="settings-section-header">
            <h2>Extraction Cache</h2>
            <p>How long a successfully scraped recipe is cached before being re-fetched from its source.</p>
        </div>

        <div class="settings-row">
            <div class="settings-group">
                <label for="cache_ttl_hours">Cache TTL <span class="label-hint">(hours)</span></label>
                <input type="number" id="cache_ttl_hours" name="cache_ttl_hours"
                       class="settings-input settings-input-sm" min="1" max="8760"
                       value="<?= (int)($s['cache']['ttl_hours'] ?? 24) ?>">
            </div>
        </div>
    </div>


    <!-- ── Scraper ──────────────────────────────────────────────────────────── -->
    <div class="settings-section">
        <div class="settings-section-header">
            <h2>Scraper</h2>
            <p>Runtime tuning for the recipe scraping engine. Applied immediately to new extraction requests.</p>
        </div>

        <div class="settings-row">
            <div class="settings-group">
                <label for="scraper_timeout">Request timeout <span class="label-hint">(seconds, 5–120)</span></label>
                <input type="number" id="scraper_timeout" name="scraper_timeout"
                       class="settings-input settings-input-sm" min="5" max="120"
                       value="<?= (int)($s['scraper']['timeout'] ?? 10) ?>">
            </div>
            <div class="settings-group">
                <label for="scraper_min_delay">Min delay between requests to same domain <span class="label-hint">(seconds, 0–30)</span></label>
                <input type="number" id="scraper_min_delay" name="scraper_min_delay"
                       class="settings-input settings-input-sm" min="0" max="30"
                       value="<?= (int)($s['scraper']['min_delay'] ?? 2) ?>">
            </div>
        </div>

        <div class="settings-group">
            <label class="toggle-label">
                <input type="checkbox" name="scraper_ssl_verify" value="1"
                    <?= !empty($s['scraper']['ssl_verify']) ? 'checked' : '' ?>>
                <span class="toggle-track"><span class="toggle-thumb"></span></span>
                <span class="toggle-text">Verify SSL certificates <span class="label-hint">(recommended: on)</span></span>
            </label>
        </div>
    </div>

    <!-- ── Rate Limiting ────────────────────────────────────────────────────── -->
    <div class="settings-section">
        <div class="settings-section-header">
            <h2>Rate Limiting</h2>
            <p>Controls how many extraction requests a single IP can make within a given time window.</p>
        </div>

        <div class="settings-group">
            <label class="toggle-label">
                <input type="checkbox" name="rate_limit_enabled" value="1"
                    <?= !empty($s['rate_limit']['enabled']) ? 'checked' : '' ?>>
                <span class="toggle-track"><span class="toggle-thumb"></span></span>
                <span class="toggle-text">Rate limiting enabled</span>
            </label>
        </div>

        <div class="settings-row">
            <div class="settings-group">
                <label for="rate_limit_requests">Max requests <span class="label-hint">(per period)</span></label>
                <input type="number" id="rate_limit_requests" name="rate_limit_requests"
                       class="settings-input settings-input-sm" min="1" max="1000"
                       value="<?= (int)($s['rate_limit']['requests'] ?? 10) ?>">
            </div>
            <div class="settings-group">
                <label for="rate_limit_period">Period <span class="label-hint">(seconds, 10–3600)</span></label>
                <input type="number" id="rate_limit_period" name="rate_limit_period"
                       class="settings-input settings-input-sm" min="10" max="3600"
                       value="<?= (int)($s['rate_limit']['period'] ?? 60) ?>">
            </div>
        </div>
    </div>

    <!-- ── Save ─────────────────────────────────────────────────────────────── -->
    <div class="settings-actions">
        <button type="submit" class="btn btn-primary">Save Settings</button>
    </div>

</form>

<style>
/* ── Settings page layout ───────────────────────────────────────────── */
.settings-section {
    background: var(--card-bg, #fff);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 8px;
    padding: 1.5rem 2rem;
    margin-bottom: 1.5rem;
}
.settings-section-header {
    margin-bottom: 1.25rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border, #e2e8f0);
}
.settings-section-header h2 {
    margin: 0 0 .25rem;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary, #1a202c);
}
.settings-section-header p {
    margin: 0;
    font-size: .85rem;
    color: var(--text-secondary, #64748b);
}
.settings-group {
    margin-bottom: 1.1rem;
}
.settings-group label:not(.toggle-label) {
    display: block;
    font-size: .875rem;
    font-weight: 500;
    color: var(--text-primary, #1a202c);
    margin-bottom: .375rem;
}
.label-hint {
    font-weight: 400;
    color: var(--text-secondary, #64748b);
    font-size: .8rem;
}
.settings-row {
    display: flex;
    gap: 2rem;
    flex-wrap: wrap;
}
.settings-row .settings-group {
    flex: 1;
    min-width: 180px;
}
.settings-input {
    width: 100%;
    padding: .5rem .75rem;
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 6px;
    font-size: .9rem;
    color: var(--text-primary, #1a202c);
    background: var(--input-bg, #f8fafc);
    box-sizing: border-box;
}
.settings-input:focus {
    outline: none;
    border-color: var(--accent, #3b82f6);
    box-shadow: 0 0 0 3px rgba(59,130,246,.15);
}
.settings-input-sm { max-width: 140px; }
.settings-textarea {
    width: 100%;
    padding: .5rem .75rem;
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 6px;
    font-size: .9rem;
    resize: vertical;
    background: var(--input-bg, #f8fafc);
    color: var(--text-primary, #1a202c);
    box-sizing: border-box;
}
.settings-textarea:focus {
    outline: none;
    border-color: var(--accent, #3b82f6);
    box-shadow: 0 0 0 3px rgba(59,130,246,.15);
}

/* ── Toggle switch ──────────────────────────────────────────────────── */
.toggle-label {
    display: inline-flex;
    align-items: center;
    gap: .6rem;
    cursor: pointer;
    user-select: none;
}
.toggle-label input[type="checkbox"] { display: none; }
.toggle-track {
    position: relative;
    width: 40px;
    height: 22px;
    background: #cbd5e1;
    border-radius: 999px;
    transition: background .2s;
    flex-shrink: 0;
}
.toggle-label input:checked + .toggle-track { background: #3b82f6; }
.toggle-thumb {
    position: absolute;
    top: 3px; left: 3px;
    width: 16px; height: 16px;
    background: #fff;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0,0,0,.2);
    transition: left .2s;
}
.toggle-label input:checked + .toggle-track .toggle-thumb { left: 21px; }
.toggle-text { font-size: .875rem; font-weight: 500; color: var(--text-primary, #1a202c); }

/* ── Status badge ───────────────────────────────────────────────────── */
.settings-badge-online {
    display: inline-block;
    margin-top: .4rem;
    padding: .2rem .65rem;
    border-radius: 999px;
    font-size: .75rem;
    font-weight: 600;
    background: #dcfce7;
    color: #166534;
}
.settings-badge-offline {
    background: #fee2e2;
    color: #991b1b;
}

/* ── Save button row ────────────────────────────────────────────────── */
.settings-actions {
    display: flex;
    justify-content: flex-end;
    padding-bottom: 2rem;
}
.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
    border-radius: 6px;
    padding: .75rem 1rem;
    margin-bottom: 1.25rem;
    font-size: .9rem;
}
</style>

<?php require __DIR__ . '/_footer.php'; ?>
