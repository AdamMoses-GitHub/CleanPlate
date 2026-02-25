<?php
/**
 * Test Suite for IngredientFilter
 * 
 * Tests post-processing filtering of ingredients and instructions
 * to remove navigation items, section headers, and other junk.
 * 
 * Usage: 
 *   CLI: php test-ingredient-filter.php [--debug]
 *   Web: http://localhost:8080/tests/test-ingredient-filter.php?debug=1
 * 
 * @version 1.0.0
 * @date 2026-02-06
 */

require_once __DIR__ . '/../../includes/IngredientFilter.php';

// Detect execution mode
$isCLI = php_sapi_name() === 'cli';

// Parse arguments (CLI or web)
$debugMode = $isCLI 
    ? in_array('--debug', $argv ?? [], true)
    : isset($_GET['debug']);

// Set headers for web mode
if (!$isCLI) {
    header('Content-Type: text/html; charset=utf-8');
}

// ANSI color codes (CLI only)
if ($isCLI) {
    define('COLOR_RESET', "\033[0m");
    define('COLOR_BOLD', "\033[1m");
    define('COLOR_GREEN', "\033[32m");
    define('COLOR_RED', "\033[31m");
    define('COLOR_YELLOW', "\033[33m");
    define('COLOR_CYAN', "\033[36m");
    define('COLOR_GRAY', "\033[90m");
} else {
    // Empty strings for web mode
    define('COLOR_RESET', '');
    define('COLOR_BOLD', '');
    define('COLOR_GREEN', '');
    define('COLOR_RED', '');
    define('COLOR_YELLOW', '');
    define('COLOR_CYAN', '');
    define('COLOR_GRAY', '');
}

// Output start for web mode
if (!$isCLI) {
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingredient Filter Test Suite</title>
    <style>
        body { font-family: "Courier New", monospace; background: #1e1e1e; color: #d4d4d4; padding: 2rem; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; }
        .pass { color: #4ec9b0; }
        .fail { color: #f48771; }
        .header { color: #4fc1ff; font-weight: bold; border-bottom: 2px solid #4fc1ff; padding-bottom: 0.5rem; margin: 1.5rem 0 1rem 0; }
        .detail { color: #808080; }
        .summary { background: #252526; padding: 1rem; border-radius: 4px; margin-top: 2rem; }
        .summary strong { color: #4fc1ff; }
        pre { background: #252526; padding: 1rem; border-radius: 4px; overflow-x: auto; }
        a { color: #4fc1ff; }
    </style>
</head>
<body>
<div class="container">
<pre>';
}

/**
 * Print a section header
 */
function printHeader($title) {
    echo "\n";
    echo COLOR_BOLD . COLOR_CYAN . str_repeat("═", 80) . COLOR_RESET . "\n";
    echo COLOR_BOLD . COLOR_CYAN . " " . $title . COLOR_RESET . "\n";
    echo COLOR_BOLD . COLOR_CYAN . str_repeat("═", 80) . COLOR_RESET . "\n";
    echo "\n";
}

/**
 * Print a test result
 */
function printTest($testName, $passed, $details = '') {
    $status = $passed ? COLOR_GREEN . "✓ PASS" : COLOR_RED . "✗ FAIL";
    echo "  " . $status . COLOR_RESET . " " . $testName;
    if ($details) {
        echo COLOR_GRAY . " (" . $details . ")" . COLOR_RESET;
    }
    echo "\n";
}

/**
 * Test ingredient filtering
 */
function testIngredientFilter($filter, $items, $expectedValid, $testName) {
    $filtered = $filter->filterIngredients($items);
    $passed = count($filtered) === $expectedValid;
    
    $details = count($filtered) . "/" . count($items) . " items kept";
    printTest($testName, $passed, $details);
    
    if (!$passed) {
        echo COLOR_RED . "    Expected: " . $expectedValid . " items" . COLOR_RESET . "\n";
        echo COLOR_RED . "    Got: " . count($filtered) . " items" . COLOR_RESET . "\n";
        echo COLOR_GRAY . "    Filtered items: " . json_encode($filtered) . COLOR_RESET . "\n";
    }
    
    return $passed;
}

/**
 * Test instruction filtering
 */
function testInstructionFilter($filter, $items, $expectedValid, $testName) {
    $filtered = $filter->filterInstructions($items);
    $passed = count($filtered) === $expectedValid;
    
    $details = count($filtered) . "/" . count($items) . " items kept";
    printTest($testName, $passed, $details);
    
    if (!$passed) {
        echo COLOR_RED . "    Expected: " . $expectedValid . " items" . COLOR_RESET . "\n";
        echo COLOR_RED . "    Got: " . count($filtered) . " items" . COLOR_RESET . "\n";
        echo COLOR_GRAY . "    Filtered items: " . json_encode($filtered) . COLOR_RESET . "\n";
    }
    
    return $passed;
}

// Start tests
echo COLOR_BOLD . "\nIngredientFilter Test Suite\n" . COLOR_RESET;
echo "Debug mode: " . ($debugMode ? "ON" : "OFF") . "\n";

$totalTests = 0;
$passedTests = 0;

// ============================================================================
// TEST SECTION 1: Navigation/UI Items
// ============================================================================
printHeader("Test Section 1: Navigation and UI Elements");

$filter = new IngredientFilter(IngredientFilter::STRICTNESS_BALANCED, $debugMode);

$navigationItems = [
    "Home",
    "Recipes",
    "Search",
    "Back to recipes",
    "Share on Facebook",
    "Print Recipe",
    "Save Recipe",
    "View all recipes",
    "Subscribe to newsletter",
    "Follow us on Instagram"
];

$totalTests++;
if (testIngredientFilter($filter, $navigationItems, 0, "All navigation items rejected")) {
    $passedTests++;
}

// ============================================================================
// TEST SECTION 2: Section Headers
// ============================================================================
printHeader("Test Section 2: Section Headers");

$headers = [
    "Ingredients:",
    "Ingredients",
    "INGREDIENTS",
    "Directions:",
    "Instructions:",
    "Method",
    "Notes:",
    "Equipment:",
    "You'll need:",
    "For the sauce:",
    "For serving:",
    "Optional:"
];

$totalTests++;
if (testIngredientFilter($filter, $headers, 0, "All section headers rejected")) {
    $passedTests++;
}

// ============================================================================
// TEST SECTION 3: Empty and Junk Content
// ============================================================================
printHeader("Test Section 3: Empty and Junk Content");

$junkItems = [
    "",
    "   ",
    "a",
    "ab",
    "...",
    "---",
    "– – –",
    "***"
];

$totalTests++;
if (testIngredientFilter($filter, $junkItems, 0, "All junk content rejected")) {
    $passedTests++;
}

// ============================================================================
// TEST SECTION 4: All Caps Headers
// ============================================================================
printHeader("Test Section 4: All Caps Single Words");

$capsItems = [
    "INGREDIENTS",
    "DIRECTIONS",
    "PREPARATION",
    "METHOD",
    "NOTES",
    "EQUIPMENT"
];

$totalTests++;
if (testIngredientFilter($filter, $capsItems, 0, "All caps single words rejected")) {
    $passedTests++;
}

// ============================================================================
// TEST SECTION 5: Valid Ingredients
// ============================================================================
printHeader("Test Section 5: Valid Ingredients");

$validIngredients = [
    "2 cups all-purpose flour",
    "1 teaspoon salt",
    "3 large eggs, beaten",
    "1/2 cup milk",
    "4 tablespoons butter, melted",
    "1 pound chicken breast, diced",
    "2 cloves garlic, minced",
    "Fresh parsley for garnish",
    "Salt and pepper to taste",
    "Olive oil"
];

$totalTests++;
if (testIngredientFilter($filter, $validIngredients, 10, "All valid ingredients kept")) {
    $passedTests++;
}

// ============================================================================
// TEST SECTION 6: Mixed Valid and Invalid
// ============================================================================
printHeader("Test Section 6: Mixed Valid and Invalid");

$mixedItems = [
    "INGREDIENTS",                      // Header (reject)
    "2 cups flour",                     // Valid
    "Ingredients:",                     // Header (reject)
    "1 teaspoon salt",                  // Valid
    "Home",                             // Navigation (reject)
    "3 eggs",                           // Valid
    "Share",                            // Navigation (reject)
    "1 cup milk",                       // Valid
    "For the sauce:",                   // Header (reject)
    "2 tablespoons butter",             // Valid
    "Optional:",                        // Header (reject)
    "Fresh herbs",                      // Valid
];

$totalTests++;
if (testIngredientFilter($filter, $mixedItems, 6, "Only valid ingredients kept from mixed list")) {
    $passedTests++;
}

// ============================================================================
// TEST SECTION 7: Valid Instructions
// ============================================================================
printHeader("Test Section 7: Valid Instructions");

$validInstructions = [
    "Preheat oven to 375°F.",
    "Mix flour, salt, and baking powder in a large bowl.",
    "In a separate bowl, beat eggs and milk together.",
    "Pour wet ingredients into dry ingredients and stir until just combined.",
    "Heat butter in a large skillet over medium heat.",
    "Add chicken and cook until browned, about 5-7 minutes.",
    "Stir in garlic and cook for 1 minute more.",
    "Season with salt and pepper to taste.",
    "Serve immediately, garnished with fresh parsley."
];

$totalTests++;
if (testInstructionFilter($filter, $validInstructions, 9, "All valid instructions kept")) {
    $passedTests++;
}

// ============================================================================
// TEST SECTION 8: Invalid Instructions
// ============================================================================
printHeader("Test Section 8: Invalid Instructions");

$invalidInstructions = [
    "DIRECTIONS",                       // Header
    "Method:",                          // Header
    "Home",                             // Navigation
    "Print",                            // Navigation
    "Share this recipe",                // Navigation
    "Subscribe"                         // Navigation
];

$totalTests++;
if (testInstructionFilter($filter, $invalidInstructions, 0, "All invalid instructions rejected")) {
    $passedTests++;
}

// ============================================================================
// TEST SECTION 9: Edge Cases
// ============================================================================
printHeader("Test Section 9: Edge Cases");

$edgeCases = [
    "1",                                // Too short
    "12",                               // Too short
    "100",                              // Just numbers, but too short
    "1 cup",                            // Valid (has measurement)
    "Salt",                             // Valid (common ingredient, single word but lowercase)
    "SALT",                             // All caps single word (reject)
    "salt and pepper",                  // Valid (multiple words)
    "1/2",                              // Too short
    "1/2 cup",                          // Valid
    "2-3 cups",                         // Valid
];

$totalTests++;
if (testIngredientFilter($filter, $edgeCases, 5, "Edge cases handled correctly")) {
    $passedTests++;
}

// ============================================================================
// TEST SECTION 10: Strictness Levels
// ============================================================================
printHeader("Test Section 10: Strictness Levels");

$borderlineItems = [
    "oil",                              // Short but valid food word
    "water",                            // Short but valid food word
    "Garnish",                          // Single word, could be instruction or ingredient
    "Fresh herbs",                      // Valid
    "To taste",                         // Phrase, not really an ingredient
];

// Lenient mode - should keep most items
$lenientFilter = new IngredientFilter(IngredientFilter::STRICTNESS_LENIENT, $debugMode);
$lenientCount = count($lenientFilter->filterIngredients($borderlineItems));

// Balanced mode - should filter some
$balancedFilter = new IngredientFilter(IngredientFilter::STRICTNESS_BALANCED, $debugMode);
$balancedCount = count($balancedFilter->filterIngredients($borderlineItems));

// Strict mode - should filter more
$strictFilter = new IngredientFilter(IngredientFilter::STRICTNESS_STRICT, $debugMode);
$strictCount = count($strictFilter->filterIngredients($borderlineItems));

$totalTests++;
$strictnessWorks = ($lenientCount >= $balancedCount) && ($balancedCount >= $strictCount);
printTest(
    "Strictness levels work correctly",
    $strictnessWorks,
    "Lenient: $lenientCount, Balanced: $balancedCount, Strict: $strictCount"
);
if ($strictnessWorks) {
    $passedTests++;
}

// ============================================================================
// TEST SECTION 11: Quality Ratio
// ============================================================================
printHeader("Test Section 11: Quality Ratio Calculation");

$originalIngredients = [
    "INGREDIENTS",                      // Invalid
    "2 cups flour",                     // Valid
    "Home",                             // Invalid
    "1 teaspoon salt",                  // Valid
    "3 eggs",                           // Valid
    "Share",                            // Invalid
    "1 cup milk",                       // Valid
];

$filtered = $filter->filterIngredients($originalIngredients);
$ratio = $filter->getQualityRatio($originalIngredients, $filtered);

$totalTests++;
$expectedRatio = 4 / 7; // 4 valid out of 7 total
$ratioCorrect = abs($ratio - $expectedRatio) < 0.01;
printTest(
    "Quality ratio calculated correctly",
    $ratioCorrect,
    number_format($ratio * 100, 1) . "% quality"
);
if ($ratioCorrect) {
    $passedTests++;
}

// ============================================================================
// TEST SECTION 12: Filtered Items Tracking
// ============================================================================
printHeader("Test Section 12: Filtered Items Tracking");

$testFilter = new IngredientFilter(IngredientFilter::STRICTNESS_BALANCED, true);
$testItems = [
    "INGREDIENTS",                      // Header
    "2 cups flour",                     // Valid
    "Home",                             // Navigation
    "1 teaspoon salt",                  // Valid
];

$testFilter->filterIngredients($testItems);
$filteredItems = $testFilter->getFilteredItems();

$totalTests++;
$trackingWorks = (count($filteredItems) === 2); // Should track 2 filtered items
printTest(
    "Filtered items tracking works",
    $trackingWorks,
    count($filteredItems) . " items tracked"
);
if ($trackingWorks) {
    $passedTests++;
}

if ($debugMode && !empty($filteredItems)) {
    echo COLOR_GRAY . "    Tracked filtered items:\n" . COLOR_RESET;
    foreach ($filteredItems as $item) {
        echo COLOR_GRAY . "      - '{$item['item']}' (reason: {$item['reason']})\n" . COLOR_RESET;
    }
}

// ============================================================================
// SUMMARY
// ============================================================================
echo "\n";
echo COLOR_BOLD . str_repeat("═", 80) . COLOR_RESET . "\n";
echo COLOR_BOLD . " TEST SUMMARY" . COLOR_RESET . "\n";
echo COLOR_BOLD . str_repeat("═", 80) . COLOR_RESET . "\n";
echo "\n";
echo "  Total Tests: " . $totalTests . "\n";
echo "  " . COLOR_GREEN . "Passed: " . $passedTests . COLOR_RESET . "\n";
echo "  " . COLOR_RED . "Failed: " . ($totalTests - $passedTests) . COLOR_RESET . "\n";
echo "  " . COLOR_BOLD . "Success Rate: " . number_format(($passedTests / $totalTests) * 100, 1) . "%" . COLOR_RESET . "\n";
echo "\n";

if ($passedTests === $totalTests) {
    echo COLOR_GREEN . COLOR_BOLD . "  ✓ ALL TESTS PASSED" . COLOR_RESET . "\n\n";
    $exitCode = 0;
} else {
    echo COLOR_RED . COLOR_BOLD . "  ✗ SOME TESTS FAILED" . COLOR_RESET . "\n\n";
    $exitCode = 1;
}

// Close HTML for web mode
if (!$isCLI) {
    echo '</pre>
<p><a href="index.html">← Back to Test Suite</a></p>
</div>
</body>
</html>';
}

exit($exitCode);
