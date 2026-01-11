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
    $phoneCc = trim((string)($_POST['phone_cc'] ?? '+63'));
    $phoneNational = preg_replace('/\D+/', '', (string)($_POST['phone_national'] ?? ''));
    $line1 = trim((string)($_POST['line1'] ?? ''));
    $line2 = trim((string)($_POST['line2'] ?? ''));
    $city = trim((string)($_POST['city'] ?? ''));
    $province = trim((string)($_POST['province'] ?? ''));
    $postal = trim((string)($_POST['postal_code'] ?? ''));
    $latRaw = trim((string)($_POST['lat'] ?? ''));
    $lngRaw = trim((string)($_POST['lng'] ?? ''));
    $lat = ($latRaw === '' ? null : (float)$latRaw);
    $lng = ($lngRaw === '' ? null : (float)$lngRaw);
    $makeDefault = !empty($_POST['is_default']);

    $phoneCc = ($phoneCc !== '' && $phoneCc[0] !== '+') ? ('+' . $phoneCc) : $phoneCc;
    $rules = [
      '+63' => 10,
      '+1' => 10,
      '+65' => 8,
      '+44' => 10,
    ];
    $expectedDigits = $rules[$phoneCc] ?? 10;
    $phone = $phoneCc . $phoneNational;

    if ($id <= 0) {
      $addrErr = 'Invalid address.';
    } elseif ($fullName === '' || $phoneNational === '' || $line1 === '' || $city === '' || $province === '' || $postal === '') {
      $addrErr = 'Please fill out all required address fields.';
    } elseif (!isset($rules[$phoneCc])) {
      $addrErr = 'Please select a valid country code.';
    } elseif (!preg_match('/^\d+$/', $phoneNational)) {
      $addrErr = 'Phone number must contain digits only.';
    } elseif (strlen($phoneNational) !== $expectedDigits) {
      $addrErr = 'Phone must be ' . $expectedDigits . ' digits for ' . $phoneCc . '.';
    } elseif (!preg_match('/^\d{4}$/', $postal)) {
      $addrErr = 'Postal code must be 4 digits (Philippines).';
    } elseif (($lat !== null && ($lat < -90 || $lat > 90)) || ($lng !== null && ($lng < -180 || $lng > 180))) {
      $addrErr = 'Invalid map coordinates.';
    } else {
      try {
        $pdo->beginTransaction();
        if ($makeDefault) {
          $pdo->prepare('UPDATE user_addresses SET is_default=false WHERE user_id=?')->execute([(int)$sessionUser['id']]);
        }
        $pdo->prepare('UPDATE user_addresses SET full_name=?, phone=?, line1=?, line2=?, city=?, province=?, postal_code=?, lat=?, lng=?, is_default=CASE WHEN ? THEN true ELSE is_default END WHERE id=? AND user_id=?')
          ->execute([$fullName, $phone, $line1, ($line2 ?: null), $city, $province, $postal, $lat, $lng, $makeDefault, $id, (int)$sessionUser['id']]);
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
    $phoneCc = trim((string)($_POST['phone_cc'] ?? '+63'));
    $phoneNational = preg_replace('/\D+/', '', (string)($_POST['phone_national'] ?? ''));
    $line1 = trim((string)($_POST['line1'] ?? ''));
    $line2 = trim((string)($_POST['line2'] ?? ''));
    $city = trim((string)($_POST['city'] ?? ''));
    $province = trim((string)($_POST['province'] ?? ''));
    $postal = trim((string)($_POST['postal_code'] ?? ''));
    $latRaw = trim((string)($_POST['lat'] ?? ''));
    $lngRaw = trim((string)($_POST['lng'] ?? ''));
    $lat = ($latRaw === '' ? null : (float)$latRaw);
    $lng = ($lngRaw === '' ? null : (float)$lngRaw);
    $makeDefault = !empty($_POST['is_default']);

    $phoneCc = ($phoneCc !== '' && $phoneCc[0] !== '+') ? ('+' . $phoneCc) : $phoneCc;
    $rules = [
      '+63' => 10,
      '+1' => 10,
      '+65' => 8,
      '+44' => 10,
    ];
    $expectedDigits = $rules[$phoneCc] ?? 10;
    $phone = $phoneCc . $phoneNational;

    if ($fullName === '' || $phoneNational === '' || $line1 === '' || $city === '' || $province === '' || $postal === '') {
      $addrErr = 'Please fill out all required address fields.';
    } elseif (!isset($rules[$phoneCc])) {
      $addrErr = 'Please select a valid country code.';
    } elseif (!preg_match('/^\d+$/', $phoneNational)) {
      $addrErr = 'Phone number must contain digits only.';
    } elseif (strlen($phoneNational) !== $expectedDigits) {
      $addrErr = 'Phone must be ' . $expectedDigits . ' digits for ' . $phoneCc . '.';
    } elseif (!preg_match('/^\d{4}$/', $postal)) {
      $addrErr = 'Postal code must be 4 digits (Philippines).';
    } elseif (($lat !== null && ($lat < -90 || $lat > 90)) || ($lng !== null && ($lng < -180 || $lng > 180))) {
      $addrErr = 'Invalid map coordinates.';
    } else {
      try {
        $pdo->beginTransaction();
        if ($makeDefault) {
          $pdo->prepare('UPDATE user_addresses SET is_default=false WHERE user_id=?')->execute([(int)$sessionUser['id']]);
        }
        $pdo->prepare('INSERT INTO user_addresses (user_id, full_name, phone, line1, line2, city, province, postal_code, lat, lng, is_default) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
          ->execute([(int)$sessionUser['id'], $fullName, $phone, $line1, $line2 ?: null, $city, $province, $postal, $lat, $lng, $makeDefault]);
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
  $aStmt = $pdo->prepare('SELECT id, full_name, phone, line1, line2, city, province, postal_code, lat, lng, is_default FROM user_addresses WHERE user_id=? ORDER BY is_default DESC, id DESC');
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
                      <?php
                        $ph = (string)($a['phone'] ?? '');
                        $cc = '+63';
                        $nat = preg_replace('/\D+/', '', $ph);
                        if (strpos($ph, '+1') === 0) { $cc = '+1'; $nat = substr($ph, 2); }
                        elseif (strpos($ph, '+65') === 0) { $cc = '+65'; $nat = substr($ph, 3); }
                        elseif (strpos($ph, '+44') === 0) { $cc = '+44'; $nat = substr($ph, 3); }
                        elseif (strpos($ph, '+63') === 0) { $cc = '+63'; $nat = substr($ph, 3); }
                        $nat = preg_replace('/\D+/', '', (string)$nat);
                      ?>
                      <div>
                        <label class="block text-sm text-neutral-400 mb-1">Full name</label>
                        <input name="full_name" required value="<?= htmlspecialchars((string)$a['full_name']) ?>" class="fh-input w-full" />
                      </div>
                      <div>
                        <label class="block text-sm text-neutral-400 mb-1">Phone</label>
                        <div class="grid grid-cols-3 gap-2">
                          <div class="relative">
                            <input type="hidden" name="phone_cc" value="<?= htmlspecialchars((string)$cc) ?>" class="fh-phone-cc" />
                            <?php
                              $ccFlag = 'https://flagcdn.com/24x18/ph.png';
                              $ccAlt = 'PH';
                              $ccMax = 10;
                              if ($cc === '+1') { $ccFlag = 'https://flagcdn.com/24x18/us.png'; $ccAlt = 'US'; $ccMax = 10; }
                              elseif ($cc === '+65') { $ccFlag = 'https://flagcdn.com/24x18/sg.png'; $ccAlt = 'SG'; $ccMax = 8; }
                              elseif ($cc === '+44') { $ccFlag = 'https://flagcdn.com/24x18/gb.png'; $ccAlt = 'UK'; $ccMax = 10; }
                            ?>
                            <button type="button" class="fh-country-btn fh-input w-full flex items-center justify-between">
                              <span class="flex items-center gap-2">
                                <img class="fh-country-flag" src="<?= htmlspecialchars($ccFlag) ?>" width="24" height="18" alt="<?= htmlspecialchars($ccAlt) ?>" />
                                <span class="fh-country-label"><?= htmlspecialchars((string)$cc) ?></span>
                              </span>
                              <span class="text-neutral-500">▾</span>
                            </button>
                            <div class="fh-country-menu hidden absolute z-20 mt-2 w-full rounded-lg border border-neutral-800 bg-neutral-900 overflow-hidden">
                              <button type="button" class="fh-country-opt w-full px-3 py-2 hover:bg-neutral-800 flex items-center gap-2" data-cc="+63" data-flag="https://flagcdn.com/24x18/ph.png" data-alt="PH" data-max="10">
                                <img src="https://flagcdn.com/24x18/ph.png" width="24" height="18" alt="PH" />
                                <span>PH +63</span>
                              </button>
                              <button type="button" class="fh-country-opt w-full px-3 py-2 hover:bg-neutral-800 flex items-center gap-2" data-cc="+1" data-flag="https://flagcdn.com/24x18/us.png" data-alt="US" data-max="10">
                                <img src="https://flagcdn.com/24x18/us.png" width="24" height="18" alt="US" />
                                <span>US +1</span>
                              </button>
                              <button type="button" class="fh-country-opt w-full px-3 py-2 hover:bg-neutral-800 flex items-center gap-2" data-cc="+65" data-flag="https://flagcdn.com/24x18/sg.png" data-alt="SG" data-max="8">
                                <img src="https://flagcdn.com/24x18/sg.png" width="24" height="18" alt="SG" />
                                <span>SG +65</span>
                              </button>
                              <button type="button" class="fh-country-opt w-full px-3 py-2 hover:bg-neutral-800 flex items-center gap-2" data-cc="+44" data-flag="https://flagcdn.com/24x18/gb.png" data-alt="UK" data-max="10">
                                <img src="https://flagcdn.com/24x18/gb.png" width="24" height="18" alt="UK" />
                                <span>UK +44</span>
                              </button>
                            </div>
                          </div>
                          <input name="phone_national" required inputmode="numeric" pattern="\d*" maxlength="<?= (int)$ccMax ?>" value="<?= htmlspecialchars((string)$nat) ?>" class="fh-input w-full col-span-2 fh-phone-national" />
                        </div>
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
                        <input name="postal_code" required inputmode="numeric" pattern="\d{4}" maxlength="4" value="<?= htmlspecialchars((string)$a['postal_code']) ?>" class="fh-input w-full" />
                      </div>
                      <input type="hidden" name="lat" value="<?= htmlspecialchars((string)($a['lat'] ?? '')) ?>" class="fh-lat" />
                      <input type="hidden" name="lng" value="<?= htmlspecialchars((string)($a['lng'] ?? '')) ?>" class="fh-lng" />
                      <div class="md:col-span-2">
                        <label class="block text-sm text-neutral-400 mb-1">Pinpoint location (optional)</label>
                        <button type="button" class="fh-btn fh-btn-ghost fh-toggle-map" aria-label="Set location">
                          <span class="inline-flex items-center gap-2">
                            <span>📍</span>
                            <span>Set / View location</span>
                          </span>
                        </button>
                        <div class="fh-map-wrap hidden mt-2">
                          <div class="fh-map" style="height: 220px; border-radius: 12px; border: 1px solid rgba(255,255,255,.08);" data-lat="<?= htmlspecialchars((string)($a['lat'] ?? '')) ?>" data-lng="<?= htmlspecialchars((string)($a['lng'] ?? '')) ?>"></div>
                          <div class="mt-2 flex items-center gap-2">
                            <button type="button" class="fh-btn fh-btn-ghost fh-use-location">Use my location</button>
                            <div class="text-xs text-neutral-400">Click the map to drop/move the pin.</div>
                          </div>
                        </div>
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
            <div class="grid grid-cols-3 gap-2">
              <div class="relative">
                <input type="hidden" name="phone_cc" value="+63" class="fh-phone-cc" />
                <button type="button" class="fh-country-btn fh-input w-full flex items-center justify-between">
                  <span class="flex items-center gap-2">
                    <img class="fh-country-flag" src="https://flagcdn.com/24x18/ph.png" width="24" height="18" alt="PH" />
                    <span class="fh-country-label">+63</span>
                  </span>
                  <span class="text-neutral-500">▾</span>
                </button>
                <div class="fh-country-menu hidden absolute z-20 mt-2 w-full rounded-lg border border-neutral-800 bg-neutral-900 overflow-hidden">
                  <button type="button" class="fh-country-opt w-full px-3 py-2 hover:bg-neutral-800 flex items-center gap-2" data-cc="+63" data-flag="https://flagcdn.com/24x18/ph.png" data-alt="PH" data-max="10">
                    <img src="https://flagcdn.com/24x18/ph.png" width="24" height="18" alt="PH" />
                    <span>PH +63</span>
                  </button>
                  <button type="button" class="fh-country-opt w-full px-3 py-2 hover:bg-neutral-800 flex items-center gap-2" data-cc="+1" data-flag="https://flagcdn.com/24x18/us.png" data-alt="US" data-max="10">
                    <img src="https://flagcdn.com/24x18/us.png" width="24" height="18" alt="US" />
                    <span>US +1</span>
                  </button>
                  <button type="button" class="fh-country-opt w-full px-3 py-2 hover:bg-neutral-800 flex items-center gap-2" data-cc="+65" data-flag="https://flagcdn.com/24x18/sg.png" data-alt="SG" data-max="8">
                    <img src="https://flagcdn.com/24x18/sg.png" width="24" height="18" alt="SG" />
                    <span>SG +65</span>
                  </button>
                  <button type="button" class="fh-country-opt w-full px-3 py-2 hover:bg-neutral-800 flex items-center gap-2" data-cc="+44" data-flag="https://flagcdn.com/24x18/gb.png" data-alt="UK" data-max="10">
                    <img src="https://flagcdn.com/24x18/gb.png" width="24" height="18" alt="UK" />
                    <span>UK +44</span>
                  </button>
                </div>
              </div>
              <input name="phone_national" required inputmode="numeric" pattern="\d*" maxlength="10" class="fh-input w-full col-span-2 fh-phone-national" />
            </div>
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
            <input name="postal_code" required inputmode="numeric" pattern="\d{4}" maxlength="4" class="fh-input w-full" />
          </div>
          <input type="hidden" name="lat" value="" class="fh-lat" />
          <input type="hidden" name="lng" value="" class="fh-lng" />
          <div class="md:col-span-2">
            <label class="block text-sm text-neutral-400 mb-1">Pinpoint location (optional)</label>
            <button type="button" class="fh-btn fh-btn-ghost fh-toggle-map" aria-label="Set location">
              <span class="inline-flex items-center gap-2">
                <span>📍</span>
                <span>Set / View location</span>
              </span>
            </button>
            <div class="fh-map-wrap hidden mt-2">
              <div class="fh-map" style="height: 220px; border-radius: 12px; border: 1px solid rgba(255,255,255,.08);"></div>
              <div class="mt-2 flex items-center gap-2">
                <button type="button" class="fh-btn fh-btn-ghost fh-use-location">Use my location</button>
                <div class="text-xs text-neutral-400">Click the map to drop/move the pin.</div>
              </div>
            </div>
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

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  (function () {
    function initMapForBlock(block) {
      if (!window.L || !block) return;
      if (block.dataset.inited === '1') return;

      var form = block.closest('form');
      if (!form) return;

      var latInput = form.querySelector('.fh-lat');
      var lngInput = form.querySelector('.fh-lng');
      var useBtn = form.querySelector('.fh-use-location');

      var lat0 = parseFloat(block.getAttribute('data-lat') || (latInput ? latInput.value : ''));
      var lng0 = parseFloat(block.getAttribute('data-lng') || (lngInput ? lngInput.value : ''));
      if (!isFinite(lat0) || !isFinite(lng0)) {
        lat0 = 14.5995;
        lng0 = 120.9842;
      }

      var map = L.map(block, { scrollWheelZoom: false }).setView([lat0, lng0], 13);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: ''
      }).addTo(map);

      var marker = null;
      function setPoint(lat, lng) {
        if (!marker) {
          marker = L.marker([lat, lng], { draggable: true }).addTo(map);
          marker.on('dragend', function (e) {
            var ll = e.target.getLatLng();
            if (latInput) latInput.value = ll.lat.toFixed(6);
            if (lngInput) lngInput.value = ll.lng.toFixed(6);
          });
        } else {
          marker.setLatLng([lat, lng]);
        }
        if (latInput) latInput.value = lat.toFixed(6);
        if (lngInput) lngInput.value = lng.toFixed(6);
      }

      if (isFinite(parseFloat(latInput && latInput.value)) && isFinite(parseFloat(lngInput && lngInput.value))) {
        setPoint(parseFloat(latInput.value), parseFloat(lngInput.value));
      } else if (block.getAttribute('data-lat') && block.getAttribute('data-lng')) {
        setPoint(lat0, lng0);
      }

      map.on('click', function (e) {
        setPoint(e.latlng.lat, e.latlng.lng);
      });

      if (useBtn) {
        useBtn.addEventListener('click', function () {
          if (!navigator.geolocation) return;
          navigator.geolocation.getCurrentPosition(function (pos) {
            var lat = pos.coords.latitude;
            var lng = pos.coords.longitude;
            setPoint(lat, lng);
            map.setView([lat, lng], 16);
          });
        });
      }

      block.dataset.inited = '1';
      setTimeout(function () { map.invalidateSize(); }, 250);
    }

    function initAllMaps() {
      document.querySelectorAll('.fh-map').forEach(function (m) {
        var wrap = m.closest('.fh-map-wrap');
        if (wrap && wrap.classList.contains('hidden')) return;
        initMapForBlock(m);
      });
    }

    function bindToggles() {
      document.querySelectorAll('.fh-toggle-map').forEach(function (btn) {
        if (btn.dataset.bound === '1') return;
        btn.dataset.bound = '1';
        btn.addEventListener('click', function () {
          var form = btn.closest('form');
          if (!form) return;
          var wrap = form.querySelector('.fh-map-wrap');
          if (!wrap) return;
          wrap.classList.toggle('hidden');
          setTimeout(initAllMaps, 50);
        });
      });
    }

    function bindCountryPickers() {
      document.querySelectorAll('.fh-country-btn').forEach(function (btn) {
        if (btn.dataset.boundCountry === '1') return;
        btn.dataset.boundCountry = '1';

        var wrap = btn.closest('.relative');
        if (!wrap) return;
        var menu = wrap.querySelector('.fh-country-menu');
        var hidden = wrap.querySelector('.fh-phone-cc');
        var flag = wrap.querySelector('.fh-country-flag');
        var label = wrap.querySelector('.fh-country-label');
        var form = btn.closest('form');
        var national = form ? form.querySelector('.fh-phone-national') : null;

        btn.addEventListener('click', function () {
          if (!menu) return;
          menu.classList.toggle('hidden');
        });

        wrap.querySelectorAll('.fh-country-opt').forEach(function (opt) {
          opt.addEventListener('click', function () {
            var cc = opt.getAttribute('data-cc') || '+63';
            var f = opt.getAttribute('data-flag') || '';
            var alt = opt.getAttribute('data-alt') || '';
            var mx = parseInt(opt.getAttribute('data-max') || '10', 10);
            if (hidden) hidden.value = cc;
            if (label) label.textContent = cc;
            if (flag && f) { flag.src = f; flag.alt = alt; }
            if (national && isFinite(mx)) { national.maxLength = mx; }
            if (menu) menu.classList.add('hidden');
          });
        });
      });
    }

    document.addEventListener('click', function (e) {
      document.querySelectorAll('.fh-country-menu').forEach(function (m) {
        var parent = m.closest('.relative');
        if (!parent) return;
        if (parent.contains(e.target)) return;
        m.classList.add('hidden');
      });
    });

    document.addEventListener('toggle', function (e) {
      if (!e || !e.target || e.target.tagName !== 'DETAILS') return;
      setTimeout(function () {
        bindToggles();
        bindCountryPickers();
        initAllMaps();
      }, 50);
    }, true);

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () {
        bindToggles();
        bindCountryPickers();
        initAllMaps();
      });
    } else {
      bindToggles();
      bindCountryPickers();
      initAllMaps();
    }
  })();
</script>
