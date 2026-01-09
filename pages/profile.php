<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/cart_store.php';
require_login();
$sessionUser = $_SESSION['user'];
$stmt = $pdo->prepare('SELECT id,name,email,photo_url,plan_json FROM users WHERE id=? LIMIT 1');
$stmt->execute([(int)$sessionUser['id']]);
$u = $stmt->fetch();

$plan = $u && !empty($u['plan_json']) ? json_decode((string)$u['plan_json'], true) : null;

$cart = fh_cart_get();
$cartCount = fh_cart_count($cart);
$products = json_decode((string)file_get_contents(__DIR__ . '/../storage/products.json'), true);
if (!is_array($products)) {
  $products = [];
}

$cartPreview = [];
$cartItems = [];
$cartTotal = 0.0;
foreach ($cart as $key => $q) {
  $parsed = fh_cart_parse_key((string)$key);
  if (!$parsed) {
    continue;
  }
  $pid = (int)$parsed['id'];
  $opt = (string)$parsed['option'];
  foreach ($products as $pp) {
    if ((int)($pp['id'] ?? 0) === $pid) {
      $title = (string)($pp['title'] ?? '');
      $cartPreview[] = ['title' => $title, 'qty' => (int)$q, 'opt' => $opt];
      $cartItems[] = [
        'title' => $title,
        'qty' => (int)$q,
        'opt' => $opt,
        'price' => (float)($pp['price'] ?? 0),
        'line' => ((int)$q) * ((float)($pp['price'] ?? 0)),
      ];
      $cartTotal += ((int)$q) * ((float)($pp['price'] ?? 0));
      break;
    }
  }
  if (count($cartPreview) >= 3) break;
}

$oStmt = $pdo->prepare('SELECT o.id, o.total, o.created_at,
   (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id=o.id) AS items
  FROM orders o WHERE o.user_id=? ORDER BY o.id DESC');
$oStmt->execute([(int)$sessionUser['id']]);
$userOrders = $oStmt->fetchAll();

$today = date('Y-m-d');
$start7 = date('Y-m-d', strtotime('-6 days'));

$foodTodayCal = 0;
$foodWeekCal = 0;
$pWeek = 0.0;
$cWeek = 0.0;
$fWeek = 0.0;

$stepsToday = 0;
$stepsWeek = 0;

$workoutMinWeek = 0;

try {
  $stmt = $pdo->prepare("SELECT calories, protein_g, carbs_g, fat_g, created_at FROM food_logs WHERE user_id=? AND created_at >= ? ORDER BY created_at DESC");
  $stmt->execute([(int)$sessionUser['id'], $start7 . ' 00:00:00']);
  $rows = $stmt->fetchAll();
  foreach ($rows as $r) {
    $cal = (int)($r['calories'] ?? 0);
    $foodWeekCal += max(0, $cal);
    $pWeek += (float)($r['protein_g'] ?? 0);
    $cWeek += (float)($r['carbs_g'] ?? 0);
    $fWeek += (float)($r['fat_g'] ?? 0);
    $d = !empty($r['created_at']) ? date('Y-m-d', strtotime((string)$r['created_at'])) : '';
    if ($d === $today) {
      $foodTodayCal += max(0, $cal);
    }
  }
} catch (Throwable $e) {
}

try {
  $stmt = $pdo->prepare('SELECT step_date, steps FROM steps_logs WHERE user_id=? AND step_date >= ? ORDER BY step_date DESC');
  $stmt->execute([(int)$sessionUser['id'], $start7]);
  $rows = $stmt->fetchAll();
  foreach ($rows as $r) {
    $steps = (int)($r['steps'] ?? 0);
    $stepsWeek += max(0, $steps);
    $d = (string)($r['step_date'] ?? '');
    if ($d === $today) {
      $stepsToday += max(0, $steps);
    }
  }
} catch (Throwable $e) {
}

try {
  $stmt = $pdo->prepare('SELECT total_duration_sec, started_at FROM workout_sessions WHERE user_id=? AND started_at >= ? ORDER BY started_at DESC');
  $stmt->execute([(int)$sessionUser['id'], $start7 . ' 00:00:00']);
  $rows = $stmt->fetchAll();
  foreach ($rows as $r) {
    $sec = (int)($r['total_duration_sec'] ?? 0);
    $workoutMinWeek += max(0, (int)round($sec / 60));
  }
} catch (Throwable $e) {
}

// Simple estimates (adjustable later)
$calBurnStepsToday = (int)round($stepsToday * 0.04);
$calBurnStepsWeek = (int)round($stepsWeek * 0.04);
$calBurnWorkoutsWeek = (int)round($workoutMinWeek * 6);
$calBurnWorkoutsToday = 0;

// Estimate today's workout burn by reading today's sessions (cheap)
try {
  $stmt = $pdo->prepare('SELECT total_duration_sec, started_at FROM workout_sessions WHERE user_id=? AND started_at >= ? ORDER BY started_at DESC');
  $stmt->execute([(int)$sessionUser['id'], $today . ' 00:00:00']);
  $rows = $stmt->fetchAll();
  $minToday = 0;
  foreach ($rows as $r) {
    $sec = (int)($r['total_duration_sec'] ?? 0);
    $minToday += max(0, (int)round($sec / 60));
  }
  $calBurnWorkoutsToday = (int)round($minToday * 6);
} catch (Throwable $e) {
}

$calBurnToday = $calBurnStepsToday + $calBurnWorkoutsToday;
$calBurnWeek = $calBurnStepsWeek + $calBurnWorkoutsWeek;

$netToday = $foodTodayCal - $calBurnToday;
$netWeek = $foodWeekCal - $calBurnWeek;
?>
<section class="max-w-4xl mx-auto">
  <div class="flex items-start justify-between gap-3 mb-4">
    <h2 class="text-2xl font-bold">Profile</h2>
    <a href="index.php?page=settings" class="fh-btn fh-btn-primary">Open Settings</a>
  </div>

  <div class="fh-card p-4 flex items-center justify-between gap-3">
    <div class="flex items-center gap-3 min-w-0">
      <img src="<?= htmlspecialchars(($u['photo_url'] ?? '') ?: 'https://i.pravatar.cc/80') ?>" class="w-12 h-12 rounded-full" alt="avatar"/>
      <div class="min-w-0">
        <div class="font-semibold text-lg truncate"><?= htmlspecialchars((string)($u['name'] ?? '')) ?></div>
        <div class="text-neutral-400 text-sm truncate"><?= htmlspecialchars((string)($u['email'] ?? '')) ?></div>
      </div>
    </div>
    <div class="text-right">
      <div class="text-xs text-neutral-400">Quick actions</div>
      <div class="mt-1 flex items-center gap-2 justify-end">
        <a href="index.php?page=cart" class="fh-btn fh-btn-ghost">Cart (<?= (int)$cartCount ?>)</a>
        <a href="index.php?page=catalog" class="fh-btn fh-btn-ghost">Shop</a>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
    <div class="fh-card p-4">
      <div class="text-neutral-400 text-sm">Items in cart</div>
      <div class="text-2xl font-bold text-brand"><?= (int)$cartCount ?></div>
    </div>
    <div class="fh-card p-4">
      <div class="text-neutral-400 text-sm">Cart total</div>
      <div class="text-2xl font-bold">₱<?= number_format((float)$cartTotal, 2) ?></div>
    </div>
    <div class="fh-card p-4">
      <div class="text-neutral-400 text-sm">Orders</div>
      <div class="text-2xl font-bold"><?= is_array($userOrders) ? count($userOrders) : 0 ?></div>
    </div>
  </div>

  <h3 class="text-xl font-semibold mt-6 mb-3">Analytics</h3>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="fh-card p-4">
      <div class="text-neutral-400 text-sm">Today calories (eaten)</div>
      <div class="text-2xl font-bold"><?= (int)$foodTodayCal ?></div>
      <div class="text-xs text-neutral-500 mt-1">From Food Logs</div>
    </div>
    <div class="fh-card p-4">
      <div class="text-neutral-400 text-sm">Today calories (burned)</div>
      <div class="text-2xl font-bold text-brand"><?= (int)$calBurnToday ?></div>
      <div class="text-xs text-neutral-500 mt-1"><?= (int)$stepsToday ?> steps • workouts est.</div>
    </div>
    <div class="fh-card p-4">
      <div class="text-neutral-400 text-sm">Today net calories</div>
      <div class="text-2xl font-bold"><?= (int)$netToday ?></div>
      <div class="text-xs text-neutral-500 mt-1">Eaten − Burned</div>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
    <div class="fh-card p-4">
      <div class="text-neutral-400 text-sm">7-day calories (eaten)</div>
      <div class="text-2xl font-bold"><?= (int)$foodWeekCal ?></div>
      <div class="text-xs text-neutral-500 mt-1">Last 7 days</div>
    </div>
    <div class="fh-card p-4">
      <div class="text-neutral-400 text-sm">7-day calories (burned)</div>
      <div class="text-2xl font-bold text-brand"><?= (int)$calBurnWeek ?></div>
      <div class="text-xs text-neutral-500 mt-1"><?= (int)$stepsWeek ?> steps • <?= (int)$workoutMinWeek ?> workout mins</div>
    </div>
    <div class="fh-card p-4">
      <div class="text-neutral-400 text-sm">7-day net calories</div>
      <div class="text-2xl font-bold"><?= (int)$netWeek ?></div>
      <div class="text-xs text-neutral-500 mt-1">Eaten − Burned</div>
    </div>
  </div>

  <div class="fh-card p-4 mt-4">
    <div class="flex items-center justify-between gap-3">
      <div>
        <div class="text-neutral-400 text-sm">7-day macros</div>
        <div class="text-xs text-neutral-500">Totals from Food Logs</div>
      </div>
      <div class="flex items-center gap-2">
        <a href="index.php?page=food_history" class="fh-btn fh-btn-ghost">Food History</a>
        <a href="index.php?page=food_scan" class="fh-btn fh-btn-primary">+ Add Meal</a>
      </div>
    </div>
    <div class="grid grid-cols-3 gap-3 mt-3">
      <div class="rounded-xl border border-white/10 bg-white/5 p-3">
        <div class="text-neutral-400 text-xs">Protein</div>
        <div class="text-lg font-semibold"><?= number_format((float)$pWeek, 1) ?> g</div>
      </div>
      <div class="rounded-xl border border-white/10 bg-white/5 p-3">
        <div class="text-neutral-400 text-xs">Carbs</div>
        <div class="text-lg font-semibold"><?= number_format((float)$cWeek, 1) ?> g</div>
      </div>
      <div class="rounded-xl border border-white/10 bg-white/5 p-3">
        <div class="text-neutral-400 text-xs">Fat</div>
        <div class="text-lg font-semibold"><?= number_format((float)$fWeek, 1) ?> g</div>
      </div>
    </div>
    <div class="mt-3 text-xs text-neutral-500">Calories burned is an estimate: steps×0.04 + workout minutes×6.</div>
  </div>

  <h3 class="text-xl font-semibold mt-6 mb-3">Current Programs</h3>
  <?php if (!$plan || !is_array($plan)): ?>
    <div class="fh-card p-4">
      <div class="text-neutral-400">No plan yet. Create one in Settings.</div>
      <div class="mt-3">
        <a href="index.php?page=settings" class="fh-btn fh-btn-primary">Go to Settings</a>
      </div>
    </div>
  <?php else: ?>
    <div class="fh-card p-4">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <div class="text-neutral-400 text-sm">Goal</div>
          <div class="font-semibold capitalize"><?= htmlspecialchars(str_replace('_', ' ', (string)($plan['goal'] ?? ''))) ?></div>
        </div>
        <div>
          <div class="text-neutral-400 text-sm">Activity</div>
          <div class="font-semibold capitalize"><?= htmlspecialchars(str_replace('_', ' ', (string)($plan['activity_level'] ?? ''))) ?></div>
        </div>
        <div>
          <div class="text-neutral-400 text-sm">Minutes / week</div>
          <div class="font-semibold"><?= (int)($plan['recommended_minutes_per_week'] ?? 0) ?> mins</div>
        </div>
      </div>
      <?php if (!empty($plan['modules']) && is_array($plan['modules'])): ?>
        <div class="mt-4 flex flex-wrap gap-2">
          <?php foreach ($plan['modules'] as $m): ?>
            <span class="px-2.5 py-1 rounded-full border border-white/10 bg-white/5 text-sm text-neutral-200"><?= htmlspecialchars((string)$m) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <h3 class="text-xl font-semibold mt-6 mb-3">Cart Summary</h3>
  <div class="fh-card p-4">
    <?php if (empty($cartItems)): ?>
      <div class="text-neutral-400">Your cart is empty.</div>
      <div class="mt-3">
        <a href="index.php?page=catalog" class="fh-btn fh-btn-primary">Browse Catalog</a>
      </div>
    <?php else: ?>
      <div class="space-y-2">
        <?php foreach (array_slice($cartItems, 0, 4) as $it): ?>
          <div class="flex items-center justify-between gap-3 border-b border-white/10 pb-2">
            <div class="min-w-0">
              <div class="font-semibold truncate"><?= htmlspecialchars((string)$it['title']) ?></div>
              <div class="text-xs text-neutral-400"><?= htmlspecialchars((string)($it['opt'] ?? 'Default')) ?> • Qty: <?= (int)$it['qty'] ?> • ₱<?= number_format((float)$it['price'], 2) ?> each</div>
            </div>
            <div class="text-brand font-semibold">₱<?= number_format((float)$it['line'], 2) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="mt-4 flex items-center justify-between gap-3">
        <div class="text-neutral-300">Total</div>
        <div class="text-lg font-bold">₱<?= number_format((float)$cartTotal, 2) ?></div>
      </div>
      <div class="mt-4 flex items-center gap-2">
        <a href="index.php?page=cart" class="fh-btn fh-btn-ghost">Open Cart</a>
        <a href="index.php?page=checkout" class="fh-btn fh-btn-primary">Checkout</a>
      </div>
    <?php endif; ?>
  </div>

  <h3 class="text-xl font-semibold mt-6 mb-3">Recent Orders</h3>
  <?php if (empty($userOrders)): ?>
    <div class="text-neutral-400">No orders yet.</div>
  <?php else: ?>
    <div class="space-y-3">
      <?php foreach (array_slice($userOrders, 0, 5) as $o): ?>
        <a href="index.php?page=order&id=<?= (int)$o['id'] ?>" class="block fh-card p-4 hover:border-brand/50">
          <div class="flex items-center justify-between">
            <div>
              <div class="font-semibold">Order #<?= (int)$o['id'] ?></div>
              <div class="text-neutral-400 text-sm">Items: <?= (int)$o['items'] ?> • Total: ₱<?= number_format((float)$o['total'], 2) ?></div>
            </div>
            <div class="text-brand text-sm">View →</div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
