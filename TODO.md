# CleanPlate - TODO List

## Priority Items

### Image Handling
- [ ] **Save images locally** - Download and store recipe images on the server
  - [ ] Download primary recipe image on extraction
  - [ ] Store images in `storage/images/` with hashed filenames
  - [ ] Fall back gracefully if remote image is unavailable
  - [ ] Serve local images via a public endpoint
  - [ ] Clean up orphaned images when recipes are deleted

### Text-Based Extraction
- [ ] **Extract recipe from arbitrary text** - Parse a recipe from any pasted or uploaded text block
  - [ ] Accept plain text, markdown, and basic HTML input
  - [ ] Reuse existing `RecipeParser` normalization pipeline
  - [ ] Expose a new API endpoint (`/api/parser.php?mode=text`)
  - [ ] Add UI textarea input alongside the URL field
  - [ ] Return the same structured JSON as URL-based extraction

### Instruction Processing
- [ ] **Post-process recipe steps** - Clean and enrich extracted instructions
  - [ ] Strip redundant whitespace, numbering artefacts, and HTML entities
  - [ ] Split run-together steps into discrete sentences
  - [ ] Detect and tag time references (e.g., "bake for 30 minutes")
  - [ ] Highlight temperature values for quick scanning
  - [ ] Optionally group steps into phases (Prep / Cook / Serve)

### Database & Storage
- [ ] **MySQL database integration** - Store extracted recipes with metadata
  - [ ] Design schema (recipes, ingredients, instructions, metadata, images)
  - [ ] Create migration scripts
  - [ ] Add database connection pooling
  - [ ] Implement CRUD operations for recipes
  
- [ ] **Recipe Collections** - User-organized recipe groups
  - [ ] Create collections table and relationships
  - [ ] Add UI for creating/managing collections
  - [ ] Support drag-and-drop recipe organization
  - [ ] Collection sharing capabilities

### AI/ML Features
- [ ] **Automatic keyword/tag generation** - AI-powered recipe categorization
  - [ ] Extract cuisine type (Italian, Mexican, Asian, etc.)
  - [ ] Identify meal type (breakfast, lunch, dinner, dessert)
  - [ ] Detect dietary tags (vegetarian, vegan, gluten-free, keto)
  - [ ] Generate cooking method tags (baked, grilled, slow-cooker)
  - [ ] Add difficulty level estimation
  - [ ] Extract main ingredients as tags

## Feature Enhancements

### Recipe Management
- [ ] **Recipe scaling** - Adjust ingredient quantities for different serving sizes
- [ ] **Shopping list aggregation** - Combine ingredients from multiple recipes
- [ ] **Recipe notes & modifications** - Save personal adjustments and cooking notes
- [ ] **Recipe versioning** - Track changes to edited recipes over time
- [ ] **Recipe duplication detection** - Identify if recipe already exists in database
- [ ] **Recipe comparison** - Side-by-side view of multiple recipes
- [ ] **Recipe rating system** - Star ratings and personal reviews

### Search & Discovery
- [ ] **Advanced search** - Filter by ingredients, cook time, difficulty, tags
- [ ] **Full-text search** - Search recipe titles, ingredients, instructions
- [ ] **Recipe recommendations** - Suggest similar recipes based on history
- [ ] **Tag-based filtering** - Browse recipes by multiple criteria
- [ ] **Recent recipes** - Quick access to recently extracted/viewed recipes

### Export & Sharing
- [ ] **Export to PDF** - Clean, printable recipe format
- [ ] **Export to JSON** - Machine-readable format for backup/migration
- [ ] **Export to formatted text** - Plain text with markdown formatting
- [ ] **Social sharing** - Share recipe links with preview cards
- [ ] **Email recipe** - Send recipe to email address
- [ ] **Recipe card generator** - Create image-based recipe cards for social media

### Import Capabilities
- [ ] **Bulk recipe import** - Process multiple URLs at once
- [ ] **Import from file** - Parse recipes from uploaded text/HTML files
- [ ] **Browser extension** - One-click extraction from any recipe page
- [ ] **Recipe book scanning** - OCR for physical cookbook pages

### Nutrition & Health
- [ ] **Nutritional information extraction** - Parse nutrition facts when available
- [ ] **Calorie calculation** - Estimate calories from ingredients
- [ ] **Allergen detection** - Identify common allergens in ingredients
- [ ] **Ingredient substitution suggestions** - Alternative ingredients (e.g., butter → oil)
- [ ] **Dietary compatibility checker** - Mark recipes that fit diet preferences

### User Experience
- [ ] **User accounts & authentication** - Login system for saved recipes
- [ ] **Dark mode** - Toggle between light/dark themes
- [ ] **Offline support** - PWA with service worker for offline access
- [ ] **Recipe cooking mode** - Step-by-step guided cooking with timers
- [ ] **Voice commands** - Hands-free navigation while cooking
- [ ] **Multiple language support** - Internationalization (i18n)
- [ ] **Responsive improvements** - Better tablet layout optimization
- [ ] **Accessibility enhancements** - Screen reader improvements, keyboard navigation

### Meal Planning
- [ ] **Weekly meal planner** - Calendar view for scheduling recipes
- [ ] **Grocery list generation** - Auto-generate shopping list from meal plan
- [ ] **Meal prep suggestions** - Batch cooking recommendations
- [ ] **Leftover tracking** - Track ingredients from previous recipes

## Bug Fixes

- [ ] Test extraction on sites with unusual JSON-LD structures
- [ ] Handle recipes with video-only instructions
- [ ] Improve error messages for rate-limited sites
- [ ] Fix decimal fraction conversion for edge cases (e.g., 0.125)
- [ ] Handle recipes with ingredient groups (e.g., "For the sauce:")
- [ ] Validate UTF-8 encoding for international recipes
- [ ] Test with extremely long ingredient lists (50+ items)
- [ ] Handle recipes split across multiple pages

## Performance Improvements

- [ ] **Caching layer** - Cache extracted recipes with Redis/Memcached
- [ ] **Database query optimization** - Index frequently searched fields
- [ ] **Image optimization** - Compress and resize recipe images on upload
- [ ] **Lazy loading** - Load images and content as needed
- [ ] **API response compression** - Gzip JSON responses
- [ ] **CDN integration** - Serve static assets from CDN
- [ ] **Rate limiting improvements** - Smarter throttling with backoff strategies
- [ ] **Parallel extraction** - Process multiple URLs simultaneously
- [ ] **Database connection pooling** - Reuse database connections efficiently

## Documentation

- [ ] Create API documentation with examples
- [ ] Add video tutorial for first-time users
- [ ] Document database schema with ER diagrams
- [ ] Create developer setup guide
- [ ] Add troubleshooting guide for common issues
- [ ] Document supported recipe sites (success rates)
- [ ] Create user guide for collections and organization
- [ ] Add inline code documentation with phpDoc/JSDoc
- [ ] Create changelog for version tracking

## Testing

- [ ] Unit tests for RecipeParser class methods
- [ ] Integration tests for API endpoints
- [ ] End-to-end tests for recipe extraction workflow
- [ ] Performance tests for database queries
- [ ] Load testing for concurrent users
- [ ] Test coverage reports with PHPUnit/Jest
- [ ] Automated testing in CI/CD pipeline
- [ ] Cross-browser testing (Safari, Firefox, Edge)
- [ ] Mobile device testing suite

## Security

- [ ] Implement rate limiting per user/IP
- [ ] Add CSRF protection for forms
- [ ] SQL injection prevention audit
- [ ] XSS vulnerability scanning
- [ ] Secure password hashing (bcrypt/Argon2)
- [ ] Session management improvements
- [ ] API authentication with JWT tokens
- [ ] Content Security Policy (CSP) headers

## Infrastructure

- [ ] Docker Compose production configuration
- [ ] Kubernetes deployment manifests
- [ ] Automated backup system for database
- [ ] Monitoring and logging with ELK stack
- [ ] Error tracking with Sentry
- [ ] Uptime monitoring
- [ ] Auto-scaling configuration for high traffic

## Future Ideas

- [ ] **Mobile native apps** - iOS and Android versions
- [ ] **Recipe AI assistant** - ChatGPT-powered cooking help
- [ ] **Ingredient inventory tracking** - Track what's in your pantry
- [ ] **Smart grocery integration** - Send list to Instacart/Amazon Fresh
- [ ] **Recipe contest/community** - User-submitted recipes
- [ ] **Cooking class integration** - Link recipes to video tutorials
- [ ] **Kitchen timer integration** - Multi-timer support with notifications
- [ ] **Recipe analytics** - Track most popular recipes, success rates
- [ ] **AR cooking assistant** - Augmented reality step-by-step guidance
- [ ] **Smart appliance integration** - Send recipes to smart ovens
- [ ] **Blockchain recipe attribution** - NFT-based recipe ownership
- [ ] **Recipe marketplace** - Buy/sell premium recipes
- [ ] **Collaborative cooking** - Real-time co-cooking sessions
- [ ] **Recipe game-ification** - Achievements, badges for variety

---

**Last Updated:** February 20, 2026  
**Project:** CleanPlate Recipe Extractor  
**Version:** 2.0.0
