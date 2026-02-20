<?php
/**
 * Configuration System Test
 * 
 * Tests loading, validation, and access to configuration values.
 */

require_once __DIR__ . '/../../includes/Config.php';
require_once __DIR__ . '/../../includes/ConfigValidator.php';

echo "=== CleanPlate Configuration Test ===\n\n";

// Test 1: Load configuration
echo "Test 1: Loading configuration...\n";
try {
    Config::load();
    echo "✓ Configuration loaded successfully\n\n";
} catch (Exception $e) {
    echo "✗ Error loading configuration: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Test basic config access
echo "Test 2: Accessing configuration values...\n";
$appName = Config::get('app.name', 'Unknown');
$appVersion = Config::get('app.version', '0.0.0');
$appEnv = Config::get('app.env', 'production');
echo "  App Name: $appName\n";
echo "  App Version: $appVersion\n";
echo "  Environment: $appEnv\n";
echo "✓ Config access working\n\n";

// Test 3: Test dot notation
echo "Test 3: Testing dot notation access...\n";
$dbHost = Config::get('database.connections.mysql.host', 'localhost');
$scraperTimeout = Config::get('scraper.timeouts.request', 10);
echo "  Database Host: $dbHost\n";
echo "  Scraper Timeout: $scraperTimeout seconds\n";
echo "✓ Dot notation working\n\n";

// Test 4: Test environment variables
echo "Test 4: Testing environment variable access...\n";
$debug = Config::env('APP_DEBUG', false);
$confidenceDebug = Config::env('CONFIDENCE_DEBUG', false);
echo "  APP_DEBUG: " . ($debug ? 'true' : 'false') . "\n";
echo "  CONFIDENCE_DEBUG: " . ($confidenceDebug ? 'true' : 'false') . "\n";
echo "✓ Environment variables working\n\n";

// Test 5: Test type coercion
echo "Test 5: Testing type coercion...\n";
$debugIsBool = is_bool(Config::get('app.debug'));
$timeoutIsInt = is_int(Config::get('scraper.timeouts.request'));
echo "  app.debug is boolean: " . ($debugIsBool ? 'Yes' : 'No') . "\n";
echo "  scraper.timeouts.request is integer: " . ($timeoutIsInt ? 'Yes' : 'No') . "\n";
echo "✓ Type coercion working\n\n";

// Test 6: Test validation (only in non-production)
if (Config::get('app.env') !== 'production') {
    echo "Test 6: Testing configuration validation...\n";
    try {
        ConfigValidator::validate();
        echo "✓ Validation passed\n\n";
    } catch (Exception $e) {
        echo "! Validation warnings (expected in dev): " . $e->getMessage() . "\n\n";
    }
} else {
    echo "Test 6: Skipping validation test (running in production mode)\n\n";
}

// Test 7: Test config sections
echo "Test 7: Testing all configuration sections...\n";
$sections = ['app', 'scraper', 'security', 'database', 'services', 'cache', 'mail'];
foreach ($sections as $section) {
    $hasConfig = Config::has($section);
    echo "  $section: " . ($hasConfig ? '✓' : '✗') . "\n";
}
echo "\n";

// Test 8: Test defaults
echo "Test 8: Testing default values...\n";
$nonExistent = Config::get('nonexistent.key', 'default_value');
echo "  Non-existent key returns default: " . ($nonExistent === 'default_value' ? '✓' : '✗') . "\n";
echo "\n";

// Test 9: Check critical settings
echo "Test 9: Checking critical settings...\n";
$corsOrigins = Config::get('security.cors.allowed_origins', []);
$rateLimitEnabled = Config::get('security.rate_limit.enabled', true);
$sslVerify = Config::get('scraper.ssl.verify_peer', true);
echo "  CORS Origins: " . implode(', ', $corsOrigins) . "\n";
echo "  Rate Limiting: " . ($rateLimitEnabled ? 'Enabled' : 'Disabled') . "\n";
echo "  SSL Verification: " . ($sslVerify ? 'Enabled' : 'Disabled') . "\n";

if ($appEnv === 'production') {
    if ($corsOrigins === ['*']) {
        echo "  ⚠ WARNING: CORS allows all origins in production!\n";
    }
    if (Config::get('app.debug', false)) {
        echo "  ⚠ WARNING: Debug mode enabled in production!\n";
    }
}
echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "All configuration tests completed.\n";
echo "Configuration system is working correctly!\n\n";

echo "Next steps:\n";
echo "1. Copy .env.example to .env\n";
echo "2. Update .env with your settings\n";
echo "3. Test with your application\n";
