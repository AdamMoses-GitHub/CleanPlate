<?php
/**
 * VisitRepository — All analytics queries against `site_visits`.
 *
 * Usage:
 *   $repo = new VisitRepository(Database::getInstance());
 *   $stats = $repo->getSummaryStats();
 */
class VisitRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    // ── Summary stats ──────────────────────────────────────────────────────────

    /**
     * High-level totals for the dashboard stat cards.
     *
     * @return array{
     *   total_views: int, views_today: int, views_week: int, views_month: int,
     *   unique_visitors_today: int, unique_visitors_week: int, unique_visitors_month: int,
     *   total_unique: int, bot_pct: float
     * }
     */
    public function getSummaryStats(): array
    {
        $row = $this->db->fetchOne(
            "SELECT
                COUNT(*)                                                      AS total_views,
                SUM(DATE(visited_at) = CURDATE())                             AS views_today,
                SUM(visited_at >= DATE_SUB(CURDATE(), INTERVAL 7  DAY))       AS views_week,
                SUM(visited_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY))       AS views_month,
                COUNT(DISTINCT CASE WHEN DATE(visited_at) = CURDATE()
                      THEN session_hash END)                                  AS unique_visitors_today,
                COUNT(DISTINCT CASE WHEN visited_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                      THEN session_hash END)                                  AS unique_visitors_week,
                COUNT(DISTINCT CASE WHEN visited_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                      THEN session_hash END)                                  AS unique_visitors_month,
                COUNT(DISTINCT session_hash)                                  AS total_unique,
                ROUND(100 * SUM(device_type = 'bot') / GREATEST(COUNT(*),1), 1) AS bot_pct
             FROM site_visits"
        );

        return array_map(fn($v) => $v ?? 0, $row ?? []);
    }

    // ── Daily trend ────────────────────────────────────────────────────────────

    /**
     * Page views and unique visitors per day for the last N days.
     *
     * @return array<int, array{date: string, views: int, unique_visitors: int}>
     */
    public function getDailyTrend(int $days = 30): array
    {
        return $this->db->fetchAll(
            "SELECT
                DATE(visited_at)              AS date,
                COUNT(*)                      AS views,
                COUNT(DISTINCT session_hash)  AS unique_visitors
             FROM site_visits
             WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
               AND device_type != 'bot'
             GROUP BY DATE(visited_at)
             ORDER BY date ASC",
            [$days]
        ) ?: [];
    }

    // ── Top pages ──────────────────────────────────────────────────────────────

    /**
     * Most-visited pages (excluding bots), last N days.
     *
     * @return array<int, array{page: string, views: int, unique_visitors: int}>
     */
    public function getTopPages(int $limit = 10, int $days = 30): array
    {
        return $this->db->fetchAll(
            "SELECT
                page,
                COUNT(*)                      AS views,
                COUNT(DISTINCT session_hash)  AS unique_visitors
             FROM site_visits
             WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
               AND device_type != 'bot'
             GROUP BY page
             ORDER BY views DESC
             LIMIT ?",
            [$days, $limit]
        ) ?: [];
    }

    // ── Top referrers ──────────────────────────────────────────────────────────

    /**
     * Top referrer domains, last N days.
     *
     * @return array<int, array{referrer_domain: string, visits: int}>
     */
    public function getTopReferrers(int $limit = 10, int $days = 30): array
    {
        return $this->db->fetchAll(
            "SELECT
                COALESCE(referrer_domain, '(direct / none)') AS referrer_domain,
                COUNT(*)                                      AS visits
             FROM site_visits
             WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
               AND device_type != 'bot'
             GROUP BY referrer_domain
             ORDER BY visits DESC
             LIMIT ?",
            [$days, $limit]
        ) ?: [];
    }

    // ── Device breakdown ──────────────────────────────────────────────────────

    /**
     * Visit counts broken down by device type, last N days (bots excluded).
     *
     * @return array<int, array{device_type: string, visits: int, pct: float}>
     */
    public function getDeviceBreakdown(int $days = 30): array
    {
        $rows = $this->db->fetchAll(
            "SELECT device_type, COUNT(*) AS visits
             FROM site_visits
             WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
               AND device_type != 'bot'
             GROUP BY device_type
             ORDER BY visits DESC",
            [$days]
        ) ?: [];

        $total = array_sum(array_column($rows, 'visits')) ?: 1;
        foreach ($rows as &$row) {
            $row['pct'] = round(100 * $row['visits'] / $total, 1);
        }
        return $rows;
    }

    // ── Browser breakdown ─────────────────────────────────────────────────────

    /**
     * Visit counts by browser, last N days (bots excluded).
     *
     * @return array<int, array{browser: string, visits: int, pct: float}>
     */
    public function getBrowserBreakdown(int $days = 30): array
    {
        $rows = $this->db->fetchAll(
            "SELECT COALESCE(browser, 'Unknown') AS browser, COUNT(*) AS visits
             FROM site_visits
             WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
               AND device_type != 'bot'
             GROUP BY browser
             ORDER BY visits DESC
             LIMIT 10",
            [$days]
        ) ?: [];

        $total = array_sum(array_column($rows, 'visits')) ?: 1;
        foreach ($rows as &$row) {
            $row['pct'] = round(100 * $row['visits'] / $total, 1);
        }
        return $rows;
    }

    // ── OS breakdown ──────────────────────────────────────────────────────────

    /**
     * Visit counts by operating system, last N days (bots excluded).
     *
     * @return array<int, array{os: string, visits: int, pct: float}>
     */
    public function getOsBreakdown(int $days = 30): array
    {
        $rows = $this->db->fetchAll(
            "SELECT COALESCE(os, 'Unknown') AS os, COUNT(*) AS visits
             FROM site_visits
             WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
               AND device_type != 'bot'
             GROUP BY os
             ORDER BY visits DESC
             LIMIT 10",
            [$days]
        ) ?: [];

        $total = array_sum(array_column($rows, 'visits')) ?: 1;
        foreach ($rows as &$row) {
            $row['pct'] = round(100 * $row['visits'] / $total, 1);
        }
        return $rows;
    }

    // ── Recent visits ─────────────────────────────────────────────────────────

    /**
     * Most recent N visits (bots excluded) for the live feed table.
     *
     * @return array<int, array{id, page, referrer_domain, device_type, browser, os, visited_at}>
     */
    public function getRecentVisits(int $limit = 20): array
    {
        return $this->db->fetchAll(
            "SELECT id, page, referrer_domain, device_type, browser, os, visited_at
             FROM site_visits
             WHERE device_type != 'bot'
             ORDER BY visited_at DESC
             LIMIT ?",
            [$limit]
        ) ?: [];
    }

    // ── Bot count ─────────────────────────────────────────────────────────────

    /**
     * Total bot visits in the last N days.
     */
    public function getBotCount(int $days = 30): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS n FROM site_visits
             WHERE device_type = 'bot'
               AND visited_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)",
            [$days]
        );
        return (int)($row['n'] ?? 0);
    }
}
