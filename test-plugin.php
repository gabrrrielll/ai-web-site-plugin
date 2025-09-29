<?php

/**
 * Test script for AI Web Site plugin
 * Run this to test the plugin locally and generate logs
 */

// Simulate WordPress environment
define('ABSPATH', dirname(__FILE__) . '/');

// Mock WordPress functions
function wp_remote_post($url, $args = array())
{
    return array(
        'body' => json_encode(array(
            'status' => 1,
            'result' => 'Subdomain created successfully'
        )),
        'response' => array('code' => 200)
    );
}

function wp_remote_get($url, $args = array())
{
    return array(
        'body' => json_encode(array(
            'status' => 1,
            'result' => 'Connection successful'
        )),
        'response' => array('code' => 200)
    );
}

function wp_remote_retrieve_body($response)
{
    return $response['body'];
}

function wp_remote_retrieve_response_code($response)
{
    return $response['response']['code'];
}

function is_wp_error($response)
{
    return false;
}

function get_option($option, $default = false)
{
    if ($option === 'ai_web_site_options') {
        return array(
            'cpanel_username' => 'r48312maga',
            'cpanel_api_token' => 'JACSKFOEX1D40JJL8UFY28ADKUXA3M9G',
            'main_domain' => 'ai-web.site'
        );
    }
    return $default;
}

function sanitize_text_field($str)
{
    return trim(strip_tags($str));
}

// Include plugin files
require_once 'includes/class-debug-logger.php';
require_once 'includes/class-cpanel-api.php';

echo "🧪 Testing AI Web Site Plugin\n";
echo "=============================\n\n";

// Test 1: Initialize logger
echo "1. Testing Debug Logger...\n";
$logger = AI_Web_Site_Debug_Logger::get_instance();
$logger->create_table();
$logger->info('TEST', 'INIT', 'Test script started');
echo "✅ Logger initialized\n\n";

// Test 2: Test cPanel API configuration
echo "2. Testing cPanel API Configuration...\n";
$cpanel_api = AI_Web_Site_CPanel_API::get_instance();
echo "✅ cPanel API initialized\n\n";

// Test 3: Test connection
echo "3. Testing cPanel Connection...\n";
$result = $cpanel_api->test_connection();
if ($result['success']) {
    echo "✅ Connection test successful: " . $result['message'] . "\n\n";
} else {
    echo "❌ Connection test failed: " . $result['message'] . "\n\n";
}

// Test 4: Test subdomain creation
echo "4. Testing Subdomain Creation...\n";
$result = $cpanel_api->create_subdomain('test-subdomain', 'ai-web.site');
if ($result['success']) {
    echo "✅ Subdomain creation successful: " . $result['message'] . "\n\n";
} else {
    echo "❌ Subdomain creation failed: " . $result['message'] . "\n\n";
}

// Test 5: Get logs
echo "5. Getting Recent Logs...\n";
$logs = $logger->get_logs_json(10);
echo "📋 Found " . count($logs) . " log entries:\n";
foreach ($logs as $log) {
    $timestamp = date('Y-m-d H:i:s', strtotime($log['timestamp']));
    echo "  [{$log['level']}] {$timestamp} - {$log['component']}::{$log['action']} - {$log['message']}\n";
    if ($log['data']) {
        echo "    Data: " . json_encode($log['data']) . "\n";
    }
}

echo "\n🎉 Test completed!\n";
