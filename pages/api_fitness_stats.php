<?php
session_start();
header('Content-Type: application/json');
if (empty($_SESSION['user'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit; }
require __DIR__ . '/../includes/db.php';
$user_id = (int)$_SESSION['user']['id'];

$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week'));

try {
  // Steps today and goal
  $stepsToday = 0; $stepsGoal = 10000;
  // goal from users
  $stmt = $pdo->prepare('SELECT steps_goal FROM users WHERE id=?');
  $stmt->execute([$user_id]);
  $row = $stmt->fetch();
  if ($row && isset($row['steps_goal'])) { $stepsGoal = (int)$row['steps_goal']; }
  // today steps
  $stmt = $pdo->prepare('SELECT steps FROM steps_logs WHERE user_id=? AND step_date=?');
  $stmt->execute([$user_id, $today]);
  $s = $stmt->fetchColumn();
  if ($s !== false) { $stepsToday = (int)$s; }

  // Streak: a day counts if steps>0 OR any workout/activity exists that day
  $streak = 0; $d = new DateTime($today);
  while (true) {
    $dateStr = $d->format('Y-m-d');
    // steps check
    $stmt = $pdo->prepare('SELECT steps FROM steps_logs WHERE user_id=? AND step_date=?');
    $stmt->execute([$user_id, $dateStr]);
    $v = $stmt->fetchColumn();
    $active = ($v && (int)$v > 0);
    if (!$active) {
      // fallback: any session finished on this date
      $stmt = $pdo->prepare("SELECT 1 FROM workout_sessions WHERE user_id=? AND DATE(ended_at)=? LIMIT 1");
      $stmt->execute([$user_id, $dateStr]);
      $active = (bool)$stmt->fetchColumn();
      if (!$active) {
        $stmt = $pdo->prepare("SELECT 1 FROM activity_sessions WHERE user_id=? AND DATE(ended_at)=? LIMIT 1");
        $stmt->execute([$user_id, $dateStr]);
        $active = (bool)$stmt->fetchColumn();
      }
    }
    if ($active) { $streak++; $d->modify('-1 day'); } else { break; }
  }

  // Minutes this week from workout_sessions + activity_sessions (duration_sec)
  $minutesWeek = 0;
  $stmt = $pdo->prepare('SELECT COALESCE(SUM(total_duration_sec),0) FROM workout_sessions WHERE user_id=? AND ended_at >= ?');
  $stmt->execute([$user_id, $weekStart]);
  $secGym = (int)$stmt->fetchColumn();

  $stmt = $pdo->prepare('SELECT COALESCE(SUM(duration_sec),0) FROM activity_sessions WHERE user_id=? AND ended_at >= ?');
  $stmt->execute([$user_id, $weekStart]);
  $secAct = (int)$stmt->fetchColumn();
  $minutesWeek = (int)round(($secGym + $secAct) / 60);

  echo json_encode([
    'ok' => true,
    'today_steps' => $stepsToday,
    'steps_goal' => $stepsGoal,
    'streak' => $streak,
    'minutes_week' => $minutesWeek,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
