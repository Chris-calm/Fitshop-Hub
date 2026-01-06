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

$_SESSION['cart'] = [];

echo json_encode([
  'ok' => true,
  'total' => 0,
  'count' => 0,
  'empty' => true,
]);
