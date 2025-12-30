<?php
// Simple nutrition detection stub. Integrate a real provider later.
require __DIR__ . '/../includes/db.php'; // loads $config, db not required but config is
header('Content-Type: application/json');
header('Cache-Control: no-store');

$provider = $config['NUTRITION_PROVIDER'] ?? '';
$apiKey = $config['NUTRITION_API_KEY'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

if (empty($_FILES['photo']['tmp_name']) || !is_uploaded_file($_FILES['photo']['tmp_name'])) {
  http_response_code(400);
  echo json_encode(['error' => 'No image uploaded']);
  exit;
}

// Demo provider returns deterministic fake values for now
if ($provider === 'demo') {
  echo json_encode([
    'calories' => 520,
    'protein_g' => 32.5,
    'carbs_g' => 45.0,
    'fat_g' => 22.0
  ]);
  exit;
}

// If no provider configured, return 501 with instructions
if (!$provider || !$apiKey) {
  http_response_code(501);
  echo json_encode(['error' => 'Nutrition auto-detect not configured', 'hint' => 'Set NUTRITION_PROVIDER and NUTRITION_API_KEY in config/config.php or use demo provider.']);
  exit;
}

// Placeholder for real providers (e.g., Nutritionix Vision, Edamam, etc.)
http_response_code(501);
echo json_encode(['error' => 'Provider not implemented yet: '.$provider]);
