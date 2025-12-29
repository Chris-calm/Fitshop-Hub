<?php
$config = require __DIR__ . '/../config/config.php';
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['DB_HOST'], $config['DB_NAME'], $config['DB_CHARSET']);
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
  $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS'], $options);
} catch (Throwable $e) {
  http_response_code(500);
  echo '<pre>Database connection failed. Check config/config.php and create the database.\n' . htmlspecialchars($e->getMessage()) . '</pre>';
  exit;
}
