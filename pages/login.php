<?php
require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_cookie.php';
require_once __DIR__ . '/../includes/mail.php';
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
      try {
        $otp = (string)random_int(100000, 999999);
        $otpHash = password_hash($otp, PASSWORD_DEFAULT);

        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'pgsql') {
          $pdo->prepare('INSERT INTO auth_otps (user_id, otp_hash, expires_at) VALUES (?,?, now() + interval \'10 minutes\')')
            ->execute([(int)$u['id'], $otpHash]);
        } else {
          $pdo->prepare('INSERT INTO auth_otps (user_id, otp_hash, expires_at) VALUES (?,?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))')
            ->execute([(int)$u['id'], $otpHash]);
        }

        $_SESSION['pending_otp'] = [
          'user_id' => (int)$u['id'],
          'name' => (string)($u['name'] ?? ''),
          'email' => (string)($u['email'] ?? ''),
          'photo_url' => $u['photo_url'] ?? null,
          'issued_at' => time(),
        ];

        $subject = 'Your Fitshop Hub login code';
        $html = '<p>Your one-time login code is:</p><h2 style="letter-spacing:2px">' . htmlspecialchars($otp) . '</h2><p>This code expires in 10 minutes.</p>';
        $text = "Your one-time login code is: $otp\nThis code expires in 10 minutes.";
        fh_send_mail((string)$u['email'], (string)($u['name'] ?? ''), $subject, $html, $text);

        header('Location: index.php?page=otp_verify');
        exit;
      } catch (Throwable $e) {
        error_log('Login OTP send failed: ' . $e->getMessage());
        $err = 'Failed to send OTP. Please try again.';
      }
    }
  }
}
?>
<section class="min-h-[calc(100vh-140px)] flex items-center">
  <div class="w-full max-w-md mx-auto">
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
          <input id="login_password" type="password" name="password" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2 pr-16" />
          <button type="button" class="pw-toggle absolute right-2 top-1/2 -translate-y-1/2 text-xs text-neutral-400 hover:text-neutral-200" data-target="login_password" data-show-text="Show" data-hide-text="Hide" aria-label="Toggle password">Show</button>
        </div>
      </div>
      <button class="w-full px-4 py-2 rounded-lg bg-brand text-white">Sign In</button>
    </form>
    <div class="mt-3 text-sm text-neutral-400 flex items-center justify-between gap-3">
      <span>No account? <a class="text-brand" href="index.php?page=register">Register</a></span>
      <a class="text-brand" href="index.php?page=forgot_password">Forgot password?</a>
    </div>
    <div class="mt-6 text-xs text-neutral-500 flex items-center justify-center gap-4">
      <a class="hover:text-neutral-300" href="index.php?page=about">About</a>
      <a class="hover:text-neutral-300" href="index.php?page=guides_public">Guides</a>
    </div>
  </div>
</section>
