# Image Carousel Implementation

## Overview
Added an intelligent image selection carousel feature that extracts multiple recipe image candidates from websites and allows users to choose their preferred image.

## Implementation Date
February 2026

## Feature Components

### 1. Backend Image Extraction (RecipeParser.php)

**New Methods:**
- `extractImageCandidates($html, $url, $primaryImage)` - Extracts top 3 recipe images
- `scoreImage($src, $alt, $isPrimary)` - Scores images based on quality indicators
- `deduplicateImages($candidates)` - Removes duplicate URLs
- `normalizeImageUrl($src, $baseUrl)` - Resolves relative URLs to absolute

**Scoring Algorithm:**
- Primary/structured data image: 100 points
- Recipe-related keywords in filename: +30 points
- Descriptive alt text: +20 points
- Large dimensions (>600px): +15 points
- Minimum threshold: 60 points

**Metadata Structure:**
```json
{
  "imageCandidates": [
    {
      "url": "https://example.com/image.jpg",
      "score": 100,
      "source": "structured-data|dom",
      "alt": "Recipe image description"
    }
  ]
}
```

### 2. Frontend HTML Structure (index.html)

**Carousel Container:**
- Header with title and hint text
- Image display with overlay counter (e.g., "2 / 3")
- Previous/Next navigation buttons with SVG icons
- "Use This Image" confirmation button

### 3. JavaScript Logic (app.js)

**ImageCarousel Class:**
- **Properties:**
  - `images[]` - Array of image candidates
  - `currentIndex` - Currently displayed image index
  - `recipeUrl` - Source URL for localStorage key generation
  
- **Methods:**
  - `init(imageCandidates, recipeUrl)` - Initialize carousel with images
  - `show()` - Display carousel
  - `hide()` - Hide carousel
  - `next()` - Navigate to next image
  - `prev()` - Navigate to previous image
  - `selectCurrent()` - Confirm image selection
  - `updateDisplay()` - Update UI (image, counter, buttons)
  
- **Local Storage:**
  - Saves user preferences per recipe URL (30-day expiration)
  - Key format: `cleanplate_image_{urlHash}`
  - Stored data: `{index, url, timestamp}`
  
- **Keyboard Support:**
  - Left arrow: Previous image
  - Right arrow: Next image
  - Enter: Confirm selection

### 4. CSS Styling (style.css)

**Design Features:**
- Gradient purple background (primary color to #6366f1)
- White circular navigation buttons (3rem diameter)
- Hover effects: scale(1.1) with enhanced shadow
- Disabled state: 40% opacity for boundary buttons
- Image counter: bottom-right overlay with blur backdrop
- Responsive mobile design (smaller buttons, stacked layout)

**Responsive Breakpoints:**
- Desktop: Full carousel with 3rem navigation buttons
- Mobile (<768px): Compact 2.5rem buttons, reduced padding

## User Flow

### Scenario 1: Multiple Images Available
1. User submits recipe URL
2. Backend extracts 3 image candidates (scored 100, 95, 85)
3. Frontend displays carousel above recipe content
4. User navigates through images using arrows or keyboard
5. User clicks "Use This Image" to confirm selection
6. Selected image replaces carousel and displays as main image
7. Preference saved to localStorage for future visits

### Scenario 2: Single Image
1. Only 1 image candidate found
2. Carousel skipped, image displayed directly
3. No user interaction needed

### Scenario 3: No Images
1. No image candidates found
2. Both carousel and image container hidden
3. Recipe displays without image

## Testing

### Test File
`tests/test-image-candidates.php` - Validates image extraction with sample URLs

### Test URLs
- AllRecipes: https://www.allrecipes.com/recipe/10813/best-chocolate-chip-cookies
- Bon Appétit: https://www.bonappetit.com/recipe/bas-best-chocolate-chip-cookies

### Expected Behavior
- 3 image candidates extracted per recipe
- Scores range from 60-100 points
- Duplicates removed (same URL with different query params)
- Relative URLs converted to absolute
- Carousel appears only when 2+ candidates available

## Browser Compatibility
- Modern browsers with ES6 support (Chrome 51+, Firefox 54+, Safari 10+)
- localStorage required for preference persistence
- Flexbox and CSS Grid for layout

## Future Enhancements
1. **Image Lazy Loading** - Load carousel images on-demand
2. **Swipe Gestures** - Touch support for mobile navigation
3. **Image Preview** - Thumbnail strip below main image
4. **Source Attribution** - Show image dimensions and source domain
5. **User Analytics** - Track which images users prefer
6. **AI Quality Scoring** - Use image recognition for better scoring

## Performance Considerations
- Maximum 3 images extracted to limit memory/bandwidth
- Images scored and sorted before sending to frontend
- localStorage used instead of database queries
- Carousel only initialized when needed (2+ candidates)

## Accessibility
- ARIA labels on navigation buttons ("Previous image", "Next image")
- Alt text preserved from original images
- Keyboard navigation supported
- High contrast between text and background
- Focus states visible on interactive elements

## Related Files
- **Backend:** `includes/RecipeParser.php` (image extraction logic)
- **API:** `api/parser.php` (passes imageCandidates in response)
- **Frontend JS:** `public/js/app.js` (ImageCarousel class)
- **Frontend HTML:** `public/index.html` (carousel markup)
- **Styles:** `public/css/style.css` (carousel CSS)
- **Tests:** `tests/test-image-candidates.php` (validation)

## Implementation Phases
- ✅ Phase 1: Backend image extraction (4 methods, 168 lines)
- ✅ Phase 2: API response verification
- ✅ Phase 3: Frontend HTML structure
- ✅ Phase 4: JavaScript ImageCarousel class
- ✅ Phase 5: CSS styling with responsive design
- ✅ Phase 6: Integration testing and edge case handling
- ✅ Phase 7: Documentation

## Total Code Added
- **PHP:** ~200 lines (RecipeParser.php)
- **JavaScript:** ~160 lines (app.js)
- **HTML:** ~35 lines (index.html)
- **CSS:** ~145 lines (style.css)
- **Total:** ~540 lines of production code

---

**Status:** Production Ready ✅  
**Last Updated:** February 7, 2026
