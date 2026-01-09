<?php
$id = (int)($_GET['id'] ?? 0);
$products = json_decode(file_get_contents(__DIR__.'/../storage/products.json'), true);
$product = null; foreach ($products as $p) { if ($p['id']===$id) { $product=$p; break; } }
if (!$product) { echo '<p>Product not found.</p>'; return; }

$titleText = (string)($product['title'] ?? 'Fitshop Hub');
$img = (string)($product['image_url'] ?? '');
$keywords = trim($titleText);
if (!empty($product['category'])) {
  $keywords .= ' ' . (string)$product['category'];
}

$queryImg = 'https://source.unsplash.com/900x900/?' . rawurlencode($keywords);
$fallbackImg = 'https://placehold.co/900x900/png?text=' . rawurlencode($titleText);

if ($img === '' || stripos($img, 'picsum.photos') !== false) {
  $img = $queryImg;
}
?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-8">
  <div class="fh-card overflow-hidden aspect-square" style="background: linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.02));">
    <img
      src="<?= htmlspecialchars($img) ?>"
      alt="<?= htmlspecialchars((string)($product['title'] ?? 'Product')) ?>"
      class="w-full h-full object-cover"
      loading="lazy"
      decoding="async"
      referrerpolicy="no-referrer"
      onerror="this.onerror=null;this.src=<?= json_encode($fallbackImg) ?>;"
    />
  </div>
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
