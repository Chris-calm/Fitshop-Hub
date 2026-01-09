<?php
$userId = !empty($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
require_once __DIR__ . '/../includes/cart_store.php';
$cart = fh_cart_get();
$products = json_decode(file_get_contents(__DIR__.'/../storage/products.json'), true);
$total = 0;
foreach ($cart as $key => $qty) {
  $parsed = fh_cart_parse_key((string)$key);
  if (!$parsed) {
    continue;
  }
  $id = (int)$parsed['id'];
  foreach ($products as $p) {
    if ((int)($p['id'] ?? 0) === $id) {
      $total += ((int)$qty) * ((float)($p['price'] ?? 0));
      break;
    }
  }
}

$addresses = [];
if ($userId) {
  try {
    require __DIR__ . '/../includes/db.php';
    $aStmt = $pdo->prepare('SELECT id, full_name, phone, line1, line2, city, province, postal_code, is_default FROM user_addresses WHERE user_id=? ORDER BY is_default DESC, id DESC');
    $aStmt->execute([$userId]);
    $addresses = $aStmt->fetchAll();
  } catch (Throwable $e) {
    $addresses = [];
  }
}
?>
<section>
  <h2 class="text-2xl font-bold mb-4">Checkout</h2>
  <?php if (!$cart): ?><p class="text-neutral-400">Your cart is empty.</p><?php else: ?>
  <?php if (!$userId || empty($addresses)): ?>
    <div class="rounded-lg border border-neutral-800 bg-neutral-900 p-4">
      <div class="text-neutral-300 font-semibold mb-1">No saved address</div>
      <div class="text-neutral-400 text-sm">You must add a saved address before checkout.</div>
      <div class="mt-3">
        <a href="index.php?page=profile" class="px-4 py-2 rounded-lg bg-brand text-white inline-block">Go to Profile</a>
      </div>
    </div>
  <?php else: ?>
  <form method="post" action="index.php?page=post_checkout" class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="space-y-3">
      <div>
        <label class="block text-sm text-neutral-400">Delivery Address</label>
        <select name="address_id" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2">
          <?php foreach ($addresses as $a): ?>
            <?php
              $label = ($a['full_name'] ?? '') . ' • ' . ($a['phone'] ?? '') . ' • ' . ($a['line1'] ?? '');
              if (!empty($a['line2'])) { $label .= ', ' . $a['line2']; }
              $label .= ', ' . ($a['city'] ?? '') . ', ' . ($a['province'] ?? '') . ' ' . ($a['postal_code'] ?? '');
              if (!empty($a['is_default'])) { $label = 'Default • ' . $label; }
            ?>
            <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="mt-2 text-sm text-neutral-400">Manage addresses in <a class="underline" href="index.php?page=profile">Profile</a>.</div>
      </div>
      <div>
        <label class="block text-sm text-neutral-400">Payment Method</label>
        <select name="payment" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2">
          <option value="gcash">GCash</option>
          <option value="maya">Maya</option>
        </select>
      </div>
    </div>
    <div class="rounded-lg border border-neutral-800 bg-neutral-900 p-4">
      <div class="text-neutral-400">Total</div>
      <div class="text-2xl text-brand mb-4">₱<?=number_format($total,2)?></div>
      <button class="w-full px-4 py-2 rounded-lg bg-brand text-white">Place Order</button>
    </div>
  </form>
  <?php endif; ?>
  <?php endif; ?>
</section>
