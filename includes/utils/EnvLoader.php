<?php
/**
 * Environment Variable Loader
 * 
 * Loads environment variables from .env file
 * Simple implementation without external dependencies
 */

namespace Mitsubishi\Utils;

class EnvLoader
{
    /**
     * Load environment variables from .env file
     * 
     * @param string|null $path Path to .env file (defaults to project root)
     * @return bool True if loaded successfully, false otherwise
     */
    public static function load($path = null)
    {
        // Default to project root .env file
        if ($path === null) {
            $path = dirname(dirname(__DIR__)) . '/.env';
        }
        
        // Check if file exists
        if (!file_exists($path)) {
            error_log("EnvLoader: .env file not found at: {$path}");
            return false;
        }
        
        // Read file line by line
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines === false) {
            error_log("EnvLoader: Failed to read .env file");
            return false;
        }
        
        foreach ($lines as $line) {
            // Skip comments and empty lines
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            // Parse KEY=VALUE format
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                    $value = $matches[2];
                }
                
                // Set environment variable if not already set
                if (!getenv($key)) {
                    putenv("{$key}={$value}");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Get an environment variable value
     * 
     * @param string $key Variable name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        $value = getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        return $value;
    }
    
    /**
     * Check if an environment variable exists
     * 
     * @param string $key Variable name
     * @return bool
     */
    public static function has($key)
    {
        return getenv($key) !== false;
    }
}

