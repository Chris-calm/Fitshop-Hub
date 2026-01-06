<?php
require_once __DIR__ . '/../includes/env.php';

$isHttps = false;
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
  $isHttps = true;
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
  $isHttps = true;
}

$sessionParams = [
  'cookie_httponly' => true,
  'cookie_secure' => $isHttps,
  'cookie_samesite' => 'Lax',
  'cookie_path' => '/',
];

if (session_status() === PHP_SESSION_NONE) {
  session_start($sessionParams);
}

$count = 0;
if (!empty($_SESSION['cart'])) {
  foreach ($_SESSION['cart'] as $q) {
    $count += (int)$q;
  }
}
header('Content-Type: application/json');
header('Cache-Control: no-store');
echo json_encode(['count'=>$count]);
