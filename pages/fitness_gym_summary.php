<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require_login();
$u = $_SESSION['user'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: index.php?page=gym');
  exit;
}

$program_id = isset($_POST['program_id']) ? intval($_POST['program_id']) : 0;
$program_title = trim($_POST['program_title'] ?? '');
$total_duration_sec = max(0, intval($_POST['total_duration_sec'] ?? 0));
$notes = trim($_POST['notes'] ?? '');
$sets = $_POST['sets'] ?? [];
$exercise_names = $_POST['exercise_names'] ?? [];
$exercise_order = $_POST['exercise_order'] ?? [];

// Create session row (started_at derived from duration)
$pdo->beginTransaction();
try {
  // started_at = NOW() - duration, ended_at = NOW()
  $stmt = $pdo->prepare("INSERT INTO workout_sessions (user_id, program_id, program_title, started_at, ended_at, total_duration_sec, notes) VALUES (?, ?, ?, DATE_SUB(NOW(), INTERVAL ? SECOND), NOW(), ?, ?)");
  $stmt->execute([$u['id'], $program_id, $program_title, $total_duration_sec, $total_duration_sec, $notes]);
  $session_id = $pdo->lastInsertId();

  $total_volume = 0.0;
  // Insert sets per exercise
  foreach ($sets as $exIdx => $perSets) {
    $ename = isset($exercise_names[$exIdx]) ? (string)$exercise_names[$exIdx] : ('Exercise '.$exIdx);
    $eorder = isset($exercise_order[$exIdx]) ? intval($exercise_order[$exIdx]) : intval($exIdx);
    foreach ($perSets as $setNum => $data) {
      $performed_reps = isset($data['reps']) ? intval($data['reps']) : null;
      $weight = isset($data['weight']) && $data['weight'] !== '' ? floatval($data['weight']) : null;
      $rpe = isset($data['rpe']) && $data['rpe'] !== '' ? floatval($data['rpe']) : null;
      $rest = isset($data['rest']) && $data['rest'] !== '' ? intval($data['rest']) : null;
      $target_reps = $performed_reps; // we didn't send explicit target separately; optional to compute later

      $stmt2 = $pdo->prepare("INSERT INTO workout_sets (session_id, exercise_order, exercise_name, set_number, target_reps, performed_reps, weight_kg, rpe, rest_sec) VALUES (?,?,?,?,?,?,?,?,?)");
      $stmt2->execute([$session_id, $eorder, $ename, intval($setNum), $target_reps, $performed_reps, $weight, $rpe, $rest]);

      if ($weight !== null && $performed_reps !== null) {
        $total_volume += ($weight * $performed_reps);
      }
    }
  }

  // Update total volume
  $stmt3 = $pdo->prepare("UPDATE workout_sessions SET total_volume = ? WHERE id = ?");
  $stmt3->execute([$total_volume, $session_id]);

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo '<section class="text-red-400">Failed to save workout: '.htmlspecialchars($e->getMessage()).'</section>';
  exit;
}

// Auto-log steps estimate for today so dashboard can progress without manual entry
try {
  // simple estimate: 80 steps per active minute for gym
  $est_steps = (int)round(($total_duration_sec/60) * 80);
  if ($est_steps > 0) {
    $today = date('Y-m-d');
    // Upsert by summing with any existing steps for today
    $stmt = $pdo->prepare("INSERT INTO steps_logs (user_id, step_date, steps) VALUES (?,?,?) ON DUPLICATE KEY UPDATE steps = steps + VALUES(steps)");
    $stmt->execute([$u['id'], $today, $est_steps]);
  }
} catch (Throwable $e) { /* non-fatal */ }

?>
<section>
  <h2 class="text-2xl font-bold mb-2">Workout Summary</h2>
  <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-4 mb-4">
    <div class="text-sm text-neutral-400">Program</div>
    <div class="font-semibold"><?= htmlspecialchars($program_title) ?></div>
    <div class="mt-2 grid grid-cols-2 md:grid-cols-4 gap-4">
      <div>
        <div class="text-neutral-400 text-sm">Duration</div>
        <div class="text-lg font-semibold"><?= intval($total_duration_sec/60) ?> min</div>
      </div>
      <div>
        <div class="text-neutral-400 text-sm">Total Volume</div>
        <div class="text-lg font-semibold"><?= number_format((float)$total_volume, 2) ?> kg</div>
      </div>
      <div>
        <div class="text-neutral-400 text-sm">Completed</div>
        <div class="text-lg font-semibold"><?= date('Y-m-d H:i') ?></div>
      </div>
      <div>
        <div class="text-neutral-400 text-sm">Session ID</div>
        <div class="text-lg font-semibold">#<?= htmlspecialchars($session_id) ?></div>
      </div>
    </div>
  </div>

  <?php if (!empty($notes)): ?>
  <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-4 mb-4">
    <div class="text-neutral-400 text-sm">Notes</div>
    <div><?= nl2br(htmlspecialchars($notes)) ?></div>
  </div>
  <?php endif; ?>

  <div class="flex items-center gap-3">
    <a href="index.php?page=gym" class="px-4 py-2 rounded-lg bg-neutral-800">Back to Programs</a>
    <a href="index.php?page=gym_detail&id=<?= urlencode($program_id) ?>" class="px-4 py-2 rounded-lg bg-brand text-white">Repeat Program</a>
  </div>

  <div class="mt-8 text-sm text-neutral-400">Tip: Use consistent RPE targets to guide progression next session.</div>
</section>
