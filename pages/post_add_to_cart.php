<?php
session_start();
$products = json_decode(file_get_contents(__DIR__.'/../storage/products.json'), true);
$id = (int)($_POST['id'] ?? 0);
$qty = max(1, (int)($_POST['qty'] ?? 1));
$found = null; foreach ($products as $p) { if ($p['id']===$id) { $found=$p; break; } }
if ($found) {
  $_SESSION['cart'] = $_SESSION['cart'] ?? [];
  $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + $qty;
}
header('Location: /Health&Fitness/index.php?page=cart');
