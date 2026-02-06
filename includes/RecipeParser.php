<?php
/**
 * RecipeParser Class
 * Two-phase recipe extraction: JSON-LD (Phase 1) and DOM fallback (Phase 2)
 */

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
            return [
                'status' => 'success',
                'phase' => 1,
                'data' => $phase1Result,
                'timestamp' => date('c')
            ];
        }
        
        // Phase 2: Fallback to DOM extraction
        $phase2Result = $this->extractFromDom($html, $url);
        if ($phase2Result !== null) {
            return [
                'status' => 'success',
                'phase' => 2,
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
        if ($this->cookieFile === null) {
            $this->cookieFile = sys_get_temp_dir() . '/cleanplate_cookie_' . session_id() . '.txt';
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => self::MAX_REDIRECTS,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_SSL_VERIFYPEER => false,  // Many recipe sites have cert issues
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
        
        curl_close($ch);
        
        if ($html === false) {
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
            $items = isset($data['@graph']) ? $data['@graph'] : [$data];
            
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
            'title' => $recipe['name'] ?? 'Untitled Recipe',
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
            $normalized['ingredients'] = $recipe['recipeIngredient'];
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
        
        return $normalized;
    }
    
    /**
     * Extract instructions from various JSON-LD formats
     */
    private function extractInstructions($instructions) {
        $result = [];
        
        if (is_string($instructions)) {
            return [$instructions];
        }
        
        if (!is_array($instructions)) {
            return [];
        }
        
        foreach ($instructions as $instruction) {
            if (is_string($instruction)) {
                $result[] = $instruction;
            } elseif (is_array($instruction)) {
                if (isset($instruction['text'])) {
                    $result[] = $instruction['text'];
                } elseif (isset($instruction['itemListElement'])) {
                    foreach ($instruction['itemListElement'] as $step) {
                        if (is_string($step)) {
                            $result[] = $step;
                        } elseif (isset($step['text'])) {
                            $result[] = $step['text'];
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
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
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
        
        return [
            'title' => $title ?: 'Untitled Recipe',
            'source' => [
                'url' => $url,
                'siteName' => $this->extractDomain($url)
            ],
            'ingredients' => $ingredients,
            'instructions' => $instructions,
            'metadata' => []
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
                return trim($nodes->item(0)->textContent);
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
                $text = trim($item->textContent);
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
                $text = trim($item->textContent);
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
        if (!is_string($duration) || !preg_match('/^PT/', $duration)) {
            return $duration;
        }
        
        $result = [];
        
        if (preg_match('/(\d+)H/', $duration, $matches)) {
            $result[] = $matches[1] . ' hour' . ($matches[1] > 1 ? 's' : '');
        }
        if (preg_match('/(\d+)M/', $duration, $matches)) {
            $result[] = $matches[1] . ' minute' . ($matches[1] > 1 ? 's' : '');
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
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
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
        if (isset($_SESSION['user_agent'])) {
            return $_SESSION['user_agent'];
        }
        
        // Fallback if session not initialized
        return $this->userAgents[array_rand($this->userAgents)];
    }
    
    /**
     * Enforce delay between requests to the same domain
     */
    private function enforcePerDomainDelay($url) {
        $domain = parse_url($url, PHP_URL_HOST);
        if (!$domain) {
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
}
