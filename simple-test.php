<?php

/**
 * Simple test for cPanel API functionality
 */

echo "🧪 Simple cPanel API Test\n";
echo "========================\n\n";

// Configuration
$config = array(
    'username' => 'r48312maga',
    'api_token' => 'JACSKFOEX1D40JJL8UFY28ADKUXA3M9G',
    'main_domain' => 'ai-web.site',
    'host' => 'ai-web.site'
);

echo "📋 Configuration:\n";
echo "  Username: {$config['username']}\n";
echo "  Host: {$config['host']}\n";
echo "  Main Domain: {$config['main_domain']}\n";
echo "  API Token Length: " . strlen($config['api_token']) . " chars\n\n";

// Test 1: Test connection
echo "1. Testing cPanel Connection...\n";
$api_url = "https://{$config['host']}:2083/execute/StatsBar/get_stats";

echo "  API URL: {$api_url}\n";
echo "  Authorization: cpanel {$config['username']}:{$config['api_token']}\n";

// Make request using curl
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: cpanel ' . $config['username'] . ':' . $config['api_token']
));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ cURL Error: {$error}\n\n";
} else {
    echo "✅ HTTP Response Code: {$http_code}\n";
    echo "📄 Response Body: {$response}\n\n";
}

// Test 2: Test subdomain creation
echo "2. Testing Subdomain Creation...\n";
$api_url = "https://{$config['host']}:2083/execute/SubDomain/addsubdomain";
$params = array(
    'domain' => 'test-subdomain',
    'rootdomain' => $config['main_domain'],
    'dir' => '/editor.ai-web.site',
    'disallowdot' => 0
);

echo "  API URL: {$api_url}\n";
echo "  Params: " . json_encode($params) . "\n";

// Make POST request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: cpanel ' . $config['username'] . ':' . $config['api_token']
));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ cURL Error: {$error}\n\n";
} else {
    echo "✅ HTTP Response Code: {$http_code}\n";
    echo "📄 Response Body: {$response}\n";

    $result = json_decode($response, true);
    if ($result && isset($result['status'])) {
        if ($result['status'] === 1) {
            echo "✅ Subdomain creation successful!\n\n";
        } else {
            echo "❌ Subdomain creation failed!\n";
            if (isset($result['errors'])) {
                echo "   Errors: " . implode(', ', $result['errors']) . "\n\n";
            }
        }
    } else {
        echo "⚠️  Unexpected response format\n\n";
    }
}

echo "🎉 Test completed!\n";
