<?php
/**
 * Test Script for Enhanced Bot-Detection Evasion
 * Tests the improved recipe scraper against various sites
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/includes/RecipeParser.php';

// Enable debug logging with --debug flag
$enableDebugLogging = in_array('--debug', $argv ?? [], true);
if ($enableDebugLogging) {
    echo "Debug logging enabled - confidence scores will be logged to error log\n\n";
}

// Test URLs - include Food Network and other sites
$testUrls = [
    'Food Network' => 'https://www.foodnetwork.com/recipes/alton-brown/overnight-oatmeal-recipe-1939140',
    'AllRecipes' => 'https://www.allrecipes.com/recipe/10813/best-chocolate-chip-cookies/',
    'Serious Eats' => 'https://www.seriouseats.com/classic-chocolate-chip-cookies-recipe',
];

echo "\n" . str_repeat('═', 70) . "\n";
echo "  CleanPlate Recipe Extraction Test Suite\n";
echo str_repeat('═', 70) . "\n\n";

// Test single URL if provided via command line
if (isset($argv[1]) && $argv[1] !== '--debug') {
    echo "Testing custom URL: {$argv[1]}\n\n";
    testUrl('Custom', $argv[1], $enableDebugLogging);
    exit;
}

// Test all predefined URLs
foreach ($testUrls as $siteName => $url) {
    testUrl($siteName, $url, $enableDebugLogging);
    
    // Add delay between tests to avoid rate limiting
    if (next($testUrls) !== false) {
        echo "\n" . str_repeat('─', 70) . "\n";
        echo "Waiting 3 seconds before next test...\n";
        echo str_repeat('─', 70) . "\n\n";
        sleep(3);
    }
}

echo "\n" . str_repeat('═', 70) . "\n";
echo "  Testing Complete\n";
echo str_repeat('═', 70) . "\n";

/**
 * Test a single URL
 */
function testUrl($siteName, $url, $enableDebugLogging = false) {
    echo "┌" . str_repeat('─', 68) . "┐\n";
    echo "│ " . str_pad($siteName, 66) . " │\n";
    echo "└" . str_repeat('─', 68) . "┘\n";
    
    // Extract domain for display
    $domain = parse_url($url, PHP_URL_HOST);
    echo "  URL: {$domain}\n\n";
    
    try {
        $parser = new RecipeParser($enableDebugLogging);
        $startTime = microtime(true);
        
        $result = $parser->parse($url);
        
        $elapsed = round((microtime(true) - $startTime), 2);
        
        // Color codes for terminal output (ANSI codes)
        $colorReset = "\033[0m";
        $colorGreen = "\033[32m";
        $colorCyan = "\033[36m";
        $colorBold = "\033[1m";
        
        echo "  {$colorGreen}✓ SUCCESS{$colorReset} │ Phase {$result['phase']} │ {$colorCyan}{$elapsed}s{$colorReset}\n";
        
        // Display confidence score prominently with color coding
        if (isset($result['confidence'])) {
            $score = $result['confidence'];
            $level = $result['confidenceLevel'];
            
            // Color codes for terminal output (ANSI codes)
            $colorReset = "\033[0m";
            $colorGreen = "\033[32m";
            $colorAmber = "\033[33m";
            $colorRed = "\033[31m";
            
            $color = $colorReset;
            $emoji = '●';
            if ($level === 'high') {
                $color = $colorGreen;
                $emoji = '✓';
            } elseif ($level === 'medium') {
                $color = $colorAmber;
                $emoji = '◐';
            } elseif ($level === 'low') {
                $color = $colorRed;
                $emoji = '✗';
            }
            
            echo "\n  {$colorBold}CONFIDENCE SCORE{$colorReset}\n";
            echo "  {$color}{$emoji} {$score}/100 ({$level}){$colorReset}\n\n";
            
            // Display detailed confidence breakdown
            if (isset($result['confidenceDetails'])) {
                displayConfidenceBreakdown($result['confidenceDetails'], $score);
            }
        }
        
        echo "\n  {$colorBold}RECIPE DATA{$colorReset}\n";
        echo "  ├─ Title: {$result['data']['title']}\n";
        echo "  ├─ Ingredients: " . count($result['data']['ingredients']) . " items\n";
        echo "  ├─ Instructions: " . count($result['data']['instructions']) . " steps\n";
        
        // Show session info
        if (isset($_SESSION['user_agent'])) {
            $ua = $_SESSION['user_agent'];
            $browser = 'Unknown';
            if (strpos($ua, 'Firefox') !== false) $browser = 'Firefox';
            elseif (strpos($ua, 'Safari') !== false && strpos($ua, 'Chrome') === false) $browser = 'Safari';
            elseif (strpos($ua, 'Chrome') !== false) $browser = 'Chrome';
            
            echo "  └─ User-Agent: {$browser}\n";
        } else {
            echo "  └─ [No user-agent info]\n";
        }
        
        // Show first ingredient and instruction as sample
        if (!empty($result['data']['ingredients']) || !empty($result['data']['instructions'])) {
            echo "\n  {$colorBold}SAMPLE DATA{$colorReset}\n";
            
            if (!empty($result['data']['ingredients'])) {
                $sample = $result['data']['ingredients'][0];
                $display = strlen($sample) > 60 ? substr($sample, 0, 60) . '...' : $sample;
                echo "  ├─ First ingredient: {$display}\n";
            }
            
            if (!empty($result['data']['instructions'])) {
                $sample = $result['data']['instructions'][0];
                $display = strlen($sample) > 60 ? substr($sample, 0, 60) . '...' : $sample;
                echo "  └─ First step: {$display}\n";
            }
        }
        
    } catch (Exception $e) {
        // Color codes for errors
        $colorReset = "\033[0m";
        $colorRed = "\033[31m";
        $colorBold = "\033[1m";
        
        echo "  {$colorRed}{$colorBold}✗ FAILED{$colorReset}\n";
        echo "  Error: {$e->getMessage()}\n";
        
        // Categorize the error
        $msg = $e->getMessage();
        $errorType = 'Unknown Error';
        
        if (strpos($msg, 'CLOUDFLARE_BLOCK') !== false) {
            $errorType = 'Cloudflare Protection Detected';
        } elseif (strpos($msg, 'JAVASCRIPT_REQUIRED') !== false) {
            $errorType = 'JavaScript Required';
        } elseif (strpos($msg, 'Access denied') !== false) {
            $errorType = 'Access Denied (HTTP 403)';
        } elseif (strpos($msg, 'No recipe found') !== false) {
            $errorType = 'No Recipe Content Found';
        }
        
        echo "  Type: {$errorType}\n";
    }
    
    echo "\n";
}

/**
 * Display confidence score breakdown table
 */
function displayConfidenceBreakdown($details, $totalScore) {
    echo "  ┌" . str_repeat('─', 66) . "┐\n";
    printf("  │ %-22s │ %8s │ %8s │ %14s │\n", "Factor", "Points", "Max", "Status");
    echo "  ├" . str_repeat('─', 66) . "┤\n";
    
    // Phase
    if (isset($details['phase'])) {
        $phase = $details['phase'];
        $status = "Phase " . $phase['value'];
        printf("  │ %-22s │ %8s │ %8s │ %14s │\n", 
            "Extraction Phase", 
            $phase['points'], 
            $phase['max'], 
            $status
        );
    }
    
    // Title
    if (isset($details['title'])) {
        $title = $details['title'];
        $status = $title['points'] > 0 ? '✓ Valid' : '✗ Missing';
        printf("  │ %-22s │ %8s │ %8s │ %14s │\n", 
            "Recipe Title", 
            $title['points'], 
            $title['max'], 
            $status
        );
    }
    
    // Ingredients
    if (isset($details['ingredients'])) {
        $ing = $details['ingredients'];
        $qualityBonus = isset($ing['qualityBonus']) ? $ing['qualityBonus'] : 0;
        $status = $ing['count'] . " items";
        if ($qualityBonus > 0) {
            $status .= " (+" . $qualityBonus . ")";
        }
        printf("  │ %-22s │ %8s │ %8s │ %14s │\n", 
            "Ingredients", 
            $ing['points'], 
            $ing['max'], 
            $status
        );
    }
    
    // Instructions
    if (isset($details['instructions'])) {
        $inst = $details['instructions'];
        $qualityBonus = isset($inst['qualityBonus']) ? $inst['qualityBonus'] : 0;
        $status = $inst['count'] . " steps";
        if ($qualityBonus > 0) {
            $status .= " (+" . $qualityBonus . ")";
        }
        printf("  │ %-22s │ %8s │ %8s │ %14s │\n", 
            "Instructions", 
            $inst['points'], 
            $inst['max'], 
            $status
        );
    }
    
    // Metadata
    if (isset($details['metadata'])) {
        $meta = $details['metadata'];
        $status = $meta['fieldsPresent'] . "/" . $meta['fieldsTotal'];
        printf("  │ %-22s │ %8s │ %8s │ %14s │\n", 
            "Metadata", 
            $meta['points'], 
            $meta['max'], 
            $status
        );
    }
    
    // Total
    echo "  ├" . str_repeat('─', 66) . "┤\n";
    printf("  │ %-22s │ %8s │ %8s │ %14s │\n", 
        "TOTAL SCORE", 
        $totalScore, 
        "100",
        ""
    );
    echo "  └" . str_repeat('─', 66) . "┘\n";
}
