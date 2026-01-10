<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/programs.php';
require_login();
// Load plan
$u = $_SESSION['user'];
$selectedPrograms = fh_user_selected_programs($pdo, (int)$u['id']);
fh_require_any_program($selectedPrograms, ['guides']);
$stmt = $pdo->prepare('SELECT plan_json FROM users WHERE id=?');
$stmt->execute([$u['id']]);
$plan = json_decode($stmt->fetchColumn() ?: 'null', true);
$content = json_decode(file_get_contents(__DIR__.'/../storage/fitness_content.json'), true);
$guides = $content['guides'] ?? [];
// Filter by user plan
$goal = $plan['goal'] ?? 'general_health';
$equip = $plan['activity_level'] ?? 'light';
$equipment = $plan['equipment'] ?? 'none';
$recommended = array_values(array_filter($guides, function($g) use ($goal,$equipment){
  foreach ($g['tags'] as $t) { if ($t===$goal || $t===$equipment || $t==='all') return true; }
  return false;
}));
?>
<section>
  <h2 class="text-2xl font-bold mb-2">Guides</h2>
  <p class="text-neutral-400 mb-4">Personalized for your goal: <span class="capitalize"><?= htmlspecialchars(str_replace('_',' ',$goal)) ?></span></p>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($recommended as $g): ?>
      <div class="rounded-lg border border-neutral-800 bg-neutral-900 p-4">
        <div class="text-sm text-neutral-400">Level: <?= htmlspecialchars($g['level']) ?></div>
        <div class="font-semibold mb-2"><?= htmlspecialchars($g['title']) ?></div>
        <a class="text-brand text-sm" href="index.php?page=guide_detail&id=<?= urlencode($g['id']) ?>">Open Guide →</a>
      </div>
    <?php endforeach; ?>
  </div>
</section>
