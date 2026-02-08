<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing Config load...\n";

try {
    require_once __DIR__ . '/../includes/Config.php';
    echo "Config.php required\n";
    
    Config::load();
    echo "Config loaded\n";
    
    $appName = Config::get('app.name');
    echo "App name: $appName\n";
    
    echo "SUCCESS!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
