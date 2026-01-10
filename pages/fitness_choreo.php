<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/programs.php';
require_login();
$u = $_SESSION['user'];
$selectedPrograms = fh_user_selected_programs($pdo, (int)$u['id']);
fh_require_any_program($selectedPrograms, ['choreography']);
$stmt = $pdo->prepare('SELECT plan_json FROM users WHERE id=?');
$stmt->execute([$u['id']]);
$plan = json_decode($stmt->fetchColumn() ?: 'null', true);
$content = json_decode(file_get_contents(__DIR__.'/../storage/fitness_content.json'), true);
$items = $content['choreography'] ?? [];
$goal = $plan['goal'] ?? 'general_health';
$recommended = array_values(array_filter($items, function($g) use ($goal){
  foreach ($g['tags'] as $t) { if ($t===$goal || $t==='general_health' || $t==='all') return true; }
  return false;
}));
?>
<section>
  <h2 class="text-2xl font-bold mb-2">Choreography</h2>
  <p class="text-neutral-400 mb-4">Dance/cardio routines tailored to your goal: <span class="capitalize"><?= htmlspecialchars(str_replace('_',' ',$goal)) ?></span></p>
  <?php if (empty($recommended)): ?>
    <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-4">
      <div class="font-semibold mb-1">No routines available</div>
      <div class="text-sm text-neutral-400">No choreography matches your current plan/goal right now. You can update your program selections.</div>
      <div class="mt-3"><a class="fh-btn fh-btn-primary" href="index.php?page=customize">Open Customize</a></div>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <?php foreach ($recommended as $g): ?>
        <div class="rounded-lg border border-neutral-800 bg-neutral-900 p-4">
          <div class="text-sm text-neutral-400">Level: <?= htmlspecialchars($g['level']) ?></div>
          <div class="font-semibold mb-2"><?= htmlspecialchars($g['title']) ?></div>
          <a class="text-brand text-sm" href="index.php?page=choreo_detail&id=<?= urlencode($g['id']) ?>">Start Routine →</a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
