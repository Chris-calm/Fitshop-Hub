<?php
$q = $_GET['q'] ?? '';
$cat = $_GET['cat'] ?? '';
$products = json_decode(file_get_contents(__DIR__.'/../storage/products.json'), true);
$filtered = array_values(array_filter($products, function($p) use ($q,$cat){
  $ok = true;
  if ($q) { $ok = $ok && (stripos($p['title'],$q)!==false || stripos($p['brand'],$q)!==false); }
  if ($cat) { $ok = $ok && ($p['category']===$cat || in_array($cat,$p['tags'])); }
  return $ok; }));
?>
<section>
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
    <h2 class="text-2xl font-bold">Shop</h2>
    <form id="catalogFilterForm" class="w-full sm:w-auto flex flex-col sm:flex-row gap-2" method="get" action="index.php">
      <input type="hidden" name="page" value="catalog" />
      <input id="catalogQ" name="q" value="<?=htmlspecialchars($q)?>" placeholder="Search..." class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
      <select id="catalogCat" name="cat" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2">
        <option value="">All</option>
        <option <?= $cat==='equipment'?'selected':'' ?> value="equipment">Equipment</option>
        <option <?= $cat==='supplements'?'selected':'' ?> value="supplements">Supplements</option>
        <option <?= $cat==='snacks'?'selected':'' ?> value="snacks">Healthy Snacks</option>
      </select>
    </form>
  </div>
  <script>
    (function(){
      const form = document.getElementById('catalogFilterForm');
      const q = document.getElementById('catalogQ');
      const cat = document.getElementById('catalogCat');
      if (!form || !q || !cat) return;

      let t = null;
      const submitNow = () => {
        if (t) { clearTimeout(t); t = null; }
        form.requestSubmit ? form.requestSubmit() : form.submit();
      };
      const submitDebounced = () => {
        if (t) clearTimeout(t);
        t = setTimeout(submitNow, 350);
      };

      cat.addEventListener('change', submitNow);
      q.addEventListener('input', submitDebounced);
    })();
  </script>
  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
    <?php foreach ($filtered as $p): ?>
      <a href="index.php?page=product&id=<?=$p['id']?>" class="group rounded-lg border border-neutral-800 bg-neutral-900 overflow-hidden hover:border-brand/50">
        <div class="aspect-square bg-neutral-800"></div>
        <div class="p-3">
          <div class="text-sm text-neutral-400"><?=htmlspecialchars($p['brand'])?></div>
          <div class="font-semibold group-hover:text-brand"><?=htmlspecialchars($p['title'])?></div>
          <div class="text-brand mt-1">₱<?=number_format($p['price'],2)?></div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
