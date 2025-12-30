<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require_login();
$u = $_SESSION['user'];

// Fetch gym sessions summary
$gymStmt = $pdo->prepare('SELECT id, program_title AS title, total_duration_sec AS duration_sec, total_volume, ended_at FROM workout_sessions WHERE user_id=? ORDER BY ended_at DESC LIMIT 50');
$gymStmt->execute([$u['id']]);
$gym = $gymStmt->fetchAll();

// Fetch activity sessions (choreo + guides)
$actStmt = $pdo->prepare('SELECT id, activity_type, title, duration_sec, completed_steps, ended_at FROM activity_sessions WHERE user_id=? ORDER BY ended_at DESC LIMIT 50');
$actStmt->execute([$u['id']]);
$acts = $actStmt->fetchAll();
?>
<section>
  <div class="mb-4 flex items-center justify-between">
    <h2 class="text-2xl font-bold">My Fitness History</h2>
    <a class="px-3 py-2 rounded-lg bg-neutral-800" href="index.php?page=fitness">Back to Fitness</a>
  </div>

  <h3 class="text-xl font-semibold mb-2">Gym Sessions</h3>
  <?php if (!$gym): ?>
    <div class="text-neutral-400 mb-6">No gym sessions yet.</div>
  <?php else: ?>
    <div class="rounded-xl border border-neutral-800 bg-neutral-900 overflow-auto mb-6">
      <table class="w-full min-w-[700px] text-sm">
        <thead class="bg-neutral-950 text-neutral-400">
          <tr>
            <th class="text-left px-3 py-2">Date</th>
            <th class="text-left px-3 py-2">Program</th>
            <th class="text-left px-3 py-2">Duration</th>
            <th class="text-left px-3 py-2">Volume (kg)</th>
            <th class="text-left px-3 py-2">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($gym as $g): ?>
          <tr class="border-t border-neutral-800">
            <td class="px-3 py-2"><?= htmlspecialchars(date('Y-m-d H:i', strtotime($g['ended_at']))) ?></td>
            <td class="px-3 py-2"><?= htmlspecialchars($g['title']) ?></td>
            <td class="px-3 py-2"><?= intval($g['duration_sec']/60) ?> min</td>
            <td class="px-3 py-2"><?= number_format((float)$g['total_volume'],2) ?></td>
            <td class="px-3 py-2"><a class="text-brand" href="index.php?page=gym">Repeat</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <h3 class="text-xl font-semibold mb-2">Choreography & Guides</h3>
  <?php if (!$acts): ?>
    <div class="text-neutral-400">No activity sessions yet.</div>
  <?php else: ?>
    <div class="rounded-xl border border-neutral-800 bg-neutral-900 overflow-auto">
      <table class="w-full min-w-[700px] text-sm">
        <thead class="bg-neutral-950 text-neutral-400">
          <tr>
            <th class="text-left px-3 py-2">Date</th>
            <th class="text-left px-3 py-2">Type</th>
            <th class="text-left px-3 py-2">Title</th>
            <th class="text-left px-3 py-2">Duration</th>
            <th class="text-left px-3 py-2">Steps</th>
            <th class="text-left px-3 py-2">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($acts as $a): ?>
          <tr class="border-t border-neutral-800">
            <td class="px-3 py-2"><?= htmlspecialchars(date('Y-m-d H:i', strtotime($a['ended_at']))) ?></td>
            <td class="px-3 py-2 capitalize"><?= htmlspecialchars($a['activity_type']) ?></td>
            <td class="px-3 py-2"><?= htmlspecialchars($a['title']) ?></td>
            <td class="px-3 py-2"><?= intval($a['duration_sec']/60) ?> min</td>
            <td class="px-3 py-2"><?= intval($a['completed_steps']) ?></td>
            <td class="px-3 py-2">
              <?php if ($a['activity_type']==='choreo'): ?>
                <a class="text-brand" href="index.php?page=choreography">Repeat</a>
              <?php else: ?>
                <a class="text-brand" href="index.php?page=guides">Repeat</a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
