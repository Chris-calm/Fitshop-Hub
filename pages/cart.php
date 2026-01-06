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
    <div class="flex items-center justify-end mb-3">
      <button type="button" id="btnClearCart" class="px-3 py-2 rounded-lg bg-neutral-800 hover:bg-neutral-700">Clear cart</button>
    </div>
    <div id="cartItems" class="space-y-3">
      <?php foreach ($items as $it): ?>
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 rounded-lg border border-neutral-800 bg-neutral-900 p-3" data-cart-item="<?=$it['id']?>">
          <div>
            <div class="font-semibold"><?=htmlspecialchars($it['title'])?></div>
            <div class="text-neutral-400 text-sm">₱<?=number_format((float)$it['price'],2)?> each</div>
          </div>
          <div class="flex items-center gap-3">
            <div class="flex items-center gap-2">
              <button type="button" class="cart-dec px-2 py-1 rounded bg-neutral-800 hover:bg-neutral-700" data-id="<?=$it['id']?>">-</button>
              <span class="min-w-10 text-center" data-qty="<?=$it['id']?>"><?=$it['qty']?></span>
              <button type="button" class="cart-inc px-2 py-1 rounded bg-neutral-800 hover:bg-neutral-700" data-id="<?=$it['id']?>">+</button>
            </div>
            <div class="text-brand min-w-28 text-right" data-line="<?=$it['id']?>">₱<?=number_format((float)$it['line'],2)?></div>
            <button type="button" class="cart-remove px-2 py-1 text-sm rounded bg-neutral-800 hover:bg-neutral-700" data-id="<?=$it['id']?>">Remove</button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="mt-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <div class="text-xl">Total: <span id="cartTotal" class="text-brand">₱<?=number_format((float)$total,2)?></span></div>
      <div class="flex items-center gap-2">
        <a href="index.php?page=catalog" class="px-4 py-2 rounded-lg bg-neutral-800 hover:bg-neutral-700">Continue shopping</a>
        <a href="index.php?page=checkout" class="px-4 py-2 rounded-lg bg-brand text-white">Checkout</a>
      </div>
    </div>
    <script>
    (function(){
      const BASE = (typeof window !== 'undefined' && window.__BASE_URL__) ? window.__BASE_URL__ : '';
      const totalEl = document.getElementById('cartTotal');
      const itemsEl = document.getElementById('cartItems');
      const clearBtn = document.getElementById('btnClearCart');

      const setNavCount = (count) => {
        const val = String(Number(count || 0));
        const navCart = document.getElementById('navCartCount');
        const navCartMobile = document.getElementById('navCartCountMobile');
        if (navCart) navCart.textContent = val;
        if (navCartMobile) navCartMobile.textContent = val;
      };

      const formatMoney = (n) => {
        const num = Number(n || 0);
        return '₱' + num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      };

      const postJson = async (url, payload) => {
        const resp = await fetch(url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload || {})
        });
        const data = await resp.json().catch(() => ({}));
        if (!resp.ok) {
          const msg = (data && data.error) ? data.error : 'Request failed';
          throw new Error(msg);
        }
        return data;
      };

      const updateTotals = (total, count) => {
        if (totalEl) totalEl.textContent = formatMoney(total);
        if (typeof count !== 'undefined') setNavCount(count);
      };

      const removeRow = (id) => {
        const row = document.querySelector('[data-cart-item="' + id + '"]');
        if (row) row.remove();
      };

      const setQtyLine = (id, qty, line) => {
        const qtyEl = document.querySelector('[data-qty="' + id + '"]');
        const lineEl = document.querySelector('[data-line="' + id + '"]');
        if (qtyEl) qtyEl.textContent = String(qty);
        if (lineEl) lineEl.textContent = formatMoney(line);
      };

      const ensureNotEmpty = (empty) => {
        if (!empty) return;
        if (itemsEl) itemsEl.innerHTML = '';
        const section = document.querySelector('section');
        if (section) {
          section.innerHTML = '<h2 class="text-2xl font-bold mb-4">Your Cart</h2><p class="text-neutral-400">Cart is empty.</p>';
        }
        setNavCount(0);
      };

      if (itemsEl) {
        itemsEl.addEventListener('click', async (e) => {
          const t = e.target;
          if (!t || !t.closest) return;
          const inc = t.closest('.cart-inc');
          const dec = t.closest('.cart-dec');
          const rem = t.closest('.cart-remove');
          const btn = inc || dec || rem;
          if (!btn) return;
          const id = parseInt(btn.getAttribute('data-id') || '0', 10);
          if (!id) return;

          try {
            if (rem) {
              const data = await postJson(BASE + '/api/cart_remove.php', { id });
              removeRow(id);
              updateTotals(data.total, data.count);
              ensureNotEmpty(data.empty);
              return;
            }

            const delta = inc ? 1 : -1;
            const data = await postJson(BASE + '/api/cart_update.php', { id, delta });
            if (!data.qty) {
              removeRow(id);
            } else {
              setQtyLine(id, data.qty, data.line);
            }
            updateTotals(data.total, data.count);
            ensureNotEmpty(data.empty);
          } catch (err) {
            alert(err && err.message ? err.message : 'Cart update failed');
          }
        });
      }

      if (clearBtn) {
        clearBtn.addEventListener('click', async () => {
          if (!confirm('Clear all items from cart?')) return;
          try {
            const data = await postJson(BASE + '/api/cart_clear.php', {});
            updateTotals(data.total, data.count);
            ensureNotEmpty(true);
          } catch (err) {
            alert(err && err.message ? err.message : 'Failed to clear cart');
          }
        });
      }
    })();
    </script>
  <?php endif; ?>
</section>
