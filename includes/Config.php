<?php

class Config {
    private static $config = null;
    private static $loaded = false;
    
    public static function load() {
        // Prevent multiple loads
        if (self::$loaded) {
            return self::$config;
        }
        
        self::$loaded = true;
        
        // Load .env file
        self::loadEnv(__DIR__ . '/../.env');
        
        // Load all config files
        $configPath = __DIR__ . '/../config';
        $configFiles = [
            'app',
            'scraper',
            'security',
            'database',
            'services',
            'cache',
            'mail'
        ];
        
        self::$config = [];
        
        foreach ($configFiles as $file) {
            $path = $configPath . '/' . $file . '.php';
            if (file_exists($path)) {
                $data = require $path;
                if (is_array($data)) {
                    self::$config = array_merge(self::$config, $data);
                }
            }
        }
        
        return self::$config;
    }
    
    private static function loadEnv($path) {
        if (!file_exists($path)) {
            return;
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines === false) {
            return;
        }
        
        foreach ($lines as $line) {
            // Skip comments and empty lines
            $line = trim($line);
            if (empty($line) || $line[0] === '#') {
                continue;
            }
            
            // Parse KEY=VALUE
            if (strpos($line, '=') === false) {
                continue;
            }
            
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Remove quotes
            if (strlen($value) > 0) {
                if (($value[0] === '"' && substr($value, -1) === '"') ||
                    ($value[0] === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }
            }
            
            // Set environment variable
            if (!empty($name)) {
                putenv("$name=$value");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
    
    public static function get($key, $default = null) {
        if (self::$config === null) {
            self::load();
        }
        
        // Support dot notation: 'database.host'
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (!is_array($value) || !isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    public static function env($key, $default = null) {
        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        }
        
        // Convert string booleans
        if (is_string($value)) {
            $lower = strtolower($value);
            if ($lower === 'true') return true;
            if ($lower === 'false') return false;
            if ($lower === 'null') return null;
        }
        
        return $value;
    }
    
    public static function has($key) {
        return self::get($key) !== null;
    }
    
    public static function all() {
        if (self::$config === null) {
            self::load();
        }
        return self::$config;
    }
    
    public static function set($key, $value) {
        if (self::$config === null) {
            self::load();
        }
        
        // Support dot notation for setting
        $keys = explode('.', $key);
        $current = &self::$config;
        
        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $current[$k] = $value;
            } else {
                if (!isset($current[$k]) || !is_array($current[$k])) {
                    $current[$k] = [];
                }
                $current = &$current[$k];
            }
        }
    }
    
    public static function require($key) {
        $value = self::get($key);
        if ($value === null) {
            throw new Exception("Required configuration key missing: $key");
        }
        return $value;
    }
}
