<?php
/**
 * CleanPlate Recipe Parser API Endpoint
 * Accepts POST requests with recipe URLs and returns JSON responses
 */

// Load configuration system
require_once __DIR__ . '/../includes/Config.php';
Config::load();

// Configure PHP based on app settings
error_reporting(E_ALL);
ini_set('display_errors', Config::get('app.debug', false) ? '1' : '0');
ini_set('log_errors', 1);
set_time_limit(Config::get('scraper.timeouts.request', 30));

// Configuration: Enable confidence score logging
$debugConfidenceScoring = Config::env('CONFIDENCE_DEBUG', false);

// Include the parser class
require_once __DIR__ . '/../includes/RecipeParser.php';

// Set JSON response headers
header('Content-Type: application/json; charset=utf-8');

// CORS configuration
$allowedOrigins = Config::get('security.cors.allowed_origins', ['*']);
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . (in_array('*', $allowedOrigins) ? '*' : $origin));
}
$allowedMethods = Config::get('security.cors.allowed_methods', ['POST', 'OPTIONS']);
$allowedHeaders = Config::get('security.cors.allowed_headers', ['Content-Type']);
header('Access-Control-Allow-Methods: ' . implode(', ', $allowedMethods));
header('Access-Control-Allow-Headers: ' . implode(', ', $allowedHeaders));

// Security headers
$securityHeaders = Config::get('security.headers', []);
foreach ($securityHeaders as $header => $value) {
    header("$header: $value");
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondWithError(405, 'METHOD_NOT_ALLOWED', 'Only POST requests are allowed.');
}

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    respondWithError(400, 'INVALID_JSON', 'Invalid JSON in request body.');
}

// Extract and validate URL
$url = isset($input['url']) ? trim($input['url']) : '';

if (empty($url)) {
    respondWithError(400, 'MISSING_URL', 'Please provide a recipe URL.');
}

// SECURITY: Validate URL length to prevent buffer attacks
$maxUrlLength = Config::get('security.validation.max_url_length', 2048);
if (strlen($url) > $maxUrlLength) {
    respondWithError(400, 'INVALID_URL', "URL is too long (maximum $maxUrlLength characters).");
}

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    respondWithError(
        400, 
        'INVALID_URL', 
        'Please provide a valid URL starting with http:// or https://'
    );
}

// Validate URL scheme
$parsedUrl = parse_url($url);
if (!isset($parsedUrl['scheme']) || !in_array($parsedUrl['scheme'], ['http', 'https'])) {
    respondWithError(400, 'INVALID_URL', 'URL must use http:// or https://');
}

// SECURITY: Enhanced SSRF protection - block internal/private IPs
if (isset($parsedUrl['host'])) {
    // Block localhost variations
    if (in_array(strtolower($parsedUrl['host']), ['localhost', '127.0.0.1', '::1', '0.0.0.0'])) {
        respondWithError(403, 'FORBIDDEN', 'Cannot access internal or private network addresses.');
    }
    
    // Resolve and check IP addresses
    $ips = @gethostbynamel($parsedUrl['host']) ?: [];
    
    if (empty($ips)) {
        // Try single result
        $ip = @gethostbyname($parsedUrl['host']);
        if ($ip && $ip !== $parsedUrl['host']) {
            $ips = [$ip];
        }
    }
    
    foreach ($ips as $ip) {
        // Check for private/reserved ranges (IPv4 and IPv6)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            respondWithError(403, 'FORBIDDEN', 'Cannot access internal or private network addresses.');
        }
        
        // Additional edge case checks
        if (preg_match('/^(127\.|10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.|169\.254\.)/i', $ip)) {
            respondWithError(403, 'FORBIDDEN', 'Cannot access internal or private network addresses.');
        }
        
        // Check IPv6 loopback and link-local
        if (preg_match('/^(::1|fe80:|fc00:|fd00:)/i', $ip)) {
            respondWithError(403, 'FORBIDDEN', 'Cannot access internal or private network addresses.');
        }
    }
}

// Simple rate limiting (session-based)
if (Config::get('security.rate_limit.enabled', true)) {
    session_start();
    $rateLimit = Config::get('security.rate_limit.requests', 10);
    $ratePeriod = Config::get('security.rate_limit.period', 60);

    if (!isset($_SESSION['api_requests'])) {
        $_SESSION['api_requests'] = [];
    }

    $now = time();
    $_SESSION['api_requests'] = array_filter(
        $_SESSION['api_requests'],
        function($timestamp) use ($now, $ratePeriod) {
            return $timestamp > ($now - $ratePeriod);
        }
    );

    if (count($_SESSION['api_requests']) >= $rateLimit) {
        respondWithError(
            429,
            'RATE_LIMIT',
            'Too many requests. Please wait a moment and try again.',
            ['You can process up to ' . $rateLimit . ' recipes per minute.']
        );
    }

    $_SESSION['api_requests'][] = $now;
}

// Parse the recipe
try {
    // Enable debug logging if configured
    $parser = new RecipeParser($debugConfidenceScoring);
    $result = $parser->parse($url);
    
    respondWithSuccess($result);
    
} catch (Exception $e) {
    // SECURITY: Log errors safely without exposing sensitive details
    $logMessage = 'Recipe parsing error for domain: ' . (parse_url($url, PHP_URL_HOST) ?: 'unknown');
    error_log($logMessage);
    
    // Only log stack traces in development
    if (getenv('APP_ENV') !== 'production') {
        error_log('Error details: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
    }
    
    // Return user-friendly error
    $errorCode = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
    $errorType = 'SERVER_ERROR';
    $userMessage = 'An unexpected error occurred while processing the recipe.';
    $suggestions = [];
    
    // Map specific exceptions to error types
    if (strpos($e->getMessage(), 'Could not fetch') !== false) {
        $errorCode = 502;
        $errorType = 'NETWORK_ERROR';
        $userMessage = 'Unable to access the recipe website. It may be temporarily unavailable.';
        $suggestions = ['Check if the website is working in your browser', 'Try again in a few moments'];
    } elseif (strpos($e->getMessage(), 'No recipe found') !== false) {
        $errorCode = 404;
        $errorType = 'NO_RECIPE_FOUND';
        $userMessage = 'No recipe content detected on this page.';
        $suggestions = [
            'Make sure the URL points to a recipe page (not a blog post or listing)',
            'Try clicking "Jump to Recipe" and using that URL',
            'Some sites may not be supported yet'
        ];
    } elseif (strpos($e->getMessage(), 'Access denied') !== false || strpos($e->getMessage(), '403') !== false) {
        $errorCode = 403;
        $errorType = 'ACCESS_DENIED';
        $userMessage = 'This website is blocking automated access.';
        $suggestions = [
            'Try copying the recipe manually',
            'Look for a print-friendly version of the page',
            'Some sites actively prevent recipe extraction'
        ];
    } elseif (strpos($e->getMessage(), 'CLOUDFLARE_BLOCK') !== false) {
        $errorCode = 403;
        $errorType = 'CLOUDFLARE_BLOCK';
        $userMessage = 'This website uses Cloudflare protection that cannot be bypassed.';
        $suggestions = [
            'Visit the page in your browser and manually copy the recipe',
            'Look for a print-friendly version (often bypasses protection)',
            'Try the website\'s mobile version which may have lighter protection'
        ];
    } elseif (strpos($e->getMessage(), 'JAVASCRIPT_REQUIRED') !== false) {
        $errorCode = 422;
        $errorType = 'JAVASCRIPT_REQUIRED';
        $userMessage = 'This page requires JavaScript to load recipe content.';
        $suggestions = [
            'This site loads recipes dynamically and cannot be scraped',
            'Try looking for a print version of the page',
            'Copy the recipe content manually from your browser'
        ];
    }
    
    respondWithError($errorCode, $errorType, $userMessage, $suggestions);
}

/**
 * Send a successful JSON response
 */
function respondWithSuccess($data) {
    http_response_code(200);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send an error JSON response
 */
function respondWithError($httpCode, $errorCode, $userMessage, $suggestions = []) {
    http_response_code($httpCode);
    echo json_encode([
        'status' => 'error',
        'code' => $errorCode,
        'userMessage' => $userMessage,
        'suggestions' => $suggestions,
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
