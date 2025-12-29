<?php
function current_user(){ return $_SESSION['user'] ?? null; }
function require_login(){ if (empty($_SESSION['user'])) { header('Location: /Health&Fitness/index.php?page=login'); exit; } }
