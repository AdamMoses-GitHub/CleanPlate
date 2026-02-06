<?php
/**
 * Test Script for Enhanced Bot-Detection Evasion
 * Tests the improved recipe scraper against various sites
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/includes/RecipeParser.php';

// Test URLs - include Food Network and other sites
$testUrls = [
    'Food Network' => 'https://www.foodnetwork.com/recipes/alton-brown/overnight-oatmeal-recipe-1939140',
    'AllRecipes' => 'https://www.allrecipes.com/recipe/10813/best-chocolate-chip-cookies/',
    'Serious Eats' => 'https://www.seriouseats.com/classic-chocolate-chip-cookies-recipe',
];

echo "=== CleanPlate Enhanced Scraper Test ===\n\n";

// Test single URL if provided via command line
if (isset($argv[1])) {
    echo "Testing custom URL: {$argv[1]}\n\n";
    testUrl('Custom', $argv[1]);
    exit;
}

// Test all predefined URLs
foreach ($testUrls as $siteName => $url) {
    testUrl($siteName, $url);
    echo "\n" . str_repeat('-', 60) . "\n\n";
    
    // Add delay between tests to avoid rate limiting
    if (next($testUrls) !== false) {
        echo "Waiting 3 seconds before next test...\n\n";
        sleep(3);
    }
}

echo "=== Testing Complete ===\n";

/**
 * Test a single URL
 */
function testUrl($siteName, $url) {
    echo "Testing: {$siteName}\n";
    echo "URL: {$url}\n";
    
    try {
        $parser = new RecipeParser();
        $startTime = microtime(true);
        
        $result = $parser->parse($url);
        
        $elapsed = round((microtime(true) - $startTime), 2);
        
        echo "✓ SUCCESS (Phase {$result['phase']}) - {$elapsed}s\n";
        echo "  Title: {$result['data']['title']}\n";
        echo "  Ingredients: " . count($result['data']['ingredients']) . " items\n";
        echo "  Instructions: " . count($result['data']['instructions']) . " steps\n";
        
        // Show session info
        if (isset($_SESSION['user_agent'])) {
            $ua = $_SESSION['user_agent'];
            $browser = 'Unknown';
            if (strpos($ua, 'Firefox') !== false) $browser = 'Firefox';
            elseif (strpos($ua, 'Safari') !== false && strpos($ua, 'Chrome') === false) $browser = 'Safari';
            elseif (strpos($ua, 'Chrome') !== false) $browser = 'Chrome';
            
            echo "  User-Agent: {$browser}\n";
        }
        
        // Show first ingredient and instruction as sample
        if (!empty($result['data']['ingredients'])) {
            echo "  First ingredient: " . substr($result['data']['ingredients'][0], 0, 50) . "...\n";
        }
        if (!empty($result['data']['instructions'])) {
            echo "  First step: " . substr($result['data']['instructions'][0], 0, 50) . "...\n";
        }
        
    } catch (Exception $e) {
        echo "✗ FAILED: {$e->getMessage()}\n";
        
        // Categorize the error
        $msg = $e->getMessage();
        if (strpos($msg, 'CLOUDFLARE_BLOCK') !== false) {
            echo "  Error Type: Cloudflare Protection Detected\n";
        } elseif (strpos($msg, 'JAVASCRIPT_REQUIRED') !== false) {
            echo "  Error Type: JavaScript Required\n";
        } elseif (strpos($msg, 'Access denied') !== false) {
            echo "  Error Type: Access Denied (HTTP 403)\n";
        } elseif (strpos($msg, 'No recipe found') !== false) {
            echo "  Error Type: No Recipe Content Found\n";
        }
    }
}
