-- ============================================================
-- CleanPlate - Recipe Extractions Table
-- Migration 01: Core extraction log + cache + admin features
-- ============================================================

CREATE TABLE IF NOT EXISTS `recipe_extractions` (
    -- ── Identity ─────────────────────────────────────────────
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `url`                 TEXT            NOT NULL               COMMENT 'Original submitted URL',
    `url_hash`            CHAR(64)        NOT NULL               COMMENT 'SHA-256 of normalized URL — dedup key',
    `domain`              VARCHAR(255)    NOT NULL DEFAULT ''    COMMENT 'Extracted hostname',

    -- ── Submission tracking ───────────────────────────────────
    `submission_count`    INT UNSIGNED    NOT NULL DEFAULT 1     COMMENT 'Incremented on each re-submission',
    `first_seen_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_seen_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- ── Extraction result ─────────────────────────────────────
    `status`              ENUM('success','error','pending') NOT NULL DEFAULT 'pending',
    `phase`               TINYINT UNSIGNED NULL               COMMENT '1=JSON-LD, 2=DOM',
    `error_code`          VARCHAR(100)    NULL,
    `error_message`       TEXT            NULL,
    `processing_time_ms`  INT UNSIGNED    NULL,

    -- ── Confidence ────────────────────────────────────────────
    `confidence_score`    DECIMAL(5,2)    NULL,
    `confidence_level`    ENUM('high','medium','low') NULL,
    `confidence_details`  JSON            NULL                   COMMENT 'Full factor breakdown from parser',

    -- ── Recipe core content ───────────────────────────────────
    `title`               VARCHAR(500)    NULL,
    `description`         TEXT            NULL,
    `ingredients`         JSON            NULL,
    `instructions`        JSON            NULL,

    -- ── Attribution & timing ─────────────────────────────────
    `site_name`           VARCHAR(255)    NULL,
    `author`              VARCHAR(255)    NULL,
    `prep_time`           VARCHAR(100)    NULL,
    `cook_time`           VARCHAR(100)    NULL,
    `total_time`          VARCHAR(100)    NULL,
    `servings`            VARCHAR(100)    NULL,

    -- ── Taxonomy ─────────────────────────────────────────────
    `category`            JSON            NULL,
    `cuisine`             JSON            NULL,
    `keywords`            JSON            NULL,
    `dietary_info`        JSON            NULL,

    -- ── Ratings ───────────────────────────────────────────────
    `rating_value`        DECIMAL(3,1)    NULL,
    `rating_count`        INT UNSIGNED    NULL,

    -- ── Images ────────────────────────────────────────────────
    `image_url`           TEXT            NULL,
    `image_candidates`    JSON            NULL,

    -- ── Extended metadata ─────────────────────────────────────
    `metadata`            JSON            NULL                   COMMENT 'Full data.metadata blob from parser',
    `raw_response`        JSON            NULL                   COMMENT 'Complete parser output for debugging',

    -- ── Cache ─────────────────────────────────────────────────
    `cached_at`           TIMESTAMP       NULL,
    `cache_expires_at`    TIMESTAMP       NULL,
    `cache_hit_count`     INT UNSIGNED    NOT NULL DEFAULT 0     COMMENT 'Times cached result was served',

    -- ── Admin ─────────────────────────────────────────────────
    `is_featured`         TINYINT(1)      NOT NULL DEFAULT 0     COMMENT 'Pinned to front-page carousel',
    `featured_order`      INT             NULL                   COMMENT 'Sort position within featured set',
    `admin_notes`         TEXT            NULL,

    -- ── Keys ──────────────────────────────────────────────────
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_url_hash` (`url_hash`),
    KEY `idx_domain`           (`domain`),
    KEY `idx_status`           (`status`),
    KEY `idx_confidence_score` (`confidence_score`),
    KEY `idx_is_featured`      (`is_featured`),
    KEY `idx_first_seen_at`    (`first_seen_at`),
    KEY `idx_last_seen_at`     (`last_seen_at`),
    FULLTEXT KEY `ft_title`    (`title`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Log of every recipe extraction attempt';
