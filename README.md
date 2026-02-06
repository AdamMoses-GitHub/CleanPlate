# CleanPlate

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg) ![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4.svg?logo=php) ![License](https://img.shields.io/badge/license-MIT-green.svg)

*Because copying recipes from blogs with 17 popups and a life story is like peeling an onion while being interviewed about your childhood.*

![App Screenshot](./screenshot.jpg)

## About

The **pain**: You found the perfect recipe online. Now you have to scroll past ads, video players, SEO keyword salad, and someone's memoir about their grandmother just to find out if you need eggs.

The **solution**: CleanPlate extracts the actual recipe—ingredients and instructions—from any website in seconds. No ads, no stories, no nonsense. Just the food.

**Repository**: [https://github.com/AdamMoses-GitHub/CleanPlate](https://github.com/AdamMoses-GitHub/CleanPlate)

---

## What It Does

### The Main Features
- **Extract recipes from any URL**: Paste a link, get a clean recipe
- **Two-phase "Waterfall" extraction**: Tries structured data first, falls back to smart DOM scraping
- **Works on protected sites**: Built-in bot-detection evasion gets past most blocks
- **Clean, readable interface**: "Warm paper" aesthetic—like a cookbook, not a dashboard
- **Instant results**: No loading spinners that make you question reality
- **Actionable error messages**: When it fails, it tells you why and what to try next

### The Nerdy Stuff
- **JSON-LD structured data parsing** (Phase 1) with 95%+ accuracy on modern recipe sites
- **Heuristic DOM fallback** (Phase 2) for legacy/non-compliant sites
- **Anti-bot measures**: User-agent rotation, HTTP/2, cookie persistence, referer spoofing
- **SSRF protection**: Blocks internal/private IP ranges
- **Session-based rate limiting**: 10 requests/minute, 2-second per-domain delays
- **Zero external dependencies**: Pure PHP + vanilla JavaScript

---

## Quick Start (TL;DR)

**Full instructions**: See [INSTALL_AND_USAGE.md](INSTALL_AND_USAGE.md)

```bash
# Clone the repo
git clone https://github.com/AdamMoses-GitHub/CleanPlate.git
cd CleanPlate

# Run system check (optional but recommended)
php -S localhost:8080
# Open: http://localhost:8080/system-check.php

# Launch the app
# Open: http://localhost:8080/public/index.html
# Paste a recipe URL, click Extract
```

---

## Tech Stack

| Component | Purpose | Why This One |
|-----------|---------|--------------|
| **PHP 7.4+** | Backend parser & API | Native DOM/cURL support, fast on shared hosting |
| **DOMDocument/XPath** | Phase 2 DOM scraping | Built-in, no dependencies, handles malformed HTML |
| **cURL** | HTTP fetching with evasion | Advanced header control, cookie persistence, HTTP/2 |
| **Vanilla JavaScript** | Frontend state management | 0 dependencies, <10KB, works everywhere |
| **CSS Grid/Flexbox** | Responsive layout | Modern, no Bootstrap bloat |

---

## Project Structure

```
cleanplate/
├── parser.php                 # JSON API endpoint (rate limiting, SSRF protection)
├── system-check.php           # Diagnostic tool (verify PHP extensions, permissions)
├── test-scraper.php           # Test harness for recipe extraction
├── includes/
│   └── RecipeParser.php       # Core extraction logic (2-phase waterfall)
└── public/
    ├── index.html             # Main UI
    ├── css/
    │   └── style.css          # Warm paper aesthetic (serif headings, sans body)
    └── js/
        └── app.js             # Client-side state management & API calls
```

---

## License

MIT License. See [LICENSE](LICENSE) for details.

## Contributing

PRs welcome. Keep it simple, keep it fast, keep it dependency-free.

---

<sub>recipe scraper, recipe parser, web scraping, PHP recipe extractor, JSON-LD parser, DOM scraping, anti-bot evasion, recipe extraction API, cloudflare bypass, structured data scraping, recipe card generator, food blog parser, cooking app, ingredient extractor, instruction parser, PHP web scraper, clean recipe interface, recipe converter, food site scraper, recipe aggregator</sub>