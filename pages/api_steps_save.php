<?php
session_start();
header('Content-Type: application/json');
if (empty($_SESSION['user'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit; }
require __DIR__ . '/../includes/db.php';
$user_id = (int)$_SESSION['user']['id'];
$steps = max(0, (int)($_POST['steps'] ?? 0));
$today = date('Y-m-d');
try {
  if (defined('IS_VERCEL') && IS_VERCEL) {
    $stmt = $pdo->prepare('INSERT INTO steps_logs(user_id,step_date,steps) VALUES (?,?,?) ON CONFLICT (user_id, step_date) DO UPDATE SET steps=EXCLUDED.steps');
    $stmt->execute([$user_id,$today,$steps]);
  } else {
    $stmt = $pdo->prepare('INSERT INTO steps_logs(user_id,step_date,steps) VALUES (?,?,?) ON DUPLICATE KEY UPDATE steps=VALUES(steps)');
    $stmt->execute([$user_id,$today,$steps]);
  }
  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
