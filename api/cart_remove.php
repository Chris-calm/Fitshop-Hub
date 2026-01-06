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
header('Content-Type: application/json');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '{}', true);
if (!is_array($body)) { $body = []; }

$id = isset($body['id']) ? (int)$body['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid product id']);
  exit;
}

$_SESSION['cart'] = $_SESSION['cart'] ?? [];
unset($_SESSION['cart'][$id]);

$products = json_decode(file_get_contents(__DIR__ . '/../storage/products.json'), true);
if (!is_array($products)) { $products = []; }

$cart = $_SESSION['cart'] ?? [];
$total = 0.0;
$count = 0;
foreach ($cart as $pid => $q) {
  $count += (int)$q;
  foreach ($products as $pp) {
    if ((int)($pp['id'] ?? 0) === (int)$pid) {
      $total += ((int)$q) * ((float)($pp['price'] ?? 0));
      break;
    }
  }
}

echo json_encode([
  'ok' => true,
  'total' => $total,
  'count' => $count,
  'empty' => empty($cart),
]);
