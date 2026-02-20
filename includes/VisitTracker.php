<?php
/**
 * VisitTracker — Server-side visitor analytics recorder.
 *
 * Records one row to `site_visits` per page request.
 * No JS, no cookies, no PII — IP is stored only as a SHA-256 hash.
 *
 * Usage (at the very top of any tracked PHP page, before output):
 *   require_once __DIR__ . '/../includes/VisitTracker.php';
 *   VisitTracker::record();
 */
class VisitTracker
{
    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Record a page visit. Silently swallows all errors so a DB hiccup
     * never breaks the public-facing page.
     */
    public static function record(): void
    {
        try {
            if (!class_exists('Database')) {
                require_once __DIR__ . '/Database.php';
            }

            $db = Database::getInstance();

            $ip        = self::resolveIp();
            $ua        = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);
            $page      = self::parsePath();
            $qs        = self::parseQueryString();
            $referrer  = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 512) ?: null;
            $refDomain = $referrer ? self::parseDomain($referrer) : null;
            $today     = date('Y-m-d');

            $ipHash      = hash('sha256', $ip);
            $sessionHash = hash('sha256', $ip . '|' . $ua . '|' . $today);

            $device  = self::detectDevice($ua);
            $browser = self::detectBrowser($ua);
            $os      = self::detectOs($ua);

            $db->execute(
                'INSERT INTO site_visits
                    (session_hash, ip_hash, page, query_string, referrer, referrer_domain,
                     device_type, browser, os, user_agent, visited_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [$sessionHash, $ipHash, $page, $qs, $referrer, $refDomain,
                 $device, $browser, $os, $ua ?: null]
            );
        } catch (Throwable $e) {
            // Silent — analytics must never break the page
            error_log('VisitTracker: ' . $e->getMessage());
        }
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Resolve the real client IP, honouring common proxy headers.
     */
    private static function resolveIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            $val = $_SERVER[$key] ?? '';
            if ($val !== '') {
                // X-Forwarded-For can be comma-separated; take first
                $ip = trim(explode(',', $val)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    /**
     * Return just the path portion of the current request URI.
     */
    private static function parsePath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        return substr($path, 0, 512);
    }

    /**
     * Return the query string, or null if empty.
     */
    private static function parseQueryString(): ?string
    {
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        return $qs !== '' ? substr($qs, 0, 1024) : null;
    }

    /**
     * Extract the hostname from a URL.
     */
    private static function parseDomain(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        return $host ? strtolower(ltrim($host, 'www.')) : null;
    }

    /**
     * Detect device type from User-Agent string.
     * Returns: desktop | mobile | tablet | bot | unknown
     */
    private static function detectDevice(string $ua): string
    {
        if ($ua === '') return 'unknown';

        $ua_lower = strtolower($ua);

        // Bots / crawlers
        if (preg_match('/bot|crawl|spider|slurp|mediapartners|facebookexternalhit|linkedinbot|twitterbot|whatsapp/i', $ua)) {
            return 'bot';
        }

        // Tablets (check before mobile — iPad UA contains "mobile" on some iOS)
        if (preg_match('/ipad|tablet|kindle|silk|playbook|nexus\s7|nexus\s10|gt-p|sch-i|gt-n/i', $ua)) {
            return 'tablet';
        }

        // Mobile
        if (preg_match('/mobile|android|iphone|ipod|windows phone|blackberry|opera mini|iemobile/i', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * Detect browser name from User-Agent string.
     */
    private static function detectBrowser(string $ua): ?string
    {
        if ($ua === '') return null;

        $checks = [
            'Edg'             => 'Edge',
            'EdgA'            => 'Edge',
            'OPR'             => 'Opera',
            'Opera'           => 'Opera',
            'SamsungBrowser'  => 'Samsung Internet',
            'UCBrowser'       => 'UC Browser',
            'YaBrowser'       => 'Yandex',
            'DuckDuckGo'      => 'DuckDuckGo',
            'Firefox'         => 'Firefox',
            'FxiOS'           => 'Firefox',
            'Chrome'          => 'Chrome',
            'CriOS'           => 'Chrome',
            'Safari'          => 'Safari',
            'MSIE'            => 'Internet Explorer',
            'Trident'         => 'Internet Explorer',
        ];

        foreach ($checks as $token => $name) {
            if (strpos($ua, $token) !== false) {
                return $name;
            }
        }

        return 'Other';
    }

    /**
     * Detect OS name from User-Agent string.
     */
    private static function detectOs(string $ua): ?string
    {
        if ($ua === '') return null;

        $checks = [
            '/Windows NT 10/'        => 'Windows 10/11',
            '/Windows NT 6\.3/'      => 'Windows 8.1',
            '/Windows NT 6\.2/'      => 'Windows 8',
            '/Windows NT 6\.1/'      => 'Windows 7',
            '/Windows/'              => 'Windows',
            '/iPhone OS 1[5-9]/'     => 'iOS 15+',
            '/iPhone OS 1[0-4]/'     => 'iOS 10-14',
            '/iPhone/'               => 'iOS',
            '/iPad/'                 => 'iPadOS',
            '/Android 1[0-9]/'       => 'Android 10+',
            '/Android/'              => 'Android',
            '/Mac OS X/'             => 'macOS',
            '/CrOS/'                 => 'Chrome OS',
            '/Linux/'                => 'Linux',
        ];

        foreach ($checks as $pattern => $name) {
            if (preg_match($pattern, $ua)) {
                return $name;
            }
        }

        return 'Other';
    }
}
