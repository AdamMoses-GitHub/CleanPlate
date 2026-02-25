<?php
/**
 * Test Script for Image Candidate Extraction
 * Validates Phase 1 implementation
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/RecipeParser.php';

echo "========================================\n";
echo "Image Candidate Extraction Test\n";
echo "========================================\n\n";

// Test with AllRecipes (known to have multiple images)
$testUrl = 'https://www.allrecipes.com/recipe/10813/best-chocolate-chip-cookies/';

echo "Testing URL: {$testUrl}\n\n";

try {
    $parser = new RecipeParser(false);
    $result = $parser->parse($testUrl);
    
    if ($result['status'] === 'success') {
        echo "✓ Extraction successful (Phase {$result['phase']})\n";
        echo "✓ Recipe: {$result['data']['title']}\n\n";
        
        // Check for image candidates
        if (isset($result['data']['metadata']['imageCandidates'])) {
            $candidates = $result['data']['metadata']['imageCandidates'];
            $count = count($candidates);
            
            echo "✓ Found {$count} image candidate(s):\n\n";
            
            foreach ($candidates as $i => $candidate) {
                echo "  Image " . ($i + 1) . ":\n";
                echo "    URL: {$candidate['url']}\n";
                echo "    Score: {$candidate['score']}\n";
                echo "    Source: {$candidate['source']}\n";
                if (isset($candidate['alt'])) {
                    echo "    Alt: {$candidate['alt']}\n";
                }
                echo "\n";
            }
            
            // Check primary image
            if (isset($result['data']['metadata']['imageUrl'])) {
                echo "✓ Primary image: {$result['data']['metadata']['imageUrl']}\n";
            }
            
            echo "\n✓✓✓ Image extraction working correctly! ✓✓✓\n";
            
        } else {
            echo "⚠ No image candidates found (this may be normal for some sites)\n";
        }
        
    } else {
        echo "✗ Extraction failed\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n========================================\n";
echo "Test Complete\n";
echo "========================================\n";
