<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require_login();
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$content = json_decode(file_get_contents(__DIR__.'/../storage/fitness_content.json'), true);
$items = $content['gym'] ?? [];
$item = null;
foreach ($items as $it) { if (intval($it['id']) === $id) { $item = $it; break; } }
?>
<section>
  <?php if (!$item): ?>
    <h2 class="text-2xl font-bold mb-2">Program not found</h2>
    <p class="text-neutral-400 mb-4">Please go back and select another program.</p>
    <a class="text-brand" href="index.php?page=gym">← Back to Programs</a>
  <?php else: ?>
    <div class="mb-4"><a class="text-brand" href="index.php?page=gym">← Back</a></div>
    <h2 class="text-2xl font-bold mb-2"><?= htmlspecialchars($item['title']) ?></h2>
    <div class="text-sm text-neutral-400 mb-4">Level: <?= htmlspecialchars($item['level']) ?></div>
    <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-4 mb-4">
      <p class="text-neutral-300">Preview information for this program will appear here. We can add exercises, sets x reps, and rest times.</p>
    </div>
    <a href="index.php?page=gym_session&program_id=<?= urlencode($item['id']) ?>" class="px-4 py-2 rounded-lg bg-brand text-white inline-block">Start Program</a>
  <?php endif; ?>
</section>
