<?php
require __DIR__ . '/../includes/session.php';
fh_boot_session();
$count = 0; if (!empty($_SESSION['cart'])) { foreach ($_SESSION['cart'] as $q) { $count += (int)$q; } }
echo json_encode(['count'=>$count]);
