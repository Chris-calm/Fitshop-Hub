<?php
// Buffer output so pages can safely set cookies/redirects even if templates output HTML
if (!ob_get_level()) {
    ob_start();
}

// Start session with secure settings
$isHttps = false;
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $isHttps = true;
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
    $isHttps = true;
}

// Revision marker to confirm which code version is running in Vercel Runtime Logs / Network headers
$__fh_rev = 'fh-rev-2025-12-31-02';
if (!headers_sent()) {
    header('X-FH-Rev: ' . $__fh_rev);
}
error_log('BOOT ' . $__fh_rev);

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

// Set base path for includes
$rootPath = dirname(__DIR__);

// Load environment configuration
require_once $rootPath . '/includes/env.php';
require_once $rootPath . '/includes/auth_cookie.php';

// Enforce canonical domain so auth cookies remain consistent (preview deployment domains won't share cookies)
$canonicalHost = getenv('CANONICAL_HOST') ?: '';
$currentHost = $_SERVER['HTTP_HOST'] ?? '';
if ($canonicalHost && $currentHost && strcasecmp($currentHost, $canonicalHost) !== 0) {
    $scheme = $isHttps ? 'https' : 'http';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: ' . $scheme . '://' . $canonicalHost . $uri, true, 302);
    exit;
}

// Load .env.local if it exists (local development only)
if (IS_LOCAL && file_exists($rootPath . '/.env.local')) {
    $envVars = parse_ini_file($rootPath . '/.env.local');
    foreach ($envVars as $key => $value) {
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start($sessionParams);
}

// Restore user session from signed cookie (required for Vercel/serverless where PHP file sessions are not stable)
fh_restore_user_from_cookie();

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
    'otp_verify' => 'pages/otp_verify.php',
    'forgot_password' => 'pages/forgot_password.php',
    'reset_password' => 'pages/reset_password.php',
    'about' => 'pages/about.php',
    'guides_public' => 'pages/guides_public.php',
    'profile' => 'pages/profile.php',
    'settings' => 'pages/settings.php',
    'customize' => 'pages/customize.php',
    'logout' => 'pages/logout.php',
    'post_add_to_cart' => 'pages/post_add_to_cart.php',
    'post_checkout' => 'pages/post_checkout.php',
    'cancel_order' => 'pages/cancel_order.php',
    'api_fitness_stats' => 'pages/api_fitness_stats.php',
    'api_steps_save' => 'pages/api_steps_save.php',
    'api_token_create' => 'pages/api_token_create.php',
    'api_token_revoke' => 'pages/api_token_revoke.php',
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

if (empty($_SESSION['user']) && !in_array($page, ['login', 'register', 'otp_verify', 'forgot_password', 'reset_password', 'about', 'guides_public'], true)) {
    $page = 'login';
}

if (!empty($_SESSION['user']) && in_array($page, ['login', 'register', 'otp_verify', 'forgot_password', 'reset_password'], true)) {
    header('Location: index.php?page=landing', true, 302);
    exit;
}

// Set the page file path
$pageFile = $rootPath . '/' . $allowed[$page];

error_log('RESOLVED pageFile=' . $pageFile . ' exists=' . (file_exists($pageFile) ? '1' : '0') . ' size=' . (file_exists($pageFile) ? (string)filesize($pageFile) : '0'));

// Check if the requested page exists
if (!file_exists($pageFile)) {
    http_response_code(404);
    die('Page not found');
}

$actionPages = ['logout', 'post_add_to_cart', 'post_checkout', 'cancel_order', 'api_fitness_stats', 'api_steps_save', 'api_token_create', 'api_token_revoke'];
if (in_array($page, $actionPages, true)) {
    require $pageFile;
    exit;
}

// Include the header
try {
    error_log('STAGE header:start');
    require $rootPath . '/includes/header.php';
    error_log('STAGE header:ok');
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Error loading header: ' . $e->getMessage());
    echo 'An error occurred while loading the page. Please try again later.';
    exit;
}

// Include the requested page
try {
    error_log('STAGE page:start');
    require $pageFile;
    error_log('STAGE page:ok');
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
    error_log('STAGE footer:start');
    require $rootPath . '/includes/footer.php';
    error_log('STAGE footer:ok');
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Error loading footer: ' . $e->getMessage());
    echo 'An error occurred while loading the page. Please try again later.';
    exit;
}
