<?php
$apkDir = __DIR__ . '/../downloads';
$apkUrl = 'index.php?page=download_apk';

$apkReleaseUrl = (string)(getenv('APK_RELEASE_URL') ?: getenv('APK_GITHUB_URL') ?: '');

$apkPath = '';
$apkName = '';
$apkSize = 0;
$apkMtime = 0;

if ($apkReleaseUrl === '') {
  if (is_dir($apkDir)) {
    $candidates = glob($apkDir . '/*.apk');
    if ($candidates && is_array($candidates)) {
      usort($candidates, function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
      });
      $apkPath = (string)$candidates[0];
      $apkName = basename($apkPath);
      $apkSize = (int)@filesize($apkPath);
      $apkMtime = (int)@filemtime($apkPath);
    }
  }
} else {
  $apkName = 'FitshopHubMobile.apk';
}

function fh_human_bytes($bytes) {
  $b = (float)$bytes;
  if ($b <= 0) return '0 B';
  $units = ['B','KB','MB','GB'];
  $i = 0;
  while ($b >= 1024 && $i < count($units) - 1) { $b /= 1024; $i++; }
  return number_format($b, $i === 0 ? 0 : 2) . ' ' . $units[$i];
}
?>

<section class="py-8 max-w-2xl mx-auto">
  <h1 class="text-3xl font-extrabold">Download Fitshop Hub Mobile</h1>
  <p class="mt-2 text-neutral-400">Install the Android APK directly from our official website.</p>

  <div class="mt-6 fh-card p-5">
    <?php if ($apkReleaseUrl !== ''): ?>
      <div class="text-sm text-neutral-400">Latest APK</div>
      <div class="mt-1 text-lg font-semibold text-neutral-100"><?= htmlspecialchars($apkName) ?></div>
      <div class="mt-1 text-sm text-neutral-400">Hosted on GitHub Releases</div>
      <div class="mt-4">
        <a class="fh-btn fh-btn-primary" href="<?= htmlspecialchars($apkUrl) ?>">Download APK</a>
      </div>
      <div class="mt-3 text-xs text-neutral-500">Android may warn about unknown apps. Only install if you trust this website.</div>
    <?php elseif ($apkPath !== '' && is_file($apkPath)): ?>
      <div class="text-sm text-neutral-400">Latest APK</div>
      <div class="mt-1 text-lg font-semibold text-neutral-100"><?= htmlspecialchars($apkName) ?></div>
      <div class="mt-1 text-sm text-neutral-400">
        Size: <?= htmlspecialchars(fh_human_bytes($apkSize)) ?>
        <?php if ($apkMtime): ?> • Updated: <?= date('M d, Y H:i', $apkMtime) ?><?php endif; ?>
      </div>
      <div class="mt-4">
        <a class="fh-btn fh-btn-primary" href="<?= htmlspecialchars($apkUrl) ?>">Download APK</a>
      </div>
      <div class="mt-3 text-xs text-neutral-500">Android may warn about unknown apps. Only install if you trust this website.</div>
    <?php else: ?>
      <div class="font-semibold">APK not uploaded yet</div>
      <div class="mt-2 text-sm text-neutral-400">
        Place your APK file in:
        <div class="mt-1 font-mono text-xs text-neutral-300"><?= htmlspecialchars($apkDir) ?></div>
        <div class="mt-2">Then refresh this page.</div>
      </div>
    <?php endif; ?>
  </div>

  <div class="mt-6 fh-card p-5">
    <h2 class="text-lg font-semibold">How to install (Android)</h2>
    <ol class="mt-3 space-y-2 text-sm text-neutral-300">
      <li>1. Download the APK using the button above.</li>
      <li>2. Open the downloaded file.</li>
      <li>3. If prompted: enable <span class="text-neutral-100">Install unknown apps</span> for your browser/file manager.</li>
      <li>4. Tap <span class="text-neutral-100">Install</span>.</li>
    </ol>
    <div class="mt-3 text-xs text-neutral-500">If Android blocks the install, check Settings → Security/Privacy → Install unknown apps.</div>
  </div>
</section>
