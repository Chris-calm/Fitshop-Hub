<?php
$id = (int)($_GET['id'] ?? 0);
$products = json_decode(file_get_contents(__DIR__.'/../storage/products.json'), true);
$product = null; foreach ($products as $p) { if ($p['id']===$id) { $product=$p; break; } }
if (!$product) { echo '<p>Product not found.</p>'; return; }
?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-8">
  <div class="rounded-lg border border-neutral-800 bg-neutral-900 aspect-square"></div>
  <div>
    <h2 class="text-2xl font-bold mb-2"><?=htmlspecialchars($product['title'])?></h2>
    <div class="text-neutral-400 mb-2"><?=htmlspecialchars($product['brand'])?></div>
    <div class="text-2xl text-brand mb-4">₱<?=number_format($product['price'],2)?></div>
    <p class="text-neutral-300 mb-6">High quality product for your fitness journey.</p>
    <form method="post" action="index.php?page=post_add_to_cart" class="flex items-center gap-2">
      <input type="hidden" name="id" value="<?=$product['id']?>" />
      <input name="qty" type="number" min="1" value="1" class="w-20 bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
      <button class="px-4 py-2 rounded-lg bg-brand text-white">Add to Cart</button>
    </form>
  </div>
</div>
