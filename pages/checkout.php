<?php
$cart = $_SESSION['cart'] ?? [];
$products = json_decode(file_get_contents(__DIR__.'/../storage/products.json'), true);
$total = 0; foreach ($cart as $id=>$qty) { foreach ($products as $p) { if ($p['id']==$id) { $total += $qty*$p['price']; } } }
?>
<section>
  <h2 class="text-2xl font-bold mb-4">Checkout</h2>
  <?php if (!$cart): ?><p class="text-neutral-400">Your cart is empty.</p><?php else: ?>
  <form method="post" action="index.php?page=post_checkout" class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="space-y-3">
      <div>
        <label class="block text-sm text-neutral-400">Full Name</label>
        <input name="name" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
      </div>
      <div>
        <label class="block text-sm text-neutral-400">Address</label>
        <textarea name="address" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2"></textarea>
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
</section>
