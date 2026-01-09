<?php
require_once __DIR__ . '/env.php';

function fh_cart_is_https() {
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    return true;
  }
  if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
    return true;
  }
  return false;
}

function fh_cart_read_cookie() {
  $raw = $_COOKIE['fh_cart'] ?? '';
  if (!$raw) {
    return [];
  }
  $data = json_decode($raw, true);
  if (!is_array($data)) {
    return [];
  }
  $out = [];
  foreach ($data as $k => $v) {
    $key = (string)$k;
    $qty = (int)$v;
    $parsed = fh_cart_parse_key($key);
    if (!$parsed) {
      continue;
    }
    if ($qty > 0) {
      $out[$key] = $qty;
    }
  }
  return $out;
}

function fh_cart_get() {
  $cookieCart = fh_cart_read_cookie();
  if (!empty($cookieCart)) {
    $_SESSION['cart'] = $cookieCart;
    return $cookieCart;
  }

  if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $clean = [];
    foreach ($_SESSION['cart'] as $k => $v) {
      $key = (string)$k;
      $qty = (int)$v;
      $parsed = fh_cart_parse_key($key);
      if (!$parsed) {
        continue;
      }
      if ($qty > 0) {
        $clean[(string)$parsed['key']] = $qty;
      }
    }
    $_SESSION['cart'] = $clean;
    return $clean;
  }

  $_SESSION['cart'] = [];
  return [];
}

function fh_cart_write($cart) {
  if (!is_array($cart)) {
    $cart = [];
  }

  $clean = [];
  foreach ($cart as $k => $v) {
    $key = (string)$k;
    $qty = (int)$v;
    $parsed = fh_cart_parse_key($key);
    if (!$parsed) {
      continue;
    }
    if ($qty > 0) {
      $clean[$key] = $qty;
    }
  }

  $_SESSION['cart'] = $clean;

  $value = json_encode($clean);
  if ($value === false) {
    $value = '{}';
  }

  $opts = [
    'expires' => time() + 60 * 60 * 24 * 30,
    'path' => '/',
    'secure' => fh_cart_is_https(),
    'httponly' => false,
    'samesite' => 'Lax',
  ];

  if (empty($clean)) {
    $_SESSION['cart'] = [];
    fh_cart_delete_cookie();
  } else {
    setcookie('fh_cart', $value, $opts);
  }

  return $clean;
}

function fh_cart_count($cart) {
  $count = 0;
  if (!is_array($cart)) {
    return 0;
  }
  foreach ($cart as $k => $q) {
    $parsed = fh_cart_parse_key((string)$k);
    if (!$parsed) {
      continue;
    }
    $count += (int)$q;
  }
  return $count;
}

function fh_cart_delete_cookie() {
  $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/');
  $p1 = rtrim((string)dirname($script), '/');
  $p2 = rtrim((string)dirname($p1 !== '' ? $p1 : '/'), '/');

  $paths = array_values(array_unique(array_filter([
    '/',
    '/api',
    $p1,
    $p2,
  ], function ($p) {
    return is_string($p) && $p !== '';
  })));

  $paths = array_values(array_unique(array_map(function ($p) {
    $p = (string)$p;
    if ($p === '.' || $p === '') {
      $p = '/';
    }
    if ($p[0] !== '/') {
      $p = '/' . $p;
    }
    return rtrim($p, '/') ?: '/';
  }, $paths)));

  foreach ($paths as $path) {
    $base = [
      'expires' => time() - 3600,
      'path' => $path,
      'httponly' => false,
      'samesite' => 'Lax',
    ];
    // Delete both variants because an old cookie might have been set with a different secure flag
    setcookie('fh_cart', '', $base + ['secure' => false]);
    setcookie('fh_cart', '', $base + ['secure' => true]);
  }

  // Also unset the runtime cookie value for the current request
  unset($_COOKIE['fh_cart']);
}

function fh_cart_make_key($id, $option) {
  $pid = (int)$id;
  $opt = trim((string)$option);
  if ($pid <= 0) {
    return '';
  }
  if ($opt === '') {
    $opt = 'Default';
  }
  $opt = preg_replace('/\s+/', ' ', $opt);
  $opt = mb_substr($opt, 0, 80);
  return $pid . '::' . $opt;
}

function fh_cart_parse_key($key) {
  $key = (string)$key;
  if ($key === '') {
    return null;
  }
  if (strpos($key, '::') === false) {
    $id = (int)$key;
    if ($id <= 0) {
      return null;
    }
    return ['id' => $id, 'option' => 'Default', 'key' => fh_cart_make_key($id, 'Default')];
  }
  $parts = explode('::', $key, 2);
  $id = (int)($parts[0] ?? 0);
  $opt = trim((string)($parts[1] ?? ''));
  if ($id <= 0) {
    return null;
  }
  if ($opt === '') {
    $opt = 'Default';
  }
  return ['id' => $id, 'option' => $opt, 'key' => fh_cart_make_key($id, $opt)];
}

function fh_product_options($product) {
  if (is_array($product) && !empty($product['options']) && is_array($product['options'])) {
    $out = [];
    foreach ($product['options'] as $o) {
      $t = trim((string)$o);
      if ($t !== '') {
        $out[] = $t;
      }
    }
    if (!empty($out)) {
      return array_values(array_unique($out));
    }
  }

  $cat = is_array($product) ? (string)($product['category'] ?? '') : '';
  $cat = strtolower(trim($cat));
  if ($cat === 'equipment') {
    return ['Black', 'Blue', 'Pink'];
  }
  if ($cat === 'supplements') {
    return ['250mg', '500mg', '1000mg'];
  }
  if ($cat === 'snacks') {
    return ['Single', 'Pack of 6', 'Pack of 12'];
  }
  return ['Default'];
}
