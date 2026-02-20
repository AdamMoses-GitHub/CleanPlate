<?php
/**
 * Admin — Extraction detail view
 * Actions: view full data, toggle featured, update admin notes,
 *          flush cache, re-extract (force refresh), delete
 */
require_once __DIR__ . '/../../includes/Config.php';
Config::load();
require_once __DIR__ . '/../../includes/AdminAuth.php';
AdminAuth::check();

require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/ExtractionRepository.php';

$repo = new ExtractionRepository(Database::getInstance());
$id   = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: /admin/extractions.php');
    exit;
}

$row = $repo->findById($id);
if (!$row) {
    header('Location: /admin/extractions.php');
    exit;
}

$flash = '';
$flashType = 'success';

// ── POST action handler ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_featured') {
        $featured = !((bool)$row['is_featured']);
        $repo->markFeatured($id, $featured);
        $flash = $featured ? '★ Marked as featured.' : 'Removed from featured.';
        $row['is_featured'] = (int)$featured;

    } elseif ($action === 'save_notes') {
        $notes = trim($_POST['admin_notes'] ?? '');
        $repo->updateAdminNotes($id, $notes);
        $row['admin_notes'] = $notes;
        $flash = 'Notes saved.';

    } elseif ($action === 'flush_cache') {
        $repo->flushCache($id);
        $row['cached_at'] = null;
        $row['cache_expires_at'] = null;
        $flash = 'Cache flushed — next submission will re-scrape.';
        $flashType = 'info';

    } elseif ($action === 'delete') {
        $repo->deleteById($id);
        header('Location: /admin/extractions.php?flash=deleted');
        exit;

    } elseif ($action === 're_extract') {
        // Flush cache then redirect to the main UI in headless mode isn't easy
        // without a background job. Best approach: flush cache and advise user.
        $repo->flushCache($id);
        $flash = 'Cache cleared. Resubmit this URL to re-scrape: ' . htmlspecialchars($row['url']);
        $flashType = 'info';
    }

    // Re-fetch to get latest DB state
    $row = $repo->findById($id);
}

// ── Decode JSON columns ────────────────────────────────────────────────────
$ingredients       = json_decode($row['ingredients'] ?? 'null', true)       ?? [];
$instructions      = json_decode($row['instructions'] ?? 'null', true)      ?? [];
$category          = json_decode($row['category'] ?? 'null', true)          ?? [];
$cuisine           = json_decode($row['cuisine'] ?? 'null', true)           ?? [];
$keywords          = json_decode($row['keywords'] ?? 'null', true)          ?? [];
$dietaryInfo       = json_decode($row['dietary_info'] ?? 'null', true)      ?? [];
$imageCandidates   = json_decode($row['image_candidates'] ?? 'null', true)  ?? [];
$confidenceDetails = json_decode($row['confidence_details'] ?? 'null', true) ?? [];
$rawResponse       = json_decode($row['raw_response'] ?? 'null', true);

$pageTitle = 'Extraction #' . $id;
$activeNav = 'extractions';
require __DIR__ . '/_header.php';

function dRow(string $label, $value, bool $wrap = false): void {
    if ($value === null || $value === '' || $value === [] || $value === 'null') return;
    $display = is_array($value) ? implode(', ', $value) : $value;
    $cls = $wrap ? 'detail-value col-wrap' : 'detail-value';
    echo '<div class="detail-row">';
    echo '  <span class="detail-label">' . htmlspecialchars($label) . '</span>';
    echo '  <span class="' . $cls . '">' . htmlspecialchars((string)$display) . '</span>';
    echo '</div>';
}
?>

<!-- ── Flash ──────────────────────────────────────────────────────────────── -->
<?php if ($flash): ?>
    <div class="alert alert-<?= $flashType === 'info' ? 'info' : 'success' ?>">
        <?= $flash ?>
    </div>
<?php endif; ?>

<!-- ── Action bar ─────────────────────────────────────────────────────────── -->
<div class="flex-gap mb-2">
    <a href="/admin/extractions.php" class="btn btn-ghost btn-sm">← Back to list</a>

    <form method="POST" style="display:inline">
        <input type="hidden" name="action" value="toggle_featured">
        <button type="submit" class="btn <?= $row['is_featured'] ? 'btn-warning' : 'btn-accent' ?> btn-sm">
            <?= $row['is_featured'] ? '✖ Unfeature' : '★ Mark Featured' ?>
        </button>
    </form>

    <form method="POST" style="display:inline">
        <input type="hidden" name="action" value="flush_cache">
        <button type="submit" class="btn btn-ghost btn-sm"
                <?= !$row['cached_at'] ? 'disabled title="No cache to flush"' : '' ?>>
            ↺ Flush Cache
        </button>
    </form>

    <form method="POST" style="display:inline">
        <input type="hidden" name="action" value="re_extract">
        <button type="submit" class="btn btn-ghost btn-sm">⟳ Force Re-extract</button>
    </form>

    <?php if ($row['url']): ?>
    <a href="<?= htmlspecialchars($row['url']) ?>" target="_blank" rel="noopener"
       class="btn btn-ghost btn-sm">↗ Open URL</a>
    <?php endif; ?>

    <span class="spacer"></span>

    <form method="POST" style="display:inline"
          onsubmit="return confirm('Delete extraction #<?= $id ?>? This cannot be undone.');">
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="btn btn-danger btn-sm">🗑 Delete</button>
    </form>
</div>

<!-- ── Detail grid ────────────────────────────────────────────────────────── -->
<div class="detail-grid">

    <!-- Left col: identity + extraction result ──────────────────────────── -->
    <div>
        <div class="panel">
            <div class="panel-header"><span class="panel-title">Extraction Info</span></div>
            <div class="panel-body">
                <?php dRow('ID',          '#' . $id); ?>
                <?php dRow('URL',         $row['url']); ?>
                <?php dRow('Domain',      $row['domain']); ?>
                <?php dRow('Status',      $row['status']); ?>
                <?php dRow('Phase',       $row['phase'] ? 'Phase ' . $row['phase'] . ($row['phase']==1?' (JSON-LD)'  :' (DOM)') : null); ?>
                <?php dRow('Confidence',  $row['confidence_score'] ? $row['confidence_score'] . '% (' . ($row['confidence_level']??'') . ')' : null); ?>
                <?php dRow('Parse Time',  $row['processing_time_ms'] ? number_format((int)$row['processing_time_ms']) . ' ms' : null); ?>
                <?php dRow('Error Code',  $row['error_code']); ?>
                <?php dRow('Error Msg',   $row['error_message'], true); ?>
                <?php dRow('Submissions', number_format((int)$row['submission_count'])); ?>
                <?php dRow('Cache Hits',  number_format((int)$row['cache_hit_count'])); ?>
                <?php dRow('Featured',    $row['is_featured'] ? '★ Yes' : 'No'); ?>
                <?php dRow('First Seen',  $row['first_seen_at']); ?>
                <?php dRow('Last Seen',   $row['last_seen_at']); ?>
                <?php dRow('Cached At',   $row['cached_at'] ?: null); ?>
                <?php dRow('Cache Exp',   $row['cache_expires_at'] ?: null); ?>
            </div>
        </div>

        <?php if ($confidenceDetails): ?>
        <div class="panel">
            <div class="panel-header"><span class="panel-title">Confidence Factors</span></div>
            <div class="panel-body">
                <?php foreach ($confidenceDetails as $factor => $val): ?>
                <div class="detail-row">
                    <span class="detail-label"><?= htmlspecialchars($factor) ?></span>
                    <span class="detail-value"><?= htmlspecialchars((string)$val) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Admin Notes -->
        <div class="panel">
            <div class="panel-header"><span class="panel-title">Admin Notes</span></div>
            <div class="panel-body">
                <form method="POST">
                    <input type="hidden" name="action" value="save_notes">
                    <textarea name="admin_notes" style="width:100%"
                              rows="4"><?= htmlspecialchars($row['admin_notes'] ?? '') ?></textarea>
                    <button type="submit" class="btn btn-primary btn-sm mt-1">Save Notes</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right col: recipe content ───────────────────────────────────────── -->
    <div>
        <?php if ($row['title']): ?>
        <div class="panel">
            <div class="panel-header"><span class="panel-title">Recipe Content</span></div>
            <div class="panel-body">
                <?php dRow('Title',       $row['title']); ?>
                <?php dRow('Description', $row['description'], true); ?>
                <?php dRow('Site Name',   $row['site_name']); ?>
                <?php dRow('Author',      $row['author']); ?>
                <?php dRow('Prep Time',   $row['prep_time']); ?>
                <?php dRow('Cook Time',   $row['cook_time']); ?>
                <?php dRow('Total Time',  $row['total_time']); ?>
                <?php dRow('Servings',    $row['servings']); ?>
                <?php dRow('Category',    $category); ?>
                <?php dRow('Cuisine',     $cuisine); ?>
                <?php dRow('Dietary',     $dietaryInfo); ?>
                <?php dRow('Rating',      ($row['rating_value'] && $row['rating_count']) ? $row['rating_value'] . '/5 (' . number_format((int)$row['rating_count']) . ' ratings)' : null); ?>
                <?php if ($keywords): dRow('Keywords', implode(', ', array_slice($keywords,0,20))); endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($row['image_url']): ?>
        <div class="panel">
            <div class="panel-header"><span class="panel-title">Image</span></div>
            <div class="panel-body">
                <img src="<?= htmlspecialchars($row['image_url']) ?>"
                     style="max-width:100%;border-radius:8px;max-height:260px;object-fit:cover;"
                     alt="" onerror="this.style.display='none'">
                <p style="font-size:.72rem;color:var(--color-text-muted);margin-top:.5rem;word-break:break-all;">
                    <?= htmlspecialchars($row['image_url']) ?>
                </p>
                <?php if ($imageCandidates): ?>
                    <p class="text-muted mt-1" style="font-size:.75rem;">
                        <?= count($imageCandidates) ?> candidate<?= count($imageCandidates)!==1?'s':'' ?> found
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Ingredients & Instructions ────────────────────────────────────────── -->
<?php if ($ingredients || $instructions): ?>
<div class="detail-grid mt-2">
    <?php if ($ingredients): ?>
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Ingredients (<?= count($ingredients) ?>)</span>
        </div>
        <div class="panel-body">
            <ol style="padding-left:1.2rem;font-size:.82rem;line-height:1.7;">
                <?php foreach ($ingredients as $ing): ?>
                    <li><?= htmlspecialchars($ing) ?></li>
                <?php endforeach; ?>
            </ol>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($instructions): ?>
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Instructions (<?= count($instructions) ?>)</span>
        </div>
        <div class="panel-body">
            <ol style="padding-left:1.2rem;font-size:.82rem;line-height:1.7;">
                <?php foreach ($instructions as $step): ?>
                    <li><?= htmlspecialchars($step) ?></li>
                <?php endforeach; ?>
            </ol>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── Raw JSON ───────────────────────────────────────────────────────────── -->
<?php if ($rawResponse): ?>
<div class="panel mt-2">
    <div class="panel-header"><span class="panel-title">Raw Parser Response</span></div>
    <div class="panel-body">
        <details class="collapsible">
            <summary>Show raw JSON</summary>
            <pre class="json-block mt-1"><?= htmlspecialchars(json_encode($rawResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
        </details>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
