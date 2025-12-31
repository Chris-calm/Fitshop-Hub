<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require_login();
$sessionUser = $_SESSION['user'];
require_once __DIR__ . '/../includes/api_tokens.php';
$stmt = $pdo->prepare('SELECT id,name,email,photo_url,goal,activity_level,equipment,diet,plan_json FROM users WHERE id=? LIMIT 1');
$stmt->execute([$sessionUser['id']]);
$u = $stmt->fetch();
$plan = $u && !empty($u['plan_json']) ? json_decode($u['plan_json'], true) : null;
$tokens = $u ? fh_list_api_tokens($pdo, (int)$u['id']) : [];
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
    <img src="<?= htmlspecialchars(($u['photo_url'] ?? '') ?: 'https://i.pravatar.cc/80') ?>" class="w-12 h-12 rounded-full" alt="avatar"/>
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

  <h3 class="text-xl font-semibold mt-6 mb-3">Android Step Sync</h3>
  <div class="rounded-lg border border-neutral-800 bg-neutral-900 p-4">
    <div class="text-neutral-400 text-sm">Generate an API token for your Android app to sync steps automatically.</div>
    <div class="mt-3 flex flex-col sm:flex-row gap-2">
      <input id="tokenName" class="w-full sm:flex-1 bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" placeholder="Token name (optional)" value="Android Step Sync" />
      <button id="createTokenBtn" class="px-4 py-2 rounded-lg bg-brand text-white">Generate Token</button>
    </div>
    <div id="tokenResult" class="hidden mt-3 p-3 rounded bg-emerald-500/10 text-emerald-200 border border-emerald-500/30 break-all"></div>

    <div class="mt-4">
      <?php if (empty($tokens)): ?>
        <div class="text-neutral-400 text-sm">No tokens yet.</div>
      <?php else: ?>
        <div class="space-y-2">
          <?php foreach ($tokens as $t): ?>
            <div class="rounded-lg border border-neutral-800 bg-neutral-950 p-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
              <div>
                <div class="font-semibold">#<?= (int)$t['id'] ?><?= !empty($t['name']) ? ' • ' . htmlspecialchars($t['name']) : '' ?></div>
                <div class="text-xs text-neutral-400">Created: <?= htmlspecialchars((string)$t['created_at']) ?><?= !empty($t['last_used_at']) ? ' • Last used: ' . htmlspecialchars((string)$t['last_used_at']) : '' ?><?= !empty($t['revoked_at']) ? ' • Revoked' : '' ?></div>
              </div>
              <?php if (empty($t['revoked_at'])): ?>
                <button class="revokeTokenBtn px-3 py-2 rounded-lg bg-red-500/15 text-red-300 border border-red-500/30" data-token-id="<?= (int)$t['id'] ?>">Revoke</button>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
  (function(){
    const btn = document.getElementById('createTokenBtn');
    const nameInput = document.getElementById('tokenName');
    const result = document.getElementById('tokenResult');
    async function postForm(url, data){
      const body = new URLSearchParams(data);
      const resp = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() });
      const json = await resp.json().catch(()=>null);
      if (!resp.ok || !json || !json.ok) {
        throw new Error((json && json.error) ? json.error : 'Request failed');
      }
      return json;
    }
    if (btn) btn.addEventListener('click', async ()=>{
      btn.disabled = true;
      try {
        const name = (nameInput && nameInput.value) ? nameInput.value : '';
        const data = await postForm('index.php?page=api_token_create', { name });
        if (result) {
          result.classList.remove('hidden');
          result.textContent = 'Your token (save this in your Android app now): ' + data.token;
        }
        setTimeout(()=>{ window.location.reload(); }, 800);
      } catch (e) {
        if (result) {
          result.classList.remove('hidden');
          result.classList.remove('bg-emerald-500/10','text-emerald-200','border-emerald-500/30');
          result.classList.add('bg-red-500/10','text-red-300','border-red-500/30');
          result.textContent = String(e && e.message ? e.message : e);
        }
      } finally {
        btn.disabled = false;
      }
    });
    document.querySelectorAll('.revokeTokenBtn').forEach(el=>{
      el.addEventListener('click', async ()=>{
        const tokenId = el.getAttribute('data-token-id');
        el.disabled = true;
        try {
          await postForm('index.php?page=api_token_revoke', { token_id: tokenId });
          window.location.reload();
        } catch (e) {
          el.disabled = false;
          alert(String(e && e.message ? e.message : e));
        }
      });
    });
  })();
  </script>

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
