<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/plan.php';
$err = '';$ok='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $password2 = $_POST['password2'] ?? '';
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
      $targetDir = __DIR__ . '/../uploads/avatars/';
      if (!is_dir($targetDir)) { @mkdir($targetDir, 0777, true); }
      $filename = 'avatar_'.time().'_'.bin2hex(random_bytes(3)).'.'.$ext;
      $dest = $targetDir.$filename;
      if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
        $photoPath = 'uploads/avatars/'.$filename;
      } else {
        $err = 'Failed to upload image.';
      }
    }
  }
  if (!$name || !$email || !$password) { $err='All fields are required.'; }
  elseif ($password !== $password2) { $err='Passwords do not match.'; }
  else {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) { $err='Email already registered.'; }
    else {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $profile = compact('goal','activity_level','equipment','diet');
      $plan = fh_build_plan($profile);
      $stmt = $pdo->prepare('INSERT INTO users(name,email,password_hash,photo_url,goal,activity_level,equipment,diet,plan_json) VALUES (?,?,?,?,?,?,?,?,?)');
      $stmt->execute([$name,$email,$hash,$photoPath,$goal,$activity_level,$equipment,$diet,json_encode($plan)]);
      $ok='Account created with your personalized plan. You can now login.';
    }
  }
}
?>
<section class="max-w-md mx-auto">
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
        <input id="reg_password" type="password" name="password" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2 pr-10" />
        <button type="button" class="pw-toggle absolute right-2 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-200" data-target="reg_password" aria-label="Toggle password">👁</button>
      </div>
    </div>
    <div>
      <label class="block text-sm text-neutral-400">Confirm Password</label>
      <div class="relative">
        <input id="reg_password2" type="password" name="password2" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2 pr-10" />
        <button type="button" class="pw-toggle absolute right-2 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-200" data-target="reg_password2" aria-label="Toggle password">👁</button>
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
</section>
