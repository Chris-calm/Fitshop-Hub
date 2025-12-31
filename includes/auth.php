<?php
function current_user(){ return $_SESSION['user'] ?? null; }
function require_login(){
  if (empty($_SESSION['user'])) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if ($uri) {
      $_SESSION['after_login'] = $uri;
    }
    header('Location: index.php?page=login');
    exit;
  }
}
