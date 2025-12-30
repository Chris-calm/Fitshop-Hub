<?php
// Include environment configuration
require_once __DIR__ . '/env.php';

// Get database configuration from environment
$dbHost = getenv('DB_HOST');
$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');
$dbCharset = getenv('DB_CHARSET') ?: 'utf8mb4';

if (IS_LOCAL) {
    $dbHost = $dbHost ?: ($_ENV['DB_HOST'] ?? 'localhost');
    $dbName = $dbName ?: ($_ENV['DB_NAME'] ?? 'fitshop_hub');
    $dbUser = $dbUser ?: ($_ENV['DB_USER'] ?? 'root');
    $dbPass = $dbPass ?: ($_ENV['DB_PASS'] ?? '');
}

if (!IS_LOCAL) {
    if (!$dbHost || !$dbName || !$dbUser) {
        if (!headers_sent()) {
            http_response_code(500);
        }
        error_log('Database is not configured. Set DB_HOST, DB_NAME, DB_USER, DB_PASS in Vercel Environment Variables.');
        die('Database is not configured. Please set the database environment variables in Vercel.');
    }
}

$dbConfig = [
    'DB_HOST' => $dbHost,
    'DB_NAME' => $dbName,
    'DB_USER' => $dbUser,
    'DB_PASS' => $dbPass,
    'DB_CHARSET' => $dbCharset,
];

// Create DSN
$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    $dbConfig['DB_HOST'],
    $dbConfig['DB_NAME'],
    $dbConfig['DB_CHARSET']
);

// PDO options
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    // Create PDO instance
    $pdo = new PDO($dsn, $dbConfig['DB_USER'], $dbConfig['DB_PASS'], $options);
    
    // Set timezone
    $pdo->exec("SET time_zone = '+08:00'");
    
} catch (PDOException $e) {
    if (!headers_sent()) {
        http_response_code(500);
    }
    
    if (IS_LOCAL) {
        // Detailed error for local development
        $errorMessage = "Database connection failed: " . $e->getMessage();
        $errorMessage .= "\nDSN: " . $dsn;
        $errorMessage .= "\nUser: " . $dbConfig['DB_USER'];
        die(nl2br(htmlspecialchars($errorMessage)));
    } else {
        // Generic error for production
        error_log('Database Error: ' . $e->getMessage());
        die('Database connection error. Please try again later.');
    }
}
