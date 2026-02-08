<?php
/**
 * Cache Configuration
 * 
 * Caching settings for improved performance.
 * Prepared for future use.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    | 
    | Supported: file, redis, memcached
    */
    'default' => getenv('CACHE_DRIVER') ?: 'file',
    
    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    */
    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => __DIR__ . '/../storage/cache',
            'ttl' => (int)(getenv('CACHE_TTL') ?: 3600),
        ],
        
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'password' => getenv('REDIS_PASSWORD') ?: null,
            'port' => (int)(getenv('REDIS_PORT') ?: 6379),
            'database' => (int)(getenv('REDIS_CACHE_DB') ?: 1),
        ],
        
        'memcached' => [
            'driver' => 'memcached',
            'servers' => [
                [
                    'host' => getenv('MEMCACHED_HOST') ?: '127.0.0.1',
                    'port' => (int)(getenv('MEMCACHED_PORT') ?: 11211),
                    'weight' => 100,
                ],
            ],
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    */
    'prefix' => getenv('CACHE_PREFIX') ?: 'cleanplate_',
];
