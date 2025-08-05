<?php

// Test script to check API keys configuration
// Run this in terminal: php test_api_keys.php

require_once 'vendor/autoload.php';

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Load API environment if exists
if (file_exists('.env.api')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '.env.api');
    $dotenv->safeLoad();
}

echo "Testing API Keys Configuration:\n\n";

// Test API keys from .env
$apiKeys = [
    'API_KEY_1' => env('API_KEY_1'),
    'API_KEY_2' => env('API_KEY_2'),
    'API_KEY_3' => env('API_KEY_3'),
];

$warehouses = [
    'API_KEY_1_WAREHOUSE' => env('API_KEY_1_WAREHOUSE'),
    'API_KEY_2_WAREHOUSE' => env('API_KEY_2_WAREHOUSE'),
    'API_KEY_3_WAREHOUSE' => env('API_KEY_3_WAREHOUSE'),
];

foreach ($apiKeys as $key => $value) {
    $warehouseKey = $key . '_WAREHOUSE';
    $warehouse = $warehouses[$warehouseKey] ?? 'Not set';

    echo "🔑 {$key}: " . ($value ? $value : 'Not set') . "\n";
    echo "🏪 {$warehouseKey}: {$warehouse}\n\n";
}

// Test config array
echo "Config array format:\n";
$configArray = [];
foreach ($apiKeys as $key => $value) {
    if ($value) {
        $warehouseKey = $key . '_WAREHOUSE';
        $warehouse = $warehouses[$warehouseKey] ?? 1;
        $configArray[$value] = (int)$warehouse;
    }
}

print_r($configArray);

echo "\nExample cURL command:\n";
$firstKey = array_key_first($configArray);
if ($firstKey) {
    echo "curl -X POST \"http://your-domain.com/api/dm/create\" \\\n";
    echo "  -H \"Content-Type: application/json\" \\\n";
    echo "  -H \"X-API-Key: {$firstKey}\" \\\n";
    echo "  -d '{\n";
    echo "    \"products\": [\n";
    echo "      {\n";
    echo "        \"Nazwa\": \"Test Product\",\n";
    echo "        \"EAN\": \"1234567890123\",\n";
    echo "        \"jednostka\": \"towar\",\n";
    echo "        \"Ilość\": 10,\n";
    echo "        \"Cena\": 25.50\n";
    echo "      }\n";
    echo "    ]\n";
    echo "  }'\n";
}
