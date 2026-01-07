<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require_login();
$sessionUser = $_SESSION['user'];
require_once __DIR__ . '/../includes/api_tokens.php';
require_once __DIR__ . '/../includes/auth_cookie.php';
require_once __DIR__ . '/../includes/supabase_storage.php';
$addrErr = '';
$acctErr = '';
$acctOk = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $accountAction = $_POST['account_action'] ?? '';
  if ($accountAction === 'update_account') {
    $newName = trim((string)($_POST['name'] ?? ''));
    $newEmail = trim((string)($_POST['email'] ?? ''));
    $photoPath = null;
    if (!empty($_FILES['photo']['name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
      $allowed = ['jpg','jpeg','png','gif','webp'];
      $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, $allowed, true)) {
        $acctErr = 'Invalid image type. Allowed: jpg, png, gif, webp.';
      } else {
        if (defined('IS_VERCEL') && IS_VERCEL) {
          try {
            $bucket = getenv('SUPABASE_AVATAR_BUCKET') ?: 'avatars';
            $key = 'avatar_' . (int)$sessionUser['id'] . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $photoPath = supabase_storage_upload_tmpfile($bucket, $key, $_FILES['photo']['tmp_name'], $_FILES['photo']['type'] ?? 'application/octet-stream');
          } catch (Throwable $e) {
            $msg = $e->getMessage();
            error_log('Avatar upload failed: ' . $msg);
            if (strpos($msg, 'Supabase Storage not configured') !== false) {
              $acctErr = 'Avatar upload is not configured on Vercel. Please set SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY (and ensure the "avatars" bucket exists).';
            } else {
              $acctErr = 'Failed to upload image. Please try again.';
            }
          }
        } else {
          $targetDir = __DIR__ . '/../uploads/avatars/';
          if (!is_dir($targetDir)) { @mkdir($targetDir, 0777, true); }
          $filename = 'avatar_' . (int)$sessionUser['id'] . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
          $dest = $targetDir . $filename;
          if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
            $photoPath = rtrim(BASE_URL, '/') . '/uploads/avatars/' . $filename;
          } else {
            $acctErr = 'Failed to upload image.';
          }
        }
      }
    }
    if ($newName === '' || $newEmail === '') {
      $acctErr = 'Name and email are required.';
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
      $acctErr = 'Please enter a valid email.';
    } elseif (!empty($acctErr)) {
      // keep error from photo upload
    } else {
      try {
        $chk = $pdo->prepare('SELECT id FROM users WHERE email=? AND id<>? LIMIT 1');
        $chk->execute([$newEmail, (int)$sessionUser['id']]);
        if ($chk->fetch()) {
          $acctErr = 'Email already registered.';
        } else {
          $stmtUp = $pdo->prepare('UPDATE users SET name=?, email=?, photo_url=COALESCE(?, photo_url) WHERE id=?');
          $stmtUp->execute([$newName, $newEmail, $photoPath, (int)$sessionUser['id']]);
          $_SESSION['user']['name'] = $newName;
          $_SESSION['user']['email'] = $newEmail;
          if ($photoPath !== null && $photoPath !== '') {
            $_SESSION['user']['photo_url'] = $photoPath;
          }
          fh_set_auth_cookie($_SESSION['user']);
          $acctOk = 'Account updated.';
        }
      } catch (Throwable $e) {
        $acctErr = 'Failed to update account.';
      }
    }
  }

  if ($accountAction === 'change_password') {
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $newPassword2 = (string)($_POST['new_password2'] ?? '');
    if ($currentPassword === '' || $newPassword === '' || $newPassword2 === '') {
      $acctErr = 'Please fill out all password fields.';
    } elseif ($newPassword !== $newPassword2) {
      $acctErr = 'New passwords do not match.';
    } else {
      try {
        $pStmt = $pdo->prepare('SELECT password_hash FROM users WHERE id=? LIMIT 1');
        $pStmt->execute([(int)$sessionUser['id']]);
        $row = $pStmt->fetch();
        $hash = $row['password_hash'] ?? '';
        if (!$hash || !password_verify($currentPassword, $hash)) {
          $acctErr = 'Current password is incorrect.';
        } else {
          $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
          $uStmt = $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');
          $uStmt->execute([$newHash, (int)$sessionUser['id']]);
          $acctOk = 'Password updated.';
        }
      } catch (Throwable $e) {
        $acctErr = 'Failed to update password.';
      }
    }
  }

  $action = $_POST['addr_action'] ?? '';

  if ($action === 'update_address') {
    $id = (int)($_POST['address_id'] ?? 0);
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $line1 = trim((string)($_POST['line1'] ?? ''));
    $line2 = trim((string)($_POST['line2'] ?? ''));
    $city = trim((string)($_POST['city'] ?? ''));
    $province = trim((string)($_POST['province'] ?? ''));
    $postal = trim((string)($_POST['postal_code'] ?? ''));
    $makeDefault = !empty($_POST['is_default']) ? 1 : 0;

    if ($id <= 0) {
      $addrErr = 'Invalid address.';
    } elseif ($fullName === '' || $phone === '' || $line1 === '' || $city === '' || $province === '' || $postal === '') {
      $addrErr = 'Please fill out all required address fields.';
    } else {
      try {
        $pdo->beginTransaction();
        if ($makeDefault) {
          $pdo->prepare('UPDATE user_addresses SET is_default=0 WHERE user_id=?')->execute([(int)$sessionUser['id']]);
        }
        $pdo->prepare('UPDATE user_addresses SET full_name=?, phone=?, line1=?, line2=?, city=?, province=?, postal_code=?, is_default=CASE WHEN ?=1 THEN 1 ELSE is_default END WHERE id=? AND user_id=?')
          ->execute([$fullName, $phone, $line1, ($line2 ?: null), $city, $province, $postal, $makeDefault, $id, (int)$sessionUser['id']]);
        $pdo->commit();
        header('Location: index.php?page=profile');
        exit;
      } catch (Throwable $e) {
        $pdo->rollBack();
        $addrErr = 'Failed to update address.';
      }
    }
  }

  if ($action === 'add_address') {
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $line1 = trim((string)($_POST['line1'] ?? ''));
    $line2 = trim((string)($_POST['line2'] ?? ''));
    $city = trim((string)($_POST['city'] ?? ''));
    $province = trim((string)($_POST['province'] ?? ''));
    $postal = trim((string)($_POST['postal_code'] ?? ''));
    $makeDefault = !empty($_POST['is_default']) ? 1 : 0;

    if ($fullName === '' || $phone === '' || $line1 === '' || $city === '' || $province === '' || $postal === '') {
      $addrErr = 'Please fill out all required address fields.';
    } else {
      try {
        $pdo->beginTransaction();
        if ($makeDefault) {
          $pdo->prepare('UPDATE user_addresses SET is_default=0 WHERE user_id=?')->execute([(int)$sessionUser['id']]);
        }
        $pdo->prepare('INSERT INTO user_addresses (user_id, full_name, phone, line1, line2, city, province, postal_code, is_default) VALUES (?,?,?,?,?,?,?,?,?)')
          ->execute([(int)$sessionUser['id'], $fullName, $phone, $line1, $line2 ?: null, $city, $province, $postal, $makeDefault]);
        $pdo->commit();
        header('Location: index.php?page=profile');
        exit;
      } catch (Throwable $e) {
        $pdo->rollBack();
        $addrErr = 'Failed to save address.';
      }
    }
  }

  if ($action === 'delete_address') {
    $id = (int)($_POST['address_id'] ?? 0);
    if ($id > 0) {
      try {
        $pdo->prepare('DELETE FROM user_addresses WHERE id=? AND user_id=?')->execute([$id, (int)$sessionUser['id']]);
        header('Location: index.php?page=profile');
        exit;
      } catch (Throwable $e) {
        $addrErr = 'Failed to delete address.';
      }
    }
  }

  if ($action === 'set_default') {
    $id = (int)($_POST['address_id'] ?? 0);
    if ($id > 0) {
      try {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE user_addresses SET is_default=0 WHERE user_id=?')->execute([(int)$sessionUser['id']]);
        $pdo->prepare('UPDATE user_addresses SET is_default=1 WHERE id=? AND user_id=?')->execute([$id, (int)$sessionUser['id']]);
        $pdo->commit();
        header('Location: index.php?page=profile');
        exit;
      } catch (Throwable $e) {
        $pdo->rollBack();
        $addrErr = 'Failed to update default address.';
      }
    }
  }
}

$stmt = $pdo->prepare('SELECT id,name,email,photo_url,goal,activity_level,equipment,diet,plan_json FROM users WHERE id=? LIMIT 1');
$stmt->execute([$sessionUser['id']]);
$u = $stmt->fetch();
$plan = $u && !empty($u['plan_json']) ? json_decode($u['plan_json'], true) : null;
$tokens = $u ? fh_list_api_tokens($pdo, (int)$u['id']) : [];

$addresses = [];
try {
  $aStmt = $pdo->prepare('SELECT id, full_name, phone, line1, line2, city, province, postal_code, is_default FROM user_addresses WHERE user_id=? ORDER BY is_default DESC, id DESC');
  $aStmt->execute([(int)$sessionUser['id']]);
  $addresses = $aStmt->fetchAll();
} catch (Throwable $e) {
  $addresses = [];
}
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

  <details class="mt-6" open>
    <summary class="text-xl font-semibold cursor-pointer select-none">Theme</summary>
    <div class="rounded-lg border border-neutral-800 bg-neutral-900 p-4 mt-3">
      <div class="text-neutral-400 text-sm mb-2">Choose a look that fits you. This changes colors across the whole system.</div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-end">
        <div>
          <label class="block text-sm text-neutral-400 mb-1">Theme preset</label>
          <select id="fhThemeSelect" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2">
            <option value="sporty">Sporty (Energetic)</option>
            <option value="calm">Calm</option>
            <option value="contrast">High Contrast</option>
          </select>
        </div>
        <div>
          <button id="fhThemeApply" type="button" class="w-full px-4 py-2 rounded-lg bg-brand text-white">Apply Theme</button>
        </div>
      </div>
    </div>
    <script>
      (function(){
        const sel = document.getElementById('fhThemeSelect');
        const btn = document.getElementById('fhThemeApply');
        if (!sel || !btn) return;
        try { sel.value = localStorage.getItem('fh_theme') || 'sporty'; } catch(e) { sel.value = 'sporty'; }
        btn.addEventListener('click', function(){
          const v = sel.value || 'sporty';
          try { localStorage.setItem('fh_theme', v); } catch(e) {}
          window.location.reload();
        });
      })();
    </script>
  </details>

  <details class="mt-6" open>
    <summary class="text-xl font-semibold cursor-pointer select-none">Edit Account</summary>
    <div class="mt-3">
      <?php if (!empty($acctErr)): ?><div class="mb-3 text-red-400"><?= htmlspecialchars($acctErr) ?></div><?php endif; ?>
      <?php if (!empty($acctOk)): ?><div class="mb-3 text-emerald-300"><?= htmlspecialchars($acctOk) ?></div><?php endif; ?>
    </div>
    <div class="rounded-lg border border-neutral-800 bg-neutral-900 p-4 space-y-4">
    <form method="post" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <input type="hidden" name="account_action" value="update_account" />
      <div>
        <label class="block text-sm text-neutral-400 mb-1">Name</label>
        <input name="name" required value="<?= htmlspecialchars((string)($u['name'] ?? '')) ?>" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
      </div>
      <div>
        <label class="block text-sm text-neutral-400 mb-1">Email</label>
        <input type="email" name="email" required value="<?= htmlspecialchars((string)($u['email'] ?? '')) ?>" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm text-neutral-400 mb-1">Profile Photo (optional)</label>
        <input type="file" name="photo" accept="image/*" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
      </div>
      <div class="md:col-span-2">
        <button class="px-4 py-2 rounded-lg bg-brand text-white">Save Changes</button>
      </div>
    </form>

    <div class="border-t border-neutral-800 pt-4">
      <div class="font-semibold mb-2">Change Password</div>
      <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <input type="hidden" name="account_action" value="change_password" />
        <div class="md:col-span-2">
          <label class="block text-sm text-neutral-400 mb-1">Current password</label>
          <input type="password" name="current_password" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm text-neutral-400 mb-1">New password</label>
          <input type="password" name="new_password" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm text-neutral-400 mb-1">Confirm new password</label>
          <input type="password" name="new_password2" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
        </div>
        <div class="md:col-span-2">
          <button class="px-4 py-2 rounded-lg bg-neutral-800 hover:bg-neutral-700">Update Password</button>
        </div>
      </form>
    </div>
    </div>
  </details>

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

  <details class="mt-6">
    <summary class="text-xl font-semibold cursor-pointer select-none">Android Step Sync</summary>
    <div class="rounded-lg border border-neutral-800 bg-neutral-900 p-4 mt-3">
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

  </details>

  <details class="mt-6" open>
    <summary class="text-xl font-semibold cursor-pointer select-none">Address Book</summary>
    <div class="mt-3">
      <?php if (!empty($addrErr)): ?><div class="mb-3 text-red-400"><?= htmlspecialchars($addrErr) ?></div><?php endif; ?>
    </div>
    <div class="rounded-lg border border-neutral-800 bg-neutral-900 p-4">
    <?php if (empty($addresses)): ?>
      <div class="text-neutral-400 text-sm">No saved addresses yet. Add one to enable checkout.</div>
    <?php else: ?>
      <div class="space-y-2">
        <?php foreach ($addresses as $a): ?>
          <div class="rounded-lg border border-neutral-800 bg-neutral-950 p-3 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
            <div>
              <div class="font-semibold"><?= !empty($a['is_default']) ? 'Default • ' : '' ?><?= htmlspecialchars((string)$a['full_name']) ?> <span class="text-neutral-400 text-sm">• <?= htmlspecialchars((string)$a['phone']) ?></span></div>
              <div class="text-sm text-neutral-300"><?= htmlspecialchars((string)$a['line1']) ?><?= !empty($a['line2']) ? ', ' . htmlspecialchars((string)$a['line2']) : '' ?></div>
              <div class="text-xs text-neutral-400"><?= htmlspecialchars((string)$a['city']) ?>, <?= htmlspecialchars((string)$a['province']) ?> <?= htmlspecialchars((string)$a['postal_code']) ?></div>
            </div>
            <div class="flex items-center gap-2">
              <?php if (empty($a['is_default'])): ?>
                <form method="post" class="inline">
                  <input type="hidden" name="addr_action" value="set_default" />
                  <input type="hidden" name="address_id" value="<?= (int)$a['id'] ?>" />
                  <button class="px-3 py-2 rounded-lg bg-neutral-800 hover:bg-neutral-700">Set default</button>
                </form>
              <?php endif; ?>
              <details>
                <summary class="px-3 py-2 rounded-lg bg-neutral-800 hover:bg-neutral-700 cursor-pointer select-none inline-block">Edit</summary>
                <div class="mt-2 rounded-lg border border-neutral-800 bg-neutral-900 p-3">
                  <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <input type="hidden" name="addr_action" value="update_address" />
                    <input type="hidden" name="address_id" value="<?= (int)$a['id'] ?>" />
                    <div>
                      <label class="block text-sm text-neutral-400 mb-1">Full name</label>
                      <input name="full_name" required value="<?= htmlspecialchars((string)$a['full_name']) ?>" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
                    </div>
                    <div>
                      <label class="block text-sm text-neutral-400 mb-1">Phone</label>
                      <input name="phone" required value="<?= htmlspecialchars((string)$a['phone']) ?>" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
                    </div>
                    <div class="md:col-span-2">
                      <label class="block text-sm text-neutral-400 mb-1">Address line 1</label>
                      <input name="line1" required value="<?= htmlspecialchars((string)$a['line1']) ?>" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
                    </div>
                    <div class="md:col-span-2">
                      <label class="block text-sm text-neutral-400 mb-1">Address line 2 (optional)</label>
                      <input name="line2" value="<?= htmlspecialchars((string)($a['line2'] ?? '')) ?>" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
                    </div>
                    <div>
                      <label class="block text-sm text-neutral-400 mb-1">City</label>
                      <input name="city" required value="<?= htmlspecialchars((string)$a['city']) ?>" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
                    </div>
                    <div>
                      <label class="block text-sm text-neutral-400 mb-1">Province</label>
                      <input name="province" required value="<?= htmlspecialchars((string)$a['province']) ?>" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
                    </div>
                    <div>
                      <label class="block text-sm text-neutral-400 mb-1">Postal code</label>
                      <input name="postal_code" required value="<?= htmlspecialchars((string)$a['postal_code']) ?>" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
                    </div>
                    <div class="flex items-end">
                      <label class="inline-flex items-center gap-2 text-sm text-neutral-300">
                        <input type="checkbox" name="is_default" value="1" class="accent-brand" <?= !empty($a['is_default']) ? 'checked' : '' ?> />
                        Set as default
                      </label>
                    </div>
                    <div class="md:col-span-2">
                      <button class="px-4 py-2 rounded-lg bg-brand text-white">Save</button>
                    </div>
                  </form>
                </div>
              </details>
              <form method="post" class="inline" onsubmit="return confirm('Delete this address?')">
                <input type="hidden" name="addr_action" value="delete_address" />
                <input type="hidden" name="address_id" value="<?= (int)$a['id'] ?>" />
                <button class="px-3 py-2 rounded-lg bg-red-500/15 text-red-300 border border-red-500/30">Delete</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="mt-4 border-t border-neutral-800 pt-4">
      <div class="font-semibold mb-2">Add new address</div>
      <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <input type="hidden" name="addr_action" value="add_address" />
        <div>
          <label class="block text-sm text-neutral-400 mb-1">Full name</label>
          <input name="full_name" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm text-neutral-400 mb-1">Phone</label>
          <input name="phone" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm text-neutral-400 mb-1">Address line 1</label>
          <input name="line1" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm text-neutral-400 mb-1">Address line 2 (optional)</label>
          <input name="line2" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm text-neutral-400 mb-1">City</label>
          <input name="city" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm text-neutral-400 mb-1">Province</label>
          <input name="province" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm text-neutral-400 mb-1">Postal code</label>
          <input name="postal_code" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
        </div>
        <div class="flex items-end">
          <label class="inline-flex items-center gap-2 text-sm text-neutral-300">
            <input type="checkbox" name="is_default" value="1" class="accent-brand" />
            Set as default
          </label>
        </div>
        <div class="md:col-span-2">
          <button class="px-4 py-2 rounded-lg bg-brand text-white">Save Address</button>
        </div>
      </form>
    </div>
  </div>

  </details>

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
