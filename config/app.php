<?php
/**
 * Application Configuration
 * 
 * Core application settings including environment, paths, and basic options.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    */
    'name' => 'CleanPlate',
    
    /*
    |--------------------------------------------------------------------------
    | Application Version
    |--------------------------------------------------------------------------
    */
    'version' => '2.0.0',
    
    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    | 
    | This value determines the "environment" your application is currently
    | running in. Values: development, staging, production, testing
    */
    'env' => getenv('APP_ENV') ?: 'production',
    
    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    | 
    | When debug mode is enabled, detailed error messages with stack traces
    | will be shown on every error. NEVER enable this in production.
    */
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    
    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    */
    'timezone' => getenv('APP_TIMEZONE') ?: 'UTC',
    
    /*
    |--------------------------------------------------------------------------
    | Application Locale
    |--------------------------------------------------------------------------
    */
    'locale' => getenv('APP_LOCALE') ?: 'en_US',
    
    /*
    |--------------------------------------------------------------------------
    | Application Paths
    |--------------------------------------------------------------------------
    */
    'paths' => [
        'storage' => __DIR__ . '/../storage',
        'temp' => getenv('TEMP_DIR') ?: sys_get_temp_dir(),
        'logs' => getenv('LOG_DIR') ?: __DIR__ . '/../storage/logs',
        'cache' => __DIR__ . '/../storage/cache',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | PHP Settings
    |--------------------------------------------------------------------------
    */
    'php' => [
        'max_execution_time' => 120,
        'memory_limit' => '256M',
        'display_errors' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'level' => getenv('LOG_LEVEL') ?: 'info',
        'file' => 'cleanplate.log',
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Dashboard
    |--------------------------------------------------------------------------
    */
    'admin' => [
        // Read via all three env channels for reliability under Apache mod_php
        'username'             => (getenv('ADMIN_USERNAME') ?: ($_SERVER['ADMIN_USERNAME'] ?? $_ENV['ADMIN_USERNAME'] ?? 'admin')),
        'password'             => (getenv('ADMIN_PASSWORD') ?: ($_SERVER['ADMIN_PASSWORD'] ?? $_ENV['ADMIN_PASSWORD'] ?? 'changeme')),
        'cache_ttl_hours'      => (int)(getenv('CACHE_TTL_HOURS') ?: ($_SERVER['CACHE_TTL_HOURS'] ?? $_ENV['CACHE_TTL_HOURS'] ?? 24)),
        'featured_subset_size' => (int)(getenv('FEATURED_SUBSET_SIZE') ?: ($_SERVER['FEATURED_SUBSET_SIZE'] ?? $_ENV['FEATURED_SUBSET_SIZE'] ?? 5)),
    ],
];
