<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require_login();
$u = $_SESSION['user'];

$items = [];
$loadErr = '';
try {
  $sql = 'SELECT id, title, photo_path, calories, protein_g, carbs_g, fat_g, created_at FROM food_logs WHERE user_id=? ORDER BY created_at DESC';
  if (defined('IS_VERCEL') && IS_VERCEL) {
    $sql = 'SELECT id, title, photo_path, calories, protein_g, carbs_g, fat_g, created_at::text as created_at FROM food_logs WHERE user_id=? ORDER BY created_at DESC';
  }
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$u['id']]);
  $items = $stmt->fetchAll();
} catch (Throwable $e) {
  $loadErr = 'Failed to load food logs.';
  error_log('Food history load failed: ' . $e->getMessage());
}
?>
<section>
  <div class="mb-4 flex items-center justify-between">
    <h2 class="text-2xl font-bold">Food History</h2>
    <a class="px-3 py-2 rounded-lg bg-brand text-white" href="index.php?page=food_scan">+ Add Meal</a>
  </div>
  <?php if (!empty($loadErr)): ?>
    <div class="text-red-300"><?= htmlspecialchars($loadErr) ?></div>
  <?php elseif (!$items): ?>
    <div class="text-neutral-400">No food logs yet.</div>
  <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <?php foreach ($items as $it): ?>
        <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-4">
          <div class="font-semibold mb-1"><?= htmlspecialchars($it['title']) ?></div>
          <div class="text-xs text-neutral-500 mb-2"><?= htmlspecialchars(date('Y-m-d H:i', strtotime((string)($it['created_at'] ?? '')))) ?></div>
          <?php if (!empty($it['photo_path'])): ?>
            <img loading="lazy" src="<?= htmlspecialchars($it['photo_path']) ?>" alt="Meal photo" class="w-full h-40 object-cover rounded mb-3" />
          <?php endif; ?>
          <div class="grid grid-cols-4 gap-2 text-sm">
            <div><div class="text-neutral-400">Cal</div><div class="font-semibold"><?= $it['calories'] !== null ? intval($it['calories']) : '-' ?></div></div>
            <div><div class="text-neutral-400">P</div><div class="font-semibold"><?= is_numeric($it['protein_g']) ? (number_format((float)$it['protein_g'],1) . ' g') : '-' ?></div></div>
            <div><div class="text-neutral-400">C</div><div class="font-semibold"><?= is_numeric($it['carbs_g']) ? (number_format((float)$it['carbs_g'],1) . ' g') : '-' ?></div></div>
            <div><div class="text-neutral-400">F</div><div class="font-semibold"><?= is_numeric($it['fat_g']) ? (number_format((float)$it['fat_g'],1) . ' g') : '-' ?></div></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
