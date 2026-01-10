<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/programs.php';
require_login();
$u = $_SESSION['user'];
$selectedPrograms = fh_user_selected_programs($pdo, (int)$u['id']);
fh_require_any_program($selectedPrograms, ['strength', 'cardio']);
$stmt = $pdo->prepare('SELECT plan_json, goal, activity_level, equipment, diet FROM users WHERE id=?');
$stmt->execute([$u['id']]);
$row = $stmt->fetch();
$plan = json_decode(($row['plan_json'] ?? '') ?: 'null', true);
$content = json_decode(file_get_contents(__DIR__.'/../storage/fitness_content.json'), true);
$items = $content['gym'] ?? [];
$equipment = $plan['equipment'] ?? ($row['equipment'] ?? 'none');
$goal = $plan['goal'] ?? ($row['goal'] ?? 'general_health');
// Filter based on selected programs.
if (!empty($selectedPrograms)) {
  $allowStrength = in_array('strength', $selectedPrograms, true);
  $allowCardio = in_array('cardio', $selectedPrograms, true);
  $items = array_values(array_filter($items, function($g) use ($allowStrength, $allowCardio){
    $tags = $g['tags'] ?? [];
    $tagsStr = implode(' ', array_map('strval', is_array($tags) ? $tags : []));
    $isCardio = stripos($tagsStr, 'endurance') !== false || stripos($tagsStr, 'cardio') !== false;
    if ($isCardio) return $allowCardio;
    return $allowStrength;
  }));
}
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
<section class="min-h-[calc(100vh-140px)] flex items-center">
  <div class="w-full">
    <h2 class="text-2xl font-bold mb-2">Gym Programs</h2>
    <p class="text-neutral-400 mb-4">Showing all programs. Best matches for your setup (<span class="capitalize"><?= htmlspecialchars(str_replace('_',' ',$equipment)) ?></span>) and goal are highlighted.</p>
    <?php if (empty($recommended)): ?>
      <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-4">
        <div class="font-semibold mb-1">No programs available</div>
        <div class="text-sm text-neutral-400">Your current Customize selection filtered out all gym programs. Update your selections to bring programs back.</div>
        <div class="mt-3"><a class="fh-btn fh-btn-primary" href="index.php?page=customize">Open Customize</a></div>
      </div>
    <?php else: ?>
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
    <?php endif; ?>
  </div>
</section>
