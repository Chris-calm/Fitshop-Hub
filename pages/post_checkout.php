<?php
session_start();
require __DIR__ . '/../includes/db.php';
$products = json_decode(file_get_contents(__DIR__.'/../storage/products.json'), true);
$cart = $_SESSION['cart'] ?? [];
if (!$cart) { header('Location: index.php?page=cart'); exit; }
$name = trim($_POST['name'] ?? '');
$address = trim($_POST['address'] ?? '');
$payment = $_POST['payment'] ?? 'gcash';
$user_id = !empty($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
$total = 0; $order_items=[];
foreach ($cart as $id=>$qty) {
  foreach ($products as $p) { if ($p['id']==$id) { $order_items[]=['product_id'=>$p['id'],'title'=>$p['title'],'qty'=>$qty,'price'=>$p['price']]; $total += $qty*$p['price']; break; } }
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

$_SESSION['cart']=[];
header('Location: index.php?page=order&id='.$order_id);
