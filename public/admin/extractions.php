<?php
/**
 * Admin — Extractions list with search, filter, sort, and pagination
 */
require_once __DIR__ . '/../../includes/Config.php';
Config::load();
require_once __DIR__ . '/../../includes/AdminAuth.php';
AdminAuth::check();

require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/ExtractionRepository.php';

$repo = new ExtractionRepository(Database::getInstance());
// ── POST: toggle featured ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_featured') {
    $toggleId = (int)($_POST['id'] ?? 0);
    if ($toggleId) {
        $existing = $repo->findById($toggleId);
        if ($existing) {
            $repo->markFeatured($toggleId, !(bool)$existing['is_featured']);
        }
    }
    // PRG — rebuild query string from hidden filter params, then redirect back
    $back = array_filter([
        'q'         => $_POST['_q']         ?? '',
        'status'    => $_POST['_status']    ?? '',
        'domain'    => $_POST['_domain']    ?? '',
        'featured'  => $_POST['_featured']  ?? '',
        'conf_min'  => $_POST['_conf_min']  ?? '',
        'conf_max'  => $_POST['_conf_max']  ?? '',
        'date_from' => $_POST['_date_from'] ?? '',
        'date_to'   => $_POST['_date_to']   ?? '',
        'sort'      => $_POST['_sort']      ?? '',
        'dir'       => $_POST['_dir']       ?? '',
        'page'      => $_POST['_page']      ?? '',
    ], fn($v) => $v !== '');
    header('Location: /admin/extractions.php' . ($back ? '?' . http_build_query($back) : ''));
    exit;
}
// ── Collect filters from GET ───────────────────────────────────────────────
$filters = [
    'q'         => trim($_GET['q']         ?? ''),
    'status'    => $_GET['status']         ?? '',
    'domain'    => trim($_GET['domain']    ?? ''),
    'featured'  => $_GET['featured']       ?? '',
    'conf_min'  => $_GET['conf_min']       ?? '',
    'conf_max'  => $_GET['conf_max']       ?? '',
    'date_from' => $_GET['date_from']      ?? '',
    'date_to'   => $_GET['date_to']        ?? '',
    'sort'      => $_GET['sort']           ?? 'last_seen_at',
    'dir'       => $_GET['dir']            ?? 'DESC',
    'page'      => max(1, (int)($_GET['page'] ?? 1)),
    'per_page'  => 25,
];

$result = $repo->search($filters);
$rows   = $result['data'];
$total  = $result['total'];
$lastPage = $result['last_page'];

// ── Helpers ────────────────────────────────────────────────────────────────
function filterUrl(array $overrides = []): string {
    global $filters;
    $p = array_merge($filters, $overrides);
    unset($p['per_page']);
    return '/admin/extractions.php?' . http_build_query(array_filter($p, fn($v) => $v !== ''));
}

function sortLink(string $col, string $label): string {
    global $filters;
    $dir = ($filters['sort'] === $col && $filters['dir'] === 'DESC') ? 'ASC' : 'DESC';
    $arrow = ($filters['sort'] === $col) ? ($filters['dir'] === 'DESC' ? ' ↓' : ' ↑') : '';
    return '<a href="' . filterUrl(['sort'=>$col,'dir'=>$dir,'page'=>1]) . '">' . htmlspecialchars($label) . $arrow . '</a>';
}

function statusBadge(string $status): string {
    $map = ['success'=>'badge-success','error'=>'badge-error','pending'=>'badge-pending'];
    $cls = $map[$status] ?? '';
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($status) . '</span>';
}

$pageTitle = 'Extractions';
$activeNav = 'extractions';
require __DIR__ . '/_header.php';
?>

<!-- ── Filter bar ─────────────────────────────────────────────────────────── -->
<div class="panel">
    <form method="GET" action="/admin/extractions.php">
        <div class="filter-bar">
            <div class="filter-group" style="flex:2;min-width:200px;">
                <label>Search</label>
                <input type="text" name="q" value="<?= htmlspecialchars($filters['q']) ?>"
                       placeholder="Title, URL, or domain…">
            </div>

            <div class="filter-group">
                <label>Status</label>
                <select name="status" style="min-width:120px;">
                    <option value="">All</option>
                    <option value="success"  <?= $filters['status']==='success'  ? 'selected':'' ?>>Success</option>
                    <option value="error"    <?= $filters['status']==='error'    ? 'selected':'' ?>>Error</option>
                    <option value="pending"  <?= $filters['status']==='pending'  ? 'selected':'' ?>>Pending</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Domain</label>
                <input type="text" name="domain" value="<?= htmlspecialchars($filters['domain']) ?>"
                       placeholder="e.g. allrecipes.com" style="min-width:160px;">
            </div>

            <div class="filter-group">
                <label>Featured</label>
                <select name="featured" style="min-width:100px;">
                    <option value="">Any</option>
                    <option value="1" <?= $filters['featured']==='1' ? 'selected':'' ?>>Yes</option>
                    <option value="0" <?= $filters['featured']==='0' ? 'selected':'' ?>>No</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Conf. min</label>
                <input type="number" name="conf_min" value="<?= htmlspecialchars($filters['conf_min']) ?>"
                       min="0" max="100" step="1" style="width:75px;">
            </div>

            <div class="filter-group">
                <label>Conf. max</label>
                <input type="number" name="conf_max" value="<?= htmlspecialchars($filters['conf_max']) ?>"
                       min="0" max="100" step="1" style="width:75px;">
            </div>

            <div class="filter-group">
                <label>From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>"
                       style="width:140px;">
            </div>

            <div class="filter-group">
                <label>To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>"
                       style="width:140px;">
            </div>

            <div class="filter-group" style="justify-content:flex-end;flex-direction:row;gap:.5rem;align-items:flex-end;">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="/admin/extractions.php" class="btn btn-ghost">Clear</a>
            </div>
        </div>
    </form>

    <!-- ── Table header row ───────────────────────────────────────────────── -->
    <div style="padding:.6rem 1.5rem;background:var(--color-background);border-bottom:1px solid var(--color-border-light);display:flex;align-items:center;justify-content:space-between;font-size:.78rem;color:var(--color-text-muted);">
        <span><?= number_format($total) ?> result<?= $total !== 1 ? 's' : '' ?></span>
        <div class="flex-gap">
            <?php
            $exportParams = array_filter([
                'q'         => $filters['q'],
                'status'    => $filters['status'],
                'domain'    => $filters['domain'],
                'featured'  => $filters['featured'],
                'conf_min'  => $filters['conf_min'],
                'conf_max'  => $filters['conf_max'],
                'date_from' => $filters['date_from'],
                'date_to'   => $filters['date_to'],
            ], fn($v) => $v !== '');
            $exportUrl = '/admin/export.php' . ($exportParams ? '?' . http_build_query($exportParams) : '');
            ?>
            <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-ghost btn-sm">⬇ Export CSV</a>
        </div>
    </div>

    <!-- ── Data table ─────────────────────────────────────────────────────── -->
    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th><?= sortLink('id', '#') ?></th>
                    <th>Img</th>
                    <th><?= sortLink('domain', 'Domain') ?></th>
                    <th><?= sortLink('title', 'Title') ?></th>
                    <th><?= sortLink('status', 'Status') ?></th>
                    <th class="col-num"><?= sortLink('confidence_score', 'Confidence') ?></th>
                    <th class="col-num"><?= sortLink('submission_count', 'Subs') ?></th>
                    <th class="col-num"><?= sortLink('cache_hit_count', 'Cache Hits') ?></th>
                    <th>Featured</th>
                    <th><?= sortLink('last_seen_at', 'Last Seen') ?></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><a href="/admin/extraction-detail.php?id=<?= (int)$row['id'] ?>">#<?= (int)$row['id'] ?></a></td>
                    <td>
                        <?php if ($row['image_url']): ?>
                            <img src="<?= htmlspecialchars($row['image_url']) ?>" class="thumb"
                                 loading="lazy" alt="" onerror="this.style.display='none'">
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= filterUrl(['domain'=>$row['domain'],'page'=>1]) ?>">
                            <?= htmlspecialchars($row['domain']) ?>
                        </a>
                    </td>
                    <td class="col-wrap">
                        <a href="/admin/extraction-detail.php?id=<?= (int)$row['id'] ?>">
                            <?= htmlspecialchars($row['title'] ?: '(untitled)') ?>
                        </a>
                    </td>
                    <td>
                        <?= statusBadge($row['status']) ?>
                        <?php if ($row['status']==='error' && $row['error_code']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($row['error_code']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="col-num">
                        <?php if ($row['confidence_score']): ?>
                            <div class="conf-bar-wrap">
                                <div class="conf-bar">
                                    <div class="conf-bar-fill" style="width:<?= min(100,(float)$row['confidence_score']) ?>%"></div>
                                </div>
                                <?= (float)$row['confidence_score'] ?>%
                            </div>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="col-num"><?= (int)$row['submission_count'] ?></td>
                    <td class="col-num"><?= (int)$row['cache_hit_count'] ?></td>
                    <td><?= $row['is_featured'] ? '<span class="badge badge-featured">★</span>' : '' ?></td>
                    <td class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars(substr($row['last_seen_at'] ?? '', 0, 16)) ?></td>
                    <td>
                        <div class="flex-gap">
                            <a href="/admin/extraction-detail.php?id=<?= (int)$row['id'] ?>"
                               class="btn btn-ghost btn-sm">View</a>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action"    value="toggle_featured">
                                <input type="hidden" name="id"        value="<?= (int)$row['id'] ?>">
                                <input type="hidden" name="_q"         value="<?= htmlspecialchars($filters['q']) ?>">
                                <input type="hidden" name="_status"    value="<?= htmlspecialchars($filters['status']) ?>">
                                <input type="hidden" name="_domain"    value="<?= htmlspecialchars($filters['domain']) ?>">
                                <input type="hidden" name="_featured"  value="<?= htmlspecialchars($filters['featured']) ?>">
                                <input type="hidden" name="_conf_min"  value="<?= htmlspecialchars($filters['conf_min']) ?>">
                                <input type="hidden" name="_conf_max"  value="<?= htmlspecialchars($filters['conf_max']) ?>">
                                <input type="hidden" name="_date_from" value="<?= htmlspecialchars($filters['date_from']) ?>">
                                <input type="hidden" name="_date_to"   value="<?= htmlspecialchars($filters['date_to']) ?>">
                                <input type="hidden" name="_sort"      value="<?= htmlspecialchars($filters['sort']) ?>">
                                <input type="hidden" name="_dir"       value="<?= htmlspecialchars($filters['dir']) ?>">
                                <input type="hidden" name="_page"      value="<?= (int)$filters['page'] ?>">
                                <button type="submit" class="btn btn-ghost btn-sm"
                                        title="<?= $row['is_featured'] ? 'Remove from featured' : 'Mark as featured' ?>">
                                    <?= $row['is_featured'] ? '★' : '☆' ?>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
                <tr><td colspan="11" class="text-muted" style="text-align:center;padding:2.5rem;">
                    No extractions match your filters.
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ── Pagination ─────────────────────────────────────────────────────── -->
    <?php if ($lastPage > 1): ?>
    <div class="pagination">
        <a href="<?= filterUrl(['page'=>max(1,$filters['page']-1)]) ?>"
           class="<?= $filters['page']<=1 ? 'disabled':'' ?>">‹ Prev</a>

        <?php
        $window = 2;
        for ($p=1; $p<=$lastPage; $p++):
            if ($p===1 || $p===$lastPage || abs($p-$filters['page'])<=$window):
        ?>
            <a href="<?= filterUrl(['page'=>$p]) ?>"
               class="<?= $p===$filters['page'] ? 'current-page':'' ?>"><?= $p ?></a>
        <?php elseif (abs($p-$filters['page'])===$window+1): ?>
            <span>…</span>
        <?php endif; endfor; ?>

        <a href="<?= filterUrl(['page'=>min($lastPage,$filters['page']+1)]) ?>"
           class="<?= $filters['page']>=$lastPage ? 'disabled':'' ?>">Next ›</a>

        <span class="text-muted" style="font-size:.72rem;margin-left:.5rem;">
            Page <?= $filters['page'] ?> of <?= $lastPage ?>
        </span>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
