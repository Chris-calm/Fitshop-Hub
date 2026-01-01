<?php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/../includes/db.php';
$user_id = !empty($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;

if ($user_id <= 0) {
  require_once __DIR__ . '/../includes/api_tokens.php';
  $bearer = fh_get_bearer_token();
  $resolved = fh_user_from_api_token($pdo, $bearer);
  if ($resolved && !empty($resolved['user_id'])) {
    $user_id = (int)$resolved['user_id'];
  }
}

if ($user_id <= 0) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'unauthorized']);
  exit;
}

$steps = null;
if (isset($_POST['steps'])) {
  $steps = (int)$_POST['steps'];
} else {
  $raw = file_get_contents('php://input');
  if (is_string($raw) && trim($raw) !== '') {
    $data = json_decode($raw, true);
    if (is_array($data) && isset($data['steps'])) {
      $steps = (int)$data['steps'];
    }
  }
}

$force = 0;
if (isset($_POST['force'])) {
  $force = (int)$_POST['force'];
} else {
  $raw2 = file_get_contents('php://input');
  if (is_string($raw2) && trim($raw2) !== '') {
    $data2 = json_decode($raw2, true);
    if (is_array($data2) && isset($data2['force'])) {
      $force = (int)$data2['force'];
    }
  }
}

$steps = max(0, (int)($steps ?? 0));
$today = date('Y-m-d');
try {
  if (defined('IS_VERCEL') && IS_VERCEL) {
    $stmt = $pdo->prepare('INSERT INTO steps_logs(user_id,step_date,steps) VALUES (?,?,?) ON CONFLICT (user_id, step_date) DO UPDATE SET steps=CASE WHEN ?=1 THEN EXCLUDED.steps ELSE GREATEST(steps_logs.steps, EXCLUDED.steps) END');
    $stmt->execute([$user_id,$today,$steps,$force]);
  } else {
    $stmt = $pdo->prepare('INSERT INTO steps_logs(user_id,step_date,steps) VALUES (?,?,?) ON DUPLICATE KEY UPDATE steps=IF(?=1, VALUES(steps), GREATEST(steps, VALUES(steps)))');
    $stmt->execute([$user_id,$today,$steps,$force]);
  }
  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
