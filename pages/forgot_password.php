<?php
require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mail.php';
require_once __DIR__ . '/../includes/env.php';

$err = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim((string)($_POST['email'] ?? ''));
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err = 'Please enter a valid email.';
  } else {
    try {
      $stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE email=? LIMIT 1');
      $stmt->execute([$email]);
      $u = $stmt->fetch();

      if ($u) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'pgsql') {
          $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?,?, now() + interval \'1 hour\')')
            ->execute([(int)$u['id'], $tokenHash]);
        } else {
          $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?,?, DATE_ADD(NOW(), INTERVAL 1 HOUR))')
            ->execute([(int)$u['id'], $tokenHash]);
        }

        $base = rtrim(BASE_URL, '/');
        $link = $base . '/index.php?page=reset_password&token=' . urlencode($token);

        $subject = 'Reset your Fitshop Hub password';
        $html = '<p>We received a request to reset your password.</p><p><a href="' . htmlspecialchars($link) . '">Reset Password</a></p><p>This link expires in 1 hour.</p>';
        $text = "Reset your password: $link\nThis link expires in 1 hour.";

        try {
          fh_send_mail((string)$u['email'], (string)($u['name'] ?? ''), $subject, $html, $text);
        } catch (Throwable $e) {
          error_log('Forgot password mail failed: ' . $e->getMessage());
        }
      }

      $ok = 'If an account with that email exists, we sent a password reset link.';
    } catch (Throwable $e) {
      error_log('Forgot password failed: ' . $e->getMessage());
      $ok = 'If an account with that email exists, we sent a password reset link.';
    }
  }
}
?>
<section class="min-h-[calc(100vh-140px)] flex items-center">
  <div class="w-full max-w-md mx-auto">
    <h2 class="text-2xl font-bold mb-4">Forgot Password</h2>
    <?php if ($err): ?><div class="mb-4 p-3 rounded bg-red-500/10 text-red-300 border border-red-500/30"><?=htmlspecialchars($err)?></div><?php endif; ?>
    <?php if ($ok): ?><div class="mb-4 p-3 rounded bg-emerald-500/10 text-emerald-300 border border-emerald-500/30"><?=htmlspecialchars($ok)?></div><?php endif; ?>
    <form method="post" class="space-y-3">
      <div>
        <label class="block text-sm text-neutral-400">Email</label>
        <input type="email" name="email" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
      </div>
      <button class="w-full px-4 py-2 rounded-lg bg-brand text-white">Send Reset Link</button>
    </form>
    <p class="mt-3 text-sm text-neutral-400"><a class="text-brand" href="index.php?page=login">Back to Login</a></p>
  </div>
</section>
