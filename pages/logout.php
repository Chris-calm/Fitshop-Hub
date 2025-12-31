<?php
require __DIR__ . '/../includes/session.php';
fh_boot_session();
session_unset();
session_destroy();
header('Location: index.php');
exit;
