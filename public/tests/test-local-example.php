<?php
/**
 * Test Script for Local Example File
 * Tests the recipe parser against the saved local HTML file
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/RecipeParser.php';

$htmlFile = __DIR__ . '/../../examples/Grilled Cheese Sandwich Recipe.htm';

if (!file_exists($htmlFile)) {
    die("Error: Example file not found at: {$htmlFile}\n");
}

$html = file_get_contents($htmlFile);

echo str_repeat('=', 70) . "\n";
echo "Testing Local Example: Grilled Cheese Sandwich Recipe\n";
echo str_repeat('=', 70) . "\n\n";

// Create parser instance
$parser = new RecipeParser(true);

// We need to use reflection to access private methods for testing
$reflection = new ReflectionClass($parser);

// Test Phase 1: JSON-LD extraction
echo "Phase 1: JSON-LD Extraction\n";
echo str_repeat('-', 70) . "\n";

$extractFromJsonLdMethod = $reflection->getMethod('extractFromJsonLd');
$extractFromJsonLdMethod->setAccessible(true);

$phase1Result = $extractFromJsonLdMethod->invoke($parser, $html, 'file://local');

if ($phase1Result) {
    echo "✓ Phase 1 successful\n\n";
    echo "Title: " . ($phase1Result['title'] ?? 'N/A') . "\n";
    echo "Prep Time: " . ($phase1Result['prepTime'] ?? 'N/A') . "\n";
    echo "Cook Time: " . ($phase1Result['cookTime'] ?? 'N/A') . "\n";
    echo "Total Time: " . ($phase1Result['totalTime'] ?? 'N/A') . "\n";
    echo "Servings: " . ($phase1Result['yield'] ?? 'N/A') . "\n\n";
    
    echo "Ingredients (" . count($phase1Result['ingredients'] ?? []) . "):\n";
    foreach ($phase1Result['ingredients'] ?? [] as $i => $ingredient) {
        echo "  " . ($i + 1) . ". {$ingredient}\n";
    }
    
    echo "\nInstructions (" . count($phase1Result['instructions'] ?? []) . "):\n";
    foreach ($phase1Result['instructions'] ?? [] as $i => $instruction) {
        echo "  " . ($i + 1) . ". {$instruction}\n";
    }
    
    echo "\nImage: " . ($phase1Result['image'] ?? 'N/A') . "\n";
} else {
    echo "✗ Phase 1 failed\n\n";
    
    // Try Phase 2
    echo "Phase 2: DOM Extraction\n";
    echo str_repeat('-', 70) . "\n";
    
    $extractFromDomMethod = $reflection->getMethod('extractFromDom');
    $extractFromDomMethod->setAccessible(true);
    
    $phase2Result = $extractFromDomMethod->invoke($parser, $html, 'file://local');
    
    if ($phase2Result) {
        echo "✓ Phase 2 successful\n\n";
        echo "Title: " . ($phase2Result['title'] ?? 'N/A') . "\n";
        
        echo "\nIngredients (" . count($phase2Result['ingredients'] ?? []) . "):\n";
        foreach ($phase2Result['ingredients'] ?? [] as $i => $ingredient) {
            echo "  " . ($i + 1) . ". {$ingredient}\n";
        }
        
        echo "\nInstructions (" . count($phase2Result['instructions'] ?? []) . "):\n";
        foreach ($phase2Result['instructions'] ?? [] as $i => $instruction) {
            echo "  " . ($i + 1) . ". {$instruction}\n";
        }
    } else {
        echo "✗ Phase 2 also failed\n";
    }
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "Test Complete\n";
echo str_repeat('=', 70) . "\n";
