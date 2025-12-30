<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require_login();
$u = $_SESSION['user'];
$stmt = $pdo->prepare('SELECT plan_json FROM users WHERE id=?');
$stmt->execute([$u['id']]);
$plan = json_decode($stmt->fetchColumn() ?: 'null', true);
$content = json_decode(file_get_contents(__DIR__.'/../storage/fitness_content.json'), true);
$items = $content['gym'] ?? [];
$equipment = $plan['equipment'] ?? 'none';
$goal = $plan['goal'] ?? 'general_health';
// Show all programs; mark those that best match the user's equipment/goal
$recommended = array_map(function($g) use ($equipment,$goal){
  $match = 0;
  if (!empty($g['equipment']) && $g['equipment']===$equipment) $match += 1;
  foreach (($g['tags'] ?? []) as $t) { if ($t===$goal) { $match += 1; break; } }
  $g['score'] = $match; // 2 = best fit
  return $g;
}, $items);
// sort by score desc
usort($recommended, function($a,$b){ return ($b['score']??0) <=> ($a['score']??0); });
?>
<section>
  <h2 class="text-2xl font-bold mb-2">Gym Programs</h2>
  <p class="text-neutral-400 mb-4">Showing all programs. Best matches for your setup (<span class="capitalize"><?= htmlspecialchars(str_replace('_',' ',$equipment)) ?></span>) and goal are highlighted.</p>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($recommended as $g): ?>
      <div class="rounded-lg border <?= ($g['score']??0) >= 2 ? 'border-brand/60' : 'border-neutral-800' ?> bg-neutral-900 p-4">
        <div class="text-sm text-neutral-400">Level: <?= htmlspecialchars($g['level']) ?></div>
        <div class="font-semibold mb-2"><?= htmlspecialchars($g['title']) ?></div>
        <?php if (($g['score']??0) >= 2): ?><div class="text-xs inline-block px-2 py-1 rounded bg-brand/20 text-brand mb-2">Best for you</div><?php endif; ?>
        <a class="text-brand text-sm" href="index.php?page=gym_detail&id=<?= urlencode($g['id']) ?>">View Program →</a>
      </div>
    <?php endforeach; ?>
  </div>
</section>
