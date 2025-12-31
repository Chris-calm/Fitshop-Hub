<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require_login();
$sessionUser = $_SESSION['user'];
$stmt = $pdo->prepare('SELECT id,name,email,photo_url,goal,activity_level,equipment,diet,plan_json FROM users WHERE id=? LIMIT 1');
$stmt->execute([$sessionUser['id']]);
$u = $stmt->fetch();
$plan = $u && !empty($u['plan_json']) ? json_decode($u['plan_json'], true) : null;
// Load user orders from DB
$oStmt = $pdo->prepare('SELECT o.id, o.total, o.created_at,
   (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id=o.id) AS items
  FROM orders o WHERE o.user_id=? ORDER BY o.id DESC');
$oStmt->execute([$u['id']]);
$userOrders = $oStmt->fetchAll();
?>
<section class="max-w-4xl mx-auto">
  <h2 class="text-2xl font-bold mb-4">Your Profile</h2>
  <div class="rounded-lg border border-neutral-800 bg-neutral-900 p-4 flex items-center gap-3">
    <?php $avatarSeed = urlencode((string)($u['email'] ?? ($u['id'] ?? 'user'))); ?>
    <img src="<?= htmlspecialchars(($u['photo_url'] ?? '') ?: ('https://i.pravatar.cc/80?u=' . $avatarSeed)) ?>" class="w-12 h-12 rounded-full" alt="avatar"/>
    <div>
      <div class="font-semibold text-lg"><?= htmlspecialchars($u['name']) ?></div>
      <div class="text-neutral-400 text-sm"><?= htmlspecialchars($u['email']) ?></div>
    </div>
  </div>

  <?php if ($plan): ?>
    <h3 class="text-xl font-semibold mt-6 mb-3">Your Personalized Plan</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="rounded-lg border border-neutral-800 bg-neutral-900 p-4">
        <div class="text-neutral-400 text-sm">Goal</div>
        <div class="font-semibold capitalize"><?= str_replace('_',' ', htmlspecialchars($plan['goal'])) ?></div>
      </div>
      <div class="rounded-lg border border-neutral-800 bg-neutral-900 p-4">
        <div class="text-neutral-400 text-sm">Activity Level</div>
        <div class="font-semibold capitalize"><?= str_replace('_',' ', htmlspecialchars($plan['activity_level'])) ?></div>
      </div>
      <div class="rounded-lg border border-neutral-800 bg-neutral-900 p-4">
        <div class="text-neutral-400 text-sm">Minutes / week</div>
        <div class="font-semibold"><?= (int)$plan['recommended_minutes_per_week'] ?> mins</div>
      </div>
    </div>
    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="rounded-lg border border-neutral-800 bg-neutral-900 p-4">
        <div class="font-semibold mb-2">Weekly Schedule</div>
        <ul class="space-y-1 text-sm text-neutral-300">
          <?php foreach ($plan['weekly_schedule'] as $day=>$act): ?>
            <li><span class="text-neutral-400 w-16 inline-block"><?= htmlspecialchars($day) ?>:</span> <?= htmlspecialchars($act) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="rounded-lg border border-neutral-800 bg-neutral-900 p-4">
        <div class="font-semibold mb-2">Recommended Modules & Tips</div>
        <ul class="list-disc pl-6 space-y-1 text-sm text-neutral-300">
          <?php foreach ($plan['modules'] as $m): ?><li><?= htmlspecialchars($m) ?></li><?php endforeach; ?>
          <?php foreach (($plan['diet_tips'] ?? []) as $t): ?><li><?= htmlspecialchars($t) ?></li><?php endforeach; ?>
        </ul>
      </div>
    </div>
  <?php endif; ?>

  <h3 class="text-xl font-semibold mt-6 mb-3">Your Orders</h3>
  <?php if (!$userOrders): ?>
    <p class="text-neutral-400">No orders yet.</p>
  <?php else: ?>
    <div class="space-y-3">
      <?php foreach ($userOrders as $o): ?>
        <a href="index.php?page=order&id=<?= (int)$o['id'] ?>" class="block rounded-lg border border-neutral-800 bg-neutral-900 p-4 hover:border-brand/50">
          <div class="flex items-center justify-between">
            <div>
              <div class="font-semibold">Order #<?= (int)$o['id'] ?></div>
              <div class="text-neutral-400 text-sm">Items: <?= (int)$o['items'] ?> • Total: ₱<?= number_format($o['total'],2) ?></div>
            </div>
            <div class="text-brand text-sm">View →</div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
