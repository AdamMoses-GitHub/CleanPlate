<?php
/**
 * Configuration Validator
 * 
 * Validates configuration values and checks for required keys
 * based on the current environment.
 */
class ConfigValidator {
    
    /**
     * Validate configuration for current environment
     * 
     * @throws Exception
     */
    public static function validate(): void {
        $env = Config::env('APP_ENV', 'production');
        
        $rules = self::getRules($env);
        
        self::validateRequired($rules['required'] ?? []);
        self::validateForbidden($rules['forbidden'] ?? []);
        self::validateTypes($rules['types'] ?? []);
    }
    
    /**
     * Get validation rules for environment
     * 
     * @param string $env
     * @return array
     */
    private static function getRules(string $env): array {
        $rules = [
            'production' => [
                'required' => [
                    'app.env',
                    'security.cors.allowed_origins',
                    'security.rate_limit.enabled'
                ],
                'forbidden' => [
                    'security.cors.allowed_origins' => ['*'],
                    'app.debug' => [true, '1', 'true']
                ],
                'types' => [
                    'app.debug' => 'boolean',
                    'security.rate_limit.requests' => 'integer',
                    'security.rate_limit.period' => 'integer',
                    'scraper.timeouts.request' => 'integer'
                ]
            ],
            'development' => [
                'required' => ['app.env'],
                'forbidden' => [],
                'types' => []
            ],
            'testing' => [
                'required' => ['app.env'],
                'forbidden' => [],
                'types' => []
            ]
        ];
        
        return $rules[$env] ?? $rules['production'];
    }
    
    /**
     * Validate required configuration keys exist
     * 
     * @param array $required
     * @throws Exception
     */
    private static function validateRequired(array $required): void {
        $missing = [];
        
        foreach ($required as $key) {
            $value = Config::get($key);
            if ($value === null || $value === '') {
                $missing[] = $key;
            }
        }
        
        if (!empty($missing)) {
            throw new Exception(
                'Configuration Error: Missing required keys: ' . 
                implode(', ', $missing)
            );
        }
    }
    
    /**
     * Validate forbidden values are not set
     * 
     * @param array $forbidden
     * @throws Exception
     */
    private static function validateForbidden(array $forbidden): void {
        $errors = [];
        
        foreach ($forbidden as $key => $forbiddenValues) {
            $value = Config::get($key);
            
            foreach ($forbiddenValues as $forbiddenValue) {
                if ($value === $forbiddenValue || $value == $forbiddenValue) {
                    $errors[] = "$key cannot be set to " . var_export($forbiddenValue, true);
                }
            }
        }
        
        if (!empty($errors)) {
            throw new Exception(
                'Configuration Error: Invalid values: ' . 
                implode('; ', $errors)
            );
        }
    }
    
    /**
     * Validate configuration value types
     * 
     * @param array $types
     * @throws Exception
     */
    private static function validateTypes(array $types): void {
        $errors = [];
        
        foreach ($types as $key => $expectedType) {
            $value = Config::get($key);
            
            if ($value === null) {
                continue; // Skip validation if not set
            }
            
            $actualType = gettype($value);
            
            // Type mapping
            $typeMap = [
                'boolean' => 'boolean',
                'bool' => 'boolean',
                'integer' => 'integer',
                'int' => 'integer',
                'string' => 'string',
                'array' => 'array',
                'float' => 'double',
                'double' => 'double'
            ];
            
            $expectedType = $typeMap[$expectedType] ?? $expectedType;
            
            if ($actualType !== $expectedType) {
                $errors[] = "$key must be $expectedType, got $actualType";
            }
        }
        
        if (!empty($errors)) {
            throw new Exception(
                'Configuration Error: Type mismatches: ' . 
                implode('; ', $errors)
            );
        }
    }
    
    /**
     * Check if database configuration is complete
     * 
     * @return bool
     */
    public static function hasDatabaseConfig(): bool {
        $required = [
            'database.connections.mysql.host',
            'database.connections.mysql.database',
            'database.connections.mysql.username',
            'database.connections.mysql.password'
        ];
        
        foreach ($required as $key) {
            if (!Config::has($key)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if external service is configured
     * 
     * @param string $service Service name (e.g., 'openai', 'nutrition')
     * @return bool
     */
    public static function hasServiceConfig(string $service): bool {
        return Config::has("services.$service.api_key");
    }
    
    /**
     * Get list of all configured external services
     * 
     * @return array
     */
    public static function getConfiguredServices(): array {
        $services = Config::get('services', []);
        $configured = [];
        
        foreach (array_keys($services) as $service) {
            if (self::hasServiceConfig($service)) {
                $configured[] = $service;
            }
        }
        
        return $configured;
    }
}
