<?php
session_start();
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
