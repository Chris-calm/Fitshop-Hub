<?php
session_start();
$count = 0; if (!empty($_SESSION['cart'])) { foreach ($_SESSION['cart'] as $q) { $count += (int)$q; } }
header('Content-Type: application/json');
header('Cache-Control: no-store');
echo json_encode(['count'=>$count]);
