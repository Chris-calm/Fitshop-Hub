<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/plan.php';
require_once __DIR__ . '/../includes/supabase_storage.php';
$err = '';$ok='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $password2 = $_POST['password2'] ?? '';
  $phoneCc = trim((string)($_POST['phone_cc'] ?? '+63'));
  $phoneNational = preg_replace('/\D+/', '', (string)($_POST['phone_national'] ?? ''));
  $line1 = trim($_POST['line1'] ?? '');
  $line2 = trim($_POST['line2'] ?? '');
  $city = trim($_POST['city'] ?? '');
  $province = trim($_POST['province'] ?? '');
  $postal_code = trim($_POST['postal_code'] ?? '');
  $goal = $_POST['goal'] ?? 'general_health';
  $activity_level = $_POST['activity_level'] ?? 'light';
  $equipment = $_POST['equipment'] ?? 'none';
  $diet = $_POST['diet'] ?? 'none';
  // handle photo upload (optional)
  $photoPath = null;
  if (!empty($_FILES['photo']['name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
    $allowed = ['jpg','jpeg','png','gif','webp'];
    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext,$allowed)) {
      $err = 'Invalid image type. Allowed: jpg, png, gif, webp.';
    } else {
      if (IS_VERCEL) {
        try {
          $bucket = getenv('SUPABASE_AVATAR_BUCKET') ?: 'avatars';
          $key = 'avatar_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
          $photoPath = supabase_storage_upload_tmpfile($bucket, $key, $_FILES['photo']['tmp_name'], $_FILES['photo']['type'] ?? 'application/octet-stream');
        } catch (Throwable $e) {
          $msg = $e->getMessage();
          error_log('Avatar upload failed: ' . $msg);
          if (strpos($msg, 'Supabase Storage not configured') !== false) {
            $err = 'Avatar upload is not configured on Vercel. Please set SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY (and ensure the "avatars" bucket exists).';
          } else {
            $err = 'Failed to upload image. Please try again (or register without a photo).';
          }
        }
      } else {
        $targetDir = __DIR__ . '/../uploads/avatars/';
        if (!is_dir($targetDir)) { @mkdir($targetDir, 0777, true); }
        $filename = 'avatar_'.time().'_'.bin2hex(random_bytes(3)).'.'.$ext;
        $dest = $targetDir.$filename;
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
          $photoPath = rtrim(BASE_URL, '/') . '/uploads/avatars/'.$filename;
        } else {
          $err = 'Failed to upload image.';
        }
      }
    }
  }
  $hasAddress = ($line1 !== '' || $city !== '' || $province !== '' || $postal_code !== '');
  $phoneCc = ($phoneCc !== '' && $phoneCc[0] !== '+') ? ('+' . $phoneCc) : $phoneCc;
  $rules = [
    '+63' => 10,
    '+1' => 10,
    '+65' => 8,
    '+44' => 10,
  ];
  $expectedDigits = $rules[$phoneCc] ?? 10;
  $phone = $phoneCc . $phoneNational;

  if (!$name || !$email || !$password || $phoneNational === '') { $err='Name, email, password, and phone are required.'; }
  elseif (!isset($rules[$phoneCc])) { $err='Please select a valid country code.'; }
  elseif (!preg_match('/^\d+$/', $phoneNational)) { $err='Phone number must contain digits only.'; }
  elseif (strlen($phoneNational) !== $expectedDigits) { $err='Phone must be ' . $expectedDigits . ' digits for ' . $phoneCc . '.'; }
  elseif ($password !== $password2) { $err='Passwords do not match.'; }
  elseif ($hasAddress && ($line1 === '' || $city === '' || $province === '' || $postal_code === '')) { $err='If you provide an address, please complete all required address fields.'; }
  elseif ($hasAddress && !preg_match('/^\d{4}$/', $postal_code)) { $err='Postal code must be 4 digits (Philippines).'; }
  else {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) { $err='Email already registered.'; }
    else {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $profile = compact('goal','activity_level','equipment','diet');
      $plan = fh_build_plan($profile);
      if (defined('IS_VERCEL') && IS_VERCEL) {
        $stmt = $pdo->prepare('INSERT INTO users(name,email,password_hash,photo_url,goal,activity_level,equipment,diet,plan_json) VALUES (?,?,?,?,?,?,?,?,?) RETURNING id');
        $stmt->execute([$name,$email,$hash,$photoPath,$goal,$activity_level,$equipment,$diet,json_encode($plan)]);
        $userId = (int)$stmt->fetchColumn();
      } else {
        $stmt = $pdo->prepare('INSERT INTO users(name,email,password_hash,photo_url,goal,activity_level,equipment,diet,plan_json) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$name,$email,$hash,$photoPath,$goal,$activity_level,$equipment,$diet,json_encode($plan)]);
        $userId = (int)$pdo->lastInsertId();
      }

      if ($userId > 0 && $hasAddress) {
        try {
          if (defined('IS_VERCEL') && IS_VERCEL) {
            $aStmt = $pdo->prepare('INSERT INTO user_addresses (user_id, full_name, phone, line1, line2, city, province, postal_code, is_default) VALUES (?,?,?,?,?,?,?,?,?)');
            $aStmt->execute([$userId, $name, $phone, $line1, ($line2 !== '' ? $line2 : null), $city, $province, $postal_code, 1]);
          } else {
            $pdo->prepare('UPDATE user_addresses SET is_default=0 WHERE user_id=?')->execute([$userId]);
            $aStmt = $pdo->prepare('INSERT INTO user_addresses (user_id, full_name, phone, line1, line2, city, province, postal_code, is_default) VALUES (?,?,?,?,?,?,?,?,?)');
            $aStmt->execute([$userId, $name, $phone, $line1, ($line2 !== '' ? $line2 : null), $city, $province, $postal_code, 1]);
          }
        } catch (Throwable $e) {
          // If the address table isn't set up yet, registration should still succeed.
        }
      }
      $ok='Account created with your personalized plan. You can now login.';
    }
  }
}
?>
<section class="min-h-[calc(100vh-140px)] flex items-center">
  <div class="w-full max-w-md mx-auto">
  <h2 class="text-2xl font-bold mb-4">Register</h2>
  <?php if ($err): ?><div class="mb-4 p-3 rounded bg-red-500/10 text-red-300 border border-red-500/30"><?=htmlspecialchars($err)?></div><?php endif; ?>
  <?php if ($ok): ?><div class="mb-4 p-3 rounded bg-emerald-500/10 text-emerald-300 border border-emerald-500/30"><?=htmlspecialchars($ok)?> <a class="text-brand" href="index.php?page=login">Login</a></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="space-y-3">
    <div>
      <label class="block text-sm text-neutral-400">Full Name</label>
      <input name="name" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
    </div>
    <div>
      <label class="block text-sm text-neutral-400">Email</label>
      <input type="email" name="email" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
    </div>
    <div>
      <label class="block text-sm text-neutral-400">Profile Photo (optional)</label>
      <input type="file" name="photo" accept="image/*" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
    </div>
    <div>
      <label class="block text-sm text-neutral-400">Password</label>
      <div class="relative">
        <input id="reg_password" type="password" name="password" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2 pr-16" />
        <button type="button" class="pw-toggle absolute right-2 top-1/2 -translate-y-1/2 text-xs text-neutral-400 hover:text-neutral-200" data-target="reg_password" data-show-text="Show" data-hide-text="Hide" aria-label="Toggle password">Show</button>
      </div>
    </div>
    <div>
      <label class="block text-sm text-neutral-400">Confirm Password</label>
      <div class="relative">
        <input id="reg_password2" type="password" name="password2" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2 pr-16" />
        <button type="button" class="pw-toggle absolute right-2 top-1/2 -translate-y-1/2 text-xs text-neutral-400 hover:text-neutral-200" data-target="reg_password2" data-show-text="Show" data-hide-text="Hide" aria-label="Toggle password">Show</button>
      </div>
    </div>
    <hr class="my-4 border-neutral-800" />
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div class="sm:col-span-2">
        <label class="block text-sm text-neutral-400">Phone</label>
        <div class="grid grid-cols-3 gap-2">
          <select name="phone_cc" class="col-span-1 w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2">
            <option value="+63" selected>🇵🇭 +63</option>
            <option value="+1">🇺🇸 +1</option>
            <option value="+65">🇸🇬 +65</option>
            <option value="+44">🇬🇧 +44</option>
          </select>
          <input name="phone_national" required inputmode="numeric" pattern="\d*" maxlength="10" placeholder="Number" class="col-span-2 w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
        </div>
        <div class="mt-1 text-xs text-neutral-500">PH: 10 digits after +63. Digits only.</div>
      </div>
      <div class="sm:col-span-2">
        <label class="block text-sm text-neutral-400">Address line 1</label>
        <input name="line1" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
      </div>
      <div class="sm:col-span-2">
        <label class="block text-sm text-neutral-400">Address line 2 (optional)</label>
        <input name="line2" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
      </div>
      <div>
        <label class="block text-sm text-neutral-400">City</label>
        <input name="city" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
      </div>
      <div>
        <label class="block text-sm text-neutral-400">Province</label>
        <input name="province" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
      </div>
      <div>
        <label class="block text-sm text-neutral-400">Postal code</label>
        <input name="postal_code" inputmode="numeric" pattern="\d{4}" maxlength="4" placeholder="4 digits" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
      </div>
    </div>
    <hr class="my-4 border-neutral-800" />
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div>
        <label class="block text-sm text-neutral-400">Goal</label>
        <select name="goal" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2">
          <option value="general_health">General Health</option>
          <option value="lose_weight">Lose Weight</option>
          <option value="build_muscle">Build Muscle</option>
          <option value="endurance">Endurance</option>
        </select>
      </div>
      <div>
        <label class="block text-sm text-neutral-400">Activity Level</label>
        <select name="activity_level" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2">
          <option value="sedentary">Sedentary</option>
          <option value="light" selected>Light</option>
          <option value="moderate">Moderate</option>
          <option value="active">Active</option>
        </select>
      </div>
      <div>
        <label class="block text-sm text-neutral-400">Equipment</label>
        <select name="equipment" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2">
          <option value="none">None</option>
          <option value="home_minimal">Home Minimal</option>
          <option value="gym_access">Gym Access</option>
        </select>
      </div>
      <div>
        <label class="block text-sm text-neutral-400">Diet Preference</label>
        <select name="diet" class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2">
          <option value="none">No preference</option>
          <option value="vegetarian">Vegetarian</option>
          <option value="keto">Keto</option>
        </select>
      </div>
    </div>
    <button class="w-full px-4 py-2 rounded-lg bg-brand text-white">Create Account</button>
  </form>
  <p class="mt-3 text-sm text-neutral-400">Already have an account? <a class="text-brand" href="index.php?page=login">Login</a></p>
  </div>
</section>
