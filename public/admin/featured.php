<?php
/**
 * Admin — Featured recipes management
 * Supports mark/unmark featured and publishing randomized carousel lists.
 */
require_once __DIR__ . '/../../includes/Config.php';
Config::load();
require_once __DIR__ . '/../../includes/AdminAuth.php';
AdminAuth::check();

require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/ExtractionRepository.php';
require_once __DIR__ . '/../../includes/SiteSettings.php';
SiteSettings::load();

$repo  = new ExtractionRepository(Database::getInstance());
$flash = '';

// ── POST actions ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'unfeature' && $id) {
        $repo->markFeatured($id, false);
        $flash = 'Removed from featured.';

    } elseif ($action === 'save_settings') {
        $listSize = max(1, min(50, (int)($_POST['list_size'] ?? 5)));
        SiteSettings::save(['carousel' => ['list_size' => $listSize]]);
        $flash = 'Carousel settings saved.';

    } elseif ($action === 'publish') {
        $listSize = max(1, (int) SiteSettings::get('carousel.list_size', 5));
        $all      = $repo->getFeaturedForPublish();
        if (!empty($all)) {
            $payload = [
                'generated_at'   => date('c'),
                'total_featured' => count($all),
                'list_size'      => $listSize,
                'recipes'        => array_map(fn($r) => [
                    'id'     => (int)$r['id'],
                    'url'    => $r['url'],
                    'title'  => $r['title'] ?: 'Untitled',
                    'domain' => $r['domain'],
                    'image'  => $r['image_url'] ?: null,
                ], $all),
            ];
            $dataDir = __DIR__ . '/../data';
            if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
            file_put_contents(
                $dataDir . '/featured.json',
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
            $flash = 'Published! ' . count($all) . ' recipe' . (count($all) !== 1 ? 's' : '')
                   . ' in pool, ' . $listSize . ' shown per visit.';
        } else {
            $flash = 'No featured recipes found — mark some as featured first.';
        }
    }
}

$featured  = $repo->getFeatured();
$pageTitle = 'Featured Recipes';
$activeNav = 'featured';
require __DIR__ . '/_header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<!-- ── Publish Settings ────────────────────────────────────────────────── -->
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">Carousel Publish Settings</span>
    </div>
    <div class="panel-body" style="padding:1.25rem 1.5rem;">
        <form method="POST" style="display:flex;gap:2rem;flex-wrap:wrap;align-items:flex-end;">
            <input type="hidden" name="action" value="save_settings">
            <div style="display:flex;flex-direction:column;gap:.35rem;">
                <label for="list_size" style="font-size:.875rem;font-weight:500;">
                    Recipes shown per visit <span style="font-weight:400;color:var(--color-text-secondary);font-size:.8rem;">(1–50)</span>
                </label>
                <input type="number" id="list_size" name="list_size"
                       min="1" max="50"
                       style="width:100px;padding:.4rem .6rem;border:1px solid var(--color-border);border-radius:4px;"
                       value="<?= (int) SiteSettings::get('carousel.list_size', 5) ?>">
            </div>
            <div>
                <button type="submit" class="btn btn-accent btn-sm">Save Settings</button>
            </div>
        </form>
        <p style="font-size:.78rem;color:var(--color-text-secondary);margin:.85rem 0 0;">
            On <strong>Publish</strong>, all featured recipes are saved to the JSON file.
            Each homepage visit randomly picks this many to display in the carousel.
        </p>
    </div>
</div>

<div class="panel">
    <div class="panel-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;">
        <div>
            <span class="panel-title">Featured Recipes (<?= count($featured) ?>)</span>
            <span class="text-muted" style="font-size:.78rem;display:block;margin-top:.2rem;">
                These appear in the front-page carousel. Order does not matter — lists are randomized on publish.
            </span>
        </div>
        <form method="POST" style="margin:0;">
            <input type="hidden" name="action" value="publish">
            <button type="submit" class="btn btn-primary btn-sm"
                    <?= empty($featured) ? 'disabled' : '' ?>
                    title="Save all <?= count($featured) ?> featured recipes to featured.json (JS picks <?= (int)SiteSettings::get('carousel.list_size', 5) ?> at random each visit)">
                ↑ Publish to Homepage
            </button>
        </form>
    </div>

    <?php if (empty($featured)): ?>
        <div class="panel-body">
            <p class="text-muted" style="text-align:center;padding:1.5rem 0;">
                No featured recipes yet.
                <a href="/admin/extractions.php?status=success">Browse successful extractions</a>
                and mark some as featured.
            </p>
        </div>
    <?php else: ?>
    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Title</th>
                    <th>Domain</th>
                    <th class="col-num">Confidence</th>
                    <th>First Seen</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($featured as $row): ?>
                <tr>
                    <td>
                        <?php if ($row['image_url']): ?>
                            <img src="<?= htmlspecialchars($row['image_url']) ?>"
                                 class="thumb" loading="lazy" alt=""
                                 onerror="this.style.display='none'">
                        <?php endif; ?>
                    </td>
                    <td class="col-wrap">
                        <a href="/admin/extraction-detail.php?id=<?= (int)$row['id'] ?>">
                            <?= htmlspecialchars($row['title'] ?: '(untitled)') ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($row['domain']) ?></td>
                    <td class="col-num">
                        <?= $row['confidence_score'] ? (float)$row['confidence_score'] . '%' : '—' ?>
                    </td>
                    <td class="text-muted" style="font-size:.72rem;">
                        <?= htmlspecialchars(substr($row['first_seen_at'] ?? '', 0, 10)) ?>
                    </td>
                    <td>
                        <form method="POST" style="display:inline"
                              onsubmit="return confirm('Remove from featured?');">
                            <input type="hidden" name="action" value="unfeature">
                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">✖ Unfeature</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="panel">
    <div class="panel-header">
        <span class="panel-title">Add More Featured</span>
    </div>
    <div class="panel-body">
        <p style="font-size:.82rem;color:var(--color-text-secondary);margin-bottom:.75rem;">
            Toggle featured status directly from the extractions list, or from any recipe's detail page.
        </p>
        <a href="/admin/extractions.php?status=success&featured=0" class="btn btn-accent btn-sm">
            Browse unfeatured successes →
        </a>
    </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
