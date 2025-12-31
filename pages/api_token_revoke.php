<?php
session_start();
header('Content-Type: application/json');
if (empty($_SESSION['user'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit; }
require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api_tokens.php';

$user_id = (int)$_SESSION['user']['id'];
$token_id = (int)($_POST['token_id'] ?? 0);
if ($token_id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'token_id required']); exit; }

try {
  fh_revoke_api_token($pdo, $user_id, $token_id);
  echo json_encode(['ok' => true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
