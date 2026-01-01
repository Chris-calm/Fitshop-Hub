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

$raw = file_get_contents('php://input');
$jsonBody = null;
if (is_string($raw) && trim($raw) !== '') {
  $tmp = json_decode($raw, true);
  if (is_array($tmp)) {
    $jsonBody = $tmp;
  }
}

$steps = null;
if (isset($_POST['steps'])) {
  $steps = (int)$_POST['steps'];
} elseif (is_array($jsonBody) && array_key_exists('steps', $jsonBody)) {
  $steps = (int)$jsonBody['steps'];
}

$force = 0;
if (isset($_POST['force'])) {
  $force = (int)$_POST['force'];
} else {
  if (is_array($jsonBody) && array_key_exists('force', $jsonBody)) {
    $force = (int)$jsonBody['force'];
  }
}

$syncing = null;
if (isset($_POST['syncing'])) {
  $syncing = (int)$_POST['syncing'];
} elseif (is_array($jsonBody) && array_key_exists('syncing', $jsonBody)) {
  $syncing = (int)$jsonBody['syncing'];
}
if ($syncing !== null) {
  $syncing = $syncing ? 1 : 0;
}

$today = date('Y-m-d');
try {
  if ($syncing !== null) {
    try {
      if (defined('IS_VERCEL') && IS_VERCEL) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS steps_sync_state (id BIGSERIAL PRIMARY KEY, user_id INT NOT NULL, step_date DATE NOT NULL, syncing INT NOT NULL DEFAULT 1, updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(), UNIQUE(user_id, step_date))');
        $stmt = $pdo->prepare('INSERT INTO steps_sync_state(user_id,step_date,syncing,updated_at) VALUES (?,?,?, NOW()) ON CONFLICT (user_id, step_date) DO UPDATE SET syncing=EXCLUDED.syncing, updated_at=NOW()');
        $stmt->execute([$user_id, $today, $syncing]);
      } else {
        $pdo->exec('CREATE TABLE IF NOT EXISTS steps_sync_state (id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, step_date DATE NOT NULL, syncing TINYINT NOT NULL DEFAULT 1, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uniq_user_date (user_id, step_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $stmt = $pdo->prepare('INSERT INTO steps_sync_state(user_id,step_date,syncing) VALUES (?,?,?) ON DUPLICATE KEY UPDATE syncing=VALUES(syncing), updated_at=CURRENT_TIMESTAMP');
        $stmt->execute([$user_id, $today, $syncing]);
      }
    } catch (Throwable $eSync) {
      // ignore syncing state failures to avoid breaking step sync
    }
  }

  if ($steps !== null) {
    $steps = max(0, (int)$steps);
    if (defined('IS_VERCEL') && IS_VERCEL) {
      $stmt = $pdo->prepare('INSERT INTO steps_logs(user_id,step_date,steps) VALUES (?,?,?) ON CONFLICT (user_id, step_date) DO UPDATE SET steps=CASE WHEN ?=1 THEN EXCLUDED.steps ELSE GREATEST(steps_logs.steps, EXCLUDED.steps) END');
      $stmt->execute([$user_id,$today,$steps,$force]);
    } else {
      $stmt = $pdo->prepare('INSERT INTO steps_logs(user_id,step_date,steps) VALUES (?,?,?) ON DUPLICATE KEY UPDATE steps=IF(?=1, VALUES(steps), GREATEST(steps, VALUES(steps)))');
      $stmt->execute([$user_id,$today,$steps,$force]);
    }
  }

  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
