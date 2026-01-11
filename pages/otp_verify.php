<?php
require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_cookie.php';

$err = '';

$pending = $_SESSION['pending_otp'] ?? null;
if (!is_array($pending) || empty($pending['user_id']) || empty($pending['email'])) {
  header('Location: index.php?page=login');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $code = preg_replace('/\s+/', '', (string)($_POST['otp'] ?? ''));
  if (!preg_match('/^\d{6}$/', $code)) {
    $err = 'Invalid code format.';
  } else {
    try {
      $stmt = $pdo->prepare('SELECT id, otp_hash, expires_at, attempts FROM auth_otps WHERE user_id=? AND consumed_at IS NULL ORDER BY id DESC LIMIT 1');
      $stmt->execute([(int)$pending['user_id']]);
      $row = $stmt->fetch();
      if (!$row) {
        $err = 'No active OTP found. Please login again.';
      } else {
        $attempts = (int)($row['attempts'] ?? 0);
        if ($attempts >= 5) {
          $err = 'Too many attempts. Please login again.';
        } else {
          $expiresAt = strtotime((string)($row['expires_at'] ?? ''));
          if ($expiresAt !== false && time() > $expiresAt) {
            $err = 'OTP expired. Please login again.';
          } else {
            $hash = (string)($row['otp_hash'] ?? '');
            $ok = password_verify($code, $hash);
            $pdo->prepare('UPDATE auth_otps SET attempts=attempts+1 WHERE id=?')->execute([(int)$row['id']]);
            if (!$ok) {
              $err = 'Incorrect code.';
            } else {
              $pdo->prepare('UPDATE auth_otps SET consumed_at=now() WHERE id=?')->execute([(int)$row['id']]);
              $_SESSION['user'] = [
                'id' => $pending['user_id'],
                'name' => $pending['name'] ?? '',
                'email' => $pending['email'] ?? '',
                'photo_url' => $pending['photo_url'] ?? null,
              ];
              fh_set_auth_cookie($_SESSION['user']);
              unset($_SESSION['pending_otp']);
              header('Location: index.php?page=landing');
              exit;
            }
          }
        }
      }
    } catch (Throwable $e) {
      error_log('OTP verify failed: ' . $e->getMessage());
      $err = 'Failed to verify OTP.';
    }
  }
}
?>
<section class="min-h-[calc(100vh-140px)] flex items-center">
  <div class="w-full max-w-md mx-auto">
    <h2 class="text-2xl font-bold mb-2">Verify OTP</h2>
    <div class="text-sm text-neutral-400 mb-4">We sent a 6-digit code to <?= htmlspecialchars((string)$pending['email']) ?>.</div>
    <?php if ($err): ?><div class="mb-4 p-3 rounded bg-red-500/10 text-red-300 border border-red-500/30"><?=htmlspecialchars($err)?></div><?php endif; ?>
    <form method="post" class="space-y-3">
      <div>
        <label class="block text-sm text-neutral-400">OTP code</label>
        <input name="otp" inputmode="numeric" pattern="\d{6}" maxlength="6" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" placeholder="123456" />
      </div>
      <button class="w-full px-4 py-2 rounded-lg bg-brand text-white">Verify & Sign In</button>
    </form>
    <p class="mt-3 text-sm text-neutral-400"><a class="text-brand" href="index.php?page=login">Back to Login</a></p>
  </div>
</section>
