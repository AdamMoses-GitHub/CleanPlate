<?php
/**
 * Admin Dashboard — Overview statistics
 */
require_once __DIR__ . '/../../includes/Config.php';
Config::load();
require_once __DIR__ . '/../../includes/AdminAuth.php';
AdminAuth::check();

require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/ExtractionRepository.php';

$repo  = new ExtractionRepository(Database::getInstance());
$stats = $repo->getDashboardStats();
$topDomains  = $repo->getTopDomains(10);
$topErrors   = $repo->getTopErrors(8);
$recent      = $repo->getRecentActivity(15);

// Derived
$successRate = ($stats['total_submissions_unique'] > 0)
    ? round(($stats['total_success'] / $stats['total_submissions_unique']) * 100, 1)
    : 0;

$cacheHitRate = ($stats['total_submissions_all'] > 0)
    ? round(($stats['total_cache_hits'] / $stats['total_submissions_all']) * 100, 1)
    : 0;

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/_header.php';

function statCard(string $label, $value, string $sub = '', string $cls = ''): void {
    echo '<div class="stat-card ' . $cls . '">';
    echo '<span class="stat-label">' . htmlspecialchars($label) . '</span>';
    echo '<span class="stat-value">' . htmlspecialchars((string)$value) . '</span>';
    if ($sub) echo '<span class="stat-sub">' . htmlspecialchars($sub) . '</span>';
    echo '</div>';
}

function statusBadge(string $status): string {
    $map = ['success'=>'badge-success','error'=>'badge-error','pending'=>'badge-pending'];
    $cls = $map[$status] ?? '';
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($status) . '</span>';
}
?>

<!-- ── Primary stat grid ──────────────────────────────────────────────────── -->
<div class="stat-grid">
    <?php statCard('Total Submissions', number_format((int)$stats['total_submissions_all']),   'all time (incl. duplicates)'); ?>
    <?php statCard('Unique URLs',        number_format((int)$stats['total_submissions_unique']), 'distinct URLs scraped'); ?>
    <?php statCard('Unique Domains',     number_format((int)$stats['unique_domains']),           'distinct sites'); ?>
    <?php statCard('Success Rate',       $successRate . '%',          (int)$stats['total_success'] . ' successful', 'stat-success'); ?>
    <?php statCard('Errors',             number_format((int)$stats['total_error']), '',           'stat-error'); ?>
    <?php statCard('Featured',           number_format((int)$stats['total_featured']),            'pinned to carousel', 'stat-accent'); ?>
    <?php statCard('Avg Confidence',     ($stats['avg_confidence'] ?? '—') . ($stats['avg_confidence'] ? '%' : ''), 'on successful extractions'); ?>
    <?php statCard('Cache Hit Rate',     $cacheHitRate . '%',         number_format((int)$stats['total_cache_hits']) . ' cache hits'); ?>
    <?php statCard('Avg Parse Time',     $stats['avg_processing_ms'] ? number_format((int)$stats['avg_processing_ms']) . ' ms' : '—', 'per successful scrape'); ?>
    <?php statCard('Today',              number_format((int)$stats['today']),      'new URLs first seen today'); ?>
    <?php statCard('This Week',          number_format((int)$stats['this_week']),  'last 7 days'); ?>
    <?php statCard('This Month',         number_format((int)$stats['this_month']), 'last 30 days'); ?>
</div>

<!-- ── Content row ────────────────────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">

    <!-- Top Domains -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Top Domains</span>
            <a href="/admin/extractions.php" class="btn btn-ghost btn-sm">View all →</a>
        </div>
        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th class="col-num">Submissions</th>
                        <th class="col-num">Success</th>
                        <th class="col-num">Avg Conf</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($topDomains as $d): ?>
                    <tr>
                        <td>
                            <a href="/admin/extractions.php?domain=<?= urlencode($d['domain']) ?>">
                                <?= htmlspecialchars($d['domain']) ?>
                            </a>
                        </td>
                        <td class="col-num"><?= number_format((int)$d['total_submissions']) ?></td>
                        <td class="col-num"><?= number_format((int)$d['successes']) ?></td>
                        <td class="col-num"><?= $d['avg_confidence'] !== null ? $d['avg_confidence'] . '%' : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($topDomains)): ?>
                    <tr><td colspan="4" class="text-muted" style="text-align:center;padding:1.5rem;">No data yet</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Errors -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Top Error Codes</span>
            <a href="/admin/extractions.php?status=error" class="btn btn-ghost btn-sm">View errors →</a>
        </div>
        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Error Code</th>
                        <th class="col-num">Occurrences</th>
                        <th>Last Seen</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($topErrors as $e): ?>
                    <tr>
                        <td><span class="badge badge-error"><?= htmlspecialchars($e['error_code']) ?></span></td>
                        <td class="col-num"><?= number_format((int)$e['occurrences']) ?></td>
                        <td class="text-muted"><?= htmlspecialchars(substr($e['last_seen'] ?? '', 0, 16)) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($topErrors)): ?>
                    <tr><td colspan="3" class="text-muted" style="text-align:center;padding:1.5rem;">No errors recorded</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Recent Activity ────────────────────────────────────────────────────── -->
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">Recent Activity</span>
        <a href="/admin/extractions.php" class="btn btn-ghost btn-sm">View all →</a>
    </div>
    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Domain</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th class="col-num">Confidence</th>
                    <th class="col-num">Submissions</th>
                    <th>Featured</th>
                    <th>Last Seen</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recent as $row): ?>
                <tr>
                    <td><a href="/admin/extraction-detail.php?id=<?= (int)$row['id'] ?>">#<?= (int)$row['id'] ?></a></td>
                    <td><?= htmlspecialchars($row['domain']) ?></td>
                    <td class="col-wrap">
                        <a href="/admin/extraction-detail.php?id=<?= (int)$row['id'] ?>">
                            <?= htmlspecialchars($row['title'] ?: '(untitled)') ?>
                        </a>
                    </td>
                    <td><?= statusBadge($row['status']) ?></td>
                    <td class="col-num">
                        <?php if ($row['confidence_score']): ?>
                            <div class="conf-bar-wrap">
                                <div class="conf-bar">
                                    <div class="conf-bar-fill" style="width:<?= min(100,(float)$row['confidence_score']) ?>%"></div>
                                </div>
                                <?= (float)$row['confidence_score'] ?>%
                            </div>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="col-num"><?= (int)$row['submission_count'] ?></td>
                    <td><?= $row['is_featured'] ? '<span class="badge badge-featured">★ featured</span>' : '' ?></td>
                    <td class="text-muted"><?= htmlspecialchars(substr($row['last_seen_at'] ?? '', 0, 16)) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($recent)): ?>
                <tr><td colspan="8" class="text-muted" style="text-align:center;padding:2rem;">
                    No extractions yet. <a href="/">Submit a recipe URL</a> to get started.
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
