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
$products = json_decode(file_get_contents(__DIR__.'/../storage/products.json'), true);
$id = (int)($_POST['id'] ?? 0);
$qty = max(1, (int)($_POST['qty'] ?? 1));
$found = null; foreach ($products as $p) { if ($p['id']===$id) { $found=$p; break; } }
if ($found) {
  $cart = fh_cart_get();
  $cart[$id] = (isset($cart[$id]) ? (int)$cart[$id] : 0) + $qty;
  fh_cart_write($cart);
}
header('Location: index.php?page=cart');
