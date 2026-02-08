# CleanPlate Database & Backend Planning

## Overview
This document outlines the database architecture, admin backend requirements, migration strategies, and implementation phases for adding MySQL database support to CleanPlate.

---

## Core Database Tables

### 1. `recipe_extractions` - Track all extraction attempts
```sql
CREATE TABLE recipe_extractions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(2048) NOT NULL,
    url_hash VARCHAR(64) NOT NULL,
    domain VARCHAR(255) NOT NULL,
    status ENUM('success', 'failed', 'partial') NOT NULL,
    extraction_phase TINYINT NOT NULL COMMENT '1 or 2, which phase succeeded',
    confidence_score DECIMAL(5,2) DEFAULT NULL COMMENT '0-100',
    confidence_details JSON DEFAULT NULL COMMENT 'Breakdown of scoring',
    error_message TEXT DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    extracted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processing_time_ms INT DEFAULT NULL COMMENT 'Performance tracking',
    INDEX idx_url_hash (url_hash),
    INDEX idx_domain (domain),
    INDEX idx_status (status),
    INDEX idx_extracted_at (extracted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Purpose:**
- Track every URL attempted (success or failure)
- Analytics on domain success rates
- Rate limiting by IP/domain
- Performance monitoring
- Debugging failed extractions

---

### 2. `recipes` - Cleaned, extracted recipe data
```sql
CREATE TABLE recipes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    extraction_id INT UNSIGNED NOT NULL,
    title VARCHAR(500) NOT NULL,
    original_url VARCHAR(2048) NOT NULL,
    domain VARCHAR(255) NOT NULL,
    slug VARCHAR(500) NOT NULL COMMENT 'For friendly URLs',
    ingredients JSON NOT NULL,
    instructions JSON NOT NULL,
    prep_time INT DEFAULT NULL COMMENT 'Minutes',
    cook_time INT DEFAULT NULL COMMENT 'Minutes',
    total_time INT DEFAULT NULL COMMENT 'Minutes',
    servings VARCHAR(100) DEFAULT NULL,
    yield_text VARCHAR(255) DEFAULT NULL,
    metadata JSON DEFAULT NULL COMMENT 'Cuisine, difficulty, etc.',
    original_image_url VARCHAR(2048) DEFAULT NULL,
    stored_image_path VARCHAR(500) DEFAULT NULL,
    image_candidates JSON DEFAULT NULL COMMENT 'All images found',
    raw_json_ld JSON DEFAULT NULL COMMENT 'Original structured data',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (extraction_id) REFERENCES recipe_extractions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_slug (slug),
    INDEX idx_domain (domain),
    INDEX idx_created_at (created_at),
    FULLTEXT idx_search (title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Purpose:**
- Store successfully extracted recipes
- Enable search and discovery
- Link to original extraction for auditing
- Support recipe collections

---

### 3. `users` - User accounts
```sql
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) DEFAULT NULL,
    api_key VARCHAR(64) UNIQUE DEFAULT NULL COMMENT 'For future API access',
    subscription_tier ENUM('free', 'pro', 'enterprise') DEFAULT 'free',
    extractions_remaining INT DEFAULT 100 COMMENT 'Rate limiting',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login_at TIMESTAMP NULL,
    settings JSON DEFAULT NULL COMMENT 'User preferences',
    INDEX idx_email (email),
    INDEX idx_api_key (api_key),
    INDEX idx_subscription_tier (subscription_tier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Purpose:**
- User authentication
- Subscription management
- Rate limiting per user
- API access (future)

---

### 4. `collections` - User recipe collections
```sql
CREATE TABLE collections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    is_public BOOLEAN DEFAULT FALSE,
    slug VARCHAR(255) NOT NULL COMMENT 'For sharing',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_slug (user_id, slug),
    INDEX idx_user_id (user_id),
    INDEX idx_is_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Purpose:**
- Organize saved recipes
- Share collections publicly
- Personal recipe management

---

### 5. `collection_recipes` - Many-to-many join
```sql
CREATE TABLE collection_recipes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    collection_id INT UNSIGNED NOT NULL,
    recipe_id INT UNSIGNED NOT NULL,
    notes TEXT DEFAULT NULL COMMENT 'User personal notes',
    rating TINYINT DEFAULT NULL COMMENT '1-5 stars',
    times_made INT DEFAULT 0,
    last_made_at TIMESTAMP NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sort_order INT DEFAULT 0 COMMENT 'Manual ordering',
    FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_collection_recipe (collection_id, recipe_id),
    INDEX idx_collection_id (collection_id),
    INDEX idx_recipe_id (recipe_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Purpose:**
- Link recipes to collections
- Store user-specific metadata (ratings, notes, cook count)
- Support manual recipe ordering

---

### 6. `recipe_tags` - Categorization
```sql
CREATE TABLE recipe_tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT UNSIGNED NOT NULL,
    tag VARCHAR(100) NOT NULL COMMENT 'vegetarian, quick, asian, etc.',
    source ENUM('manual', 'ai_generated', 'auto_detected') DEFAULT 'auto_detected',
    confidence DECIMAL(3,2) DEFAULT NULL COMMENT '0-1 for AI tags',
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
    INDEX idx_recipe_id (recipe_id),
    INDEX idx_tag (tag),
    INDEX idx_source (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Purpose:**
- Categorize recipes
- Enable filtering (vegetarian, quick meals, etc.)
- Support future AI-based tagging

---

### 7. `recipe_images` - Image storage
```sql
CREATE TABLE recipe_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT UNSIGNED NOT NULL,
    original_url VARCHAR(2048) NOT NULL,
    stored_path VARCHAR(500) DEFAULT NULL COMMENT 'Local file or S3',
    width INT DEFAULT NULL,
    height INT DEFAULT NULL,
    file_size INT DEFAULT NULL COMMENT 'Bytes',
    mime_type VARCHAR(100) DEFAULT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    source ENUM('og_image', 'json_ld', 'scraped', 'user_uploaded') DEFAULT 'scraped',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
    INDEX idx_recipe_id (recipe_id),
    INDEX idx_is_primary (is_primary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Purpose:**
- Store multiple images per recipe
- Track image metadata
- Support image candidates selection

---

### 8. `extraction_analytics` - Aggregate stats
```sql
CREATE TABLE extraction_analytics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    domain VARCHAR(255) NOT NULL,
    total_attempts INT DEFAULT 0,
    successful_extractions INT DEFAULT 0,
    failed_extractions INT DEFAULT 0,
    avg_confidence_score DECIMAL(5,2) DEFAULT NULL,
    avg_processing_time_ms INT DEFAULT NULL,
    phase_1_success_rate DECIMAL(5,2) DEFAULT NULL,
    phase_2_success_rate DECIMAL(5,2) DEFAULT NULL,
    UNIQUE KEY unique_date_domain (date, domain),
    INDEX idx_date (date),
    INDEX idx_domain (domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Purpose:**
- Pre-aggregated analytics for performance
- Track success rates by domain over time
- Identify problematic domains

---

## Additional Tables to Consider

### `sessions` - Guest/anonymous user support
```sql
CREATE TABLE sessions (
    id VARCHAR(64) PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    data JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_expires_at (expires_at),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Purpose:**
- Track anonymous extractions
- Allow "claim" of extractions after signup
- Session management

---

### `rate_limits` - Advanced rate limiting
```sql
CREATE TABLE rate_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL COMMENT 'IP, user_id, domain',
    type ENUM('ip', 'user', 'domain') NOT NULL,
    window_start TIMESTAMP NOT NULL,
    request_count INT DEFAULT 0,
    INDEX idx_identifier_type (identifier, type),
    INDEX idx_window_start (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Purpose:**
- Prevent abuse
- Protect against hammering same domain
- User tier-based limits

---

### `extraction_cache` - Cache successful extractions
```sql
CREATE TABLE extraction_cache (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    url_hash VARCHAR(64) NOT NULL UNIQUE,
    recipe_data JSON NOT NULL,
    cached_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    hit_count INT DEFAULT 0,
    INDEX idx_url_hash (url_hash),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Purpose:**
- Avoid re-scraping same URLs
- Improve performance
- Reduce load on source websites

---

### `user_activity_log` - Track user actions
```sql
CREATE TABLE user_activity_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    session_id VARCHAR(64) DEFAULT NULL,
    action VARCHAR(100) NOT NULL COMMENT 'view, save, print, export',
    resource_type VARCHAR(50) DEFAULT NULL COMMENT 'recipe, collection',
    resource_id INT UNSIGNED DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Purpose:**
- Analytics on user behavior
- Popular recipes/features
- Debugging user issues

---

### `api_keys` - API access management
```sql
CREATE TABLE api_keys (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    key_hash VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(100) DEFAULT NULL COMMENT 'User-friendly name',
    rate_limit_per_hour INT DEFAULT 100,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_key_hash (key_hash),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Purpose:**
- Enable programmatic API access
- Track API usage
- Future monetization opportunity

---

## Image Storage Strategy

### Option 1: Filesystem (Recommended for MVP)
**Structure:**
```
/storage/images/recipes/
  ├── {recipe_id}/
  │   ├── primary.jpg
  │   ├── candidate_1.jpg
  │   ├── candidate_2.jpg
  │   └── thumbnails/
  │       ├── 150x150.jpg
  │       └── 300x300.jpg
```

**Pros:**
- Simple implementation
- Fast access
- No database bloat
- Works on shared hosting

**Cons:**
- Manual backup required
- No automatic CDN
- Scaling requires NFS/shared storage

**Implementation:**
- Store paths in `recipe_images.stored_path`
- Generate thumbnails on upload
- Serve via PHP or direct file access

---

### Option 2: Database BLOBs
**Not Recommended**
- Bloats database
- Slower performance
- Complicates backups
- No CDN support

---

### Option 3: Cloud Storage (S3, DigitalOcean Spaces)
**Structure:**
```
s3://cleanplate-images/recipes/
  ├── {recipe_id}/
  │   ├── primary.jpg
  │   ├── candidate_1.jpg
  │   └── thumbnails/...
```

**Pros:**
- Automatic CDN
- Unlimited scalability
- Automatic backups
- Geographic distribution

**Cons:**
- Monthly costs ($5-20/month for small scale)
- Requires API keys
- Vendor lock-in

**When to Switch:**
- 1000+ recipes
- International traffic
- High bandwidth usage

---

## Admin Backend Requirements

### Essential Admin Features

#### 1. Dashboard (`/admin/index.php`)
- Total users, recipes, extractions
- Today's stats (extractions, new users)
- Recent failed extractions
- System health indicators
- Quick links to common tasks

#### 2. User Management (`/admin/users.php`)
- List all users with search/filter
- View user details:
  - Extraction history
  - Saved collections
  - Activity log
- Actions:
  - Ban/unban user
  - Adjust rate limits
  - Reset password
  - Change subscription tier
  - Delete user + data

#### 3. Extraction Logs (`/admin/extractions.php`)
- List all extractions with filters:
  - Status (success/failed)
  - Domain
  - Date range
  - Confidence score
- View extraction details:
  - Full URL
  - Processing time
  - Confidence breakdown
  - Error messages
  - Raw JSON-LD data
- Actions:
  - Re-run extraction
  - Mark as spam
  - Blacklist domain

#### 4. Analytics Dashboard (`/admin/analytics.php`)
- Charts:
  - Extractions over time
  - Success rate by domain
  - Average confidence scores
  - Processing time trends
- Top domains (success/failure)
- Geographic distribution (if tracking IP)
- Performance metrics

#### 5. Recipe Management (`/admin/recipes.php`)
- List all recipes
- Search/filter by domain, date, tags
- View recipe details
- Actions:
  - Edit recipe data
  - Delete recipe
  - Merge duplicates
  - Regenerate thumbnails
  - Flag as inappropriate

#### 6. System Settings (`/admin/settings.php`)
- Rate limit configuration
- Domain blacklist/whitelist
- Feature flags
- Cache settings
- Image storage settings
- Email configuration

#### 7. Error Logs (`/admin/logs.php`)
- PHP errors
- Extraction failures
- API errors
- Filter by severity, date

---

### Nice-to-Have Admin Features
- Bulk operations (delete old extractions, clear cache)
- Export analytics (CSV, PDF reports)
- User impersonation (for support)
- A/B test management
- Email template editor
- Backup/restore interface

---

## Database Migration Strategy

### Phase 1: Manual SQL Files (MVP)

**Directory structure:**
```
/database/migrations/
  ├── 001_create_recipe_extractions_table.sql
  ├── 002_create_recipes_table.sql
  ├── 003_create_users_table.sql
  ├── 004_create_collections_table.sql
  ├── 005_create_collection_recipes_table.sql
  └── 006_create_recipe_images_table.sql
```

**Run via simple PHP script:**
```php
// filepath: database/migrate.php
<?php
require_once __DIR__ . '/../includes/Config.php';

$config = Config::get('database');
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']}",
    $config['username'],
    $config['password']
);

$migrationsDir = __DIR__ . '/migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files);

foreach ($files as $file) {
    echo "Running: " . basename($file) . "\n";
    $sql = file_get_contents($file);
    $pdo->exec($sql);
    echo "  ✓ Complete\n";
}

echo "\nAll migrations complete!\n";
```

**Usage:** `php database/migrate.php`

---

### Phase 2: Phinx Migration Framework

**Install:**
```bash
composer require robmorgan/phinx
```

**Create migration:**
```bash
vendor/bin/phinx create CreateUsersTable
```

**Example migration:**
```php
// filepath: database/migrations/20260208000001_create_users_table.php
<?php
use Phinx\Migration\AbstractMigration;

class CreateUsersTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('users');
        $table->addColumn('email', 'string', ['limit' => 255])
              ->addColumn('password_hash', 'string', ['limit' => 255])
              ->addColumn('subscription_tier', 'enum', [
                  'values' => ['free', 'pro', 'enterprise'],
                  'default' => 'free'
              ])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['email'], ['unique' => true])
              ->create();
    }
}
```

**Run migrations:**
```bash
vendor/bin/phinx migrate
```

**Rollback:**
```bash
vendor/bin/phinx rollback
```

**Benefits:**
- Version control for schema
- Easy rollback
- Works across environments
- Tracks migration status

---

## Database Layer Architecture

### Model Classes

Create model classes in `/includes/models/`:

```php
// filepath: includes/models/User.php
<?php
class User {
    private $pdo;
    private $id;
    private $email;
    private $name;
    
    public function __construct($pdo, $id = null) {
        $this->pdo = $pdo;
        if ($id) {
            $this->load($id);
        }
    }
    
    public static function findByEmail($pdo, $email) {
        // Implementation
    }
    
    public function save() {
        // Implementation
    }
    
    public function getCollections() {
        // Implementation
    }
}
```

```php
// filepath: includes/models/Recipe.php
<?php
class Recipe {
    private $pdo;
    private $id;
    private $title;
    private $ingredients;
    
    public function __construct($pdo, $id = null) {
        $this->pdo = $pdo;
        if ($id) {
            $this->load($id);
        }
    }
    
    public static function search($pdo, $query) {
        // Full-text search
    }
    
    public function saveToCollection($collectionId, $userId) {
        // Implementation
    }
}
```

---

## Implementation Phases

### Phase 1: Core Infrastructure (Week 1-2)
**Goal:** Basic database and user accounts

**Tasks:**
1. Create 6 core tables (users, recipes, extractions, collections, collection_recipes, recipe_images)
2. Set up migration system
3. Build basic User model class
4. Implement user registration/login
5. Create admin authentication

**Deliverables:**
- Working user signup/login
- Admin login page
- Database schema v1

---

### Phase 2: Recipe Saving (Week 3)
**Goal:** Users can save extracted recipes

**Tasks:**
1. Modify extraction API to save to database
2. Implement "Save Recipe" button on frontend
3. Build collections UI (create, view, manage)
4. Add recipe to collection functionality

**Deliverables:**
- Users can save recipes
- Users can create collections
- Collections display page

---

### Phase 3: Admin Dashboard (Week 4)
**Goal:** Basic admin tools

**Tasks:**
1. Build admin dashboard with stats
2. Create extraction logs viewer
3. Add user management page
4. Implement basic analytics charts

**Deliverables:**
- Admin can view all extractions
- Admin can manage users
- Analytics dashboard

---

### Phase 4: Image Storage (Week 5)
**Goal:** Save and serve recipe images

**Tasks:**
1. Implement image downloading
2. Create thumbnail generation
3. Store images on filesystem
4. Update frontend to use stored images
5. Add image selector UI improvement

**Deliverables:**
- Images saved locally
- Thumbnails generated
- Faster image loading

---

### Phase 5: Rate Limiting & Cache (Week 6)
**Goal:** Performance and abuse prevention

**Tasks:**
1. Implement rate limiting by IP/user/domain
2. Build extraction cache system
3. Add cache hit/miss tracking
4. Create cache management in admin

**Deliverables:**
- Rate limits enforced
- Cache reduces duplicate extractions
- Admin can clear cache

---

### Phase 6: Enhanced Features (Ongoing)
**Future enhancements:**
- Recipe search and discovery
- Public recipe sharing
- Social features (likes, comments)
- Meal planning
- Shopping list generation
- API access
- Mobile app
- Browser extension

---

## Technology Stack Recommendations

### Backend
- **PHP 8.0+** (current codebase)
- **MySQL 8.0+** or MariaDB 10.5+
- **Composer** for dependency management
- **Phinx** for migrations (later)

### Admin Interface
- **Vanilla PHP** for MVP (keep it simple)
- **Bootstrap 5** for UI (fast, no JS framework needed)
- **Chart.js** for analytics charts

### Future Considerations
- **Laravel/Symfony** if rewriting backend
- **React/Vue** if building SPA admin
- **Redis** for caching
- **Elasticsearch** for recipe search

---

## Security Considerations

### Authentication
- Password hashing with `password_hash()` and `PASSWORD_DEFAULT`
- Session management with secure cookies
- CSRF protection on all forms
- Rate limiting on login attempts

### Data Protection
- Prepared statements for all queries (already implemented)
- Input validation and sanitization
- Escape output in HTML
- HTTPS required for production

### Admin Access
- Separate admin user table or role-based permissions
- IP whitelist for admin panel (optional)
- Two-factor authentication (future)
- Activity logging for admin actions

### Privacy Compliance (GDPR)
- User data export functionality
- User data deletion (right to be forgotten)
- Cookie consent
- Privacy policy

---

## Monitoring & Logging

### Application Logs
```
/storage/logs/
  ├── app.log          # General application
  ├── extractions.log  # All extraction attempts
  ├── errors.log       # PHP errors
  ├── admin.log        # Admin actions
  └── api.log          # API requests (future)
```

### Metrics to Track
- Extraction success rate (overall and by domain)
- Average processing time
- Cache hit rate
- User retention
- Popular domains
- Error frequency

### Alerting
- Email admin on repeated extraction failures
- Alert on unusually high error rates
- Notify on system resource issues

---

## Cost Estimates (Small Scale)

### Hosting
- **Shared hosting:** $5-15/month (sufficient for MVP)
- **VPS (DigitalOcean/Linode):** $10-20/month
- **Database:** Included with hosting

### Storage (if using cloud)
- **S3/Spaces:** $5/month for 250GB storage + $1-5 for bandwidth
- **Cloudflare R2:** $0.015/GB (no egress fees)

### Total MVP Cost: $10-25/month

### Scaling Cost (1000+ users)
- **VPS:** $40-80/month
- **Database:** May need dedicated instance ($15-30/month)
- **CDN:** $10-30/month
- **Total:** $65-140/month

---

## Open Questions / Decisions Needed

1. **User registration:**
   - Email verification required?
   - Allow social login (Google, GitHub)?
   - Guest accounts with extraction limits?

2. **Subscription model:**
   - Free tier limits (extractions per day)?
   - Paid tier benefits (unlimited, priority, export)?
   - Pricing: $5/month, $50/year?

3. **Image handling:**
   - Download all image candidates or just primary?
   - Convert all to WebP for compression?
   - Generate thumbnails immediately or on-demand?

4. **Data retention:**
   - Keep failed extractions forever or purge after 30 days?
   - Anonymous extractions expire after X days?
   - Delete old cached data after X days?

5. **API access:**
   - Offer API from day one or later?
   - Free tier for API access?
   - Rate limits for API?

---

## Next Steps

**Immediate (This week):**
1. Create core SQL migration files
2. Add database config to `config/database.php`
3. Build User model class
4. Implement basic user registration/login

**Short-term (Next 2 weeks):**
1. Modify extraction flow to save to database
2. Build "Save Recipe" feature
3. Create collections UI
4. Build basic admin dashboard

**Medium-term (Month 2):**
1. Implement image storage
2. Add rate limiting
3. Build extraction cache
4. Enhance admin features

**Long-term (Month 3+):**
1. Recipe search and discovery
2. Public recipe sharing
3. API development
4. Mobile app considerations

---

## Resources & References

### Documentation
- [MySQL 8.0 Reference](https://dev.mysql.com/doc/refman/8.0/en/)
- [Phinx Migrations](https://book.cakephp.org/phinx/0/en/index.html)
- [PHP PDO Documentation](https://www.php.net/manual/en/book.pdo.php)

### Similar Projects (for inspiration)
- Paprika Recipe Manager
- Copy Me That
- Whisk
- Plan to Eat

### Tools
- phpMyAdmin (database management)
- Adminer (lightweight alternative)
- MySQL Workbench (schema design)
- DBeaver (cross-platform DB tool)

---

**Document Version:** 1.0  
**Last Updated:** February 8, 2026  
**Author:** System Planning  
**Status:** Planning Phase
