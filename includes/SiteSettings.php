<?php
/**
 * SiteSettings — Persistent runtime settings stored in storage/settings.json
 *
 * Settings are read once per request and can override env-based Config values
 * via SiteSettings::apply(). This allows the admin panel to change runtime
 * behaviour without editing .env or restarting the server.
 *
 * Storage: storage/settings.json  (above web root, not web-accessible)
 *
 * Usage:
 *   SiteSettings::load();              // load from JSON — idempotent
 *   SiteSettings::apply();             // push values into Config (call after Config::load())
 *   SiteSettings::get('offline.enabled', false);
 *   SiteSettings::save(['offline' => ['enabled' => true, 'message' => '...']]);
 */
class SiteSettings
{
    private static ?array $data    = null;
    private static bool   $loaded  = false;

    /** Absolute path to the settings JSON file */
    private static function filePath(): string
    {
        return __DIR__ . '/../storage/settings.json';
    }

    /** Default values — used when a key is absent from the JSON file */
    public static function defaults(): array
    {
        return [
            'offline' => [
                'enabled' => false,
                'message' => "We'll be back soon! CleanPlate is currently undergoing maintenance.",
                'eta'     => '',
            ],
            'cache' => [
                'ttl_hours' => 24,
            ],
            'carousel' => [
                'subset_size' => 5,
            ],
            'scraper' => [
                'timeout'    => 10,
                'min_delay'  => 2,
                'ssl_verify' => true,
            ],
            'rate_limit' => [
                'enabled'  => true,
                'requests' => 10,
                'period'   => 60,
            ],
        ];
    }

    // ── Load ───────────────────────────────────────────────────────────────────

    /**
     * Load settings from JSON.  Merges with defaults so missing keys always
     * have a sensible value.  Idempotent — subsequent calls are no-ops.
     */
    public static function load(): void
    {
        if (self::$loaded) return;
        self::$loaded = true;

        $path    = self::filePath();
        $saved   = [];

        if (file_exists($path)) {
            $raw = @file_get_contents($path);
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $saved = $decoded;
                }
            }
        }

        // Deep-merge saved values over defaults
        self::$data = self::deepMerge(self::defaults(), $saved);
    }

    // ── Read ───────────────────────────────────────────────────────────────────

    /**
     * Get a setting value by dot-notation key.
     *
     * Example: SiteSettings::get('offline.enabled', false)
     */
    public static function get(string $key, $default = null)
    {
        self::load();
        $keys  = explode('.', $key);
        $value = self::$data;
        foreach ($keys as $k) {
            if (!is_array($value) || !isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        return $value;
    }

    /** Return all settings as an array. */
    public static function all(): array
    {
        self::load();
        return self::$data ?? self::defaults();
    }

    // ── Write ──────────────────────────────────────────────────────────────────

    /**
     * Merge $newData over the current settings and persist to JSON.
     * Only the keys present in $newData are updated; others are preserved.
     *
     * @param  array $newData  Partial or full settings array
     * @return bool            True on success
     */
    public static function save(array $newData): bool
    {
        self::load();
        self::$data = self::deepMerge(self::$data ?? self::defaults(), $newData);

        $path = self::filePath();
        $dir  = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json = json_encode(
            self::$data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return file_put_contents($path, $json) !== false;
    }

    // ── Apply ──────────────────────────────────────────────────────────────────

    /**
     * Push loaded settings into Config so the rest of the app transparently
     * uses them via Config::get().  Call once per request after Config::load().
     *
     * Values that map to Config keys:
     *   cache.ttl_hours         → admin.cache_ttl_hours
     *   carousel.subset_size    → admin.featured_subset_size
     *   scraper.timeout         → scraper.timeouts.request
     *   scraper.min_delay       → scraper.timeouts.min_delay
     *   scraper.ssl_verify      → scraper.ssl.verify_peer
     *   rate_limit.enabled      → security.rate_limit.enabled
     *   rate_limit.requests     → security.rate_limit.requests
     *   rate_limit.period       → security.rate_limit.period
     */
    public static function apply(): void
    {
        self::load();

        if (!class_exists('Config')) return;

        $s = self::$data;

        // Cache
        if (isset($s['cache']['ttl_hours'])) {
            Config::set('admin.cache_ttl_hours', (int)$s['cache']['ttl_hours']);
        }

        // Carousel
        if (isset($s['carousel']['subset_size'])) {
            Config::set('admin.featured_subset_size', (int)$s['carousel']['subset_size']);
        }

        // Scraper
        if (isset($s['scraper']['timeout'])) {
            Config::set('scraper.timeouts.request', (int)$s['scraper']['timeout']);
        }
        if (isset($s['scraper']['min_delay'])) {
            Config::set('scraper.timeouts.min_delay', (int)$s['scraper']['min_delay']);
            Config::set('scraper.rate_limiting.per_domain_delay', (int)$s['scraper']['min_delay']);
        }
        if (isset($s['scraper']['ssl_verify'])) {
            Config::set('scraper.ssl.verify_peer', (bool)$s['scraper']['ssl_verify']);
        }

        // Rate limiting
        if (isset($s['rate_limit']['enabled'])) {
            Config::set('security.rate_limit.enabled', (bool)$s['rate_limit']['enabled']);
        }
        if (isset($s['rate_limit']['requests'])) {
            Config::set('security.rate_limit.requests', (int)$s['rate_limit']['requests']);
        }
        if (isset($s['rate_limit']['period'])) {
            Config::set('security.rate_limit.period', (int)$s['rate_limit']['period']);
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Recursively merge $overlay onto $base.
     * Scalar values in $overlay overwrite those in $base.
     * Array values are merged recursively.
     */
    private static function deepMerge(array $base, array $overlay): array
    {
        foreach ($overlay as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }
}
