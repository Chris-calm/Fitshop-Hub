<?php
require_once __DIR__ . '/../includes/env.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED);

$provider = strtolower(trim(getenv('NUTRITION_PROVIDER') ?: ''));
$apiKey = getenv('OPENAI_API_KEY') ?: (getenv('OPENAI_API_KEY') ?: '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

if (!isset($_FILES['photo'])) {
  http_response_code(400);
  echo json_encode(['error' => 'No image uploaded']);
  exit;
}

$uploadErr = (int)($_FILES['photo']['error'] ?? UPLOAD_ERR_OK);
if ($uploadErr !== UPLOAD_ERR_OK) {
  $msg = 'Upload failed.';
  if ($uploadErr === UPLOAD_ERR_INI_SIZE || $uploadErr === UPLOAD_ERR_FORM_SIZE) $msg = 'Image is too large.';
  if ($uploadErr === UPLOAD_ERR_PARTIAL) $msg = 'Image upload incomplete.';
  if ($uploadErr === UPLOAD_ERR_NO_FILE) $msg = 'No image uploaded.';
  if ($uploadErr === UPLOAD_ERR_NO_TMP_DIR) $msg = 'Server missing temp folder.';
  if ($uploadErr === UPLOAD_ERR_CANT_WRITE) $msg = 'Server failed to write upload.';
  if ($uploadErr === UPLOAD_ERR_EXTENSION) $msg = 'Upload blocked by server extension.';
  http_response_code(400);
  echo json_encode(['error' => $msg, 'code' => $uploadErr]);
  exit;
}

if (empty($_FILES['photo']['tmp_name']) || !is_uploaded_file($_FILES['photo']['tmp_name'])) {
  http_response_code(400);
  echo json_encode(['error' => 'No image uploaded']);
  exit;
}

if ($provider !== 'openai') {
    http_response_code(501);
    echo json_encode(['error' => 'Nutrition auto-detect not configured for this provider.', 'hint' => 'Set NUTRITION_PROVIDER=openai']);
    exit;
}

if (!$apiKey) {
    http_response_code(501);
    echo json_encode(['error' => 'API key for OpenAI is missing.', 'hint' => 'Set OPENAI_API_KEY in your environment variables.']);
    exit;
}

// OpenAI Vision implementation
try {
    $imagePath = $_FILES['photo']['tmp_name'];
    $imageMime = mime_content_type($imagePath) ?: ($_FILES['photo']['type'] ?? 'application/octet-stream');

    $maxBytes = (int)(getenv('OPENAI_IMAGE_MAX_BYTES') ?: (2 * 1024 * 1024));
    $imageData = file_get_contents($imagePath);
    if ($imageData === false) {
        throw new Exception('Failed to read uploaded image.');
    }

    if (strlen($imageData) > $maxBytes) {
        if (function_exists('imagecreatefromstring')) {
            $src = @imagecreatefromstring($imageData);
            if ($src !== false) {
                $w = imagesx($src);
                $h = imagesy($src);
                $maxDim = (int)(getenv('OPENAI_IMAGE_MAX_DIM') ?: 1280);
                $scale = min(1.0, $maxDim / max($w, $h));
                $nw = max(1, (int)round($w * $scale));
                $nh = max(1, (int)round($h * $scale));
                $dst = imagecreatetruecolor($nw, $nh);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                ob_start();
                $quality = (int)(getenv('OPENAI_IMAGE_JPEG_QUALITY') ?: 75);
                imagejpeg($dst, null, $quality);
                $jpeg = ob_get_clean();
                if ($jpeg !== false && strlen($jpeg) > 0) {
                    $imageData = $jpeg;
                    $imageMime = 'image/jpeg';
                }
            }
        }
    }

    $base64Image = base64_encode($imageData);

    $model = getenv('OPENAI_MODEL') ?: 'gpt-4o';
    $maxTokens = (int)(getenv('OPENAI_MAX_TOKENS') ?: 300);

    $payload = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Extract nutrition facts AND ingredients from the product/package in this image. Return ONLY a single JSON object with these keys: "description" (string), "serving_size" (string or empty), "ingredients" (string or empty; the full ingredients list as seen), "calories" (number), "protein_g" (number), "carbs_g" (number), "fat_g" (number). If a value is not visible, set it to 0 for numbers and empty string for strings. Do not include any extra text or markdown outside the JSON object.'
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => "data:{$imageMime};base64,{$base64Image}"
                        ]
                    ]
                ]
            ]
        ],
        'max_tokens' => $maxTokens
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int)(getenv('OPENAI_CONNECT_TIMEOUT') ?: 15));
    curl_setopt($ch, CURLOPT_TIMEOUT, (int)(getenv('OPENAI_TIMEOUT') ?: 60));
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: FitshopHub/1.0 (+https://fitshop-hub.vercel.app)',
        'Authorization: Bearer ' . $apiKey
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        throw new Exception('Failed to communicate with OpenAI API (cURL ' . $errno . '): ' . $err);
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode !== 200) {
        $msg = '';
        $decodedErr = json_decode($response, true);
        if (is_array($decodedErr)) {
            $msg = (string)($decodedErr['error']['message'] ?? $decodedErr['message'] ?? '');
        }
        $detail = $msg ? (' Message: ' . $msg) : '';
        throw new Exception('Failed to communicate with OpenAI API. HTTP status: ' . $httpCode . '.' . $detail . ' Response: ' . $response);
    }

    $result = json_decode($response, true);
    $content = $result['choices'][0]['message']['content'] ?? '';

    // Extract JSON from the content, which might be wrapped in markdown
    preg_match('/\{.*\}/s', $content, $matches);
    if (empty($matches[0])) {
        throw new Exception('No valid JSON found in OpenAI response.');
    }

    $nutritionJson = json_decode($matches[0], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Failed to decode nutrition JSON from OpenAI response.');
    }

    if (!is_array($nutritionJson)) {
        throw new Exception('Nutrition JSON is not an object.');
    }

    $out = [
        'description' => (string)($nutritionJson['description'] ?? ''),
        'serving_size' => (string)($nutritionJson['serving_size'] ?? ''),
        'ingredients' => (string)($nutritionJson['ingredients'] ?? ''),
        'calories' => (float)($nutritionJson['calories'] ?? 0),
        'protein_g' => (float)($nutritionJson['protein_g'] ?? 0),
        'carbs_g' => (float)($nutritionJson['carbs_g'] ?? 0),
        'fat_g' => (float)($nutritionJson['fat_g'] ?? 0),
    ];

    echo json_encode($out);


} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred during nutrition analysis.', 'details' => $e->getMessage()]);
}
