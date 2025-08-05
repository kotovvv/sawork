<?php
// Simple test to check if API keys are working
// Place this file in public folder and access via browser: http://fulstor.test/test_config.php

require_once '../vendor/autoload.php';

// Initialize Laravel app
$app = require_once '../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

echo "<h2>API Keys Configuration Test</h2>";

// Test environment variables
echo "<h3>Environment Variables:</h3>";
echo "API_KEY_1: " . (env('API_KEY_1') ?: 'NOT SET') . "<br>";
echo "API_KEY_1_WAREHOUSE: " . (env('API_KEY_1_WAREHOUSE') ?: 'NOT SET') . "<br>";
echo "API_KEY_2: " . (env('API_KEY_2') ?: 'NOT SET') . "<br>";
echo "API_KEY_2_WAREHOUSE: " . (env('API_KEY_2_WAREHOUSE') ?: 'NOT SET') . "<br>";
echo "API_KEY_3: " . (env('API_KEY_3') ?: 'NOT SET') . "<br>";
echo "API_KEY_3_WAREHOUSE: " . (env('API_KEY_3_WAREHOUSE') ?: 'NOT SET') . "<br>";

// Test config
echo "<h3>Config Array:</h3>";
$apiKeys = config('app.api_keys', []);
echo "<pre>";
print_r($apiKeys);
echo "</pre>";

// Test API endpoint
echo "<h3>Test API Endpoint:</h3>";
$firstKey = array_key_first($apiKeys);
if ($firstKey) {
    echo "Test with key: <strong>{$firstKey}</strong><br>";
    echo "Expected warehouse: <strong>{$apiKeys[$firstKey]}</strong><br><br>";

    echo "cURL command to test:<br>";
    echo "<code>";
    echo "curl -X POST \"http://fulstor.test/api/dm/create\" \\<br>";
    echo "&nbsp;&nbsp;-H \"Content-Type: application/json\" \\<br>";
    echo "&nbsp;&nbsp;-H \"X-API-Key: {$firstKey}\" \\<br>";
    echo "&nbsp;&nbsp;-d '{<br>";
    echo "&nbsp;&nbsp;&nbsp;&nbsp;\"products\": [{<br>";
    echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\"Nazwa\": \"Test Product\",<br>";
    echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\"EAN\": \"1234567890123\",<br>";
    echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\"jednostka\": \"towar\",<br>";
    echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\"Ilość\": 1,<br>";
    echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\"Cena\": 10.00<br>";
    echo "&nbsp;&nbsp;&nbsp;&nbsp;}]<br>";
    echo "&nbsp;&nbsp;}'";
    echo "</code>";
} else {
    echo "<span style='color: red;'>❌ No API keys configured!</span>";
}
