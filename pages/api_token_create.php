<?php
session_start();
header('Content-Type: application/json');
if (empty($_SESSION['user'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit; }
require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api_tokens.php';

$user_id = (int)$_SESSION['user']['id'];
$name = trim($_POST['name'] ?? '');
if ($name === '') { $name = 'Android Step Sync'; }

try {
  [$plain, $tokenId] = fh_issue_api_token($pdo, $user_id, $name);
  echo json_encode(['ok' => true, 'token' => $plain, 'token_id' => $tokenId]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
