<?php
require_once __DIR__ . '/../../includes/Config.php';
require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/AdminAuth.php';
require_once __DIR__ . '/../../includes/VisitRepository.php';

Config::load();
AdminAuth::check();

// ── Query params ───────────────────────────────────────────────────────────────
$days = max(1, min(365, (int)($_GET['days'] ?? 30)));

// ── Data ───────────────────────────────────────────────────────────────────────
$dbError = null;
$stats = $trend = $topPages = $topReferrers = $devices = $browsers = $oses = $recent = [];

try {
    $repo      = new VisitRepository(Database::getInstance());
    $stats     = $repo->getSummaryStats();
    $trend     = $repo->getDailyTrend($days);
    $topPages  = $repo->getTopPages(10, $days);
    $topReferrers = $repo->getTopReferrers(10, $days);
    $devices   = $repo->getDeviceBreakdown($days);
    $browsers  = $repo->getBrowserBreakdown($days);
    $oses      = $repo->getOsBreakdown($days);
    $recent    = $repo->getRecentVisits(25);
    $botCount  = $repo->getBotCount($days);
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

// ── Sparkline data (max value for scaling) ─────────────────────────────────────
$trendViews    = array_column($trend, 'views');
$trendUniq     = array_column($trend, 'unique_visitors');
$trendMaxViews = max(array_merge($trendViews, [1]));

// ── Helpers ────────────────────────────────────────────────────────────────────
function sv(array $stats, string $key, $default = 0) {
    return $stats[$key] ?? $default;
}
function pct(int $a, int $b): string {
    return $b > 0 ? round(100 * $a / $b, 1) . '%' : '—';
}
function deviceIcon(string $d): string {
    return match($d) {
        'mobile'  => '📱',
        'tablet'  => '🖥',
        'desktop' => '💻',
        'bot'     => '🤖',
        default   => '❓',
    };
}

$pageTitle = 'Analytics';
$activeNav = 'analytics';
require_once __DIR__ . '/_header.php';
?>

<?php if ($dbError): ?>
<div class="alert alert-error">Database error: <?= htmlspecialchars($dbError) ?></div>
<?php endif; ?>

<!-- ── Period selector ─────────────────────────────────────────────────────── -->
<div class="filter-bar" style="margin-bottom:1.5rem;">
    <span style="font-weight:500;color:var(--color-text-secondary);">Period:</span>
    <?php foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days', 365 => '1 year'] as $d => $label): ?>
        <a href="?days=<?= $d ?>"
           class="btn <?= $days === $d ? 'btn-primary' : 'btn-ghost' ?>"
           style="padding:.25rem .75rem;font-size:.8rem;">
            <?= $label ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- ── Stat cards ─────────────────────────────────────────────────────────── -->
<div class="stat-grid" style="margin-bottom:2rem;">
    <div class="stat-card">
        <div class="stat-label">Page Views (today)</div>
        <div class="stat-value"><?= number_format((int)sv($stats,'views_today')) ?></div>
        <div class="stat-sub">Total all time: <?= number_format((int)sv($stats,'total_views')) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Unique Visitors (today)</div>
        <div class="stat-value"><?= number_format((int)sv($stats,'unique_visitors_today')) ?></div>
        <div class="stat-sub">All time: <?= number_format((int)sv($stats,'total_unique')) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Views (<?= $days ?>d)</div>
        <div class="stat-value"><?= number_format((int)sv($stats,'views_month')) ?></div>
        <div class="stat-sub">Last 7 days: <?= number_format((int)sv($stats,'views_week')) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Unique Visitors (<?= $days ?>d)</div>
        <div class="stat-value"><?= number_format((int)sv($stats,'unique_visitors_month')) ?></div>
        <div class="stat-sub">Last 7 days: <?= number_format((int)sv($stats,'unique_visitors_week')) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Bot Visits (<?= $days ?>d)</div>
        <div class="stat-value"><?= number_format($botCount ?? 0) ?></div>
        <div class="stat-sub"><?= sv($stats,'bot_pct') ?>% of all traffic</div>
    </div>
</div>

<!-- ── Daily trend chart ───────────────────────────────────────────────────── -->
<div class="panel" style="margin-bottom:2rem;">
    <div class="panel-header">Daily Traffic — last <?= $days ?> days</div>
    <div class="panel-body">
        <?php if (empty($trend)): ?>
            <p style="color:var(--color-text-muted);text-align:center;padding:2rem 0;">No visit data yet for this period.</p>
        <?php else: ?>
        <div class="trend-chart-wrap">
            <div class="trend-chart">
                <?php foreach ($trend as $point):
                    $barH   = $trendMaxViews > 0 ? round(100 * $point['views'] / $trendMaxViews) : 0;
                    $title  = $point['date'] . ': ' . $point['views'] . ' views, ' . $point['unique_visitors'] . ' unique';
                ?>
                <div class="trend-bar-col" title="<?= htmlspecialchars($title) ?>">
                    <div class="trend-bar" style="height:<?= $barH ?>%;"></div>
                    <div class="trend-uniq-dot" style="bottom:<?= $barH ?>%;" title="<?= $point['unique_visitors'] ?> unique"></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="trend-legend">
                <span class="legend-views">■ Page views</span>
                <span class="legend-uniq">● Unique visitors</span>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Top Pages + Top Referrers ──────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:2rem;">

    <!-- Top pages -->
    <div class="panel">
        <div class="panel-header">Top Pages (<?= $days ?>d, excl. bots)</div>
        <div class="panel-body" style="padding:0;">
            <?php if (empty($topPages)): ?>
                <p style="padding:1rem;color:var(--color-text-muted);">No data yet.</p>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Page</th><th style="text-align:right;">Views</th><th style="text-align:right;">Unique</th></tr></thead>
                <tbody>
                <?php foreach ($topPages as $row): ?>
                <tr>
                    <td><span class="truncate-cell" title="<?= htmlspecialchars($row['page']) ?>"><?= htmlspecialchars($row['page']) ?></span></td>
                    <td style="text-align:right;"><?= number_format((int)$row['views']) ?></td>
                    <td style="text-align:right;"><?= number_format((int)$row['unique_visitors']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top referrers -->
    <div class="panel">
        <div class="panel-header">Top Referrers (<?= $days ?>d)</div>
        <div class="panel-body" style="padding:0;">
            <?php if (empty($topReferrers)): ?>
                <p style="padding:1rem;color:var(--color-text-muted);">No data yet.</p>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Source</th><th style="text-align:right;">Visits</th></tr></thead>
                <tbody>
                <?php foreach ($topReferrers as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['referrer_domain']) ?></td>
                    <td style="text-align:right;"><?= number_format((int)$row['visits']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ── Device / Browser / OS ──────────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.5rem;margin-bottom:2rem;">

    <!-- Devices -->
    <div class="panel">
        <div class="panel-header">Devices (<?= $days ?>d)</div>
        <div class="panel-body">
            <?php if (empty($devices)): ?>
                <p style="color:var(--color-text-muted);">No data yet.</p>
            <?php else: ?>
            <?php foreach ($devices as $row): ?>
            <div class="breakdown-row">
                <span><?= deviceIcon($row['device_type']) ?> <?= htmlspecialchars(ucfirst($row['device_type'])) ?></span>
                <div class="breakdown-bar-wrap">
                    <div class="breakdown-bar" style="width:<?= $row['pct'] ?>%;"></div>
                </div>
                <span class="breakdown-pct"><?= $row['pct'] ?>%</span>
                <span class="breakdown-count">(<?= number_format((int)$row['visits']) ?>)</span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Browsers -->
    <div class="panel">
        <div class="panel-header">Browsers (<?= $days ?>d)</div>
        <div class="panel-body">
            <?php if (empty($browsers)): ?>
                <p style="color:var(--color-text-muted);">No data yet.</p>
            <?php else: ?>
            <?php foreach ($browsers as $row): ?>
            <div class="breakdown-row">
                <span><?= htmlspecialchars($row['browser']) ?></span>
                <div class="breakdown-bar-wrap">
                    <div class="breakdown-bar" style="width:<?= $row['pct'] ?>%;"></div>
                </div>
                <span class="breakdown-pct"><?= $row['pct'] ?>%</span>
                <span class="breakdown-count">(<?= number_format((int)$row['visits']) ?>)</span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- OS -->
    <div class="panel">
        <div class="panel-header">Operating Systems (<?= $days ?>d)</div>
        <div class="panel-body">
            <?php if (empty($oses)): ?>
                <p style="color:var(--color-text-muted);">No data yet.</p>
            <?php else: ?>
            <?php foreach ($oses as $row): ?>
            <div class="breakdown-row">
                <span><?= htmlspecialchars($row['os']) ?></span>
                <div class="breakdown-bar-wrap">
                    <div class="breakdown-bar" style="width:<?= $row['pct'] ?>%;"></div>
                </div>
                <span class="breakdown-pct"><?= $row['pct'] ?>%</span>
                <span class="breakdown-count">(<?= number_format((int)$row['visits']) ?>)</span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ── Recent visits ───────────────────────────────────────────────────────── -->
<div class="panel">
    <div class="panel-header">Recent Visits (excl. bots)</div>
    <div class="panel-body" style="padding:0;">
        <?php if (empty($recent)): ?>
            <p style="padding:1rem;color:var(--color-text-muted);">No visit data yet. Visit the <a href="/">homepage</a> and refresh.</p>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Page</th>
                    <th>Referrer</th>
                    <th>Device</th>
                    <th>Browser</th>
                    <th>OS</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recent as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['page']) ?></td>
                <td><?= htmlspecialchars($row['referrer_domain'] ?? '—') ?></td>
                <td><?= deviceIcon($row['device_type']) ?> <?= htmlspecialchars(ucfirst($row['device_type'])) ?></td>
                <td><?= htmlspecialchars($row['browser'] ?? '—') ?></td>
                <td><?= htmlspecialchars($row['os'] ?? '—') ?></td>
                <td style="white-space:nowrap;"><?= htmlspecialchars($row['visited_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
