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
