<?php
$products = json_decode(file_get_contents(__DIR__.'/../storage/products.json'), true);
$cart = $_SESSION['cart'] ?? [];
$total = 0; $items=[];
foreach ($cart as $id=>$qty) {
  foreach ($products as $p) { if ($p['id']==$id) { $p['qty']=$qty; $p['line']=$qty*$p['price']; $items[]=$p; $total+=$p['line']; break; } }
}
?>
<section>
  <h2 class="text-2xl font-bold mb-4">Your Cart</h2>
  <?php if (!$items): ?>
    <p class="text-neutral-400">Cart is empty.</p>
  <?php else: ?>
    <div class="space-y-3">
      <?php foreach ($items as $it): ?>
        <div class="flex items-center justify-between rounded-lg border border-neutral-800 bg-neutral-900 p-3">
          <div>
            <div class="font-semibold"><?=htmlspecialchars($it['title'])?></div>
            <div class="text-neutral-400 text-sm">Qty: <?=$it['qty']?></div>
          </div>
          <div class="text-brand">₱<?=number_format($it['line'],2)?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="mt-6 flex items-center justify-between">
      <div class="text-xl">Total: <span class="text-brand">₱<?=number_format($total,2)?></span></div>
      <a href="index.php?page=checkout" class="px-4 py-2 rounded-lg bg-brand text-white">Checkout</a>
    </div>
  <?php endif; ?>
</section>
