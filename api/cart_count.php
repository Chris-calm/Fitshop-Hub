<?php
require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/cart_store.php';

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

$cart = fh_cart_get();
$count = fh_cart_count($cart);
header('Content-Type: application/json');
header('Cache-Control: no-store');
echo json_encode(['count'=>$count]);
