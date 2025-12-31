<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require_login();
$u = $_SESSION['user'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: index.php?page=choreography');
  exit;
}

$item_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$title = trim($_POST['title'] ?? '');
$duration_sec = max(0, intval($_POST['duration_sec'] ?? 0));
$completed_steps = max(0, intval($_POST['completed_steps'] ?? 0));
$notes = trim($_POST['notes'] ?? '');

$pdo->beginTransaction();
try {
  // started_at = NOW() - duration, ended_at = NOW()
  if (defined('IS_VERCEL') && IS_VERCEL) {
    $stmt = $pdo->prepare("INSERT INTO activity_sessions (user_id, activity_type, item_id, title, started_at, ended_at, duration_sec, completed_steps, notes) VALUES (?,?,?,?, (NOW() - (? * INTERVAL '1 second')), NOW(), ?, ?, ?) RETURNING id");
    $stmt->execute([$u['id'], 'choreo', $item_id, $title, $duration_sec, $duration_sec, $completed_steps, $notes]);
    $session_id = (int)$stmt->fetchColumn();
  } else {
    $stmt = $pdo->prepare("INSERT INTO activity_sessions (user_id, activity_type, item_id, title, started_at, ended_at, duration_sec, completed_steps, notes) VALUES (?,?,?,?, DATE_SUB(NOW(), INTERVAL ? SECOND), NOW(), ?, ?, ?)");
    $stmt->execute([$u['id'], 'choreo', $item_id, $title, $duration_sec, $duration_sec, $completed_steps, $notes]);
    $session_id = $pdo->lastInsertId();
  }
  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo '<section class="text-red-400">Failed to save routine: '.htmlspecialchars($e->getMessage()).'</section>';
  exit;
}
?>
<section>
  <h2 class="text-2xl font-bold mb-2">Routine Summary</h2>
  <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-4 mb-4">
    <div class="text-sm text-neutral-400">Title</div>
    <div class="font-semibold"><?= htmlspecialchars($title) ?></div>
    <div class="mt-2 grid grid-cols-2 md:grid-cols-4 gap-4">
      <div>
        <div class="text-neutral-400 text-sm">Duration</div>
        <div class="text-lg font-semibold"><?= intval($duration_sec/60) ?> min</div>
      </div>
      <div>
        <div class="text-neutral-400 text-sm">Sections Completed</div>
        <div class="text-lg font-semibold"><?= intval($completed_steps) ?></div>
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
    <a href="index.php?page=choreography" class="px-4 py-2 rounded-lg bg-neutral-800">Back to Choreography</a>
    <a href="index.php?page=choreo_detail&id=<?= urlencode($item_id) ?>" class="px-4 py-2 rounded-lg bg-brand text-white">Repeat Routine</a>
  </div>
</section>
