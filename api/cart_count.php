<?php
session_start();
$count = 0; if (!empty($_SESSION['cart'])) { foreach ($_SESSION['cart'] as $q) { $count += (int)$q; } }
echo json_encode(['count'=>$count]);
