# CleanPlate Database Integration - Complete Implementation Plan

**Document Version:** 1.0  
**Created:** February 10, 2026  
**Status:** Planning Phase

---

## Overview

This is a comprehensive implementation plan to transform CleanPlate from a stateless recipe extraction tool into a full-featured recipe management platform with user accounts, collections, admin dashboard, and analytics. The plan builds incrementally over 6 phases, maintaining backward compatibility while adding database persistence layer, authentication, and advanced features.

**Key Technical Decisions:**
- Maintain zero-dependency PHP architecture with custom PDO wrapper
- Use simple SQL migrations initially (can upgrade to Phinx later)
- Implement session-based authentication
- Defer complex features like image storage and AI tagging until core functionality proves stable

---

## **Phase 1: Core Infrastructure (Week 1, Days 1-7)**

### **Goal: Establish database foundation with connection layer, migrations, and basic extraction logging**

**Steps:**

1. **Create database connection wrapper** - Build `includes/Database.php` as a singleton PDO wrapper with methods: `connect()`, `query()`, `execute()`, `lastInsertId()`, `isConnected()`, `beginTransaction()`, `commit()`, `rollback()`. Follow the existing `Config` class singleton pattern. Load settings from `config/database.php` using `Config::get('database.connections.mysql.*')`.

2. **Write SQL migration files** - Create directory `/database/migrations/` with sequential files:
   - `001_create_recipe_extractions_table.sql` - Table with columns: id, url, url_hash (SHA-256 of URL), domain, status ENUM, extraction_phase, confidence_score, confidence_details JSON, error_message, user_agent, ip_address, extracted_at, processing_time_ms. Add indexes on url_hash, domain, status, extracted_at.
   - `002_create_recipes_table.sql` - Table with columns: id, extraction_id (FK), title, original_url, domain, slug, ingredients JSON, instructions JSON, prep_time, cook_time, total_time, servings, yield_text, metadata JSON, original_image_url, image_candidates JSON, raw_json_ld JSON, created_at, updated_at. Add FULLTEXT index on title, indexes on domain and created_at.
   - `003_create_users_table.sql` - Table with columns: id, email (unique), password_hash, name, api_key, subscription_tier ENUM (free/pro/enterprise), extractions_remaining, created_at, last_login_at, settings JSON. Add unique index on email and api_key.

3. **Build migration runner** - Create `database/migrate.php` script that connects via `Database` class, reads all `.sql` files from `/database/migrations/` in sorted order, executes each file's SQL, outputs progress. Add error handling with try-catch, rollback on failure. Make executable from command line: `php database/migrate.php`.

4. **Create migration tracking table** - Add `000_create_migrations_table.sql` that creates `migrations` table with columns: id, migration, batch, executed_at. Update runner to check this table before executing migrations (skip already-run migrations).

5. **Build base Model class** - Create `includes/models/BaseModel.php` with protected `$db` property, `__construct()` that gets Database instance, static methods: `find($id)`, `all()`, `where($column, $value)`. Implement magic methods `__get()` and `__set()` for property access. Use prepared statements for all queries.

6. **Implement ExtractionLog model** - Create `includes/models/ExtractionLog.php` extending `BaseModel`. Add static method `create($data)` that inserts extraction record with url_hash generation (SHA-256), domain extraction from URL, timestamp setting. Add method `calculateProcessingTime($startTime)` to compute ms. Add static method `getStats($startDate, $endDate)` for analytics.

7. **Integrate extraction logging into API** - Modify `api/parser.php` at line 166 (after `$result = $parser->parse($url)`). Add check if `Database::isConnected()`, then call `ExtractionLog::create()` with success status, phase, confidence, user agent, IP address, processing time. Wrap in try-catch to prevent DB errors from breaking extraction. Add similar logging in catch block for failed extractions.

8. **Add environment setup documentation** - Create `SETUP_DATABASE.md` with instructions for copying `.env.example` to `.env`, setting database credentials, running `docker-compose up -d`, executing migrations, verifying connection with test script.

9. **Create database connection test** - Build `tests/test-database-connection.php` that loads `Database` class, attempts connection, runs simple query (SELECT 1), displays connection info, lists tables. Use this to verify Phase 1 completion.

10. **Update Docker initialization** - Modify `docker-compose.yaml` to add init script mounting. Create `docker/init-db.sh` that waits for MySQL availability, runs migration script automatically on container startup.

**Verification:**
- Run `docker-compose up -d` and verify MySQL container starts
- Execute `php database/migrate.php` successfully migrates all tables
- Run `php tests/test-database-connection.php` and see successful connection
- Extract a test recipe via UI and verify entry appears in `recipe_extractions` table via phpMyAdmin
- Check that extraction failures also log to database with error messages

---

## **Phase 2: User Authentication & Recipe Saving (Week 2, Days 8-14)**

### **Goal: Enable user accounts, login system, and ability to save extracted recipes to personal collections**

**Steps:**

1. **Implement User model** - Create `includes/models/User.php` extending `BaseModel`. Add methods: `register($email, $password, $name)` with password hashing via `password_hash()` with PASSWORD_DEFAULT, `authenticate($email, $password)` using `password_verify()`, `findByEmail($email)`, `updateLastLogin()`, `getExtractionCount()`. Add validation for email format and password strength (min 8 chars).

2. **Build authentication helper** - Create `includes/Auth.php` with static methods: `login($user)` to set session variables, `logout()` to destroy session, `check()` to verify if user logged in, `user()` to get current user object, `guest()` to check if not logged in, `requireAuth()` middleware that redirects/errors if not authenticated.

3. **Create registration API endpoint** - Build `api/auth/register.php` that accepts POST with email, password, name. Validates input, checks email not taken, calls `User::register()`, auto-logs in new user, returns success with user object. Handle errors: email taken, invalid format, password too weak.

4. **Create login API endpoint** - Build `api/auth/login.php` that accepts POST with email, password. Calls `User::authenticate()`, sets session via `Auth::login()`, returns success with user object. Handle errors: invalid credentials, account not found, too many attempts (add rate limiting).

5. **Create logout endpoint** - Build `api/auth/logout.php` that calls `Auth::logout()` and returns success.

6. **Add authentication UI to frontend** - Modify `public/index.html` to add modal for login/register forms with tabs. Add navigation showing username when logged in, buttons for login/logout/register. Use CSS to hide login-required features when guest.

7. **Implement frontend authentication** - Extend `public/js/app.js` with new `AuthManager` class that handles: `register(email, password, name)`, `login(email, password)`, `logout()`, `getCurrentUser()`, `isAuthenticated()`. Store auth state in `RecipeState.currentUser`. Add listeners for form submissions.

8. **Create Recipe model** - Build `includes/models/Recipe.php` extending `BaseModel`. Add static method `createFromExtractionData($extractionId, $data)` that inserts recipe with title, URL, domain, slug generation (from title), JSON encoding of ingredients/instructions/metadata. Add methods: `findBySlug($slug)`, `findByDomain($domain)`, `search($query)` using FULLTEXT, `getImages()`.

9. **Create Collection model** - Build `includes/models/Collection.php` with methods: `create($userId, $name, $description)`, `findByUser($userId)`, `addRecipe($recipeId, $notes)`, `removeRecipe($recipeId)`, `getRecipes()`, `update($name, $description)`, `delete()`. Generate slug from name for URL-friendly access.

10. **Create save recipe API endpoint** - Build `api/recipes/save.php` that requires authentication, accepts POST with recipe data and collection_id. Creates Recipe if not exists (checks by URL hash), adds to CollectionRecipe table, returns recipe_id and success message. Handle duplicate saves gracefully.

11. **Add "Save Recipe" button to UI** - Modify recipe display section in `public/index.html` to add save button in toolbar. When clicked, show collection selector modal (list user's collections, option to create new). Wire up in `public/js/app.js` to call save API endpoint.

12. **Create collections management page** - Build `public/collections.html` with list of user's collections, ability to create/rename/delete collections, view recipes in each collection. Add navigation link to this page from main UI.

13. **Build collections API endpoints** - Create `api/collections/create.php`, `api/collections/list.php`, `api/collections/view.php` (show recipes), `api/collections/delete.php`. All require authentication and operate on current user's data only.

14. **Add collection management JavaScript** - Create `public/js/collections.js` to handle collections page UI: fetching collections, rendering list, create/edit/delete operations, drag-drop recipe organization, inline editing of collection names.

**Verification:**
- Register new user account via UI successfully
- Login with credentials and see username in navigation
- Extract a recipe and click "Save Recipe" button
- Create a new collection and save recipe to it
- Navigate to collections page and see saved recipe
- Verify database tables `users`, `recipes`, `collections`, `collection_recipes` have correct data
- Test logout and verify cannot access save features when not logged in

---

## **Phase 3: Admin Dashboard & Analytics (Week 3, Days 15-21)**

### **Goal: Build comprehensive admin interface for monitoring extractions, managing users, and viewing analytics**

**Steps:**

1. **Add admin role to users** - Create migration `004_add_admin_role_to_users.sql` that adds `is_admin` BOOLEAN column to users table, defaults to FALSE. Manually update one user to admin in database for testing.

2. **Create admin authentication middleware** - Add method `requireAdmin()` to `includes/Auth.php` that checks if current user has `is_admin = 1`, otherwise responds with 403 error. Create `isAdmin()` helper method.

3. **Build admin layout template** - Create `admin/layout.php` with header, sidebar navigation (Dashboard, Extractions, Recipes, Users, Analytics, Settings), main content area, footer. Use Bootstrap 5 for UI framework. Include Chart.js from CDN for graphs.

4. **Create admin dashboard** - Build `admin/index.php` that requires admin auth, displays key metrics in cards: total extractions (today/week/all-time), success rate percentage, total users, total recipes saved, average confidence score. Show recent activity list (last 20 extractions with status, URL, timestamp). Add quick action buttons.

5. **Implement extraction logs viewer** - Create `admin/extractions.php` with paginated table showing all extractions. Columns: ID, URL (truncated with tooltip), domain, status badge, phase, confidence score with color coding (green >80, yellow 50-80, red <50), timestamp, user (if logged in), IP address. Add filters: date range, status, domain dropdown, confidence threshold. Add search by URL.

6. **Build extraction detail page** - Create `admin/extraction-detail.php` that accepts extraction_id parameter, displays full extraction record including: complete URL, full error messages, confidence breakdown visualization, raw JSON-LD data in formatted code block, user agent, processing time, option to re-run extraction.

7. **Create users management page** - Build `admin/users.php` with table of all users showing: ID, email, name, subscription tier, registration date, last login, extraction count, admin status. Add actions: view user details, change subscription tier, toggle admin, delete user. Add search by email/name.

8. **Implement user detail page** - Create `admin/user-detail.php` that shows user profile, extraction history, saved recipes, collections, activity log. Add ability to adjust rate limits, reset password, ban/unban user.

9. **Build analytics dashboard** - Create `admin/analytics.php` with multiple Chart.js visualizations: line chart of extractions over time (daily for last 30 days), pie chart of Phase 1 vs Phase 2 success rates, bar chart of top 10 domains by extraction count, bar chart of success rate by domain, line chart of average confidence scores over time. Add date range selector.

10. **Create ExtractionAnalytics aggregator** - Build `includes/jobs/AggregateExtractionStats.php` that can be run as cron job. Queries `recipe_extractions` table, groups by date and domain, calculates totals, averages, success rates. Inserts into `extraction_analytics` table (create migration `005_create_extraction_analytics_table.sql`). This pre-computes analytics for fast dashboard loading.

11. **Build analytics API endpoints** - Create `api/admin/analytics.php` that requires admin auth, accepts date range and metric type parameters, returns JSON data for charts. Queries `extraction_analytics` table for performance. Add endpoints: `/api/admin/stats.php` for dashboard cards, `/api/admin/recent-activity.php` for activity feed.

12. **Add recipe management page** - Create `admin/recipes.php` showing all saved recipes with filters by domain, date, tags. Add actions: view recipe detail, edit recipe data (fix typos), delete recipe, merge duplicates. Show which users have saved each recipe.

13. **Implement domain statistics page** - Build `admin/domains.php` showing table of all domains encountered, total attempts, success count, failure count, success rate percentage, average confidence, average processing time. Add ability to blacklist problematic domains, whitelist known-good domains.

14. **Create system settings page** - Build `admin/settings.php` with form to edit configuration: rate limit values (requests per period), cache expiration time, enable/disable features (guest extraction, public sharing), CORS settings, logging levels. Store in new `settings` table (create migration `006_create_settings_table.sql`).

**Verification:**
- Login as admin user and access `/admin/` successfully
- View dashboard and see accurate extraction counts and success rates
- Filter extraction logs by date range and status
- Click an extraction to view full details including confidence breakdown
- View users list and search for specific user email
- Open analytics page and see charts rendering with real data
- Run aggregator script and verify `extraction_analytics` table populates
- Test that non-admin users cannot access admin pages (403 error)

---

## **Phase 4: Extraction Caching & Rate Limiting (Week 4, Days 22-28)**

### **Goal: Improve performance with URL caching and implement robust rate limiting to prevent abuse**

**Steps:**

1. **Create extraction cache table** - Build migration `007_create_extraction_cache_table.sql` with columns: id, url_hash (unique), url, recipe_data JSON, cached_at, expires_at, hit_count, last_hit_at. Add index on expires_at for cleanup queries.

2. **Implement ExtractionCache model** - Create `includes/models/ExtractionCache.php` with methods: `get($url)` that checks cache by url_hash and expires_at > NOW(), increments hit_count, returns data or null. `set($url, $data, $ttl)` that inserts/updates cache entry with expiration. `clear($url)` to invalidate, `clearExpired()` for cleanup, `getStats()` for cache hit rate analytics.

3. **Integrate cache into RecipeParser** - Modify `includes/RecipeParser.php` in `parse()` method at line 64. Before calling `fetchUrl()`, check if Database connected and call `ExtractionCache::get($url)`. If cache hit, log cache usage and return cached data immediately (add 'cached' flag to response). After successful extraction, call `ExtractionCache::set()` with TTL from config (default 7 days for success, 1 day for failures).

4. **Add cache configuration** - Update `config/cache.php` to add extraction cache settings: default_ttl (604800 for 7 days), max_age for different confidence levels (high confidence = 30 days, low = 3 days), enable/disable flag. Load in RecipeParser via `Config::get('cache.extraction.*')`.

5. **Create rate limits table** - Build migration `008_create_rate_limits_table.sql` with columns: id, identifier (IP or user_id), type ENUM (ip/user/domain), resource (URL or domain), window_start, window_end, request_count, last_request_at. Add composite index on (identifier, type, resource).

6. **Implement RateLimiter class** - Create `includes/RateLimiter.php` with methods: `check($identifier, $type, $limit, $period)` that queries rate_limits table, counts requests in current window, returns true/false. `record($identifier, $type, $resource)` to log request. `getRemainingRequests()`, `getResetTime()`, `clearOldRecords()` cleanup method. Use sliding window algorithm for accuracy.

7. **Add per-domain rate limiting** - Extend `RateLimiter` with `checkDomain($domain)` that limits requests to same domain (default 5 per 60 seconds from same IP). Prevents hammering individual websites. Integrate into `api/parser.php` after URL validation, before extraction.

8. **Implement user-tier rate limiting** - Modify `api/parser.php` to check user's subscription tier and apply different limits: free = 10/hour, pro = 100/hour, enterprise = unlimited. Query from User model's `subscription_tier`. Return helpful error message with tier upgrade suggestion when limit hit.

9. **Add rate limit headers to responses** - Modify `api/parser.php` to add HTTP headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset` (Unix timestamp). Add to both success and error responses. Frontend can display this info to users.

10. **Create cache management admin page** - Build `admin/cache.php` showing cache statistics: total entries, hit rate percentage, storage size, most cached domains. Add actions: clear all cache, clear expired, clear specific URL, view cached data. Show list of cached URLs with hit counts and expiration.

11. **Add cache cleanup cron job** - Create `includes/jobs/ClearExpiredCache.php` that calls `ExtractionCache::clearExpired()` to delete entries where `expires_at < NOW()`. Should run daily via cron. Add `RateLimiter::clearOldRecords()` to same job (delete records older than 30 days).

12. **Implement cache warming feature** - Create `includes/jobs/WarmPopularRecipeCache.php` that identifies most-saved recipes, checks if their source URLs are cached, re-extracts if cache expired. Keeps popular content fresh. Can run weekly.

13. **Add cache status to UI** - Modify `public/js/app.js` to detect if extraction response includes 'cached' flag. Display small badge "Served from cache" near confidence score. Show faster response time to users as quality signal.

14. **Create rate limit API endpoint** - Build `api/rate-limit-status.php` that returns current user's remaining extractions, reset time, tier limits. Frontend can query this on page load to show "10 extractions remaining today" message.

**Verification:**
- Extract a recipe URL twice and verify second request is instant (cache hit)
- Check database and confirm `extraction_cache` table has entry with correct expiration
- View admin cache page and see hit count increased
- Attempt to extract 6 recipes from same domain rapidly and get rate limited
- Login as free user and hit hourly extraction limit, see helpful error message
- Check response headers and verify rate limit info present
- Run cache cleanup job and verify expired entries deleted
- Check that cached extractions still log to `recipe_extractions` with cached flag

---

## **Phase 5: Image Storage & Processing (Week 5-6, Days 29-42)**

### **Goal: Download, store, and serve recipe images locally with thumbnail generation**

**Steps:**

1. **Create recipe images table** - Build migration `009_create_recipe_images_table.sql` with columns: id, recipe_id (FK), original_url, stored_path, width, height, file_size, mime_type, is_primary BOOLEAN, source ENUM (og_image/json_ld/scraped), created_at. Add indexes on recipe_id and is_primary.

2. **Set up storage directory structure** - Create directory `/storage/images/recipes/` with subdirectories `/original/` and `/thumbnails/`. Add `.gitignore` to exclude image files. Set proper permissions (writable by PHP). Add to `docker-compose.yaml` as volume mount for persistence.

3. **Create ImageStorage utility class** - Build `includes/ImageStorage.php` with static methods: `download($url, $recipeId)` that fetches image via cURL, validates is image type, generates unique filename (hash + extension), saves to `/storage/images/recipes/original/{recipe_id}/`, returns stored path. Add error handling for failed downloads, timeouts, invalid images.

4. **Implement thumbnail generator** - Add method `generateThumbnail($sourcePath, $size)` to `ImageStorage` that uses GD or ImageMagick to create thumbnail. Generate 3 sizes: 150x150 (tiny), 300x300 (small), 600x600 (medium). Save to `/storage/images/recipes/thumbnails/{recipe_id}/{size}_`. Maintain aspect ratio with cropping to square.

5. **Create RecipeImage model** - Build `includes/models/RecipeImage.php` with methods: `create($recipeId, $url, $storedPath, $metadata)`, `findByRecipe($recipeId)`, `setPrimary($imageId)` (unsets other primary flags), `delete()` (removes file and DB record), `getUrl($size)` that returns public URL for specified thumbnail size.

6. **Integrate image download into recipe saving** - Modify `api/recipes/save.php` to call image storage after recipe created. Loop through `image_candidates` from extraction data, call `ImageStorage::download()` for top 3 candidates, create RecipeImage records. Set highest-scored candidate as primary. Run in try-catch to prevent image failures from blocking recipe save.

7. **Create image processing queue** - Build `includes/jobs/ProcessRecipeImages.php` that queries recipes with missing images, downloads and generates thumbnails. Can run as background job queue for recipes saved without images. Add status tracking in recipes table (enum: pending/processing/complete/failed).

8. **Build image serving endpoint** - Create `api/images/serve.php` that accepts recipe_id and size parameters, looks up RecipeImage record, reads file from storage, sets proper Content-Type headers, streams image content. Add caching headers (max-age 1 year since images immutable by ID).

9. **Update Recipe model for images** - Add methods to `includes/models/Recipe.php`: `getPrimaryImage()`, `getAllImages()`, `setImage($imageUrl)`, `removeImage($imageId)`. Update `createFromExtractionData()` to handle image storage if flag enabled in config.

10. **Modify frontend to use stored images** - Update `public/js/app.js` image carousel to check if recipe has stored images (recipe_id present), construct URLs like `/api/images/serve.php?recipe={id}&size=medium`, fallback to original URLs if not stored. Lazy load images for performance.

11. **Add image management to admin** - Create `admin/images.php` showing all stored images with thumbnails, recipe titles, file sizes, storage paths. Add actions: delete image, regenerate thumbnails, set as primary, re-download from original URL. Show total storage used.

12. **Implement image optimization** - Add method `optimize($imagePath)` to `ImageStorage` that compresses images: converts to WebP for smaller size, strips EXIF data, reduces quality to 85% if over 500KB. Run automatically after download. Add config toggle for this feature.

13. **Create image cleanup job** - Build `includes/jobs/CleanupOrphanedImages.php` that finds images in storage with no corresponding RecipeImage record, deletes them. Also removes RecipeImage records where recipe deleted but files remain. Run weekly.

14. **Add bulk image processing UI** - Create admin page `admin/process-images.php` with queue status, ability to trigger bulk processing of all recipes missing images, progress bar, error log. Use AJAX polling to update status.

15. **Implement CDN preparation** - Add config for CDN URL in `config/services.php` under 'cdn' key. Modify image URLs to use CDN domain if configured. Prepare for future S3/Cloudflare integration but keep filesystem default.

**Verification:**
- Save a new recipe and verify images download to `/storage/images/recipes/original/{id}/`
- Check that 3 thumbnail sizes generated automatically
- View recipe in frontend and see images served from local storage
- Test image serving endpoint directly in browser
- Open admin images page and see all stored images with thumbnails
- Delete an image via admin and verify file removed from disk
- Run image cleanup job and verify orphaned files deleted
- Check total storage size in admin dashboard
- Disable image storage in config and verify recipes still save (URLs only)

---

## **Phase 6: Advanced Features & Polish (Week 7+, Days 43-60)**

### **Goal: Add recipe search, discovery features, public sharing, and final optimizations**

**Steps:**

1. **Implement full-text recipe search** - Create migration `010_add_fulltext_indexes.sql` that adds FULLTEXT indexes on recipes.title, JSON_EXTRACT on ingredients array. Modify `Recipe::search($query)` to use MATCH AGAINST query for relevance scoring. Add filters: cuisine, dietary restrictions, cook time range.

2. **Build recipe browse page** - Create `public/browse.html` with grid layout showing all public recipes (paginated). Display recipe cards with thumbnail, title, domain badge, confidence score, save count. Add sidebar with filters: cuisine type, dietary tags, cook time, domain. Sort options: newest, popular, highest rated.

3. **Add recipe detail page** - Create `public/recipe.html` that accepts recipe slug or ID parameter, displays full recipe with clean formatting, all metadata, instructions, notes section. Show "Originally from [domain]" link. Add "Save to Collection" button for logged-in users. Include print-friendly CSS.

4. **Implement public recipe sharing** - Add column `is_public` BOOLEAN to recipes table with migration `011_add_public_sharing_to_recipes.sql`. Add toggle in UI when saving recipe. Create `api/recipes/share.php` endpoint that generates shareable link. Add OpenGraph meta tags to recipe detail page for social previews.

5. **Create recipe recommendation engine** - Build `includes/RecommendationEngine.php` that suggests similar recipes based on: shared ingredients, same cuisine, same cook time range, same domain. Use simple collaborative filtering: "Users who saved X also saved Y". Return top 5 recommendations. Add to recipe detail page.

6. **Build user profile pages** - Create `public/profile.html` showing user's public collections, recipe count, member since date. Add privacy settings so users can make profile public/private. If public, show on recipe pages ("Saved by 47 users" with avatars).

7. **Implement recipe ratings and notes** - Add columns to `collection_recipes` table: rating (1-5), notes TEXT, times_made, last_made_at with migration `012_add_recipe_metadata_to_collections.sql`. Add UI in collections view for users to rate recipes, add cooking notes, track cook history.

8. **Create shopping list aggregator** - Build `public/shopping-list.html` where users select multiple recipes, system combines all ingredients, groups by category (produce, dairy, meat, etc.), adjusts quantities. Add checkboxes to mark items purchased. Store in new `shopping_lists` table.

9. **Add recipe scaling feature** - Modify recipe detail page to include servings adjuster (slider or +/- buttons). Calculate ingredient quantity multipliers in JavaScript, update displayed amounts. Works for fractions: "1/2 cup" becomes "1 cup" when doubled. Use existing measurement parsing from IngredientFilter.

10. **Implement API access system** - Create `api/v1/` directory for public API. Build endpoints: `/api/v1/extract` (extract recipe), `/api/v1/recipes` (list user's recipes), `/api/v1/recipes/{id}` (get recipe). Require API key authentication from users table. Add rate limiting per API key. Document in new `API.md` file.

11. **Add tagging system** - Create tables: `tags` (id, name, type), `recipe_tags` (recipe_id, tag_id) with migration `013_create_tags_tables.sql`. Build `includes/TagExtractor.php` that auto-detects tags from recipe data: cuisine (italian, mexican), dietary (vegetarian, vegan), method (baked, grilled), meal (breakfast, dessert). Add to recipe display and filters.

12. **Create export functionality** - Build endpoints: `api/recipes/export-pdf.php` (generates PDF using TCPDF or similar), `api/recipes/export-json.php` (structured data export), `api/recipes/export-text.php` (markdown format). Add export buttons to recipe detail page and collection pages.

13. **Implement email recipe feature** - Create `api/recipes/email.php` that accepts email address, sends formatted recipe via configured mail service. Use template from `includes/templates/email-recipe.html`. Add rate limiting (5 emails per day per user) to prevent spam.

14. **Build weekly digest email** - Create `includes/jobs/SendWeeklyDigest.php` that runs every Monday, sends users summary of: newly saved recipes, collection activity, recommended recipes based on their saves. Add user preference to opt in/out in settings.

15. **Add performance monitoring** - Create `admin/performance.php` dashboard showing: average extraction time by domain, cache hit rate over time, database query performance (slow query log), API endpoint response times. Use data from extraction logs and add new `performance_logs` table.

16. **Implement A/B testing framework** - Build `includes/ABTest.php` that randomly assigns users to test variants, tracks which features they use, measures conversion (recipe saves). Create admin interface to view test results. Use for testing new features before full rollout.

17. **Create backup and restore system** - Build `admin/backup.php` that generates database dumps (mysqldump), backs up images directory, stores in `/storage/backups/`. Add restore functionality. Schedule automatic daily backups. Add download link for latest backup.

18. **Optimize database queries** - Review all model methods, add missing indexes found via slow query log. Add query result caching for expensive operations (use `config/cache.php` Redis config). Implement eager loading to prevent N+1 queries when fetching recipes with images.

19. **Add comprehensive error logging** - Enhance error logging in `includes/Logger.php` (create if doesn't exist) to write to `/storage/logs/` with rotation. Log levels: debug, info, warning, error, critical. Add context data. Create `admin/logs.php` viewer with filtering and search.

20. **Create user documentation** - Build `docs/` directory with guides: USER_GUIDE.md (how to use all features), ADMIN_GUIDE.md (managing the system), API_DOCUMENTATION.md (for developers), TROUBLESHOOTING.md (common issues). Add help links throughout UI.

**Verification:**
- Search for "chicken" and see relevant recipes ranked by relevance
- Browse recipes page and filter by cuisine type
- View recipe detail page with clean layout and all metadata
- Toggle recipe to public and verify shareable link works (no login required)
- Save multiple recipes and get relevant recommendations
- Create shopping list from 3 recipes and see combined ingredients
- Scale recipe servings and verify ingredient amounts multiply correctly
- Generate API key and successfully call `/api/v1/extract` endpoint
- Export recipe as PDF and verify clean formatting
- Email recipe to test address and receive formatted HTML email
- View performance dashboard and see extraction time trends
- Create database backup and verify dump file created with images
- Check admin logs viewer and see all application errors logged
- Review all documentation files and verify completeness

---

## **Final Verification (All Phases Complete)**

After completing all 6 phases, perform comprehensive end-to-end testing:

1. **User Journey Test:** Register new account → extract recipe → save to collection → view in collections page → edit notes → rate recipe → create shopping list → export as PDF

2. **Admin Journey Test:** Login as admin → view dashboard → review extraction logs → manage user accounts → view analytics charts → clear cache → manage images

3. **Performance Test:** Run 100 concurrent extractions, measure response times, verify cache working, check rate limits enforced

4. **Database Integrity Test:** Verify all foreign keys working, cascade deletes functional, no orphaned records

5. **Security Test:** Attempt SQL injection, XSS attacks, CSRF attacks, verify all blocked. Test rate limiting, authentication bypasses

6. **Backup/Restore Test:** Create backup, delete test data, restore backup, verify data intact

7. **Docker Test:** Run `docker-compose down -v`, restart containers, verify migrations auto-run, app fully functional

8. **Load Test:** Simulate 1000 users, check database connection pooling, verify no memory leaks, monitor query performance

---

## **Key Design Decisions**

### **Architecture Choices**
- **Maintain zero-dependency PHP** for core functionality (no Composer required for basic features)
- **Use PDO directly** instead of ORM - keeps codebase simple and performant
- **Session-based authentication** initially (JWT tokens in Phase 6 for API)
- **Filesystem image storage** for MVP (cloud storage prepared but optional)

### **Database Design**
- **MySQL 8.0** as primary database (SQLite tested but not recommended for production)
- **JSON columns** for flexible metadata storage (ingredients, instructions, confidence details)
- **Composite indexes** on frequently queried column combinations
- **Cascade deletes** for related records (recipes delete when user deleted)

### **Performance Strategy**
- **Two-level caching:** extraction cache (database) + query result cache (Redis optional)
- **Sliding window rate limiting** for accuracy over speed
- **Async image processing** via queue to not block recipe saves
- **Pre-aggregated analytics** in extraction_analytics table

### **Scalability Plan**
- **Phase 1-3:** Single server with MySQL adequate for 1000+ users
- **Phase 4-5:** Add Redis for caching, connection pooling, read replicas
- **Phase 6+:** Microservices (extraction service, image service), CDN for images, horizontal scaling

### **Migration Strategy**
- **Start simple:** Manual SQL files executed by PHP script
- **Upgrade later:** Move to Phinx if team grows and needs advanced features
- **Track in database:** migrations table prevents double-execution

### **Security Approach**
- **Defense in depth:** Input validation, prepared statements, rate limiting, CSRF tokens
- **Fail securely:** Database errors don't expose SQL, always use prepared statements
- **Least privilege:** Database user has only necessary permissions
- **Logging:** All authentication attempts, rate limit hits, errors logged for audit

---

## **Implementation Timeline Summary**

| Phase | Duration | Focus | Key Deliverables |
|-------|----------|-------|------------------|
| 1 | Week 1 (7 days) | Infrastructure | Database layer, migrations, extraction logging |
| 2 | Week 2 (7 days) | Authentication | User accounts, login, recipe saving, collections |
| 3 | Week 3 (7 days) | Admin Tools | Dashboard, analytics, user management |
| 4 | Week 4 (7 days) | Performance | Caching, rate limiting, optimization |
| 5 | Weeks 5-6 (14 days) | Images | Storage, thumbnails, serving, optimization |
| 6 | Weeks 7-8+ (15+ days) | Advanced | Search, sharing, exports, API, polish |

**Total Estimated Time:** 8-10 weeks for all phases

---

## **Risk Assessment & Mitigation**

### **Potential Risks**

1. **Risk:** Database migration failures corrupt existing data
   - **Mitigation:** Always backup before migrations, use transactions, test on staging first

2. **Risk:** Image storage fills disk space quickly
   - **Mitigation:** Set storage quotas, implement automatic cleanup, monitor disk usage

3. **Risk:** Rate limiting too aggressive, frustrates users
   - **Mitigation:** Start with generous limits, monitor analytics, adjust based on usage patterns

4. **Risk:** Performance degrades with database writes on every extraction
   - **Mitigation:** Implement caching aggressively, make DB saves async where possible

5. **Risk:** Authentication system has security vulnerabilities
   - **Mitigation:** Use proven patterns (password_hash), implement rate limiting on login, add CSRF protection

6. **Risk:** Complex features (Phase 6) take longer than expected
   - **Mitigation:** Phases 1-5 provide complete working system, Phase 6 is optional enhancements

---

## **Success Metrics**

Track these metrics to measure implementation success:

- **Phase 1:** 100% of extractions logged to database
- **Phase 2:** >50% of extractions result in saved recipes
- **Phase 3:** Admin can view real-time analytics within 2 seconds
- **Phase 4:** Cache hit rate >40% within 1 week of deployment
- **Phase 5:** Images load <500ms for thumbnails
- **Phase 6:** Full-text search returns results <100ms

---

## **Next Steps**

1. **Review and approve** this implementation plan
2. **Set up development environment** (copy .env.example to .env, configure database credentials)
3. **Begin Phase 1** with database connection wrapper
4. **Schedule regular check-ins** to review progress after each phase
5. **Maintain this document** - update with actual timelines, blockers, and learnings

---

**Document Status:** Ready for Implementation  
**Last Updated:** February 10, 2026  
**Prepared By:** AI Planning Assistant
