# CleanPlate - Installation & Usage Manual

Complete guide from installation to power-user workflows.

---

## Feature Recap

CleanPlate allows you to:
- **Extract recipes from any URL** using intelligent two-phase parsing
- **Bypass bot-detection systems** on major recipe sites (AllRecipes, FoodNetwork, etc.)
- **Get clean, structured output** with automatic junk removal (navigation, headers, etc.)
- **View confidence scores** (0-100) to assess extraction reliability
- **Run comprehensive tests** in CLI or web browser with visual interface
- **Run locally or deploy to shared hosting** (no special server requirements)
- **Diagnose issues** with the built-in system check tool

---

## Installation

### Method A: Production Deployment (Recommended)

For Apache/Nginx servers or shared hosting:

1. **Clone the repository**:
   ```bash
   git clone https://github.com/AdamMoses-GitHub/CleanPlate.git
   cd CleanPlate
   ```

2. **Verify PHP requirements**:
   ```bash
   php -v  # Must be 7.4 or higher
   php -m | grep -E 'curl|dom|json|mbstring|libxml'
   ```
   All five extensions must be enabled.

3. **Set permissions** (Linux/macOS):
   ```bash
   chmod 755 api/parser.php tests/*.php
   chmod 644 includes/*.php
   ```

4. **Configure web server**:
   - **Apache**: `.htaccess` files are included (no additional config needed)
   - **Nginx**: Add this to your `server` block:
     ```nginx
     location ~ ^/includes/ {
         deny all;
         return 403;
     }
     ```

5. **Test the deployment**:
   - Navigate to `http://your-domain.com/tests/system-check.php`
   - Verify all checks pass (green indicators)

### Method B: Quick Local Development

For rapid testing on your machine:

1. **Clone & navigate**:
   ```bash
   git clone https://github.com/AdamMoses-GitHub/CleanPlate.git
   cd CleanPlate
   ```

2. **Start PHP built-in server**:
   ```bash
   php -S localhost:8080 -t public
   ```

3. **Open in browser**:
   - System check: `http://localhost:8080/tests/system-check.php`
   - Main app: `http://localhost:8080/`

**Note**: The built-in server is NOT suitable for production (single-threaded, no caching).

---

## Usage - Execution

### Web Interface (Recommended)

1. **Launch the app**:
   - Local: `http://localhost:8080/`
   - Production: `https://your-domain.com/`

2. **Extract a recipe**:
   - Paste a recipe URL (e.g., `https://www.allrecipes.com/recipe/12345/`)
   - Click "Extract Recipe"
   - View the formatted output

3. **Understand the feedback**:
   - **Toast notification** ("Using deep-scan mode"): Phase 2 (DOM fallback) was used
   - **Error messages**: Red box with actionable suggestions (see scenarios below)

### API Endpoint (Programmatic Access)

Send POST requests to `api/parser.php`:

**Request**:
```bash
curl -X POST http://localhost:8080/api/parser.php \
  -H "Content-Type: application/json" \
  -d '{"url": "https://www.example.com/recipe"}'
```

**Success Response** (HTTP 200):
```json
{
  "status": "success",
  "phase": 1,
  "data": {
    "title": "Chocolate Chip Cookies",
    "source": {
      "url": "https://www.example.com/recipe",
      "siteName": "www.example.com"
    },
    "ingredients": [
      "2 cups all-purpose flour",
      "1 cup butter, softened",
      "3/4 cup sugar"
    ],
    "instructions": [
      "Preheat oven to 375°F",
      "Mix butter and sugar until fluffy",
      "Bake for 10-12 minutes"
    ],
    "metadata": {
      "prepTime": "15 minutes",
      "cookTime": "12 minutes",
      "servings": "24 cookies"
    }
  },
  "timestamp": "2026-02-06T10:30:00Z"
}
```

**Error Response** (HTTP 4xx/5xx):
```json
{
  "status": "error",
  "code": "NO_RECIPE_FOUND",
  "userMessage": "No recipe content detected on this page.",
  "suggestions": [
    "Make sure the URL points to a recipe page",
    "Try clicking 'Jump to Recipe' and using that URL"
  ],
  "timestamp": "2026-02-06T10:30:00Z"
}
```

---

## Usage - Workflows

### Scenario 1: Standard Recipe Extraction

**Problem**: You found a recipe on AllRecipes but want it in a clean format.

**Workflow**:
1. Copy the recipe page URL from your browser
2. Open CleanPlate in a new tab
3. Paste the URL into the input field
4. Click "Extract Recipe"
5. View the formatted recipe with ingredients and instructions

**Example Use Case**: You're meal-prepping and need 5 recipes from different sites. Instead of keeping 5 browser tabs open, extract all recipes into CleanPlate and print/save them in one clean format.

---

### Scenario 2: Handling Cloudflare-Protected Sites

**Problem**: The site returns "Cloudflare protection detected" error.

**Workflow**:
1. Read the error suggestions in the red error box
2. Open the recipe URL in your browser manually
3. Look for a "Print Recipe" or "Jump to Recipe" button
4. Copy the URL from the print-friendly page
5. Try extracting from that URL instead

**Example Use Case**: FoodNetwork has Cloudflare enabled. You visit the page, click "Print Recipe" which opens `https://www.foodnetwork.com/recipes/.../print`, and extract from that URL (print pages often bypass protection).

---

### Scenario 3: JavaScript-Heavy Recipe Sites

**Problem**: The site loads recipes dynamically via JavaScript (error: "JAVASCRIPT_REQUIRED").

**Workflow**:
1. Recognize that CleanPlate cannot execute JavaScript
2. Check the error suggestions for alternatives
3. **Option A**: Look for a static version (e.g., AMP or mobile URL)
4. **Option B**: Manually copy ingredients/instructions from your browser
5. **Option C**: Use browser DevTools to find the JSON-LD data manually (Network tab)

**Example Use Case**: A modern SPA-based recipe site that loads content via AJAX. You open DevTools, find the JSON-LD script tag in the rendered HTML, copy the JSON, and validate it manually.

---

### Scenario 4: Testing Extraction on Multiple Sites

**Problem**: You're a developer integrating CleanPlate and need to test 20 different recipe sites.

**Workflow**:
1. Create a text file with one URL per line (`test-urls.txt`)
2. Write a bash loop to test all URLs:
   ```bash
   while read url; do
     echo "Testing: $url"
     curl -s -X POST http://localhost:8080/api/parser.php \
       -H "Content-Type: application/json" \
       -d "{\"url\": \"$url\"}" | jq '.status'
   done < test-urls.txt
   ```
3. Review the output for success/failure patterns
4. Investigate failures by checking error codes

**Example Use Case**: You're building a recipe aggregator. You test CleanPlate against the top 50 recipe sites, log success rates, and decide which sites to add fallback handling for.

---

### Scenario 5: Rate Limit Handling

**Problem**: You extracted 10 recipes and now get "RATE_LIMIT" errors.

**Workflow**:
1. Note the rate limit message: "Too many requests. Please wait a moment."
2. Wait 60 seconds (rate limit resets)
3. Continue extracting
4. **For automation**: Implement a 6-second delay between requests (10 per minute)

**Example Use Case**: You're batch-extracting recipes for a cookbook. Your script hits the rate limit. You add `sleep 6` between curl requests to stay under 10 req/min.

---

## Development

### Project Structure

```
cleanplate/
├── LICENSE                    # MIT license
├── README.md                  # Marketing overview
├── INSTALL_AND_USAGE.md       # This file
├── api/
│   └── parser.php             # JSON API endpoint (255 lines)
│                              # - Rate limiting (session-based)
│                              # - SSRF protection
│                              # - Error mapping
├── includes/
│   ├── RecipeParser.php       # Core extraction engine (952 lines)
│   │                          # - fetchUrl(): cURL with bot-evasion headers
│   │                          # - extractFromJsonLd(): Phase 1 parser
│   │                          # - extractFromDom(): Phase 2 DOM scraper
│   │                          # - normalizeRecipeData(): Format converter
│   └── IngredientFilter.php   # Post-processing filter (411 lines)
│                              # - Pattern-based junk removal
│                              # - Navigation/header detection
│                              # - Quality ratio tracking
├── public/
│   ├── index.html             # Main UI (73 lines)
│   │                          # - Form input
│   │                          # - Recipe card display
│   │                          # - Error/toast notifications
│   ├── css/
│   │   └── style.css          # Warm paper aesthetic (373 lines)
│   │                          # - CSS custom properties for theming
│   │                          # - Serif headings, sans-serif body
│   │                          # - Paper texture background
│   └── js/
│       └── app.js             # Client logic (300 lines)
│                              # - RecipeState object (state management)
│                              # - CleanPlateAPI class (fetch wrapper)
│                              # - DOM rendering functions
└── tests/
    ├── index.html             # Visual test suite interface (345 lines)
    ├── system-check.php       # Diagnostic tool (508 lines)
    │                          # - PHP version check
    │                          # - Extension verification
    │                          # - File permission tests
    ├── test-scraper.php       # Recipe extraction test harness (322 lines)
    ├── test-confidence-scoring.php  # Confidence algorithm tests (603 lines)
    ├── test-ingredient-filter.php   # Post-processing filter tests (481 lines)
    └── test-security.php      # Security validation tests (248 lines)
```

### Key Directories

- **`api/`**: JSON endpoint for recipe extraction. Contains `parser.php` with rate limiting and SSRF protection.
- **`includes/`**: Core backend classes. `RecipeParser.php` (two-phase waterfall) and `IngredientFilter.php` (post-processing).
- **`public/`**: Static frontend files (HTML/CSS/JS). Safe to expose to the internet.
- **`tests/`**: Test suite with dual CLI/web execution. Includes visual interface at `tests/index.html`.

### Tests & Style
Visual test suite** (recommended):
```bash
php -S localhost:8080
# Open: http://localhost:8080/tests/
```
The test suite interface provides cards for all available tests with "Run in Browser" buttons.

**CLI mode** (for automation):
```bash
php tests/system-check.php                           # System diagnostics
php tests/test-scraper.php "https://example.com"     # Recipe extraction
php tests/test-confidence-scoring.php                # Confidence algorithm
php tests/test-ingredient-filter.php                 # Post-processing filter
php tests/test-security.php                          # Security validation
```

**Web mode** (for visual debugging):
```bash
# Start server: php -S localhost:8080
http://localhost:8080/tests/system-check.php
http://localhost:8080/tests/test-scraper.php?url=https://example.com
http://localhost:8080/tests/test-confidence-scoring.php
http://localhost:8080/tests/test-ingredient-filter.php?debug=1
http://localhost:8080/tests/test-security.php
```

All test files support both CLI and web execution with automatic detection. Use `--debug` flag (CLI) or `?debug=1` parameter (web) for verbose output.

**Code style guidelines**:
- PHP: PSR-2 (implied, not enforced)
- JavaScript: ES6+ features, semicolon-terminated
- CSS: BEM-like naming (`.recipe-card`, `.input-group`)

---

## Requirements

### Core Dependencies
- **PHP 7.4+** (tested on 7.4, 8.0, 8.1, 8.2)
- **cURL extension** (for HTTP requests with advanced headers)
- **DOM extension** (for Phase 2 HTML parsing)
- **JSON extension** (for JSON-LD decoding and API responses)
- **mbstring extension** (for UTF-8 encoding conversion)
- **libxml extension** (for DOM/XPath support)

### Optional (but recommended)
- **Apache mod_rewrite** (for `.htaccess` protection of `includes/`)
- **Session support** (for rate limiting; enabled by default)

### Browser Compatibility (Frontend)
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

(Uses standard Fetch API, CSS Grid, and ES6 features.)

---

## Troubleshooting

### "Call to undefined function curl_init()"
**Cause**: cURL extension is not enabled.  
**Fix**: Edit `php.ini`, uncomment `extension=curl`, restart web server.

### "Class 'DOMDocument' not found"
**Cause**: DOM extension is not installed.  
**Fix**: Install via package manager (e.g., `apt install php-xml` on Ubuntu).

### "Access denied by website (HTTP 403)"
**Cause**: The site is blocking automated access.  
**Fix**: Try the print-friendly version of the page, or manually copy the recipe.

### "No recipe found on this page"
**Cause**: URL points to a blog post, not a recipe.  
**Fix**: Scroll to the recipe card on the page, click "Jump to Recipe", use that URL.

### Rate limits hit too quickly
**Cause**: Testing too aggressively (>10 req/min).  
**Fix**: Clear sessions (`session_destroy()`) or wait 60 seconds.

---

## Advanced Configuration

### Adjust Rate Limits

Edit [api/parser.php](api/parser.php) (lines 73-74):
```php
$rateLimit = 20;  // requests (default: 10)
$ratePeriod = 60; // seconds (default: 60)
```

### Adjust Per-Domain Delays

Edit [includes/RecipeParser.php](includes/RecipeParser.php) (line 9):
```php
const MIN_REQUEST_DELAY = 5;  // seconds between same-domain requests (default: 2)
```

### Customize User-Agent Pool

Edit [includes/RecipeParser.php](includes/RecipeParser.php) (lines 13-19):
```php
private $userAgents = [
    'Your custom user-agent string here',
    // Add more...
];
```

### Disable SSRF Protection (NOT recommended)

Comment out lines 60-66 in [api/parser.php](api/parser.php):
```php
// if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
//     respondWithError(403, 'FORBIDDEN', 'Cannot access internal or private network addresses.');
// }
```

---

## FAQ

**Q: Can I run this on shared hosting?**  
A: Yes, CleanPlate is designed for shared hosting. Just ensure PHP 7.4+ and required extensions are available.

**Q: Does it work with paywalled recipes?**  
A: No. CleanPlate cannot bypass login walls or subscriptions.

**Q: Why not use Selenium/Puppeteer for JavaScript sites?**  
A: Performance. CleanPlate is lightweight (zero dependencies) and designed for speed, not rendering full browser sessions.

**Q: Can I use this commercially?**  
A: Yes. MIT license allows commercial use with attribution.

**Q: Does it respect robots.txt?**  
A: No. CleanPlate does not check `robots.txt`. Use responsibly and respect site terms of service.

---

## See Also

- **Main README**: [README.md](README.md)
- **License**: [LICENSE](LICENSE)
- **Repository**: [https://github.com/AdamMoses-GitHub/CleanPlate](https://github.com/AdamMoses-GitHub/CleanPlate)

---

*Last updated: February 6, 2026*
