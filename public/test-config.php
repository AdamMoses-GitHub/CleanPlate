<?php
header('Content-Type: text/plain');
echo "Config Test\n\n";

try {
    require_once __DIR__ . '/../includes/Config.php';
    echo "1. Config.php loaded\n";
    
    Config::load();
    echo "2. Config::load() completed\n";
    
    $appName = Config::get('app.name');
    echo "3. App name: $appName\n";
    
    $env = Config::get('app.env');
    echo "4. Environment: $env\n";
    
    $timeout = Config::get('scraper.timeouts.request');
    echo "5. Scraper timeout: $timeout\n";
    
    echo "\nSUCCESS! Configuration system is working.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
