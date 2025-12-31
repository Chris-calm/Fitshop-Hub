<?php
// Start session with secure settings
$isHttps = false;
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $isHttps = true;
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
    $isHttps = true;
}

register_shutdown_function(function () {
    $err = error_get_last();
    if (!$err) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array($err['type'] ?? 0, $fatalTypes, true)) {
        return;
    }

    error_log('FATAL: ' . ($err['message'] ?? 'unknown') . ' in ' . ($err['file'] ?? 'unknown') . ':' . ($err['line'] ?? 0));

    if (!headers_sent()) {
        http_response_code(500);
        echo 'An error occurred while loading the page. Please try again later.';
    }
});

$sessionParams = [
    'cookie_httponly' => true,
    'cookie_secure' => $isHttps,
    'cookie_samesite' => 'Lax'
];

// Ensure the session cookie applies to the whole site (important behind rewrites like /api/index.php)
$sessionParams['cookie_path'] = '/';

if (session_status() === PHP_SESSION_NONE) {
    session_start($sessionParams);
}

// Set base path for includes
$rootPath = dirname(__DIR__);

// Load environment configuration
require_once $rootPath . '/includes/env.php';

// Load .env.local if it exists (local development only)
if (IS_LOCAL && file_exists($rootPath . '/.env.local')) {
    $envVars = parse_ini_file($rootPath . '/.env.local');
    foreach ($envVars as $key => $value) {
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

// Error handling setup
if (IS_LOCAL) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    // E_DEPRECATED is kept as it's still valid
    error_reporting(E_ALL & ~E_DEPRECATED);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', 'php://stderr');
}

// Define allowed pages with their corresponding file paths
$allowed = [
    'landing' => 'pages/landing.php',
    'health' => 'pages/health.php',
    'fitness' => 'pages/fitness.php',
    'catalog' => 'pages/catalog.php',
    'product' => 'pages/product.php',
    'cart' => 'pages/cart.php',
    'checkout' => 'pages/checkout.php',
    'order' => 'pages/order.php',
    'login' => 'pages/login.php',
    'register' => 'pages/register.php',
    'profile' => 'pages/profile.php',
    'logout' => 'pages/logout.php',
    'post_add_to_cart' => 'pages/post_add_to_cart.php',
    'post_checkout' => 'pages/post_checkout.php',
    'api_fitness_stats' => 'pages/api_fitness_stats.php',
    'api_steps_save' => 'pages/api_steps_save.php',
    'choreography' => 'pages/fitness_choreo.php',
    'guides' => 'pages/fitness_guides.php',
    'gym' => 'pages/fitness_gym.php',
    'choreo_detail' => 'pages/fitness_choreo_detail.php',
    'guide_detail' => 'pages/fitness_guides_detail.php',
    'gym_detail' => 'pages/fitness_gym_detail.php',
    'gym_session' => 'pages/fitness_gym_session.php',
    'gym_summary' => 'pages/fitness_gym_summary.php',
    'choreo_session' => 'pages/fitness_choreo_session.php',
    'choreo_summary' => 'pages/fitness_choreo_summary.php',
    'guide_session' => 'pages/fitness_guides_session.php',
    'guide_summary' => 'pages/fitness_guides_summary.php',
    'food_scan' => 'pages/food_scan.php',
    'food_history' => 'pages/food_history.php',
    'fitness_history' => 'pages/fitness_history.php'
];

// Get the requested page from query parameters
$page = $_GET['page'] ?? '';
if ($page === '' || !isset($allowed[$page])) {
    $page = empty($_SESSION['user']) ? 'login' : 'landing';
}

error_log('REQ uri=' . ($_SERVER['REQUEST_URI'] ?? '') . ' page=' . $page . ' user=' . (!empty($_SESSION['user']['id']) ? (string)$_SESSION['user']['id'] : 'guest'));

if (empty($_SESSION['user']) && !in_array($page, ['login', 'register'], true)) {
    $page = 'login';
}

if (!empty($_SESSION['user']) && in_array($page, ['login', 'register'], true)) {
    $page = 'landing';
}

// Set the page file path
$pageFile = $rootPath . '/' . $allowed[$page];

// Check if the requested page exists
if (!file_exists($pageFile)) {
    http_response_code(404);
    die('Page not found');
}

$actionPages = ['logout', 'post_add_to_cart', 'post_checkout', 'api_fitness_stats', 'api_steps_save'];
if (in_array($page, $actionPages, true)) {
    require $pageFile;
    exit;
}

// Include the header
try {
    require $rootPath . '/includes/header.php';
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Error loading header: ' . $e->getMessage());
    echo 'An error occurred while loading the page. Please try again later.';
    exit;
}

// Include the requested page
try {
    require $pageFile;
} catch (Throwable $e) {
    http_response_code(500);
    if (getenv('VERCEL_ENV') === 'production') {
        error_log('Error loading page: ' . $e->getMessage());
        echo 'An error occurred while loading the page. Please try again later.';
    } else {
        echo 'Error: ' . $e->getMessage();
    }
}

// Include the footer
try {
    require $rootPath . '/includes/footer.php';
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Error loading footer: ' . $e->getMessage());
    echo 'An error occurred while loading the page. Please try again later.';
    exit;
}
