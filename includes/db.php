<?php
// Include environment configuration
require_once __DIR__ . '/env.php';

// Get database configuration from environment
$databaseUrl = getenv('DATABASE_URL');
$dbHost = getenv('DB_HOST');
$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');
$dbCharset = getenv('DB_CHARSET') ?: 'utf8mb4';
$dbDriver = getenv('DB_DRIVER') ?: '';
$dbPort = getenv('DB_PORT') ?: '';
$dbSslMode = getenv('DB_SSLMODE') ?: '';

if (IS_LOCAL) {
    $dbHost = $dbHost ?: ($_ENV['DB_HOST'] ?? 'localhost');
    $dbName = $dbName ?: ($_ENV['DB_NAME'] ?? 'fitshop_hub');
    $dbUser = $dbUser ?: ($_ENV['DB_USER'] ?? 'root');
    $dbPass = $dbPass ?: ($_ENV['DB_PASS'] ?? '');
}

if (!IS_LOCAL) {
    if (!$databaseUrl && (!$dbHost || !$dbName || !$dbUser)) {
        if (!headers_sent()) {
            http_response_code(500);
        }
        error_log('Database is not configured. Set DATABASE_URL (recommended for Supabase) or DB_HOST/DB_NAME/DB_USER/DB_PASS in Vercel Environment Variables.');
        die('Database is not configured. Please set the database environment variables in Vercel.');
    }
}

$dbConfig = [
    'DB_HOST' => $dbHost,
    'DB_NAME' => $dbName,
    'DB_USER' => $dbUser,
    'DB_PASS' => $dbPass,
    'DB_CHARSET' => $dbCharset,
    'DB_DRIVER' => $dbDriver,
    'DB_PORT' => $dbPort,
    'DB_SSLMODE' => $dbSslMode,
    'DATABASE_URL' => $databaseUrl,
];

// Create DSN
$dsn = '';
if (!empty($dbConfig['DATABASE_URL'])) {
    $parts = parse_url($dbConfig['DATABASE_URL']);
    $scheme = $parts['scheme'] ?? '';
    if ($scheme !== 'postgres' && $scheme !== 'postgresql') {
        if (!headers_sent()) {
            http_response_code(500);
        }
        die('Invalid DATABASE_URL scheme. Expected postgres/postgresql.');
    }

    $host = $parts['host'] ?? '';
    $port = $parts['port'] ?? 5432;
    $db = isset($parts['path']) ? ltrim($parts['path'], '/') : '';
    $user = $parts['user'] ?? '';
    $pass = $parts['pass'] ?? '';

    parse_str($parts['query'] ?? '', $query);
    $sslmode = $query['sslmode'] ?? ($dbConfig['DB_SSLMODE'] ?: 'require');

    $dbConfig['DB_DRIVER'] = 'pgsql';
    $dbConfig['DB_HOST'] = $host;
    $dbConfig['DB_PORT'] = (string)$port;
    $dbConfig['DB_NAME'] = $db;
    $dbConfig['DB_USER'] = $user;
    $dbConfig['DB_PASS'] = $pass;
    $dbConfig['DB_SSLMODE'] = $sslmode;

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
        $dbConfig['DB_HOST'],
        $dbConfig['DB_PORT'],
        $dbConfig['DB_NAME'],
        $dbConfig['DB_SSLMODE']
    );
} elseif (($dbConfig['DB_DRIVER'] === 'pgsql') || ($dbConfig['DB_DRIVER'] === 'postgres')) {
    $port = $dbConfig['DB_PORT'] ?: '5432';
    $sslmode = $dbConfig['DB_SSLMODE'] ?: 'require';
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
        $dbConfig['DB_HOST'],
        $port,
        $dbConfig['DB_NAME'],
        $sslmode
    );
} else {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $dbConfig['DB_HOST'],
        $dbConfig['DB_NAME'],
        $dbConfig['DB_CHARSET']
    );
}

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
    if ($dbConfig['DB_DRIVER'] === 'pgsql') {
        $pdo->exec("SET TIME ZONE '+08:00'");
    } else {
        $pdo->exec("SET time_zone = '+08:00'");
    }
    
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
