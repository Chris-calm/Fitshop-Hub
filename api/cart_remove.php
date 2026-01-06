<?php
session_start();
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
