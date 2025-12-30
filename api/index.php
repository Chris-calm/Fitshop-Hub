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
  'gym' => 'pages/fitness_gym.php',
  'choreo_detail' => 'pages/fitness_choreo_detail.php',
  'guide_detail' => 'pages/fitness_guides_detail.php',
  'gym_detail' => 'pages/fitness_gym_detail.php',
  'gym_session' => 'pages/fitness_gym_session.php',
  'gym_summary' => 'pages/fitness_gym_summary.php',
  'choreo_session' => 'pages/fitness_choreo_session.php',
  'choreo_summary' => 'pages/fitness_choreo_summary.php',
  'guide_session' => 'pages/fitness_guides_session.php',
  'guide_summary' => 'pages/fitness_guides_summary.php',
  'food_scan' => 'pages/food_scan.php',
  'food_history' => 'pages/food_history.php',
  'fitness_history' => 'pages/fitness_history.php'
];
$page = $_GET['page'] ?? 'landing';
if (!isset($allowed[$page])) { $page = 'landing'; }
require __DIR__ . '/includes/header.php';
require __DIR__ . '/' . $allowed[$page];
require __DIR__ . '/includes/footer.php';
