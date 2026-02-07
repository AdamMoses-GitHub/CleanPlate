<?php
/**
 * RecipeParser Class
 * Two-phase recipe extraction: JSON-LD (Phase 1) and DOM fallback (Phase 2)
 */

require_once __DIR__ . '/IngredientFilter.php';

class RecipeParser {
    // Configuration constants
    const MIN_REQUEST_DELAY = 2;  // seconds between requests to same domain
    const MAX_REDIRECTS = 5;      // maximum redirect hops
    const REQUEST_TIMEOUT = 10;   // timeout in seconds
    
    // User-agent pool for rotation
    private $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ];
    
    private $timeout = self::REQUEST_TIMEOUT;
    private $cookieFile = null;
    private $debugLogging = false;  // Enable to log confidence scores for analysis
    private $ingredientFilter = null;
    
    /**
     * Constructor - optionally enable debug logging
     */
    public function __construct($debugLogging = false) {
        $this->debugLogging = $debugLogging;
        // Initialize ingredient filter with balanced strictness
        $this->ingredientFilter = new IngredientFilter(
            IngredientFilter::STRICTNESS_BALANCED,
            $debugLogging
        );
    }
    
    /**
     * Main parse method - orchestrates the waterfall logic
     */
    public function parse($url) {
        // Initialize session storage if needed
        $this->initializeSession();
        
        // Implement per-domain delay to avoid aggressive scraping detection
        $this->enforcePerDomainDelay($url);
        
        $html = $this->fetchUrl($url);
        
        // Phase 1: Try JSON-LD extraction
        $phase1Result = $this->extractFromJsonLd($html, $url);
        if ($phase1Result !== null) {
            // Calculate confidence score for Phase 1 extraction
            $confidenceResult = $this->calculateConfidenceScore($phase1Result, 1);
            
            // Log confidence score if debug logging is enabled
            $this->logConfidenceScore($url, 1, $confidenceResult);
            
            return [
                'status' => 'success',
                'phase' => 1,
                'confidence' => $confidenceResult['score'],
                'confidenceLevel' => $confidenceResult['level'],
                'confidenceDetails' => $confidenceResult['factors'],
                'data' => $phase1Result,
                'timestamp' => date('c')
            ];
        }
        
        // Phase 2: Fallback to DOM extraction
        $phase2Result = $this->extractFromDom($html, $url);
        if ($phase2Result !== null) {
            // Calculate confidence score for Phase 2 extraction
            $confidenceResult = $this->calculateConfidenceScore($phase2Result, 2);
            
            // Log confidence score if debug logging is enabled
            $this->logConfidenceScore($url, 2, $confidenceResult);
            
            return [
                'status' => 'success',
                'phase' => 2,
                'confidence' => $confidenceResult['score'],
                'confidenceLevel' => $confidenceResult['level'],
                'confidenceDetails' => $confidenceResult['factors'],
                'data' => $phase2Result,
                'timestamp' => date('c')
            ];
        }
        
        // Both phases failed
        throw new Exception('No recipe found on this page.');
    }
    
    /**
     * Fetch URL content with enhanced bot-detection evasion
     */
    private function fetchUrl($url) {
        $ch = curl_init();
        
        // Get session-consistent user-agent
        $userAgent = $this->getRandomUserAgent();
        
        // Setup cookie file for session persistence
        // SECURITY: Use random filename to prevent prediction
        if ($this->cookieFile === null) {
            $randomSuffix = bin2hex(random_bytes(8));
            $this->cookieFile = sys_get_temp_dir() . '/cleanplate_cookie_' . $randomSuffix . '.txt';
            
            // Clean up cookie file on shutdown
            $cookieFile = $this->cookieFile;
            register_shutdown_function(function() use ($cookieFile) {
                if (file_exists($cookieFile)) {
                    @unlink($cookieFile);
                }
            });
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => self::MAX_REDIRECTS,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $userAgent,
            // SECURITY: Enable SSL verification (critical for security)
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => '',  // Accept gzip, deflate, br
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,  // HTTP/2 for modern browsers
            
            // Cookie persistence for session tracking
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            
            // Comprehensive browser-like headers
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Accept-Encoding: gzip, deflate, br',
                'DNT: 1',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: none',
                'Sec-Fetch-User: ?1',
                'Cache-Control: max-age=0',
                'Referer: https://www.google.com/',
            ]
        ]);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        
        curl_close($ch);
        
        if ($html === false) {
            // SECURITY: Provide helpful but secure error messages for SSL issues
            if (in_array($errno, [60, 51, 58, 77])) {
                // SSL certificate errors (CURLE_SSL_CACERT, CURLE_PEER_FAILED_VERIFICATION, etc.)
                throw new Exception('SSL certificate verification failed. The website may have an invalid or expired certificate.');
            }
            throw new Exception('Could not fetch URL: ' . $error);
        }
        
        // Enhanced error detection
        if ($httpCode === 403 || $httpCode === 429) {
            throw new Exception('Access denied by website (HTTP ' . $httpCode . ')');
        }
        
        if ($httpCode >= 400) {
            throw new Exception('Failed to fetch URL (HTTP ' . $httpCode . ')');
        }
        
        // Detect Cloudflare challenges
        if (stripos($html, 'cf-browser-verification') !== false || 
            stripos($html, 'Checking your browser') !== false) {
            throw new Exception('CLOUDFLARE_BLOCK: Website is using Cloudflare protection');
        }
        
        // Detect JavaScript-required pages
        if (stripos($html, 'Please enable JavaScript') !== false && strlen($html) < 5000) {
            throw new Exception('JAVASCRIPT_REQUIRED: This page requires JavaScript rendering');
        }
        
        return $html;
    }
    
    /**
     * Phase 1: Extract recipe from JSON-LD structured data
     */
    private function extractFromJsonLd($html, $url) {
        // Find all JSON-LD script tags
        preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches);
        
        if (empty($matches[1])) {
            return null;
        }
        
        // Parse each JSON-LD block
        foreach ($matches[1] as $jsonString) {
            $data = json_decode($jsonString, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }
            
            // Handle both single objects and arrays
            // If @graph exists, use it; if root is array, use it directly; otherwise wrap single object
            if (isset($data['@graph'])) {
                $items = $data['@graph'];
            } elseif (isset($data[0]) && is_array($data[0])) {
                // Root is already an array of items
                $items = $data;
            } else {
                // Single object
                $items = [$data];
            }
            
            foreach ($items as $item) {
                if ($this->isRecipeObject($item)) {
                    return $this->normalizeRecipeData($item, $url);
                }
            }
        }
        
        return null;
    }
    
    /**
     * Check if JSON-LD object is a Recipe
     */
    private function isRecipeObject($data) {
        if (!is_array($data)) {
            return false;
        }
        
        $type = $data['@type'] ?? '';
        
        if (is_array($type)) {
            return in_array('Recipe', $type);
        }
        
        return $type === 'Recipe';
    }
    
    /**
     * Normalize JSON-LD recipe data to standard format
     */
    private function normalizeRecipeData($recipe, $url) {
        $normalized = [
            'title' => $this->cleanText($recipe['name'] ?? 'Untitled Recipe'),
            'source' => [
                'url' => $url,
                'siteName' => $this->extractDomain($url)
            ],
            'ingredients' => [],
            'instructions' => [],
            'metadata' => []
        ];
        
        // Extract ingredients
        if (isset($recipe['recipeIngredient']) && is_array($recipe['recipeIngredient'])) {
            $normalized['ingredients'] = array_map(function($ingredient) {
                return $this->formatIngredientQuantities($this->cleanText($ingredient));
            }, $recipe['recipeIngredient']);
        }
        
        // Extract instructions
        if (isset($recipe['recipeInstructions'])) {
            $normalized['instructions'] = $this->extractInstructions($recipe['recipeInstructions']);
        }
        
        // Extract metadata
        if (isset($recipe['prepTime'])) {
            $normalized['metadata']['prepTime'] = $this->formatDuration($recipe['prepTime']);
        }
        if (isset($recipe['cookTime'])) {
            $normalized['metadata']['cookTime'] = $this->formatDuration($recipe['cookTime']);
        }
        if (isset($recipe['totalTime'])) {
            $normalized['metadata']['totalTime'] = $this->formatDuration($recipe['totalTime']);
        }
        if (isset($recipe['recipeYield'])) {
            $normalized['metadata']['servings'] = is_array($recipe['recipeYield']) 
                ? $recipe['recipeYield'][0] 
                : $recipe['recipeYield'];
        }
        if (isset($recipe['image'])) {
            $normalized['metadata']['imageUrl'] = $this->extractImageUrl($recipe['image']);
        }
        
        // Apply post-processing filter to remove navigation/header junk
        if ($this->ingredientFilter) {
            $originalIngredientCount = count($normalized['ingredients']);
            $originalInstructionCount = count($normalized['instructions']);
            
            $normalized['ingredients'] = $this->ingredientFilter->filterIngredients($normalized['ingredients']);
            $normalized['instructions'] = $this->ingredientFilter->filterInstructions($normalized['instructions']);
            
            // Calculate and store quality ratio for confidence bonus
            if ($originalIngredientCount > 0) {
                $ingredientQualityRatio = count($normalized['ingredients']) / $originalIngredientCount;
                $normalized['metadata']['ingredientQualityRatio'] = $ingredientQualityRatio;
            }
            if ($originalInstructionCount > 0) {
                $instructionQualityRatio = count($normalized['instructions']) / $originalInstructionCount;
                $normalized['metadata']['instructionQualityRatio'] = $instructionQualityRatio;
            }
        }
        
        return $normalized;
    }
    
    /**
     * Extract instructions from various JSON-LD formats
     */
    private function extractInstructions($instructions) {
        $result = [];
        
        if (is_string($instructions)) {
            return [$this->cleanText($instructions)];
        }
        
        if (!is_array($instructions)) {
            return [];
        }
        
        foreach ($instructions as $instruction) {
            if (is_string($instruction)) {
                $result[] = $this->cleanText($instruction);
            } elseif (is_array($instruction)) {
                if (isset($instruction['text'])) {
                    $result[] = $this->cleanText($instruction['text']);
                } elseif (isset($instruction['itemListElement'])) {
                    foreach ($instruction['itemListElement'] as $step) {
                        if (is_string($step)) {
                            $result[] = $this->cleanText($step);
                        } elseif (isset($step['text'])) {
                            $result[] = $this->cleanText($step['text']);
                        }
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Phase 2: Extract recipe from DOM heuristics
     */
    private function extractFromDom($html, $url) {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        // Use proper UTF-8 encoding without deprecated mb_convert_encoding
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Try to find title
        $title = $this->findTitle($xpath);
        
        // Try to find ingredients
        $ingredients = $this->findIngredients($xpath);
        
        // Try to find instructions
        $instructions = $this->findInstructions($xpath);
        
        // Need at least ingredients or instructions to consider it a valid recipe
        if (empty($ingredients) && empty($instructions)) {
            return null;
        }
        
        // Apply post-processing filter to remove navigation/header junk
        if ($this->ingredientFilter) {
            $originalIngredientCount = count($ingredients);
            $originalInstructionCount = count($instructions);
            
            $ingredients = $this->ingredientFilter->filterIngredients($ingredients);
            $instructions = $this->ingredientFilter->filterInstructions($instructions);
            
            // Calculate and store quality ratio for confidence bonus
            $metadata = [];
            if ($originalIngredientCount > 0) {
                $ingredientQualityRatio = count($ingredients) / $originalIngredientCount;
                $metadata['ingredientQualityRatio'] = $ingredientQualityRatio;
            }
            if ($originalInstructionCount > 0) {
                $instructionQualityRatio = count($instructions) / $originalInstructionCount;
                $metadata['instructionQualityRatio'] = $instructionQualityRatio;
            }
        } else {
            $metadata = [];
        }
        
        return [
            'title' => $title ?: 'Untitled Recipe',
            'source' => [
                'url' => $url,
                'siteName' => $this->extractDomain($url)
            ],
            'ingredients' => $ingredients,
            'instructions' => $instructions,
            'metadata' => $metadata
        ];
    }
    
    /**
     * Calculate confidence score for extracted recipe data
     * 
     * @param array $data Normalized recipe data
     * @param int $phase Extraction phase (1 = JSON-LD, 2 = DOM scraping)
     * @return array ['score' => int, 'level' => string, 'factors' => array]
     */
    private function calculateConfidenceScore($data, $phase) {
        $score = 0;
        $maxScore = 100;
        $factors = [];
        
        // Validate and normalize phase value
        // Default to Phase 2 if phase is invalid
        if (!in_array($phase, [1, 2], true)) {
            $phase = 2;
        }
        
        // Phase baseline (40 points for Phase 1, 20 for Phase 2)
        // Structured data (Phase 1) is inherently more reliable
        $phasePoints = ($phase === 1) ? 40 : 20;
        $score += $phasePoints;
        $factors['phase'] = [
            'value' => $phase,
            'points' => $phasePoints,
            'max' => 40
        ];
        
        // Title quality (10 points)
        // Penalize generic, placeholder, or missing titles
        $titlePoints = 0;
        $title = isset($data['title']) ? trim($data['title']) : '';
        $genericTitles = [
            'Untitled Recipe', 'Recipe', 'Untitled', '',
            'No Title', 'Unknown Recipe', 'Recipe Title',
            'New Recipe', 'Default Recipe'
        ];
        
        // Check if title is non-empty and not a generic placeholder
        if (!empty($title) && !in_array($title, $genericTitles, true)) {
            $titlePoints = 10;
        }
        $score += $titlePoints;
        $factors['title'] = [
            'value' => $title,
            'points' => $titlePoints,
            'max' => 10
        ];
        
        // Ingredients (20 points)
        // Ensure ingredients is an array and count items
        $ingredients = $data['ingredients'] ?? [];
        if (!is_array($ingredients)) {
            $ingredients = [];
        }
        $ingredientCount = count($ingredients);
        $ingredientPoints = 0;
        if ($ingredientCount >= 5) {
            $ingredientPoints = 20;
        } elseif ($ingredientCount >= 2) {
            $ingredientPoints = 10;
        }
        $score += $ingredientPoints;
        $factors['ingredients'] = [
            'count' => $ingredientCount,
            'points' => $ingredientPoints,
            'max' => 20
        ];
        
        // Instructions (20 points)
        // Ensure instructions is an array and count steps
        $instructions = $data['instructions'] ?? [];
        if (!is_array($instructions)) {
            $instructions = [];
        }
        $instructionCount = count($instructions);
        $instructionPoints = 0;
        if ($instructionCount >= 5) {
            $instructionPoints = 20;
        } elseif ($instructionCount >= 2) {
            $instructionPoints = 10;
        }
        $score += $instructionPoints;
        $factors['instructions'] = [
            'count' => $instructionCount,
            'points' => $instructionPoints,
            'max' => 20
        ];
        
        // Metadata completeness (10 points - 2 per field)
        // Ensure metadata array exists, default to empty array
        $metadata = $data['metadata'] ?? [];
        if (!is_array($metadata)) {
            $metadata = [];
        }
        
        $metadataFields = ['prepTime', 'cookTime', 'totalTime', 'servings', 'imageUrl'];
        $metadataPresent = 0;
        $metadataPoints = 0;
        
        foreach ($metadataFields as $field) {
            $value = $metadata[$field] ?? null;
            
            // Treat empty strings as missing
            if (!empty($value) && is_scalar($value)) {
                $metadataPresent++;
                $metadataPoints += 2;
            }
        }
        
        $score += $metadataPoints;
        $factors['metadata'] = [
            'fieldsPresent' => $metadataPresent,
            'fieldsTotal' => count($metadataFields),
            'points' => $metadataPoints,
            'max' => 10
        ];
        
        // Quality bonuses and penalties
        $qualityAdjustments = 0;
        
        // Ingredient quality bonus (only if we have ingredients)
        if ($ingredientCount > 0) {
            $ingredientQuality = $this->validateIngredientQuality($ingredients);
            if ($ingredientQuality['hasGoodMeasurements']) {
                $qualityAdjustments += 5;
                $factors['ingredientQuality'] = [
                    'hasMeasurements' => true,
                    'percentWithMeasurements' => $ingredientQuality['percentWithMeasurements'],
                    'points' => 5,
                    'max' => 5
                ];
            }
        }
        
        // Instruction quality check (only if we have instructions)
        if ($instructionCount > 0) {
            $instructionQuality = $this->validateInstructionQuality($instructions);
            $qualityAdjustments += $instructionQuality['points'];
            $factors['instructionQuality'] = [
                'hasActionVerbs' => $instructionQuality['hasActionVerbs'],
                'isSingleParagraph' => $instructionQuality['isSingleParagraph'],
                'points' => $instructionQuality['points'],
                'max' => 3
            ];
        }
        
        // Data quality bonus (post-processing filter effectiveness)
        // Award bonus if ≥95% of scraped data was valid (low junk removal)
        $dataQualityBonus = 0;
        $ingredientRatio = $data['metadata']['ingredientQualityRatio'] ?? null;
        $instructionRatio = $data['metadata']['instructionQualityRatio'] ?? null;
        
        if ($ingredientRatio !== null && $ingredientRatio >= 0.95) {
            $dataQualityBonus += 1;
        }
        if ($instructionRatio !== null && $instructionRatio >= 0.95) {
            $dataQualityBonus += 1;
        }
        
        if ($dataQualityBonus > 0) {
            $qualityAdjustments += $dataQualityBonus;
            $factors['dataQuality'] = [
                'ingredientRatio' => $ingredientRatio,
                'instructionRatio' => $instructionRatio,
                'points' => $dataQualityBonus,
                'max' => 2
            ];
        }
        
        $score += $qualityAdjustments;
        $factors['qualityAdjustments'] = [
            'total' => $qualityAdjustments,
            'max' => 10
        ];
        
        // Cap score at max
        $score = min($score, $maxScore);
        
        // Determine confidence level based on score thresholds
        $level = 'low';
        if ($score >= 80) {
            $level = 'high';
        } elseif ($score >= 50) {
            $level = 'medium';
        }
        
        return [
            'score' => $score,
            'level' => $level,
            'factors' => $factors,
            'maxScore' => $maxScore
        ];
    }
    
    /**
     * Log confidence score for analysis (only if debug logging is enabled)
     * 
     * @param string $url Recipe URL
     * @param int $phase Extraction phase
     * @param array $confidenceResult Confidence calculation result
     */
    private function logConfidenceScore($url, $phase, $confidenceResult) {
        if (!$this->debugLogging) {
            return;
        }
        
        $score = $confidenceResult['score'];
        $level = $confidenceResult['level'];
        $factors = $confidenceResult['factors'];
        
        // Build compact factor breakdown
        $factorBreakdown = [];
        if (isset($factors['phase'])) {
            $factorBreakdown[] = "Phase={$factors['phase']['points']}/{$factors['phase']['max']}";
        }
        if (isset($factors['title'])) {
            $factorBreakdown[] = "Title={$factors['title']['points']}/{$factors['title']['max']}";
        }
        if (isset($factors['ingredients'])) {
            $ing = $factors['ingredients'];
            $factorBreakdown[] = "Ingredients={$ing['points']}/{$ing['max']}({$ing['count']})";
        }
        if (isset($factors['instructions'])) {
            $inst = $factors['instructions'];
            $factorBreakdown[] = "Instructions={$inst['points']}/{$inst['max']}({$inst['count']})";
        }
        if (isset($factors['metadata'])) {
            $meta = $factors['metadata'];
            $factorBreakdown[] = "Metadata={$meta['points']}/{$meta['max']}({$meta['fieldsPresent']}/{$meta['fieldsTotal']})";
        }
        if (isset($factors['qualityAdjustments'])) {
            $qa = $factors['qualityAdjustments'];
            if ($qa['total'] > 0) {
                $factorBreakdown[] = "Quality=+{$qa['total']}";
            }
        }
        
        // Extract domain for easier analysis
        $domain = parse_url($url, PHP_URL_HOST);
        
        // Format: [timestamp] CONFIDENCE | domain | phase | score | level | factors
        $logMessage = sprintf(
            "[%s] CONFIDENCE | %s | Phase %d | Score: %d/100 (%s) | %s",
            date('Y-m-d H:i:s'),
            $domain,
            $phase,
            $score,
            strtoupper($level),
            implode(', ', $factorBreakdown)
        );
        
        // Log to error log (typically goes to PHP error log or custom log file)
        error_log($logMessage);
    }
    
    /**
     * Validate ingredient quality by checking for measurements
     * 
     * @param array $ingredients List of ingredient strings
     * @return array Quality assessment with percentage and bonus eligibility
     */
    private function validateIngredientQuality($ingredients) {
        if (empty($ingredients)) {
            return [
                'hasGoodMeasurements' => false,
                'percentWithMeasurements' => 0,
                'count' => 0
            ];
        }
        
        // Pattern to match common cooking measurements
        // Includes: numbers, fractions, and common units
        $measurementPattern = '/\d+\s*(cup|tbsp|tsp|tablespoon|teaspoon|oz|ounce|lb|pound|g|gram|ml|milliliter|kg|kilogram|½|¼|⅓|⅔|¾|⅛|⅜|⅝|⅞)/i';
        
        $withMeasurements = 0;
        foreach ($ingredients as $ingredient) {
            if (preg_match($measurementPattern, $ingredient)) {
                $withMeasurements++;
            }
        }
        
        $total = count($ingredients);
        $percentage = ($total > 0) ? ($withMeasurements / $total) * 100 : 0;
        
        // Award bonus if >70% have measurements
        $hasGoodMeasurements = $percentage >= 70;
        
        return [
            'hasGoodMeasurements' => $hasGoodMeasurements,
            'percentWithMeasurements' => round($percentage, 1),
            'count' => $withMeasurements,
            'total' => $total
        ];
    }
    
    /**
     * Validate instruction quality
     * 
     * @param array $instructions List of instruction strings
     * @return array Quality assessment with points adjustment
     */
    private function validateInstructionQuality($instructions) {
        $points = 0;
        $hasActionVerbs = false;
        $isSingleParagraph = false;
        
        if (empty($instructions)) {
            return [
                'points' => 0,
                'hasActionVerbs' => false,
                'isSingleParagraph' => false
            ];
        }
        
        // Check for single-paragraph instruction (likely parsing error)
        // Penalize if only 1 instruction but it's very long
        if (count($instructions) === 1 && strlen($instructions[0]) > 500) {
            $isSingleParagraph = true;
            $points -= 5;
        }
        
        // Check for action verbs (indicates well-structured instructions)
        // Common cooking verbs that suggest proper recipe instructions
        $actionVerbs = [
            'mix', 'stir', 'whisk', 'beat', 'fold', 'blend',
            'bake', 'cook', 'roast', 'grill', 'fry', 'sauté', 'simmer', 'boil',
            'heat', 'warm', 'cool', 'chill', 'freeze',
            'chop', 'dice', 'mince', 'slice', 'cut', 'peel', 'grate',
            'combine', 'add', 'pour', 'place', 'spread', 'layer',
            'cover', 'wrap', 'seal', 'remove', 'drain', 'rinse'
        ];
        
        $verbPattern = '/\b(' . implode('|', $actionVerbs) . ')\b/i';
        
        // Check if instructions contain action verbs
        $verbCount = 0;
        foreach ($instructions as $instruction) {
            if (preg_match($verbPattern, $instruction)) {
                $verbCount++;
            }
        }
        
        // Award bonus if majority of instructions have action verbs
        if ($verbCount >= count($instructions) * 0.5) {
            $hasActionVerbs = true;
            $points += 3;
        }
        
        return [
            'points' => $points,
            'hasActionVerbs' => $hasActionVerbs,
            'isSingleParagraph' => $isSingleParagraph,
            'verbCount' => $verbCount
        ];
    }
    
    /**
     * Find recipe title from DOM
     */
    private function findTitle($xpath) {
        // Try h1 with recipe-related class or id
        $queries = [
            "//h1[contains(@class, 'recipe') or contains(@id, 'recipe')]",
            "//h1[contains(@class, 'title') or contains(@id, 'title')]",
            "//h1"
        ];
        
        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes->length > 0) {
                return $this->cleanText($nodes->item(0)->textContent);
            }
        }
        
        return null;
    }
    
    /**
     * Find ingredients list from DOM
     */
    private function findIngredients($xpath) {
        $ingredients = [];
        
        // Look for containers with "ingredient" in class or id
        $containers = $xpath->query("//*[contains(translate(@class, 'INGREDIENT', 'ingredient'), 'ingredient') or contains(translate(@id, 'INGREDIENT', 'ingredient'), 'ingredient')]");
        
        foreach ($containers as $container) {
            // Find all li or span elements within
            $items = $xpath->query(".//li | .//span[@class] | .//p", $container);
            
            foreach ($items as $item) {
                $text = $this->formatIngredientQuantities($this->cleanText($item->textContent));
                if (!empty($text) && strlen($text) > 2 && strlen($text) < 500) {
                    $ingredients[] = $text;
                }
            }
            
            // If we found ingredients, stop looking
            if (!empty($ingredients)) {
                break;
            }
        }
        
        return array_values(array_unique($ingredients));
    }
    
    /**
     * Find instructions from DOM
     */
    private function findInstructions($xpath) {
        $instructions = [];
        
        // Look for containers with "instruction" or "direction" or "method" in class or id
        $containers = $xpath->query("//*[contains(translate(@class, 'INSTRUCTION', 'instruction'), 'instruction') or contains(translate(@id, 'INSTRUCTION', 'instruction'), 'instruction') or contains(translate(@class, 'DIRECTION', 'direction'), 'direction') or contains(translate(@id, 'DIRECTION', 'direction'), 'direction') or contains(translate(@class, 'METHOD', 'method'), 'method') or contains(translate(@id, 'METHOD', 'method'), 'method')]");
        
        foreach ($containers as $container) {
            // Find all li or p elements within
            $items = $xpath->query(".//li | .//p", $container);
            
            foreach ($items as $item) {
                $text = $this->cleanText($item->textContent);
                if (!empty($text) && strlen($text) > 5 && strlen($text) < 1000) {
                    $instructions[] = $text;
                }
            }
            
            // If we found instructions, stop looking
            if (!empty($instructions)) {
                break;
            }
        }
        
        return array_values(array_unique($instructions));
    }
    
    /**
     * Helper: Extract domain from URL
     */
    private function extractDomain($url) {
        $parsed = parse_url($url);
        return $parsed['host'] ?? 'Unknown';
    }
    
    /**
     * Helper: Format ISO 8601 duration to readable format
     */
    private function formatDuration($duration) {
        if (!is_string($duration) || !preg_match('/^P/', $duration)) {
            return $duration;
        }
        
        $result = [];
        
        // Extract days (before T)
        if (preg_match('/(\d+)D/', $duration, $matches)) {
            $days = intval($matches[1]);
            if ($days > 0) {
                $result[] = $days . ' day' . ($days > 1 ? 's' : '');
            }
        }
        
        // Extract hours (after T)
        if (preg_match('/T.*?(\d+)H/', $duration, $matches)) {
            $hours = intval($matches[1]);
            if ($hours > 0) {
                $result[] = $hours . ' hour' . ($hours > 1 ? 's' : '');
            }
        }
        
        // Extract minutes (after T, careful not to confuse with months)
        if (preg_match('/T.*?(\d+)M/', $duration, $matches)) {
            $minutes = intval($matches[1]);
            if ($minutes > 0) {
                $result[] = $minutes . ' minute' . ($minutes > 1 ? 's' : '');
            }
        }
        
        return empty($result) ? $duration : implode(' ', $result);
    }
    
    /**
     * Helper: Extract image URL from various formats
     */
    private function extractImageUrl($image) {
        if (is_string($image)) {
            return $image;
        }
        if (is_array($image)) {
            if (isset($image['url'])) {
                return $image['url'];
            }
            if (isset($image[0])) {
                return is_string($image[0]) ? $image[0] : ($image[0]['url'] ?? null);
            }
        }
        return null;
    }
    
    /**
     * Initialize session storage for user-agent and domain tracking
     */
    private function initializeSession() {
        // Only start session if not in CLI mode
        if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Skip session initialization in CLI mode
        if (php_sapi_name() === 'cli') {
            return;
        }
        
        // Initialize user-agent storage
        if (!isset($_SESSION['user_agent'])) {
            $_SESSION['user_agent'] = $this->userAgents[array_rand($this->userAgents)];
        }
        
        // Initialize domain request tracking
        if (!isset($_SESSION['domain_requests'])) {
            $_SESSION['domain_requests'] = [];
        }
    }
    
    /**
     * Get consistent user-agent for this session
     */
    private function getRandomUserAgent() {
        // In CLI mode, just return random user-agent
        if (php_sapi_name() === 'cli' || !isset($_SESSION['user_agent'])) {
            return $this->userAgents[array_rand($this->userAgents)];
        }
        
        return $_SESSION['user_agent'];
    }
    
    /**
     * Enforce delay between requests to the same domain
     */
    private function enforcePerDomainDelay($url) {
        $domain = parse_url($url, PHP_URL_HOST);
        if (!$domain) {
            return;
        }
        
        // Skip delay enforcement in CLI mode (for testing)
        if (php_sapi_name() === 'cli') {
            return;
        }
        
        // Check if we've made a recent request to this domain
        if (isset($_SESSION['domain_requests'][$domain])) {
            $lastRequest = $_SESSION['domain_requests'][$domain];
            $elapsed = time() - $lastRequest;
            
            // If less than minimum delay, sleep for the remainder
            if ($elapsed < self::MIN_REQUEST_DELAY) {
                sleep(self::MIN_REQUEST_DELAY - $elapsed);
            }
        }
        
        // Update last request time for this domain
        $_SESSION['domain_requests'][$domain] = time();
    }
    
    /**
     * Clean text by decoding HTML entities and trimming whitespace
     * 
     * @param string $text Text to clean
     * @return string Cleaned text
     */
    private function cleanText($text) {
        if (!is_string($text)) {
            return $text;
        }
        
        // Decode HTML entities (e.g., &#34; to ", &amp; to &)
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Trim whitespace
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * Format ingredient quantities by converting decimals to fractions
     * 
     * @param string $ingredient Ingredient text
     * @return string Formatted ingredient text
     */
    private function formatIngredientQuantities($ingredient) {
        if (!is_string($ingredient)) {
            return $ingredient;
        }
        
        // Replace long decimals and common fraction decimals with fractions
        $ingredient = preg_replace_callback(
            '/\b(\d+\.)?(\d{3,})\b/',
            function($matches) {
                $full = $matches[0];
                $decimal = floatval($full);
                
                // Convert to fraction if it's a common one
                $fraction = $this->decimalToFraction($decimal);
                return $fraction !== null ? $fraction : number_format($decimal, 2);
            },
            $ingredient
        );
        
        return $ingredient;
    }
    
    /**
     * Convert decimal to common fraction
     * 
     * @param float $decimal Decimal value
     * @return string|null Fraction string or null if no match
     */
    private function decimalToFraction($decimal) {
        // Common cooking fractions with tolerance
        $fractions = [
            0.125 => '1/8',
            0.166667 => '1/6',
            0.2 => '1/5',
            0.25 => '1/4',
            0.333333 => '1/3',
            0.375 => '3/8',
            0.4 => '2/5',
            0.5 => '1/2',
            0.6 => '3/5',
            0.625 => '5/8',
            0.666667 => '2/3',
            0.75 => '3/4',
            0.8 => '4/5',
            0.833333 => '5/6',
            0.875 => '7/8',
        ];
        
        // Extract whole number and fractional part
        $whole = floor($decimal);
        $frac = $decimal - $whole;
        
        // Find matching fraction (within 0.01 tolerance)
        foreach ($fractions as $value => $fraction) {
            if (abs($frac - $value) < 0.01) {
                return $whole > 0 ? $whole . ' ' . $fraction : $fraction;
            }
        }
        
        return null;
    }
}
