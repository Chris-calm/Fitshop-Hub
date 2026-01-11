<?php
require __DIR__ . '/../includes/db.php';

$err = '';
$ok = '';

$token = (string)($_GET['token'] ?? ($_POST['token'] ?? ''));
$token = trim($token);

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
  $err = 'Invalid reset token.';
}

$resetRow = null;
if (!$err) {
  try {
    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare('SELECT id, user_id, token_hash, expires_at, used_at FROM password_resets WHERE used_at IS NULL AND token_hash=? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$tokenHash]);
    $resetRow = $stmt->fetch();

    if (!$resetRow) {
      $err = 'Reset token is invalid or already used.';
    } else {
      $expiresAt = strtotime((string)($resetRow['expires_at'] ?? ''));
      if ($expiresAt !== false && time() > $expiresAt) {
        $err = 'Reset token expired.';
      }
    }
  } catch (Throwable $e) {
    error_log('Reset token lookup failed: ' . $e->getMessage());
    $err = 'Failed to validate token.';
  }
}

if (!$err && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $p1 = (string)($_POST['password'] ?? '');
  $p2 = (string)($_POST['password2'] ?? '');
  if ($p1 === '' || $p2 === '') {
    $err = 'Please fill out both password fields.';
  } elseif ($p1 !== $p2) {
    $err = 'Passwords do not match.';
  } elseif (strlen($p1) < 8) {
    $err = 'Password must be at least 8 characters.';
  } else {
    try {
      $newHash = password_hash($p1, PASSWORD_DEFAULT);
      $pdo->beginTransaction();
      $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$newHash, (int)$resetRow['user_id']]);
      $pdo->prepare('UPDATE password_resets SET used_at=now() WHERE id=?')->execute([(int)$resetRow['id']]);
      $pdo->commit();
      $ok = 'Password updated. You can now login.';
    } catch (Throwable $e) {
      $pdo->rollBack();
      error_log('Reset password failed: ' . $e->getMessage());
      $err = 'Failed to reset password.';
    }
  }
}
?>
<section class="min-h-[calc(100vh-140px)] flex items-center">
  <div class="w-full max-w-md mx-auto">
    <h2 class="text-2xl font-bold mb-4">Reset Password</h2>
    <?php if ($err): ?><div class="mb-4 p-3 rounded bg-red-500/10 text-red-300 border border-red-500/30"><?=htmlspecialchars($err)?></div><?php endif; ?>
    <?php if ($ok): ?><div class="mb-4 p-3 rounded bg-emerald-500/10 text-emerald-300 border border-emerald-500/30"><?=htmlspecialchars($ok)?> <a class="text-brand" href="index.php?page=login">Login</a></div><?php endif; ?>

    <?php if (!$ok && !$err): ?>
      <form method="post" class="space-y-3">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>" />
        <div>
          <label class="block text-sm text-neutral-400">New password</label>
          <input type="password" name="password" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm text-neutral-400">Confirm new password</label>
          <input type="password" name="password2" required class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-3 py-2" />
        </div>
        <button class="w-full px-4 py-2 rounded-lg bg-brand text-white">Update Password</button>
      </form>
    <?php endif; ?>

    <p class="mt-3 text-sm text-neutral-400"><a class="text-brand" href="index.php?page=login">Back to Login</a></p>
  </div>
</section>
