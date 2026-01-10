<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require_login();

$u = $_SESSION['user'];

$programs = [
  ['id' => 'strength', 'title' => 'Strength / Gym', 'desc' => 'Build muscle, track sets, and progress your lifts.'],
  ['id' => 'cardio', 'title' => 'Cardio', 'desc' => 'Improve endurance and burn calories with cardio sessions.'],
  ['id' => 'choreography', 'title' => 'Choreography', 'desc' => 'Dance/cardio routines tailored to your goal.'],
  ['id' => 'guides', 'title' => 'Guides', 'desc' => 'Learn proper form, routines, and training fundamentals.'],
  ['id' => 'nutrition', 'title' => 'Nutrition Tracking', 'desc' => 'Log meals and track calories and macros.'],
  ['id' => 'recovery', 'title' => 'Recovery / Mobility', 'desc' => 'Improve mobility and reduce soreness with recovery sessions.'],
  ['id' => 'steps', 'title' => 'Steps Goal', 'desc' => 'Focus on daily steps and consistency.'],
];

$validIds = array_column($programs, 'id');

$stmt = $pdo->prepare('SELECT plan_json FROM users WHERE id=?');
$stmt->execute([(int)$u['id']]);
$plan = json_decode($stmt->fetchColumn() ?: 'null', true);
if (!is_array($plan)) {
  $plan = [];
}

$selected = [];
if (!empty($plan['programs']) && is_array($plan['programs'])) {
  foreach ($plan['programs'] as $pid) {
    $pid = (string)$pid;
    if (in_array($pid, $validIds, true)) {
      $selected[] = $pid;
    }
  }
}

$selected = array_values(array_unique($selected));

$ok = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $incoming = $_POST['programs'] ?? [];
  if (!is_array($incoming)) {
    $incoming = [];
  }

  $next = [];
  foreach ($incoming as $pid) {
    $pid = (string)$pid;
    if (in_array($pid, $validIds, true)) {
      $next[] = $pid;
    }
  }
  $next = array_values(array_unique($next));

  try {
    // Empty selection means: do not restrict modules (show all)
    $plan['programs'] = $next;
    $encoded = json_encode($plan);
    if ($encoded === false) {
      $encoded = '{}';
    }
    $up = $pdo->prepare('UPDATE users SET plan_json=? WHERE id=?');
    $up->execute([$encoded, (int)$u['id']]);
    $selected = $next;
    $ok = 'Programs updated.';
  } catch (Throwable $e) {
    $err = 'Failed to save programs.';
  }
}
?>

<section class="min-h-[calc(100vh-140px)] flex items-center">
  <div class="w-full max-w-4xl mx-auto">
    <div class="flex items-end justify-between gap-3 mb-4">
      <div>
        <h2 class="text-2xl font-bold">Customize Programs</h2>
        <div class="text-sm text-neutral-400">Pick the programs you want to focus on. You can change this anytime.</div>
      </div>
      <a href="index.php?page=fitness" class="fh-btn fh-btn-ghost">Back to Fitness</a>
    </div>

  <?php if (!empty($err)): ?><div class="mb-3 text-red-400"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <?php if (!empty($ok)): ?><div class="mb-3 text-emerald-300"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

  <form method="post" class="fh-card p-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <?php foreach ($programs as $p): ?>
        <?php $pid = (string)$p['id']; $isChecked = in_array($pid, $selected, true); ?>
        <label class="rounded-xl border border-white/10 bg-white/5 p-4 flex gap-3 cursor-pointer">
          <input type="checkbox" class="mt-1 accent-brand" name="programs[]" value="<?= htmlspecialchars($pid) ?>" <?= $isChecked ? 'checked' : '' ?> />
          <span class="block">
            <span class="block font-semibold"><?= htmlspecialchars((string)$p['title']) ?></span>
            <span class="block text-sm text-neutral-400"><?= htmlspecialchars((string)$p['desc']) ?></span>
          </span>
        </label>
      <?php endforeach; ?>
    </div>

    <div class="mt-4 flex items-center justify-end gap-2">
      <button class="fh-btn fh-btn-primary" type="submit">Save Programs</button>
    </div>
    </form>
  </div>
</section>
