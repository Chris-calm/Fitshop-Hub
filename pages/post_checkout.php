<?php
require __DIR__ . '/../includes/auth.php';
require_login();
require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/cart_store.php';
$products = json_decode(file_get_contents(__DIR__.'/../storage/products.json'), true);
$cart = fh_cart_get();
if (!$cart) { header('Location: index.php?page=cart'); exit; }
$payment = $_POST['payment'] ?? 'gcash';
$user_id = !empty($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
$addressId = isset($_POST['address_id']) ? (int)$_POST['address_id'] : 0;

if ($user_id <= 0) {
  header('Location: index.php?page=login');
  exit;
}

if ($addressId <= 0) {
  header('Location: index.php?page=checkout');
  exit;
}

$aStmt = $pdo->prepare('SELECT full_name, phone, line1, line2, city, province, postal_code FROM user_addresses WHERE id=? AND user_id=? LIMIT 1');
$aStmt->execute([$addressId, $user_id]);
$addr = $aStmt->fetch();
if (!$addr) {
  header('Location: index.php?page=checkout');
  exit;
}

$name = trim((string)($addr['full_name'] ?? ''));
$address = trim((string)($addr['phone'] ?? ''));
$address .= "\n" . trim((string)($addr['line1'] ?? ''));
if (!empty($addr['line2'])) {
  $address .= "\n" . trim((string)($addr['line2'] ?? ''));
}
$address .= "\n" . trim((string)($addr['city'] ?? '')) . ', ' . trim((string)($addr['province'] ?? '')) . ' ' . trim((string)($addr['postal_code'] ?? ''));

if (!in_array($payment, ['gcash','maya'], true)) {
  $payment = 'gcash';
}
$total = 0; $order_items=[];
foreach ($cart as $key => $qty) {
  $parsed = fh_cart_parse_key((string)$key);
  if (!$parsed) {
    continue;
  }
  $id = (int)$parsed['id'];
  $opt = (string)$parsed['option'];
  foreach ($products as $p) {
    if ((int)($p['id'] ?? 0) == $id) {
      $title = (string)($p['title'] ?? '');
      if ($opt !== '' && $opt !== 'Default') {
        $title .= ' • ' . $opt;
      }
      $order_items[] = [
        'product_id' => (int)($p['id'] ?? 0),
        'title' => $title,
        'qty' => (int)$qty,
        'price' => (float)($p['price'] ?? 0),
      ];
      $total += ((int)$qty) * ((float)($p['price'] ?? 0));
      break;
    }
  }
}

// Create order within a transaction
$pdo->beginTransaction();
try {
  if (defined('IS_VERCEL') && IS_VERCEL) {
    $stmt = $pdo->prepare('INSERT INTO orders(user_id,name,address,payment,total) VALUES (?,?,?,?,?) RETURNING id');
    $stmt->execute([$user_id,$name,$address,$payment,$total]);
    $order_id = (int)$stmt->fetchColumn();
  } else {
    $stmt = $pdo->prepare('INSERT INTO orders(user_id,name,address,payment,total) VALUES (?,?,?,?,?)');
    $stmt->execute([$user_id,$name,$address,$payment,$total]);
    $order_id = (int)$pdo->lastInsertId();
  }

  $oi = $pdo->prepare('INSERT INTO order_items(order_id,product_id,title,qty,price) VALUES (?,?,?,?,?)');
  foreach ($order_items as $it) { $oi->execute([$order_id,$it['product_id'],$it['title'],$it['qty'],$it['price']]); }

  // Initial shipment record
  $history = [[ 'status'=>'Order Placed', 'time'=>date('c') ]];
  $tracking = 'FH'.date('ymd').str_pad((string)$order_id,6,'0',STR_PAD_LEFT);
  $sh = $pdo->prepare('INSERT INTO shipments(order_id,carrier,tracking_no,current_status,history) VALUES (?,?,?,?,?)');
  $sh->execute([$order_id,'LocalCourier',$tracking,'Order Placed',json_encode($history)]);

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo '<pre>Checkout failed: '.htmlspecialchars($e->getMessage()).'</pre>';
  exit;
}

fh_cart_write([]);
header('Location: index.php?page=order&id='.$order_id);
