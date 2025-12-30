<?php
// Environment detection
function isLocalEnvironment() {
    // Check for common local development server indicators
    return 
        $_SERVER['SERVER_NAME'] === 'localhost' || 
        in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) ||
        strpos($_SERVER['HTTP_HOST'], '.local') !== false ||
        getenv('LOCAL_DEVELOPMENT') === 'true';
}

// Set environment constants
define('IS_LOCAL', isLocalEnvironment());
define('IS_VERCEL', !IS_LOCAL && getenv('VERCEL') === '1');

// Set base URL
define('BASE_URL', IS_LOCAL 
    ? 'http://localhost/Health&Fitness' 
    : 'https://' . $_SERVER['HTTP_HOST']);

// Set environment-specific database configuration
if (IS_LOCAL) {
    // Local database configuration
    $_ENV['DB_HOST'] = 'localhost';
    $_ENV['DB_NAME'] = 'fitshop_hub';
    $_ENV['DB_USER'] = 'root';
    $_ENV['DB_PASS'] = '';
    $_ENV['DB_CHARSET'] = 'utf8mb4';
    
    // Enable error reporting for local development
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    // Vercel environment - uses environment variables from Vercel
    // These will be automatically loaded from vercel.json and Vercel's environment
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
}
