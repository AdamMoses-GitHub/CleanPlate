<?php

/**
 * IngredientFilter
 * 
 * Post-processing filter to remove non-food items from extracted recipe data.
 * Removes navigation items, section headers, and other junk that may be 
 * incorrectly scraped during DOM extraction.
 * 
 * @version 1.0.0
 * @date 2026-02-06
 */
class IngredientFilter {
    
    // Strictness levels
    const STRICTNESS_LENIENT = 1;  // threshold 0, allow almost everything
    const STRICTNESS_BALANCED = 2; // threshold 2, balanced filtering (default)
    const STRICTNESS_STRICT = 3;   // threshold 5, aggressive filtering
    
    private $strictness;
    private $debugMode;
    private $filteredItems;
    
    // Navigation/UI patterns to reject
    private $navigationPatterns = [
        '/^(home|recipes|search|menu|navigation|nav|share|print|save|comment|rating|review)s?$/i',
        '/^(back to|return to|view all|see more|read more|show more|load more)/i',
        '/^(follow us|subscribe|sign up|log in|login|register)/i',
        '/^(facebook|twitter|instagram|pinterest|youtube|tiktok|social)/i',
        '/^(advertisement|sponsored|affiliate|disclosure)/i',
        '/^(privacy|cookies|terms|contact|about|help|faq)$/i',
        '/^(skip to|jump to|go to)/i',
        '/^(email|newsletter|updates)/i',
    ];
    
    // Section header patterns to reject
    private $headerPatterns = [
        '/^(ingredients?|directions?|instructions?|steps?|method|preparation|notes?|tips?):?\s*$/i',
        '/^(equipment|tools|supplies|you\'?ll? needs?):?\s*$/i',
        '/^(nutrition|nutritional info|calories|servings?):?\s*$/i',
        '/^(video|watch|photos?|images?|gallery):?\s*$/i',
        '/^(related|similar|more|other) (recipes?|posts?|articles?):?\s*$/i',
        '/^(print|save|share|rate) (recipe|this):?\s*$/i',
        '/^(for the|for serving|to serve|to garnish):?\s*$/i',
        '/^(optional|recommended|suggested):?\s*$/i',
    ];
    
    // Food/ingredient indicators (positive signals)
    private $foodIndicators = [
        // Measurements
        '/\b(cup|tablespoon|teaspoon|tbsp|tsp|oz|ounce|pound|lb|gram|kg|ml|liter|pinch|dash|handful)s?\b/i',
        '/\b\d+\s*(\/\s*\d+)?\s*(cup|tbsp|tsp|oz|lb|g|kg|ml|l)\b/i',
        // Quantities
        '/\b\d+\s*(to|\-|or)\s*\d+\b/',
        '/^\d+/',
        // Preparations
        '/\b(chopped|diced|sliced|minced|grated|shredded|crushed|ground|fresh|dried|frozen|cooked|raw|whole|halved|quartered)/i',
        // Common food words
        '/\b(chicken|beef|pork|fish|egg|milk|cheese|butter|oil|salt|pepper|sugar|flour|water|onion|garlic|tomato|potato|rice|pasta)/i',
        // Punctuation (food items often have commas, dashes, parentheses)
        '/[,\-\(\)]/',
    ];
    
    // Cooking action verbs (for instructions)
    private $cookingActions = [
        '/\b(add|mix|stir|whisk|beat|fold|combine|blend|pour|sprinkle|season)/i',
        '/\b(cook|bake|roast|grill|fry|sauté|simmer|boil|steam|broil)/i',
        '/\b(heat|warm|cool|chill|freeze|thaw|refrigerate)/i',
        '/\b(cut|chop|dice|slice|mince|grate|shred|peel|trim|core)/i',
        '/\b(place|arrange|spread|layer|transfer|remove|drain|strain)/i',
        '/\b(serve|garnish|top|drizzle|sprinkle|dust|present)/i',
        '/\b(preheat|prepare|set|adjust|reduce|increase)/i',
        '/\b(let|allow|wait|rest|stand|sit)/i',
    ];
    
    /**
     * Constructor
     * 
     * @param int $strictness Strictness level (default: BALANCED)
     * @param bool $debugMode Enable debug logging
     */
    public function __construct($strictness = self::STRICTNESS_BALANCED, $debugMode = false) {
        $this->strictness = $strictness;
        $this->debugMode = $debugMode;
        $this->filteredItems = [];
    }
    
    /**
     * Filter ingredients list
     * 
     * @param array $ingredients Raw ingredients array
     * @return array Filtered ingredients array
     */
    public function filterIngredients($ingredients) {
        if (!is_array($ingredients) || empty($ingredients)) {
            return $ingredients;
        }
        
        $filtered = [];
        $this->filteredItems = [];
        
        foreach ($ingredients as $item) {
            if (!is_string($item)) {
                continue;
            }
            
            $item = trim($item);
            
            if (empty($item)) {
                continue;
            }
            
            if ($this->isValidIngredient($item)) {
                $filtered[] = $item;
            } else {
                $this->filteredItems[] = [
                    'item' => $item,
                    'reason' => $this->getFilterReason($item),
                ];
            }
        }
        
        if ($this->debugMode && !empty($this->filteredItems)) {
            error_log("IngredientFilter: Removed " . count($this->filteredItems) . " items:");
            foreach ($this->filteredItems as $filtered) {
                error_log("  - '{$filtered['item']}' (reason: {$filtered['reason']})");
            }
        }
        
        return $filtered;
    }
    
    /**
     * Filter instructions list
     * 
     * @param array $instructions Raw instructions array
     * @return array Filtered instructions array
     */
    public function filterInstructions($instructions) {
        if (!is_array($instructions) || empty($instructions)) {
            return $instructions;
        }
        
        $filtered = [];
        $instructionFilteredItems = [];
        
        foreach ($instructions as $item) {
            if (!is_string($item)) {
                continue;
            }
            
            $item = trim($item);
            
            if (empty($item)) {
                continue;
            }
            
            if ($this->isValidInstruction($item)) {
                $filtered[] = $item;
            } else {
                $instructionFilteredItems[] = [
                    'item' => $item,
                    'reason' => $this->getFilterReason($item),
                ];
            }
        }
        
        if ($this->debugMode && !empty($instructionFilteredItems)) {
            error_log("IngredientFilter: Removed " . count($instructionFilteredItems) . " instruction items:");
            foreach ($instructionFilteredItems as $filtered) {
                error_log("  - '{$filtered['item']}' (reason: {$filtered['reason']})");
            }
        }
        
        return $filtered;
    }
    
    /**
     * Check if item is a valid ingredient
     * 
     * @param string $item Ingredient text
     * @return bool True if valid ingredient
     */
    public function isValidIngredient($item) {
        $score = 0;
        
        // Immediate rejections
        if ($this->isNavigationItem($item)) {
            return false;
        }
        
        if ($this->isSectionHeader($item)) {
            return false;
        }
        
        // Length checks
        $length = mb_strlen($item);
        
        // Too short (likely junk)
        if ($length < 3) {
            return false;
        }
        
        // Single word in ALL CAPS (likely header)
        if (str_word_count($item) === 1 && $item === mb_strtoupper($item)) {
            return false;
        }
        
        // Positive scoring
        if ($this->containsFoodIndicators($item)) {
            $score += 3;
        }
        
        // Contains numbers (measurements, quantities)
        if (preg_match('/\d/', $item)) {
            $score += 2;
        }
        
        // Contains comma (compound ingredients)
        if (strpos($item, ',') !== false) {
            $score += 1;
        }
        
        // Has reasonable length (10-200 chars is typical)
        if ($length >= 10 && $length <= 200) {
            $score += 1;
        }
        
        // Negative scoring
        // All uppercase (likely headers)
        if ($item === mb_strtoupper($item) && $length > 5) {
            $score -= 3;
        }
        
        // Starts with common navigation words
        if (preg_match('/^(click|tap|view|see|browse|explore)\b/i', $item)) {
            $score -= 2;
        }
        
        // Apply strictness threshold
        $threshold = $this->getScoreThreshold();
        
        return $score >= $threshold;
    }
    
    /**
     * Check if item is a valid instruction
     * 
     * @param string $item Instruction text
     * @return bool True if valid instruction
     */
    public function isValidInstruction($item) {
        // Immediate rejections
        if ($this->isNavigationItem($item)) {
            return false;
        }
        
        if ($this->isSectionHeader($item)) {
            return false;
        }
        
        $length = mb_strlen($item);
        
        // Too short
        if ($length < 10) {
            return false;
        }
        
        // All caps single word
        if (str_word_count($item) === 1 && $item === mb_strtoupper($item)) {
            return false;
        }
        
        // Should contain cooking action verbs
        $hasCookingAction = false;
        foreach ($this->cookingActions as $pattern) {
            if (preg_match($pattern, $item)) {
                $hasCookingAction = true;
                break;
            }
        }
        
        // Should look like a sentence (starts with capital, ends with period, or has multiple words)
        $looksLikeSentence = (
            preg_match('/^[A-Z]/', $item) || // Starts with capital
            preg_match('/\.$/', $item) ||    // Ends with period
            str_word_count($item) >= 3       // Has multiple words
        );
        
        return $hasCookingAction || $looksLikeSentence;
    }
    
    /**
     * Check if item is a navigation/UI element
     * 
     * @param string $item Text to check
     * @return bool True if navigation item
     */
    public function isNavigationItem($item) {
        foreach ($this->navigationPatterns as $pattern) {
            if (preg_match($pattern, $item)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if item is a section header
     * 
     * @param string $item Text to check
     * @return bool True if section header
     */
    public function isSectionHeader($item) {
        foreach ($this->headerPatterns as $pattern) {
            if (preg_match($pattern, $item)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if item contains food indicators
     * 
     * @param string $item Text to check
     * @return bool True if contains food indicators
     */
    public function containsFoodIndicators($item) {
        foreach ($this->foodIndicators as $pattern) {
            if (preg_match($pattern, $item)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get score threshold based on strictness level
     * 
     * @return int Score threshold
     */
    private function getScoreThreshold() {
        switch ($this->strictness) {
            case self::STRICTNESS_LENIENT:
                return 0; // Allow almost everything
            case self::STRICTNESS_STRICT:
                return 5; // Aggressive filtering
            case self::STRICTNESS_BALANCED:
            default:
                return 2; // Balanced (default)
        }
    }
    
    /**
     * Get human-readable reason why item was filtered
     * 
     * @param string $item Filtered item
     * @return string Reason
     */
    private function getFilterReason($item) {
        if ($this->isNavigationItem($item)) {
            return 'navigation/UI element';
        }
        
        if ($this->isSectionHeader($item)) {
            return 'section header';
        }
        
        if (mb_strlen($item) < 3) {
            return 'too short';
        }
        
        if (str_word_count($item) === 1 && $item === mb_strtoupper($item)) {
            return 'single word all caps';
        }
        
        if ($item === mb_strtoupper($item) && mb_strlen($item) > 5) {
            return 'all caps (likely header)';
        }
        
        return 'low confidence score';
    }
    
    /**
     * Get list of filtered items (for debugging)
     * 
     * @return array Filtered items with reasons
     */
    public function getFilteredItems() {
        return $this->filteredItems;
    }
    
    /**
     * Get quality ratio of ingredients
     * 
     * @param array $originalIngredients Original ingredient list
     * @param array $filteredIngredients Filtered ingredient list
     * @return float Quality ratio (0.0 - 1.0)
     */
    public function getQualityRatio($originalIngredients, $filteredIngredients) {
        $originalCount = count($originalIngredients);
        
        if ($originalCount === 0) {
            return 1.0;
        }
        
        $filteredCount = count($filteredIngredients);
        return $filteredCount / $originalCount;
    }
}
