<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../includes/Config.php';
    Config::load();
    
    echo json_encode([
        'status' => 'ok',
        'message' => 'Configuration system is working',
        'config' => [
            'app_name' => Config::get('app.name'),
            'app_env' => Config::get('app.env'),
            'app_debug' => Config::get('app.debug'),
            'scraper_timeout' => Config::get('scraper.timeouts.request'),
            'scraper_min_delay' => Config::get('scraper.timeouts.min_delay'),
            'rate_limit_enabled' => Config::get('security.rate_limit.enabled'),
            'rate_limit_requests' => Config::get('security.rate_limit.requests'),
            'cors_origins' => Config::get('security.cors.allowed_origins'),
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}
