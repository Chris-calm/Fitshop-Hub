<?php
// Environment detection
$isVercel = (getenv('VERCEL') === '1') || (getenv('VERCEL_ENV') !== false) || (getenv('VERCEL_URL') !== false);

function isLocalEnvironment($isVercel) {
    if ($isVercel) {
        return false;
    }
    // Check for common local development server indicators
    $serverName = $_SERVER['SERVER_NAME'] ?? '';
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    $httpHost = $_SERVER['HTTP_HOST'] ?? '';

    return 
        $serverName === 'localhost' || 
        in_array($remoteAddr, ['127.0.0.1', '::1'], true) ||
        strpos($httpHost, '.local') !== false ||
        getenv('LOCAL_DEVELOPMENT') === 'true';
}

// Set environment constants
define('IS_VERCEL', $isVercel);
define('IS_LOCAL', isLocalEnvironment($isVercel));

// Set base URL
define('BASE_URL', IS_LOCAL 
    ? 'http://localhost/Health&Fitness' 
    : 'https://' . ($_SERVER['HTTP_HOST'] ?? getenv('VERCEL_URL')));

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
    // E_DEPRECATED is kept as it's still valid
    error_reporting(E_ALL & ~E_DEPRECATED);
    ini_set('display_errors', 0);
}
