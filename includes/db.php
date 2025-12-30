<?php
// Include environment configuration
require_once __DIR__ . '/env.php';

// Get database configuration from environment
$dbConfig = [
    'DB_HOST' => getenv('DB_HOST') ?: $_ENV['DB_HOST'] ?? 'localhost',
    'DB_NAME' => getenv('DB_NAME') ?: $_ENV['DB_NAME'] ?? 'fitshop_hub',
    'DB_USER' => getenv('DB_USER') ?: $_ENV['DB_USER'] ?? 'root',
    'DB_PASS' => getenv('DB_PASS') ?: $_ENV['DB_PASS'] ?? '',
    'DB_CHARSET' => getenv('DB_CHARSET') ?: $_ENV['DB_CHARSET'] ?? 'utf8mb4'
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
    http_response_code(500);
    
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
