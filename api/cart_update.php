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

$key = isset($body['key']) ? (string)$body['key'] : '';
$id = isset($body['id']) ? (int)$body['id'] : 0;
$option = isset($body['option']) ? (string)$body['option'] : '';
$delta = isset($body['delta']) ? (int)$body['delta'] : null;
$qty = isset($body['qty']) ? (int)$body['qty'] : null;

if ($key === '' && $id <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid cart item']);
  exit;
}

$parsedKey = $key !== '' ? fh_cart_parse_key($key) : null;
if (!$parsedKey) {
  $key = fh_cart_make_key($id, $option);
  $parsedKey = fh_cart_parse_key($key);
}

if (!$parsedKey) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid cart item']);
  exit;
}

$id = (int)$parsedKey['id'];
$key = (string)$parsedKey['key'];

$products = json_decode(file_get_contents(__DIR__ . '/../storage/products.json'), true);
if (!is_array($products)) { $products = []; }

$product = null;
foreach ($products as $p) {
  if ((int)($p['id'] ?? 0) === $id) { $product = $p; break; }
}

if (!$product) {
  http_response_code(404);
  echo json_encode(['error' => 'Product not found']);
  exit;
}

$cart = fh_cart_get();
$current = isset($cart[$key]) ? (int)$cart[$key] : 0;

if ($qty !== null) {
  $newQty = $qty;
} elseif ($delta !== null) {
  $newQty = $current + $delta;
} else {
  $newQty = $current;
}

$newQty = max(1, min(99, $newQty));
$cart[$key] = $newQty;
$cart = fh_cart_write($cart);

// Recompute totals
$total = 0.0;
$count = 0;
foreach ($cart as $pid => $q) {
  $count += (int)$q;
  $parsed = fh_cart_parse_key((string)$pid);
  if (!$parsed) {
    continue;
  }
  $realId = (int)$parsed['id'];
  foreach ($products as $pp) {
    if ((int)($pp['id'] ?? 0) === $realId) {
      $total += ((int)$q) * ((float)($pp['price'] ?? 0));
      break;
    }
  }
}

$price = (float)($product['price'] ?? 0);
$line = $newQty * $price;

echo json_encode([
  'ok' => true,
  'key' => $key,
  'id' => $id,
  'qty' => $newQty,
  'line' => $line,
  'total' => $total,
  'count' => $count,
  'empty' => empty($cart),
]);
