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
    $id = (int)$k;
    $qty = (int)$v;
    if ($id > 0 && $qty > 0) {
      $out[$id] = $qty;
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
    return $_SESSION['cart'];
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
    $id = (int)$k;
    $qty = (int)$v;
    if ($id > 0 && $qty > 0) {
      $clean[$id] = $qty;
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
    setcookie('fh_cart', '', [
      'expires' => time() - 3600,
      'path' => '/',
      'secure' => fh_cart_is_https(),
      'httponly' => false,
      'samesite' => 'Lax',
    ]);
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
  foreach ($cart as $q) {
    $count += (int)$q;
  }
  return $count;
}
