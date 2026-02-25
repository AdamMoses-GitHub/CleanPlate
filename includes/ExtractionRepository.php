<?php
/**
 * ExtractionRepository — All SQL for the recipe_extractions table
 *
 * Handles insert/upsert, cache lookups, search/filter/pagination,
 * featured management, and aggregated dashboard statistics.
 */
class ExtractionRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Cache helpers (called by parser.php before scraping)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Hash a URL to a consistent 64-char key.
     * Normalises scheme+host to lowercase; keeps path/query as-is.
     */
    public static function hashUrl(string $url): string
    {
        $p = parse_url(trim($url));
        if ($p === false) {
            return hash('sha256', trim($url));
        }
        $scheme = strtolower($p['scheme'] ?? 'https');
        $host   = strtolower($p['host']   ?? '');
        $path   = $p['path']  ?? '/';
        $query  = isset($p['query'])    ? '?' . $p['query']    : '';
        $frag   = '';  // ignore fragments
        return hash('sha256', "{$scheme}://{$host}{$path}{$query}{$frag}");
    }

    /**
     * Return the cached extraction row if it exists AND the cache hasn't expired.
     * Returns null when a fresh scrape is needed.
     */
    public function findCachedByHash(string $hash): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM recipe_extractions
              WHERE url_hash = ?
                AND status = "success"
                AND cache_expires_at IS NOT NULL
                AND cache_expires_at > NOW()
              LIMIT 1',
            [$hash]
        );

        return $row ?: null;
    }

    /**
     * Increment the cache hit counter without touching other columns.
     */
    public function incrementCacheHit(int $id): void
    {
        $this->db->execute(
            'UPDATE recipe_extractions
                SET cache_hit_count  = cache_hit_count + 1,
                    submission_count = submission_count + 1,
                    last_seen_at     = NOW()
              WHERE id = ?',
            [$id]
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Upsert — called after every parse attempt
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Insert or update an extraction record.
     *
     * $parseResult is the array returned by RecipeParser::parse(), or null on error.
     * $errorDetails is an array with 'code' / 'message' keys on failure.
     *
     * Returns the record ID.
     */
    public function upsert(
        string $url,
        int    $processingTimeMs,
        ?array $parseResult  = null,
        ?array $errorDetails = null
    ): int {
        $hash   = self::hashUrl($url);
        $domain = strtolower(parse_url($url, PHP_URL_HOST) ?: '');

        $existing = $this->db->fetchOne(
            'SELECT id, status FROM recipe_extractions WHERE url_hash = ? LIMIT 1',
            [$hash]
        );

        $isSuccess = ($parseResult !== null && ($parseResult['status'] ?? '') === 'success');

        if ($existing === null) {
            return $this->insert($url, $hash, $domain, $processingTimeMs, $parseResult, $errorDetails, $isSuccess);
        }

        return $this->update((int)$existing['id'], $processingTimeMs, $parseResult, $errorDetails, $isSuccess);
    }

    // ── private helpers ───────────────────────────────────────────────────────

    private function insert(
        string $url,
        string $hash,
        string $domain,
        int    $ms,
        ?array $result,
        ?array $err,
        bool   $isSuccess
    ): int {
        $ttlHours = (int)(Config::get('admin.cache_ttl_hours', 24));
        $fields   = $this->buildFields($ms, $result, $err, $isSuccess, $ttlHours);

        $this->db->execute(
            'INSERT INTO recipe_extractions
             (url, url_hash, domain, submission_count, status, phase,
              error_code, error_message, processing_time_ms,
              confidence_score, confidence_level, confidence_details,
              title, description, ingredients, instructions,
              site_name, author, prep_time, cook_time, total_time, servings,
              category, cuisine, keywords, dietary_info,
              rating_value, rating_count, image_url, image_candidates,
              metadata, raw_response,
              cached_at, cache_expires_at, cache_hit_count,
              first_seen_at, last_seen_at)
             VALUES
             (?, ?, ?, 1, ?, ?,
              ?, ?, ?,
              ?, ?, ?,
              ?, ?, ?, ?,
              ?, ?, ?, ?, ?, ?,
              ?, ?, ?, ?,
              ?, ?, ?, ?,
              ?, ?,
              ?, ?, 0,
              NOW(), NOW())',
            [
                $url, $hash, $domain, $fields['status'], $fields['phase'],
                $fields['error_code'], $fields['error_message'], $fields['processing_time_ms'],
                $fields['confidence_score'], $fields['confidence_level'], $fields['confidence_details'],
                $fields['title'], $fields['description'], $fields['ingredients'], $fields['instructions'],
                $fields['site_name'], $fields['author'],
                $fields['prep_time'], $fields['cook_time'], $fields['total_time'], $fields['servings'],
                $fields['category'], $fields['cuisine'], $fields['keywords'], $fields['dietary_info'],
                $fields['rating_value'], $fields['rating_count'], $fields['image_url'], $fields['image_candidates'],
                $fields['metadata'], $fields['raw_response'],
                $fields['cached_at'], $fields['cache_expires_at'],
            ]
        );

        return (int)$this->db->lastInsertId();
    }

    private function update(
        int    $id,
        int    $ms,
        ?array $result,
        ?array $err,
        bool   $isSuccess
    ): int {
        $ttlHours = (int)(Config::get('admin.cache_ttl_hours', 24));
        $fields   = $this->buildFields($ms, $result, $err, $isSuccess, $ttlHours);

        // On a successful re-scrape: overwrite all content columns + refresh cache.
        // On an error re-scrape:  only update error columns + bump counters.
        if ($isSuccess) {
            $this->db->execute(
                'UPDATE recipe_extractions SET
                    submission_count   = submission_count + 1,
                    last_seen_at       = NOW(),
                    status             = ?,
                    phase              = ?,
                    error_code         = NULL,
                    error_message      = NULL,
                    processing_time_ms = ?,
                    confidence_score   = ?,
                    confidence_level   = ?,
                    confidence_details = ?,
                    title              = ?,
                    description        = ?,
                    ingredients        = ?,
                    instructions       = ?,
                    site_name          = ?,
                    author             = ?,
                    prep_time          = ?,
                    cook_time          = ?,
                    total_time         = ?,
                    servings           = ?,
                    category           = ?,
                    cuisine            = ?,
                    keywords           = ?,
                    dietary_info       = ?,
                    rating_value       = ?,
                    rating_count       = ?,
                    image_url          = ?,
                    image_candidates   = ?,
                    metadata           = ?,
                    raw_response       = ?,
                    cached_at          = ?,
                    cache_expires_at   = ?
                  WHERE id = ?',
                [
                    $fields['status'], $fields['phase'],
                    $fields['processing_time_ms'],
                    $fields['confidence_score'], $fields['confidence_level'], $fields['confidence_details'],
                    $fields['title'], $fields['description'], $fields['ingredients'], $fields['instructions'],
                    $fields['site_name'], $fields['author'],
                    $fields['prep_time'], $fields['cook_time'], $fields['total_time'], $fields['servings'],
                    $fields['category'], $fields['cuisine'], $fields['keywords'], $fields['dietary_info'],
                    $fields['rating_value'], $fields['rating_count'], $fields['image_url'], $fields['image_candidates'],
                    $fields['metadata'], $fields['raw_response'],
                    $fields['cached_at'], $fields['cache_expires_at'],
                    $id,
                ]
            );
        } else {
            $this->db->execute(
                'UPDATE recipe_extractions SET
                    submission_count   = submission_count + 1,
                    last_seen_at       = NOW(),
                    status             = ?,
                    error_code         = ?,
                    error_message      = ?,
                    processing_time_ms = ?
                  WHERE id = ?',
                [
                    $fields['status'], $fields['error_code'], $fields['error_message'],
                    $fields['processing_time_ms'], $id,
                ]
            );
        }

        return $id;
    }

    /**
     * Map a raw RecipeParser result into flat DB column values.
     */
    private function buildFields(
        int   $ms,
        ?array $result,
        ?array $err,
        bool   $isSuccess,
        int    $ttlHours
    ): array {
        $now = date('Y-m-d H:i:s');
        $exp = date('Y-m-d H:i:s', strtotime("+{$ttlHours} hours"));

        if ($isSuccess && $result !== null) {
            $data = $result['data'] ?? [];
            $meta = $data['metadata'] ?? [];
            $src  = $data['source']   ?? [];
            $conf = $result['confidenceDetails'] ?? [];

            $rating = $meta['rating'] ?? [];

            // Build a raw_response without the full HTML to keep storage slim
            $rawForStorage          = $result;
            unset($rawForStorage['_html']); // safety strip if ever added

            return [
                'status'              => 'success',
                'phase'               => $result['phase'] ?? null,
                'error_code'          => null,
                'error_message'       => null,
                'processing_time_ms'  => $ms,
                'confidence_score'    => $result['confidence']      ?? null,
                'confidence_level'    => $result['confidenceLevel'] ?? null,
                'confidence_details'  => json_encode($conf),
                'title'               => $this->truncate($data['title'] ?? null, 500),
                'description'         => $meta['description'] ?? null,
                'ingredients'         => json_encode($data['ingredients'] ?? []),
                'instructions'        => json_encode($data['instructions'] ?? []),
                'site_name'           => $this->truncate($src['siteName'] ?? null, 255),
                'author'              => $this->truncate($src['author']   ?? null, 255),
                'prep_time'           => $this->truncate($meta['prepTime']   ?? null, 100),
                'cook_time'           => $this->truncate($meta['cookTime']   ?? null, 100),
                'total_time'          => $this->truncate($meta['totalTime']  ?? null, 100),
                'servings'            => $this->truncate($meta['servings']   ?? null, 100),
                'category'            => json_encode($meta['category']     ?? []),
                'cuisine'             => json_encode($meta['cuisine']      ?? []),
                'keywords'            => json_encode($meta['keywords']     ?? []),
                'dietary_info'        => json_encode($meta['dietaryInfo']  ?? []),
                'rating_value'        => isset($rating['value'])  ? (float)$rating['value']  : null,
                'rating_count'        => isset($rating['count'])  ? (int)$rating['count']    : null,
                'image_url'           => $meta['imageUrl']         ?? null,
                'image_candidates'    => json_encode($meta['imageCandidates'] ?? []),
                'metadata'            => json_encode($meta),
                'raw_response'        => json_encode($rawForStorage),
                'cached_at'           => $now,
                'cache_expires_at'    => $exp,
            ];
        }

        // Error case
        return [
            'status'              => 'error',
            'phase'               => null,
            'error_code'          => $this->truncate($err['code']    ?? 'SERVER_ERROR', 100),
            'error_message'       => $err['message'] ?? null,
            'processing_time_ms'  => $ms,
            'confidence_score'    => null,
            'confidence_level'    => null,
            'confidence_details'  => null,
            'title'               => null,
            'description'         => null,
            'ingredients'         => null,
            'instructions'        => null,
            'site_name'           => null,
            'author'              => null,
            'prep_time'           => null,
            'cook_time'           => null,
            'total_time'          => null,
            'servings'            => null,
            'category'            => null,
            'cuisine'             => null,
            'keywords'            => null,
            'dietary_info'        => null,
            'rating_value'        => null,
            'rating_count'        => null,
            'image_url'           => null,
            'image_candidates'    => null,
            'metadata'            => null,
            'raw_response'        => null,
            'cached_at'           => null,
            'cache_expires_at'    => null,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Single-record lookups
    // ══════════════════════════════════════════════════════════════════════════

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM recipe_extractions WHERE id = ? LIMIT 1',
            [$id]
        );
    }

    public function findByHash(string $hash): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM recipe_extractions WHERE url_hash = ? LIMIT 1',
            [$hash]
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Search / paginate (admin extractions list)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Paginated, filtered extraction list for the admin panel.
     *
     * $filters keys (all optional):
     *   q          — full-text search across title + domain + url
     *   status     — 'success' | 'error' | 'pending'
     *   domain     — exact domain match
     *   featured   — '1' | '0'
     *   conf_min   — minimum confidence_score
     *   conf_max   — maximum confidence_score
     *   date_from  — YYYY-MM-DD
     *   date_to    — YYYY-MM-DD
     *   sort       — column name (whitelist enforced)
     *   dir        — 'ASC' | 'DESC'
     *   page       — 1-based page number
     *   per_page   — rows per page (max 100)
     */
    public function search(array $filters = []): array
    {
        $allowedSorts = [
            'id', 'domain', 'title', 'status', 'confidence_score',
            'submission_count', 'first_seen_at', 'last_seen_at',
            'processing_time_ms', 'cache_hit_count',
        ];

        $sort    = in_array($filters['sort']    ?? '', $allowedSorts) ? $filters['sort']    : 'last_seen_at';
        $dir     = strtoupper($filters['dir']   ?? 'DESC') === 'ASC'  ? 'ASC'               : 'DESC';
        $page    = max(1, (int)($filters['page']     ?? 1));
        $perPage = min(100, max(1, (int)($filters['per_page'] ?? 25)));
        $offset  = ($page - 1) * $perPage;

        [$where, $params] = $this->buildWhereClause($filters);

        $countSql = "SELECT COUNT(*) FROM recipe_extractions {$where}";
        $dataSql  = "SELECT id, url, domain, title, status, confidence_score, confidence_level,
                            submission_count, cache_hit_count, is_featured, first_seen_at,
                            last_seen_at, processing_time_ms, error_code, image_url
                       FROM recipe_extractions
                     {$where}
                     ORDER BY `{$sort}` {$dir}
                     LIMIT ? OFFSET ?";

        $dataParams   = array_merge($params, [$perPage, $offset]);
        $total        = (int)$this->db->query($countSql, $params)->fetchColumn();
        $rows         = $this->db->fetchAll($dataSql, $dataParams);

        return [
            'data'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $perPage,
            'last_page'  => (int)ceil($total / $perPage),
        ];
    }

    private function buildWhereClause(array $f): array
    {
        $conditions = [];
        $params     = [];

        if (!empty($f['q'])) {
            $conditions[] = '(title LIKE ? OR domain LIKE ? OR url LIKE ?)';
            $like = '%' . $f['q'] . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }

        if (!empty($f['status']) && in_array($f['status'], ['success','error','pending'])) {
            $conditions[] = 'status = ?';
            $params[]     = $f['status'];
        }

        if (!empty($f['domain'])) {
            $conditions[] = 'domain = ?';
            $params[]     = strtolower($f['domain']);
        }

        if (isset($f['featured']) && $f['featured'] !== '') {
            $conditions[] = 'is_featured = ?';
            $params[]     = (int)$f['featured'];
        }

        if (isset($f['conf_min']) && is_numeric($f['conf_min'])) {
            $conditions[] = 'confidence_score >= ?';
            $params[]     = (float)$f['conf_min'];
        }

        if (isset($f['conf_max']) && is_numeric($f['conf_max'])) {
            $conditions[] = 'confidence_score <= ?';
            $params[]     = (float)$f['conf_max'];
        }

        if (!empty($f['date_from'])) {
            $conditions[] = 'first_seen_at >= ?';
            $params[]     = $f['date_from'] . ' 00:00:00';
        }

        if (!empty($f['date_to'])) {
            $conditions[] = 'first_seen_at <= ?';
            $params[]     = $f['date_to'] . ' 23:59:59';
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return [$where, $params];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Admin actions
    // ══════════════════════════════════════════════════════════════════════════

    public function markFeatured(int $id, bool $featured): void
    {
        $this->db->execute(
            'UPDATE recipe_extractions SET is_featured = ? WHERE id = ?',
            [(int)$featured, $id]
        );

        if ($featured) {
            // Auto-assign to the end of the featured list so it doesn't
            // appear first (MySQL sorts NULLs before non-NULLs in ASC)
            $maxOrder = $this->db->fetchOne(
                'SELECT COALESCE(MAX(featured_order), 0) AS m
                   FROM recipe_extractions
                  WHERE is_featured = 1 AND id != ?',
                [$id]
            );
            $this->db->execute(
                'UPDATE recipe_extractions SET featured_order = ? WHERE id = ?',
                [(int)($maxOrder['m'] ?? 0) + 1, $id]
            );
        } else {
            // Clear sort order when unfeaturing
            $this->db->execute(
                'UPDATE recipe_extractions SET featured_order = NULL WHERE id = ?',
                [$id]
            );
        }
    }

    public function setFeaturedOrder(int $id, ?int $order): void
    {
        $this->db->execute(
            'UPDATE recipe_extractions SET featured_order = ? WHERE id = ?',
            [$order, $id]
        );
    }

    public function updateAdminNotes(int $id, string $notes): void
    {
        $this->db->execute(
            'UPDATE recipe_extractions SET admin_notes = ? WHERE id = ?',
            [$notes ?: null, $id]
        );
    }

    public function flushCache(int $id): void
    {
        $this->db->execute(
            'UPDATE recipe_extractions
                SET cached_at = NULL, cache_expires_at = NULL
              WHERE id = ?',
            [$id]
        );
    }

    public function deleteById(int $id): void
    {
        $this->db->execute(
            'DELETE FROM recipe_extractions WHERE id = ?',
            [$id]
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Featured list
    // ══════════════════════════════════════════════════════════════════════════

    public function getFeatured(): array
    {
        return $this->db->fetchAll(
            'SELECT id, title, domain, image_url, confidence_score,
                    featured_order, first_seen_at
               FROM recipe_extractions
              WHERE is_featured = 1
              ORDER BY featured_order ASC, id ASC'
        );
    }

    /**
     * Return all featured recipes with the url column included,
     * used by the "Publish to homepage" action.
     */
    public function getFeaturedForPublish(): array
    {
        return $this->db->fetchAll(
            'SELECT id, url, title, domain, image_url
               FROM recipe_extractions
              WHERE is_featured = 1
                AND status = "success"
              ORDER BY featured_order ASC, id ASC'
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Dashboard statistics
    // ══════════════════════════════════════════════════════════════════════════

    public function getDashboardStats(): array
    {
        $totals = $this->db->fetchOne(
            'SELECT
                COUNT(*)                                              AS total_submissions_unique,
                SUM(submission_count)                                 AS total_submissions_all,
                COUNT(DISTINCT domain)                                AS unique_domains,
                SUM(status = "success")                               AS total_success,
                SUM(status = "error")                                 AS total_error,
                SUM(status = "pending")                               AS total_pending,
                SUM(is_featured)                                      AS total_featured,
                ROUND(AVG(CASE WHEN status="success" THEN confidence_score END), 1) AS avg_confidence,
                ROUND(AVG(CASE WHEN status="success" THEN processing_time_ms END))  AS avg_processing_ms,
                SUM(cache_hit_count)                                  AS total_cache_hits
              FROM recipe_extractions'
        );

        $timeBuckets = $this->db->fetchOne(
            'SELECT
                SUM(first_seen_at >= DATE_SUB(NOW(), INTERVAL 1 DAY))   AS today,
                SUM(first_seen_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))   AS this_week,
                SUM(first_seen_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))  AS this_month
              FROM recipe_extractions'
        );

        return array_merge($totals ?? [], $timeBuckets ?? []);
    }

    public function getTopDomains(int $limit = 10): array
    {
        return $this->db->fetchAll(
            'SELECT domain,
                    COUNT(*)         AS unique_urls,
                    SUM(submission_count) AS total_submissions,
                    SUM(status="success") AS successes,
                    SUM(status="error")   AS errors,
                    ROUND(AVG(CASE WHEN status="success" THEN confidence_score END),1) AS avg_confidence
               FROM recipe_extractions
              GROUP BY domain
              ORDER BY total_submissions DESC
              LIMIT ?',
            [$limit]
        );
    }

    public function getTopErrors(int $limit = 10): array
    {
        return $this->db->fetchAll(
            'SELECT error_code,
                    COUNT(*) AS occurrences,
                    MAX(last_seen_at) AS last_seen
               FROM recipe_extractions
              WHERE status = "error"
                AND error_code IS NOT NULL
              GROUP BY error_code
              ORDER BY occurrences DESC
              LIMIT ?',
            [$limit]
        );
    }

    public function getRecentActivity(int $limit = 10): array
    {
        return $this->db->fetchAll(
            'SELECT id, domain, title, status, confidence_score,
                    submission_count, is_featured, last_seen_at, error_code
               FROM recipe_extractions
              ORDER BY last_seen_at DESC
              LIMIT ?',
            [$limit]
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CSV export — returns raw rows matching filters (no pagination limit)
    // ══════════════════════════════════════════════════════════════════════════

    public function exportRows(array $filters = []): array
    {
        [$where, $params] = $this->buildWhereClause($filters);
        return $this->db->fetchAll(
            "SELECT id, url, domain, title, status, phase,
                    confidence_score, confidence_level,
                    submission_count, cache_hit_count,
                    site_name, author, total_time, servings,
                    rating_value, rating_count,
                    is_featured, first_seen_at, last_seen_at,
                    processing_time_ms, error_code
               FROM recipe_extractions
             {$where}
             ORDER BY last_seen_at DESC",
            $params
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Utilities
    // ══════════════════════════════════════════════════════════════════════════

    private function truncate(?string $value, int $max): ?string
    {
        if ($value === null) return null;
        return mb_substr($value, 0, $max);
    }
}
