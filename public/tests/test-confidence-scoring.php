<?php
/**
 * Comprehensive Test Suite for Confidence Scoring System
 * Tests the confidence calculation algorithm with various test cases
 * 
 * Usage:
 *   CLI: php test-confidence-scoring.php
 *   Web: http://localhost:8080/tests/test-confidence-scoring.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/RecipeParser.php';

// Detect execution mode
$isCLI = php_sapi_name() === 'cli';

// Set headers for web mode
if (!$isCLI) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confidence Scoring Test Suite</title>
    <style>
        body { font-family: "Courier New", monospace; background: #1e1e1e; color: #d4d4d4; padding: 2rem; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; }
        .pass { color: #4ec9b0; }
        .fail { color: #f48771; }
        .header { color: #4fc1ff; font-weight: bold; border-bottom: 2px solid #4fc1ff; padding-bottom: 0.5rem; margin: 1.5rem 0 1rem 0; }
        pre { background: #252526; padding: 1rem; border-radius: 4px; overflow-x: auto; }
        a { color: #4fc1ff; }
    </style>
</head>
<body>
<div class="container">
<pre>';
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "  CleanPlate Confidence Scoring Test Suite\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Track test results
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

/**
 * SECTION 1: Real URL Tests
 * Tests actual recipe websites to validate scoring in production scenarios
 */
echo "━━━ SECTION 1: Real URL Tests ━━━\n\n";

$realUrlTests = [
    [
        'name' => 'AllRecipes (Expected: High)',
        'url' => 'https://www.allrecipes.com/recipe/10813/best-chocolate-chip-cookies/',
        'expectedLevel' => 'high',
        'minScore' => 80
    ],
    [
        'name' => 'Serious Eats (Expected: High)',
        'url' => 'https://www.seriouseats.com/classic-chocolate-chip-cookies-recipe',
        'expectedLevel' => 'high',
        'minScore' => 80
    ],
    [
        'name' => 'Food Network (Expected: Medium-High)',
        'url' => 'https://www.foodnetwork.com/recipes/alton-brown/overnight-oatmeal-recipe-1939140',
        'expectedLevel' => 'medium',
        'minScore' => 50
    ]
];

foreach ($realUrlTests as $test) {
    $totalTests++;
    echo "Test: {$test['name']}\n";
    echo "URL: {$test['url']}\n";
    
    try {
        $parser = new RecipeParser();
        $result = $parser->parse($test['url']);
        
        $score = $result['confidence'] ?? null;
        $level = $result['confidenceLevel'] ?? null;
        
        if ($score === null) {
            echo "✗ FAIL: No confidence score returned\n";
            $failedTests++;
        } elseif ($score < $test['minScore']) {
            echo "✗ FAIL: Score {$score} below minimum {$test['minScore']}\n";
            $failedTests++;
        } elseif ($level !== $test['expectedLevel'] && !($test['expectedLevel'] === 'medium' && $level === 'high')) {
            echo "◐ PARTIAL: Score {$score}, Level '{$level}' (expected '{$test['expectedLevel']}')\n";
            displayScoreBreakdown($result);
            $passedTests++;
        } else {
            echo "✓ PASS: Score {$score}/100, Level '{$level}'\n";
            displayScoreBreakdown($result);
            $passedTests++;
        }
        
    } catch (Exception $e) {
        echo "✗ FAIL: {$e->getMessage()}\n";
        $failedTests++;
    }
    
    echo "\n" . str_repeat('-', 60) . "\n\n";
    
    // Delay to avoid rate limiting
    sleep(2);
}

/**
 * SECTION 2: Mock Data Unit Tests
 * Tests the scoring algorithm with controlled mock data
 */
echo "\n━━━ SECTION 2: Mock Data Unit Tests ━━━\n\n";

$mockTests = [
    [
        'name' => 'Perfect Recipe (Phase 1)',
        'data' => [
            'phase' => 1,
            'title' => 'Delicious Chocolate Chip Cookies',
            'ingredients' => [
                '2 cups all-purpose flour',
                '1 teaspoon baking soda',
                '1/2 teaspoon salt',
                '1 cup butter, softened',
                '3/4 cup granulated sugar',
                '3/4 cup packed brown sugar',
                '2 large eggs',
                '2 cups chocolate chips'
            ],
            'instructions' => [
                'Preheat oven to 375°F',
                'Mix flour, baking soda and salt in bowl',
                'Beat butter and sugars until creamy',
                'Add eggs and vanilla',
                'Gradually blend in flour mixture',
                'Stir in chocolate chips',
                'Drop by rounded tablespoons onto ungreased cookie sheets',
                'Bake 9 to 11 minutes'
            ],
            'metadata' => [
                'prepTime' => '15 minutes',
                'cookTime' => '10 minutes',
                'totalTime' => '25 minutes',
                'servings' => '48 cookies',
                'imageUrl' => 'https://example.com/image.jpg'
            ]
        ],
        'expectedMin' => 85,
        'expectedLevel' => 'high'
    ],
    [
        'name' => 'Good Recipe (Phase 2)',
        'data' => [
            'phase' => 2,
            'title' => 'Simple Pasta Recipe',
            'ingredients' => [
                'pasta',
                'olive oil',
                'garlic',
                'salt',
                'pepper'
            ],
            'instructions' => [
                'Boil water',
                'Cook pasta',
                'Drain and serve'
            ],
            'metadata' => [
                'servings' => '4'
            ]
        ],
        'expectedMin' => 40,
        'expectedMax' => 70,
        'expectedLevel' => 'medium'
    ],
    [
        'name' => 'Minimal Recipe',
        'data' => [
            'phase' => 2,
            'title' => 'Quick Snack',
            'ingredients' => [
                'bread',
                'butter'
            ],
            'instructions' => [
                'Spread butter on bread'
            ],
            'metadata' => []
        ],
        'expectedMax' => 50,
        'expectedLevel' => 'low'
    ]
];

foreach ($mockTests as $test) {
    $totalTests++;
    echo "Test: {$test['name']}\n";
    
    $parser = new RecipeParser();
    $reflection = new ReflectionClass($parser);
    $method = $reflection->getMethod('calculateConfidenceScore');
    $method->setAccessible(true);
    
    // Pass both required arguments: data and phase
    $phase = $test['data']['phase'] ?? 1;
    $result = $method->invoke($parser, $test['data'], $phase);
    
    $score = $result['score'];
    $level = $result['level'];
    
    $pass = true;
    if (isset($test['expectedMin']) && $score < $test['expectedMin']) {
        $pass = false;
    }
    if (isset($test['expectedMax']) && $score > $test['expectedMax']) {
        $pass = false;
    }
    if ($level !== $test['expectedLevel']) {
        $pass = false;
    }
    
    if ($pass) {
        echo "✓ PASS: Score {$score}/100, Level '{$level}'\n";
        $passedTests++;
    } else {
        echo "✗ FAIL: Score {$score}/100, Level '{$level}'\n";
        echo "  Expected: Level '{$test['expectedLevel']}'";
        if (isset($test['expectedMin'])) echo ", Min {$test['expectedMin']}";
        if (isset($test['expectedMax'])) echo ", Max {$test['expectedMax']}";
        echo "\n";
        $failedTests++;
    }
    
    // Show breakdown
    echo "  Breakdown: ";
    if (isset($result['details'])) {
        $parts = [];
        if (isset($result['details']['phase'])) {
            $parts[] = "Phase={$result['details']['phase']['points']}";
        }
        if (isset($result['details']['title'])) {
            $parts[] = "Title={$result['details']['title']['points']}";
        }
        if (isset($result['details']['ingredients'])) {
            $parts[] = "Ing={$result['details']['ingredients']['points']}";
        }
        if (isset($result['details']['instructions'])) {
            $parts[] = "Inst={$result['details']['instructions']['points']}";
        }
        if (isset($result['details']['metadata'])) {
            $parts[] = "Meta={$result['details']['metadata']['points']}";
        }
        echo implode(', ', $parts) . "\n";
    }
    
    echo "\n";
}

/**
 * SECTION 3: Edge Case Tests
 * Tests handling of problematic or invalid data
 */
echo "━━━ SECTION 3: Edge Case Tests ━━━\n\n";

$edgeCases = [
    [
        'name' => 'Empty Ingredients Array',
        'data' => [
            'phase' => 1,
            'title' => 'Test Recipe',
            'ingredients' => [],
            'instructions' => ['Step 1', 'Step 2'],
            'metadata' => []
        ],
        'shouldNotCrash' => true
    ],
    [
        'name' => 'Empty Instructions Array',
        'data' => [
            'phase' => 1,
            'title' => 'Test Recipe',
            'ingredients' => ['Ingredient 1', 'Ingredient 2'],
            'instructions' => [],
            'metadata' => []
        ],
        'shouldNotCrash' => true
    ],
    [
        'name' => 'Missing Title',
        'data' => [
            'phase' => 1,
            'title' => '',
            'ingredients' => ['Ingredient 1'],
            'instructions' => ['Step 1'],
            'metadata' => []
        ],
        'shouldNotCrash' => true,
        'expectedMax' => 60
    ],
    [
        'name' => 'Generic Title',
        'data' => [
            'phase' => 1,
            'title' => 'Recipe',
            'ingredients' => ['Ingredient 1', 'Ingredient 2'],
            'instructions' => ['Step 1', 'Step 2'],
            'metadata' => []
        ],
        'shouldNotCrash' => true,
        'titlePointsExpected' => 0
    ],
    [
        'name' => 'No Metadata',
        'data' => [
            'phase' => 1,
            'title' => 'Good Recipe Name',
            'ingredients' => ['1 cup flour', '2 eggs'],
            'instructions' => ['Mix ingredients', 'Bake for 30 minutes'],
            'metadata' => []
        ],
        'shouldNotCrash' => true,
        'expectedMin' => 60
    ],
    [
        'name' => 'Duplicate Ingredients',
        'data' => [
            'phase' => 1,
            'title' => 'Test Recipe',
            'ingredients' => ['salt', 'salt', 'salt', 'pepper'],
            'instructions' => ['Step 1'],
            'metadata' => []
        ],
        'shouldNotCrash' => true
    ],
    [
        'name' => 'Very Long Lists',
        'data' => [
            'phase' => 1,
            'title' => 'Complex Recipe',
            'ingredients' => array_fill(0, 50, 'Ingredient'),
            'instructions' => array_fill(0, 50, 'Step'),
            'metadata' => []
        ],
        'shouldNotCrash' => true,
        'expectedMin' => 70
    ],
    [
        'name' => 'Invalid Phase Number',
        'data' => [
            'phase' => 99,
            'title' => 'Test Recipe',
            'ingredients' => ['Ingredient 1'],
            'instructions' => ['Step 1'],
            'metadata' => []
        ],
        'shouldNotCrash' => true
    ]
];

foreach ($edgeCases as $test) {
    $totalTests++;
    echo "Test: {$test['name']}\n";
    
    try {
        $parser = new RecipeParser();
        $reflection = new ReflectionClass($parser);
        $method = $reflection->getMethod('calculateConfidenceScore');
        $method->setAccessible(true);
        
        // Pass both required arguments: data and phase
        $phase = $test['data']['phase'] ?? 1;
        $result = $method->invoke($parser, $test['data'], $phase);
        
        $score = $result['score'];
        $level = $result['level'];
        
        $pass = true;
        $message = "Score {$score}/100, Level '{$level}'";
        
        // Check expectations
        if (isset($test['expectedMin']) && $score < $test['expectedMin']) {
            $pass = false;
            $message .= " (Expected min {$test['expectedMin']})";
        }
        if (isset($test['expectedMax']) && $score > $test['expectedMax']) {
            $pass = false;
            $message .= " (Expected max {$test['expectedMax']})";
        }
        if (isset($test['titlePointsExpected'])) {
            $titlePoints = $result['details']['title']['points'] ?? null;
            if ($titlePoints !== $test['titlePointsExpected']) {
                $pass = false;
                $message .= " (Title points: {$titlePoints}, expected {$test['titlePointsExpected']})";
            }
        }
        
        if ($pass) {
            echo "✓ PASS: {$message}\n";
            $passedTests++;
        } else {
            echo "✗ FAIL: {$message}\n";
            $failedTests++;
        }
        
    } catch (Exception $e) {
        if ($test['shouldNotCrash']) {
            echo "✗ FAIL: Crashed with error: {$e->getMessage()}\n";
            $failedTests++;
        } else {
            echo "✓ PASS: Correctly threw exception\n";
            $passedTests++;
        }
    }
    
    echo "\n";
}

/**
 * SECTION 4: Quality Bonus Tests
 * Tests that quality bonuses are properly applied
 */
echo "━━━ SECTION 4: Quality Bonus Tests ━━━\n\n";

$qualityTests = [
    [
        'name' => 'Ingredients with Measurements',
        'data' => [
            'phase' => 1,
            'title' => 'Test Recipe',
            'ingredients' => [
                '2 cups flour',
                '1 teaspoon salt',
                '3 tablespoons butter',
                '1/2 cup sugar',
                '4 eggs'
            ],
            'instructions' => ['Step 1'],
            'metadata' => []
        ],
        'expectQualityBonus' => true
    ],
    [
        'name' => 'Ingredients without Measurements',
        'data' => [
            'phase' => 1,
            'title' => 'Test Recipe',
            'ingredients' => [
                'flour',
                'salt',
                'butter',
                'sugar',
                'eggs'
            ],
            'instructions' => ['Step 1'],
            'metadata' => []
        ],
        'expectQualityBonus' => false
    ],
    [
        'name' => 'Instructions with Action Verbs',
        'data' => [
            'phase' => 1,
            'title' => 'Test Recipe',
            'ingredients' => ['flour'],
            'instructions' => [
                'Preheat oven to 350°F',
                'Mix all ingredients',
                'Pour into pan',
                'Bake for 30 minutes',
                'Cool before serving'
            ],
            'metadata' => []
        ],
        'expectQualityBonus' => true
    ],
    [
        'name' => 'Instructions without Action Verbs',
        'data' => [
            'phase' => 1,
            'title' => 'Test Recipe',
            'ingredients' => ['flour'],
            'instructions' => [
                'Step one',
                'Step two',
                'Step three'
            ],
            'metadata' => []
        ],
        'expectQualityBonus' => false
    ]
];

foreach ($qualityTests as $test) {
    $totalTests++;
    echo "Test: {$test['name']}\n";
    
    $parser = new RecipeParser();
    $reflection = new ReflectionClass($parser);
    $method = $reflection->getMethod('calculateConfidenceScore');
    $method->setAccessible(true);
    
    // Pass both required arguments: data and phase
    $phase = $test['data']['phase'] ?? 1;
    $result = $method->invoke($parser, $test['data'], $phase);
    
    $ingBonus = $result['details']['ingredients']['qualityBonus'] ?? 0;
    $instBonus = $result['details']['instructions']['qualityBonus'] ?? 0;
    $hasBonus = ($ingBonus > 0 || $instBonus > 0);
    
    if ($hasBonus === $test['expectQualityBonus']) {
        echo "✓ PASS: Quality bonus ";
        echo $test['expectQualityBonus'] ? "correctly applied" : "correctly not applied";
        echo " (Ing: +{$ingBonus}, Inst: +{$instBonus})\n";
        $passedTests++;
    } else {
        echo "✗ FAIL: Quality bonus ";
        echo $test['expectQualityBonus'] ? "not applied" : "incorrectly applied";
        echo " (Ing: +{$ingBonus}, Inst: +{$instBonus})\n";
        $failedTests++;
    }
    
    echo "\n";
}

/**
 * Display final results
 */
echo "═══════════════════════════════════════════════════════════════\n";
echo "  Test Results Summary\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  Total Tests:  {$totalTests}\n";
echo "  Passed:       {$passedTests} ✓\n";
echo "  Failed:       {$failedTests} ✗\n";
$successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;
echo "  Success Rate: {$successRate}%\n";
echo "═══════════════════════════════════════════════════════════════\n";

// Close HTML for web mode
if (!$isCLI) {
    echo '</pre>
<p><a href="index.html">← Back to Test Suite</a></p>
</div>
</body>
</html>';
}

// Exit with appropriate code
exit($failedTests > 0 ? 1 : 0);

/**
 * Helper function to display confidence score breakdown
 */
function displayScoreBreakdown($result) {
    if (!isset($result['confidenceDetails'])) {
        return;
    }
    
    $details = $result['confidenceDetails'];
    echo "  Breakdown: ";
    
    $parts = [];
    if (isset($details['phase'])) {
        $parts[] = "Phase={$details['phase']['points']}/{$details['phase']['max']}";
    }
    if (isset($details['title'])) {
        $parts[] = "Title={$details['title']['points']}/{$details['title']['max']}";
    }
    if (isset($details['ingredients'])) {
        $ing = $details['ingredients'];
        $bonus = isset($ing['qualityBonus']) && $ing['qualityBonus'] > 0 ? "+{$ing['qualityBonus']}" : "";
        $parts[] = "Ing={$ing['points']}/{$ing['max']}{$bonus}";
    }
    if (isset($details['instructions'])) {
        $inst = $details['instructions'];
        $bonus = isset($inst['qualityBonus']) && $inst['qualityBonus'] > 0 ? "+{$inst['qualityBonus']}" : "";
        $parts[] = "Inst={$inst['points']}/{$inst['max']}{$bonus}";
    }
    if (isset($details['metadata'])) {
        $parts[] = "Meta={$details['metadata']['points']}/{$details['metadata']['max']}";
    }
    
    echo implode(', ', $parts) . "\n";
}
