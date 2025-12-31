<?php
require __DIR__ . '/../includes/db.php';
$err = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  if (!$email || !$password) { $err='Email and password are required.'; }
  else {
    $stmt = $pdo->prepare('SELECT id,name,email,password_hash,photo_url FROM users WHERE email=? LIMIT 1');
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    if (!$u || !password_verify($password, $u['password_hash'])) {
      $err = 'Invalid email or password.';
    } else {
      $_SESSION['user'] = ['id'=>$u['id'],'name'=>$u['name'],'email'=>$u['email'],'photo_url'=>$u['photo_url']];
      $dest = $_SESSION['after_login'] ?? 'index.php';
      unset($_SESSION['after_login']);
      header('Location: ' . $dest);
      exit;
    }
  }
}
?>
<section class="max-w-md mx-auto">
  <h2 class="text-2xl font-bold mb-4">Login</h2>
  <?php if ($err): ?><div class="mb-4 p-3 rounded bg-red-500/10 text-red-300 border border-red-500/30"><?=htmlspecialchars($err)?></div><?php endif; ?>
  <form method="post" class="space-y-3">
    <div>
      <label class="block text-sm text-neutral-400">Email</label>
      <input type="email" name="email" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
    </div>
    <div>
      <label class="block text-sm text-neutral-400">Password</label>
      <div class="relative">
        <input id="login_password" type="password" name="password" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2 pr-10" />
        <button type="button" class="pw-toggle absolute right-2 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-200" data-target="login_password" aria-label="Toggle password">👁</button>
      </div>
    </div>
    <button class="w-full px-4 py-2 rounded-lg bg-brand text-white">Sign In</button>
  </form>
  <p class="mt-3 text-sm text-neutral-400">No account? <a class="text-brand" href="index.php?page=register">Register</a></p>
</section>
