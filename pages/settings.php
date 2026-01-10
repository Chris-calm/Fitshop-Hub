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

$defaultAvatar = 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80"><rect width="80" height="80" rx="40" fill="#111827"/><circle cx="40" cy="30" r="14" fill="#374151"/><path d="M16 70c4-16 16-24 24-24s20 8 24 24" fill="#374151"/></svg>');

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
    $makeDefault = !empty($_POST['is_default']);

    if ($id <= 0) {
      $addrErr = 'Invalid address.';
    } elseif ($fullName === '' || $phone === '' || $line1 === '' || $city === '' || $province === '' || $postal === '') {
      $addrErr = 'Please fill out all required address fields.';
    } else {
      try {
        $pdo->beginTransaction();
        if ($makeDefault) {
          $pdo->prepare('UPDATE user_addresses SET is_default=false WHERE user_id=?')->execute([(int)$sessionUser['id']]);
        }
        $pdo->prepare('UPDATE user_addresses SET full_name=?, phone=?, line1=?, line2=?, city=?, province=?, postal_code=?, is_default=CASE WHEN ? THEN true ELSE is_default END WHERE id=? AND user_id=?')
          ->execute([$fullName, $phone, $line1, ($line2 ?: null), $city, $province, $postal, $makeDefault, $id, (int)$sessionUser['id']]);
        $pdo->commit();
        header('Location: index.php?page=settings');
        exit;
      } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('Address update failed: ' . $e->getMessage());
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
    $makeDefault = !empty($_POST['is_default']);

    if ($fullName === '' || $phone === '' || $line1 === '' || $city === '' || $province === '' || $postal === '') {
      $addrErr = 'Please fill out all required address fields.';
    } else {
      try {
        $pdo->beginTransaction();
        if ($makeDefault) {
          $pdo->prepare('UPDATE user_addresses SET is_default=false WHERE user_id=?')->execute([(int)$sessionUser['id']]);
        }
        $pdo->prepare('INSERT INTO user_addresses (user_id, full_name, phone, line1, line2, city, province, postal_code, is_default) VALUES (?,?,?,?,?,?,?,?,?)')
          ->execute([(int)$sessionUser['id'], $fullName, $phone, $line1, $line2 ?: null, $city, $province, $postal, $makeDefault]);
        $pdo->commit();
        header('Location: index.php?page=settings');
        exit;
      } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('Address save failed: ' . $e->getMessage());
        $addrErr = 'Failed to save address.';
      }
    }
  }

  if ($action === 'delete_address') {
    $id = (int)($_POST['address_id'] ?? 0);
    if ($id > 0) {
      try {
        $pdo->prepare('DELETE FROM user_addresses WHERE id=? AND user_id=?')->execute([$id, (int)$sessionUser['id']]);
        header('Location: index.php?page=settings');
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
        $pdo->prepare('UPDATE user_addresses SET is_default=false WHERE user_id=?')->execute([(int)$sessionUser['id']]);
        $pdo->prepare('UPDATE user_addresses SET is_default=true WHERE id=? AND user_id=?')->execute([$id, (int)$sessionUser['id']]);
        $pdo->commit();
        header('Location: index.php?page=settings');
        exit;
      } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('Address set default failed: ' . $e->getMessage());
        $addrErr = 'Failed to update default address.';
      }
    }
  }
}

$stmt = $pdo->prepare('SELECT id,name,email,photo_url,goal,activity_level,equipment,diet,plan_json FROM users WHERE id=? LIMIT 1');
$stmt->execute([$sessionUser['id']]);
$u = $stmt->fetch();
$tokens = $u ? fh_list_api_tokens($pdo, (int)$u['id']) : [];

$addresses = [];
try {
  $aStmt = $pdo->prepare('SELECT id, full_name, phone, line1, line2, city, province, postal_code, is_default FROM user_addresses WHERE user_id=? ORDER BY is_default DESC, id DESC');
  $aStmt->execute([(int)$sessionUser['id']]);
  $addresses = $aStmt->fetchAll();
} catch (Throwable $e) {
  $addresses = [];
}
?>

<section class="min-h-[calc(100vh-140px)] flex items-center">
  <div class="w-full max-w-5xl mx-auto">
    <div class="flex items-end justify-between gap-3 mb-4">
      <div>
        <h2 class="text-2xl font-bold">Settings</h2>
        <div class="text-sm text-neutral-400">Manage your preferences and account details.</div>
      </div>
      <a href="index.php?page=fitness" class="fh-btn fh-btn-ghost">Back to Fitness</a>
    </div>

  <div class="fh-card p-4 flex items-center gap-3">
    <img src="<?= htmlspecialchars(($u['photo_url'] ?? '') ?: $defaultAvatar) ?>" class="w-12 h-12 rounded-full object-cover" alt="avatar"/>
    <div>
      <div class="font-semibold text-lg"><?= htmlspecialchars($u['name']) ?></div>
      <div class="text-neutral-400 text-sm"><?= htmlspecialchars($u['email']) ?></div>
    </div>
  </div>

  <details class="mt-6" open>
    <summary class="text-xl font-semibold cursor-pointer select-none">Account</summary>
    <div class="mt-3">
      <?php if (!empty($acctErr)): ?><div class="mb-3 text-red-400"><?= htmlspecialchars($acctErr) ?></div><?php endif; ?>
      <?php if (!empty($acctOk)): ?><div class="mb-3 text-emerald-300"><?= htmlspecialchars($acctOk) ?></div><?php endif; ?>
    </div>
    <div class="fh-card p-4 space-y-4">
      <form method="post" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <input type="hidden" name="account_action" value="update_account" />
        <div>
          <label class="block text-sm text-neutral-400 mb-1">Name</label>
          <input name="name" required value="<?= htmlspecialchars((string)($u['name'] ?? '')) ?>" class="fh-input w-full" />
        </div>
        <div>
          <label class="block text-sm text-neutral-400 mb-1">Email</label>
          <input type="email" name="email" required value="<?= htmlspecialchars((string)($u['email'] ?? '')) ?>" class="fh-input w-full" />
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm text-neutral-400 mb-1">Profile Photo (optional)</label>
          <input type="file" name="photo" accept="image/*" class="fh-input w-full" />
        </div>
        <div class="md:col-span-2">
          <button class="fh-btn fh-btn-primary">Save Changes</button>
        </div>
      </form>

      <div class="border-t border-white/10 pt-4">
        <div class="font-semibold mb-2">Change Password</div>
        <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <input type="hidden" name="account_action" value="change_password" />
          <div class="md:col-span-2">
            <label class="block text-sm text-neutral-400 mb-1">Current password</label>
            <input type="password" name="current_password" required class="fh-input w-full" />
          </div>
          <div>
            <label class="block text-sm text-neutral-400 mb-1">New password</label>
            <input type="password" name="new_password" required class="fh-input w-full" />
          </div>
          <div>
            <label class="block text-sm text-neutral-400 mb-1">Confirm new password</label>
            <input type="password" name="new_password2" required class="fh-input w-full" />
          </div>
          <div class="md:col-span-2">
            <button class="fh-btn fh-btn-ghost">Update Password</button>
          </div>
        </form>
      </div>
    </div>
  </details>

  <details class="mt-6" open>
    <summary class="text-xl font-semibold cursor-pointer select-none">Address Book</summary>
    <div class="mt-3">
      <?php if (!empty($addrErr)): ?><div class="mb-3 text-red-400"><?= htmlspecialchars($addrErr) ?></div><?php endif; ?>
    </div>
    <div class="fh-card p-4">
      <?php if (empty($addresses)): ?>
        <div class="text-neutral-400 text-sm">No saved addresses yet. Add one to enable checkout.</div>
      <?php else: ?>
        <div class="space-y-2">
          <?php foreach ($addresses as $a): ?>
            <div class="fh-card p-3 flex flex-col md:flex-row md:items-center md:justify-between gap-2" style="background: rgba(255,255,255,.03);">
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
                    <button class="fh-btn fh-btn-ghost">Set default</button>
                  </form>
                <?php endif; ?>
                <details>
                  <summary class="fh-btn fh-btn-ghost cursor-pointer select-none inline-block">Edit</summary>
                  <div class="mt-2 fh-card p-3" style="background: rgba(255,255,255,.03);">
                    <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                      <input type="hidden" name="addr_action" value="update_address" />
                      <input type="hidden" name="address_id" value="<?= (int)$a['id'] ?>" />
                      <div>
                        <label class="block text-sm text-neutral-400 mb-1">Full name</label>
                        <input name="full_name" required value="<?= htmlspecialchars((string)$a['full_name']) ?>" class="fh-input w-full" />
                      </div>
                      <div>
                        <label class="block text-sm text-neutral-400 mb-1">Phone</label>
                        <input name="phone" required value="<?= htmlspecialchars((string)$a['phone']) ?>" class="fh-input w-full" />
                      </div>
                      <div class="md:col-span-2">
                        <label class="block text-sm text-neutral-400 mb-1">Address line 1</label>
                        <input name="line1" required value="<?= htmlspecialchars((string)$a['line1']) ?>" class="fh-input w-full" />
                      </div>
                      <div class="md:col-span-2">
                        <label class="block text-sm text-neutral-400 mb-1">Address line 2 (optional)</label>
                        <input name="line2" value="<?= htmlspecialchars((string)($a['line2'] ?? '')) ?>" class="fh-input w-full" />
                      </div>
                      <div>
                        <label class="block text-sm text-neutral-400 mb-1">City</label>
                        <input name="city" required value="<?= htmlspecialchars((string)$a['city']) ?>" class="fh-input w-full" />
                      </div>
                      <div>
                        <label class="block text-sm text-neutral-400 mb-1">Province</label>
                        <input name="province" required value="<?= htmlspecialchars((string)$a['province']) ?>" class="fh-input w-full" />
                      </div>
                      <div>
                        <label class="block text-sm text-neutral-400 mb-1">Postal code</label>
                        <input name="postal_code" required value="<?= htmlspecialchars((string)$a['postal_code']) ?>" class="fh-input w-full" />
                      </div>
                      <div class="flex items-end">
                        <label class="inline-flex items-center gap-2 text-sm text-neutral-300">
                          <input type="checkbox" name="is_default" value="1" class="accent-brand" <?= !empty($a['is_default']) ? 'checked' : '' ?> />
                          Set as default
                        </label>
                      </div>
                      <div class="md:col-span-2">
                        <button class="fh-btn fh-btn-primary">Save</button>
                      </div>
                    </form>
                  </div>
                </details>
                <form method="post" class="inline" onsubmit="return confirm('Delete this address?')">
                  <input type="hidden" name="addr_action" value="delete_address" />
                  <input type="hidden" name="address_id" value="<?= (int)$a['id'] ?>" />
                  <button class="fh-btn" style="background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.28); color: rgba(252,165,165,1);">Delete</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="mt-4 border-t border-white/10 pt-4">
        <div class="font-semibold mb-2">Add new address</div>
        <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <input type="hidden" name="addr_action" value="add_address" />
          <div>
            <label class="block text-sm text-neutral-400 mb-1">Full name</label>
            <input name="full_name" required class="fh-input w-full" />
          </div>
          <div>
            <label class="block text-sm text-neutral-400 mb-1">Phone</label>
            <input name="phone" required class="fh-input w-full" />
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm text-neutral-400 mb-1">Address line 1</label>
            <input name="line1" required class="fh-input w-full" />
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm text-neutral-400 mb-1">Address line 2 (optional)</label>
            <input name="line2" class="fh-input w-full" />
          </div>
          <div>
            <label class="block text-sm text-neutral-400 mb-1">City</label>
            <input name="city" required class="fh-input w-full" />
          </div>
          <div>
            <label class="block text-sm text-neutral-400 mb-1">Province</label>
            <input name="province" required class="fh-input w-full" />
          </div>
          <div>
            <label class="block text-sm text-neutral-400 mb-1">Postal code</label>
            <input name="postal_code" required class="fh-input w-full" />
          </div>
          <div class="flex items-end">
            <label class="inline-flex items-center gap-2 text-sm text-neutral-300">
              <input type="checkbox" name="is_default" value="1" class="accent-brand" />
              Set as default
            </label>
          </div>
          <div class="md:col-span-2">
            <button class="fh-btn fh-btn-primary">Save Address</button>
          </div>
        </form>
      </div>
    </div>
  </details>

  </div>
</section>
