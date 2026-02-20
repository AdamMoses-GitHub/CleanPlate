-- ============================================================
-- CleanPlate - Site Visits Table
-- Migration 02: Visitor analytics (server-side, privacy-first)
-- ============================================================

CREATE TABLE IF NOT EXISTS `site_visits` (
    -- ── Identity ─────────────────────────────────────────────
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- ── Session & visitor de-duplication (no PII stored) ─────
    -- session_hash  : SHA-256(ip + user_agent + date) — daily unique visitor proxy
    -- ip_hash       : SHA-256(raw IP) — for uniqueness checks, never reversible
    `session_hash`  CHAR(64)        NOT NULL               COMMENT 'SHA-256(IP+UA+date) — daily unique visitor key',
    `ip_hash`       CHAR(64)        NOT NULL               COMMENT 'SHA-256 of raw IP — never stores plain IP',

    -- ── Request info ──────────────────────────────────────────
    `page`          VARCHAR(512)    NOT NULL DEFAULT '/'   COMMENT 'URL path (no query string)',
    `query_string`  VARCHAR(1024)   NULL                   COMMENT 'Query string portion, if any',
    `referrer`      VARCHAR(512)    NULL                   COMMENT 'HTTP_REFERER header',
    `referrer_domain` VARCHAR(255)  NULL                   COMMENT 'Parsed hostname from referrer',

    -- ── Device / browser ──────────────────────────────────────
    `device_type`   ENUM('desktop','mobile','tablet','bot','unknown') NOT NULL DEFAULT 'unknown',
    `browser`       VARCHAR(100)    NULL                   COMMENT 'Detected browser name',
    `os`            VARCHAR(100)    NULL                   COMMENT 'Detected OS name',
    `user_agent`    VARCHAR(512)    NULL                   COMMENT 'Raw User-Agent (truncated to 512)',

    -- ── Geo (stubbed — populate with GeoIP later) ─────────────
    `country_code`  CHAR(2)         NULL                   COMMENT 'ISO 3166-1 alpha-2 (e.g. US)',
    `country_name`  VARCHAR(100)    NULL,
    `city`          VARCHAR(100)    NULL,

    -- ── Timestamp ─────────────────────────────────────────────
    `visited_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_visited_at`      (`visited_at`),
    INDEX `idx_session_hash`    (`session_hash`),
    INDEX `idx_page`            (`page`(191)),
    INDEX `idx_device_type`     (`device_type`),
    INDEX `idx_referrer_domain` (`referrer_domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Server-side visitor analytics log';
