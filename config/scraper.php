<?php
/**
 * Web Scraper Configuration
 * 
 * Settings for the recipe scraper including timeouts, user agents,
 * rate limiting, and HTTP options.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Request Timeouts
    |--------------------------------------------------------------------------
    */
    'timeouts' => [
        'request' => (int)(getenv('SCRAPER_TIMEOUT') ?: 10),
        'min_delay' => (int)(getenv('SCRAPER_MIN_DELAY') ?: 2),
        'max_redirects' => (int)(getenv('SCRAPER_MAX_REDIRECTS') ?: 5),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | SSL Verification
    |--------------------------------------------------------------------------
    */
    'ssl' => [
        'verify_peer' => filter_var(getenv('SCRAPER_SSL_VERIFY') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'verify_host' => 2,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | HTTP Options
    |--------------------------------------------------------------------------
    */
    'http' => [
        'version' => CURL_HTTP_VERSION_2_0,
        'follow_redirects' => true,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | User Agents
    |--------------------------------------------------------------------------
    | 
    | Array of user agents to rotate through for requests.
    | This helps avoid being blocked by websites.
    */
    'user_agents' => [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | HTTP Headers
    |--------------------------------------------------------------------------
    */
    'headers' => [
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language' => 'en-US,en;q=0.9',
        'Accept-Encoding' => 'gzip, deflate, br',
        'DNT' => '1',
        'Connection' => 'keep-alive',
        'Upgrade-Insecure-Requests' => '1',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limiting' => [
        'enabled' => true,
        'per_domain_delay' => (int)(getenv('SCRAPER_MIN_DELAY') ?: 2),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Image Extraction
    |--------------------------------------------------------------------------
    */
    'images' => [
        'max_candidates' => 3,
        'min_score' => 60,
        'min_width' => 200,
        'min_height' => 200,
    ],
];
