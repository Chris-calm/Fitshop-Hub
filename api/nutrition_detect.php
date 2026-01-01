<?php
require_once __DIR__ . '/../includes/env.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

$provider = getenv('NUTRITION_PROVIDER') ?: '';
$apiKey = getenv('OPENAI_API_KEY') ?: ''; // Use OpenAI key

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
    $imageData = file_get_contents($imagePath);
    $base64Image = base64_encode($imageData);
    $imageMime = $_FILES['photo']['type'];

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
                        'text' => 'Analyze the food item in this image. Provide a nutrition estimate in JSON format. The JSON object should only contain these keys: "calories" (number), "protein_g" (number), "carbs_g" (number), "fat_g" (number), and "description" (string, brief name of the food). Do not include any extra text or markdown formatting outside the JSON object itself.'
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
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode !== 200) {
        throw new Exception('Failed to communicate with OpenAI API. HTTP status: ' . $httpCode . ' Response: ' . $response);
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

    echo json_encode($nutritionJson);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred during nutrition analysis.', 'details' => $e->getMessage()]);
}
