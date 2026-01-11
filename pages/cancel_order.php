<?php
require __DIR__ . '/../includes/auth.php';
require_login();
require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/cart_store.php';

$userId = !empty($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
$orderId = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));

if ($userId <= 0 || $orderId <= 0) {
  header('Location: index.php?page=profile');
  exit;
}

try {
  $pdo->beginTransaction();

  $oStmt = $pdo->prepare('SELECT id, user_id FROM orders WHERE id=? LIMIT 1');
  $oStmt->execute([$orderId]);
  $order = $oStmt->fetch();
  if (!$order || (int)($order['user_id'] ?? 0) !== $userId) {
    $pdo->rollBack();
    header('Location: index.php?page=profile');
    exit;
  }

  $sStmt = $pdo->prepare('SELECT current_status, history FROM shipments WHERE order_id=? LIMIT 1');
  $sStmt->execute([$orderId]);
  $ship = $sStmt->fetch();
  if (!$ship) {
    $pdo->rollBack();
    header('Location: index.php?page=order&id=' . $orderId);
    exit;
  }

  $currentStatus = (string)($ship['current_status'] ?? '');
  $notCancellable = ['Shipped', 'Out for Delivery', 'Delivered', 'Cancelled'];
  if (in_array($currentStatus, $notCancellable, true)) {
    $pdo->rollBack();
    header('Location: index.php?page=order&id=' . $orderId);
    exit;
  }

  $itemsStmt = $pdo->prepare('SELECT product_id, title, qty FROM order_items WHERE order_id=? ORDER BY id ASC');
  $itemsStmt->execute([$orderId]);
  $items = $itemsStmt->fetchAll();

  $history = json_decode((string)($ship['history'] ?? ''), true);
  if (!is_array($history)) {
    $history = [];
  }
  $history[] = ['status' => 'Cancelled', 'time' => date('c')];
  $pdo->prepare('UPDATE shipments SET current_status=?, history=? WHERE order_id=?')
    ->execute(['Cancelled', json_encode($history), $orderId]);

  $pdo->commit();

  // Restore to cart after transaction
  $cart = fh_cart_get();
  if (!is_array($cart)) {
    $cart = [];
  }

  foreach ($items as $it) {
    $pid = (int)($it['product_id'] ?? 0);
    $qty = (int)($it['qty'] ?? 0);
    if ($pid <= 0 || $qty <= 0) {
      continue;
    }

    $title = (string)($it['title'] ?? '');
    $opt = 'Default';
    if ($title !== '' && strpos($title, ' • ') !== false) {
      $parts = explode(' • ', $title);
      $maybeOpt = trim((string)end($parts));
      if ($maybeOpt !== '') {
        $opt = $maybeOpt;
      }
    }

    $key = fh_cart_make_key($pid, $opt);
    if ($key === '') {
      continue;
    }
    $cart[$key] = (int)($cart[$key] ?? 0) + $qty;
  }

  fh_cart_write($cart);
  header('Location: index.php?page=cart');
  exit;
} catch (Throwable $e) {
  try { $pdo->rollBack(); } catch (Throwable $e2) {}
  error_log('Cancel order failed: ' . $e->getMessage());
  header('Location: index.php?page=order&id=' . $orderId);
  exit;
}
