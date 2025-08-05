<?php

require_once 'vendor/autoload.php';

// Load Laravel app
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing middleware Unicode support...\n\n";

// Simulate middleware behavior
$response = new \Illuminate\Http\JsonResponse([
  'message' => 'Dokument DM został utworzony pomyślnie',
  'status' => 'success',
  'polish_chars' => 'ąćęłńóśźż'
]);

echo "=== Before Middleware ===\n";
echo "Content: " . $response->getContent() . "\n\n";

// Apply middleware logic
$data = $response->getData();
$response->setData($data);
$encodingOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
$response->setEncodingOptions($encodingOptions);

echo "=== After Middleware ===\n";
echo "Content: " . $response->getContent() . "\n\n";

echo "Testing completed.\n";
