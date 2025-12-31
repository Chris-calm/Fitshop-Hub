<?php
session_start();
require_once __DIR__ . '/../includes/auth_cookie.php';
session_unset();
session_destroy();
fh_clear_auth_cookie();
header('Location: index.php?page=login');
exit;
