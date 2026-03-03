<?php
$apkDir = __DIR__ . '/../downloads';
if (!is_dir($apkDir)) {
  http_response_code(404);
  echo 'APK not found';
  exit;
}

$candidates = glob($apkDir . '/*.apk');
if (!$candidates || !is_array($candidates)) {
  http_response_code(404);
  echo 'APK not found';
  exit;
}

usort($candidates, function ($a, $b) {
  return filemtime($b) <=> filemtime($a);
});

$apkPath = (string)$candidates[0];
if (!is_file($apkPath)) {
  http_response_code(404);
  echo 'APK not found';
  exit;
}

$filename = basename($apkPath);
$size = (int)@filesize($apkPath);

header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
header('Content-Length: ' . $size);
header('X-Content-Type-Options: nosniff');

$fp = fopen($apkPath, 'rb');
if ($fp === false) {
  http_response_code(500);
  echo 'Failed to read APK';
  exit;
}

while (!feof($fp)) {
  $buf = fread($fp, 8192);
  if ($buf === false) {
    break;
  }
  echo $buf;
  @flush();
}

fclose($fp);
exit;
