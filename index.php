<?php
session_start();
$allowed = [
  'landing' => 'pages/landing.php',
  'health' => 'pages/health.php',
  'fitness' => 'pages/fitness.php',
  'catalog' => 'pages/catalog.php',
  'product' => 'pages/product.php',
  'cart' => 'pages/cart.php',
  'checkout' => 'pages/checkout.php',
  'order' => 'pages/order.php',
  'login' => 'pages/login.php',
  'register' => 'pages/register.php',
  'profile' => 'pages/profile.php',
  'logout' => 'pages/logout.php',
  'choreography' => 'pages/fitness_choreo.php',
  'guides' => 'pages/fitness_guides.php',
  'gym' => 'pages/fitness_gym.php'
];
$page = $_GET['page'] ?? 'landing';
if (!isset($allowed[$page])) { $page = 'landing'; }
require __DIR__ . '/includes/header.php';
require __DIR__ . '/' . $allowed[$page];
require __DIR__ . '/includes/footer.php';
