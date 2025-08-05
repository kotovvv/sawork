<?php

require_once 'vendor/autoload.php';

// Load Laravel app
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing Unicode support...\n\n";

// Test 1: Standard response()->json() (should use AppServiceProvider macro)
echo "=== Test 1: response()->json() ===\n";
$response = response()->json([
  'message' => 'Dokument DM został utworzony pomyślnie',
  'status' => 'success',
  'polish_chars' => 'ąćęłńóśźż'
]);

echo "Content: " . $response->getContent() . "\n\n";

// Test 2: Direct JsonResponse (without macro)
echo "=== Test 2: Direct JsonResponse (without Unicode flags) ===\n";
$directResponse = new \Illuminate\Http\JsonResponse([
  'message' => 'Dokument DM został utworzony pomyślnie',
  'status' => 'success',
  'polish_chars' => 'ąćęłńóśźż'
]);

echo "Content: " . $directResponse->getContent() . "\n\n";

// Test 3: Direct JsonResponse with Unicode flags
echo "=== Test 3: Direct JsonResponse (with Unicode flags) ===\n";
$unicodeResponse = new \Illuminate\Http\JsonResponse([
  'message' => 'Dokument DM został utworzony pomyślnie',
  'status' => 'success',
  'polish_chars' => 'ąćęłńóśźż'
], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

echo "Content: " . $unicodeResponse->getContent() . "\n\n";

echo "Testing completed.\n";
