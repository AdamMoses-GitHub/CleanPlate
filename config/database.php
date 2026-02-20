<?php
/**
 * Database Configuration
 * 
 * Database connection settings for MySQL and other drivers.
 * Currently prepared for future use.
 */

// Helper: read env var from process env, $_SERVER, or $_ENV — works reliably
// with mod_php under Apache even when PassEnv is not configured.
function _db_env(string $key, $default = '') {
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    return $_SERVER[$key] ?? $_ENV[$key] ?? $default;
}

return [
    /*
    |--------------------------------------------------------------------------
    | Default Database Connection
    |--------------------------------------------------------------------------
    */
    'default' => _db_env('DB_CONNECTION', 'mysql'),
    
    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    */
    'connections' => [
        'mysql' => [
            'driver'    => 'mysql',
            'host'      => _db_env('DB_HOST',      'db'),
            'port'      => (int)_db_env('DB_PORT',  3306),
            'database'  => _db_env('DB_DATABASE',  'cleanplate'),
            'username'  => _db_env('DB_USERNAME',  'root'),
            'password'  => _db_env('DB_PASSWORD',  ''),
            'charset'   => _db_env('DB_CHARSET',   'utf8mb4'),
            'collation' => _db_env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix'    => _db_env('DB_PREFIX',    ''),
            'strict' => true,
            'engine' => 'InnoDB',
            
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
            ],
        ],
        
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => getenv('DB_SQLITE_PATH') ?: ':memory:',
            'prefix' => '',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Migration Settings
    |--------------------------------------------------------------------------
    */
    'migrations' => [
        'table' => 'migrations',
        'path' => __DIR__ . '/../database/migrations',
    ],
];
