<?php
/**
 * Security Configuration
 * 
 * CORS, rate limiting, validation rules, and security headers.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | CORS (Cross-Origin Resource Sharing)
    |--------------------------------------------------------------------------
    */
    'cors' => [
        'allowed_origins' => explode(',', getenv('ALLOWED_ORIGINS') ?: '*'),
        'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization'],
        'max_age' => 86400,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'enabled' => filter_var(getenv('RATE_LIMIT_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'requests' => (int)(getenv('RATE_LIMIT_REQUESTS') ?: 10),
        'period' => (int)(getenv('RATE_LIMIT_PERIOD') ?: 60), // seconds
        'storage' => 'memory', // memory or redis
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    */
    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Content-Security-Policy' => getenv('CSP_POLICY') ?: "default-src 'self'",
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Input Validation
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'max_url_length' => (int)(getenv('MAX_URL_LENGTH') ?: 2048),
        'max_request_size' => (int)(getenv('MAX_REQUEST_SIZE') ?: 10485760), // 10MB
        'allowed_protocols' => ['http', 'https'],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | SSRF Protection
    |--------------------------------------------------------------------------
    | 
    | Server-Side Request Forgery protection.
    | Blocks requests to private IP ranges and localhost.
    */
    'ssrf_protection' => [
        'enabled' => true,
        'blocked_ips' => [
            '127.0.0.0/8',      // localhost
            '10.0.0.0/8',       // private
            '172.16.0.0/12',    // private
            '192.168.0.0/16',   // private
            '169.254.0.0/16',   // link-local
            '::1/128',          // IPv6 localhost
            'fe80::/10',        // IPv6 link-local
            'fc00::/7',         // IPv6 private
        ],
        'blocked_domains' => [
            'localhost',
            'metadata.google.internal',
            '169.254.169.254', // AWS/GCP/Azure metadata
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Encryption
    |--------------------------------------------------------------------------
    */
    'encryption' => [
        'key' => getenv('APP_KEY') ?: '',
        'cipher' => 'AES-256-CBC',
    ],
];
