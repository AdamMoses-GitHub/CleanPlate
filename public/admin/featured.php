<?php
/**
 * Admin — Featured recipes management
 * Supports mark/unmark featured, and up/down ordering for the carousel.
 */
require_once __DIR__ . '/../../includes/Config.php';
Config::load();
require_once __DIR__ . '/../../includes/AdminAuth.php';
AdminAuth::check();

require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/ExtractionRepository.php';

$repo  = new ExtractionRepository(Database::getInstance());
$flash = '';

// ── POST actions ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'unfeature' && $id) {
        $repo->markFeatured($id, false);
        $flash = 'Removed from featured.';

    } elseif ($action === 'move_up' && $id) {
        // Swap featured_order with the item above
        $featured = $repo->getFeatured();
        $idx = array_search($id, array_column($featured, 'id'));
        if ($idx > 0) {
            $above = $featured[$idx - 1];
            $orderA = $featured[$idx]['featured_order'] ?? ($idx + 1);
            $orderB = $above['featured_order']          ?? $idx;
            $repo->setFeaturedOrder((int)$featured[$idx]['id'], (int)$orderB);
            $repo->setFeaturedOrder((int)$above['id'],          (int)$orderA);
        }

    } elseif ($action === 'move_down' && $id) {
        $featured = $repo->getFeatured();
        $idx = array_search($id, array_column($featured, 'id'));
        if ($idx !== false && $idx < count($featured) - 1) {
            $below = $featured[$idx + 1];
            $orderA = $featured[$idx]['featured_order'] ?? ($idx + 1);
            $orderB = $below['featured_order']          ?? ($idx + 2);
            $repo->setFeaturedOrder((int)$featured[$idx]['id'], (int)$orderB);
            $repo->setFeaturedOrder((int)$below['id'],          (int)$orderA);
        }

    } elseif ($action === 'publish') {
        $subsetSize = max(1, (int) Config::get('admin.featured_subset_size', 5));
        $all = $repo->getFeaturedForPublish();
        if (!empty($all)) {
            $pool = $all;
            shuffle($pool);
            $subset = array_slice($pool, 0, $subsetSize);
            $payload = [
                'generated_at'   => date('c'),
                'total_featured' => count($all),
                'subset_size'    => count($subset),
                'recipes'        => array_map(fn($r) => [
                    'id'     => (int)$r['id'],
                    'url'    => $r['url'],
                    'title'  => $r['title'] ?: 'Untitled',
                    'domain' => $r['domain'],
                    'image'  => $r['image_url'] ?: null,
                ], $subset),
            ];
            $dataDir = __DIR__ . '/../data';
            if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
            file_put_contents(
                $dataDir . '/featured.json',
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
            $flash = 'Homepage carousel published! (' . count($subset) . ' recipe' . (count($subset) !== 1 ? 's' : '') . ' from ' . count($all) . ' featured).';
        } else {
            $flash = 'No featured recipes found — mark some as featured first.';
        }
    }

    // Normalise order values after every change to keep them clean
    $featured = $repo->getFeatured();
    foreach ($featured as $pos => $item) {
        $repo->setFeaturedOrder((int)$item['id'], $pos + 1);
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

<div class="panel">
    <div class="panel-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;">
        <div>
            <span class="panel-title">Featured Recipes (<?= count($featured) ?>)</span>
            <span class="text-muted" style="font-size:.78rem;display:block;margin-top:.2rem;">
                These appear in the front-page carousel. Drag order is set with ↑↓ buttons.
            </span>
        </div>
        <form method="POST" style="margin:0;">
            <input type="hidden" name="action" value="publish">
            <button type="submit" class="btn btn-primary btn-sm"
                    <?= empty($featured) ? 'disabled' : '' ?>
                    title="Randomly pick up to <?= (int)Config::get('admin.featured_subset_size', 5) ?> featured recipes and write /public/data/featured.json">
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
                    <th style="width:48px;">Order</th>
                    <th>Image</th>
                    <th>Title</th>
                    <th>Domain</th>
                    <th class="col-num">Confidence</th>
                    <th>First Seen</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($featured as $pos => $row): ?>
                <tr>
                    <td style="font-weight:600;text-align:center;"><?= $pos + 1 ?></td>
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
                        <div class="flex-gap">
                            <?php if ($pos > 0): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="move_up">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" class="btn btn-ghost btn-sm" title="Move up">↑</button>
                            </form>
                            <?php endif; ?>

                            <?php if ($pos < count($featured) - 1): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="move_down">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" class="btn btn-ghost btn-sm" title="Move down">↓</button>
                            </form>
                            <?php endif; ?>

                            <form method="POST" style="display:inline"
                                  onsubmit="return confirm('Remove from featured?');">
                                <input type="hidden" name="action" value="unfeature">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">✖ Unfeature</button>
                            </form>
                        </div>
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
            Browse successful extractions and click <strong>★ Mark Featured</strong> on any detail page.
        </p>
        <a href="/admin/extractions.php?status=success&featured=0" class="btn btn-accent btn-sm">
            Browse unfeatured successes →
        </a>
    </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
