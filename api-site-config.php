<?php

/**
 * TODO: Remove this file - kept for compatibility
 * Standalone API for website configuration management
 * Compatible with existing editor frontend
 * This file should be placed in the WordPress root directory
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Headers pentru CORS și JSON
header('Content-Type: application/json; charset=utf-8');

// CORS headers - PERMITE TOATE ORIGIN-URILE PENTRU TESTARE
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';

// Log pentru debugging CORS
error_log("CORS Debug - Origin: " . $origin . ", Host: " . $host);

// Permite toate origin-urile pentru testare
header('Access-Control-Allow-Origin: *');
error_log("CORS - Permis pentru toate origin-urile (testare): " . $origin);

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Origin');
header('Access-Control-Allow-Credentials: true');

// Răspunde la preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include WordPress
require_once('wp-config.php');
require_once(ABSPATH . 'wp-includes/wp-db.php');
require_once(ABSPATH . 'wp-admin/includes/plugin.php');

// Include plugin classes
$plugin_dir = WP_PLUGIN_DIR . '/ai-web-site/';
if (file_exists($plugin_dir . 'includes/class-debug-logger.php')) {
    require_once($plugin_dir . 'includes/class-debug-logger.php');
    require_once($plugin_dir . 'includes/class-website-manager.php');
}

// Funcție pentru logging
function logRequest($message, $data = null)
{
    $timestamp = date('Y-m-d H:i:s');
    $logData = [
        'timestamp' => $timestamp,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'message' => $message
    ];

    if ($data) {
        $logData['data'] = $data;
    }

    error_log(json_encode($logData) . "\n", 3, 'api-requests.log');
}

try {
    // Initialize website manager
    if (class_exists('AI_Web_Site_Website_Manager')) {
        $website_manager = AI_Web_Site_Website_Manager::get_instance();
    } else {
        throw new Exception('Website Manager class not available');
    }

    // Determină operația bazată pe metoda HTTP și URL
    $requestMethod = $_SERVER['REQUEST_METHOD'];
    $requestUri = $_SERVER['REQUEST_URI'];
    $pathInfo = parse_url($requestUri, PHP_URL_PATH);

    // Extrage domeniul din URL dacă este prezent
    $pathParts = explode('/', trim($pathInfo, '/'));
    $domain = null;

    if (count($pathParts) >= 2 && $pathParts[0] === 'site-config') {
        $domain = $pathParts[1];
    }

    switch ($requestMethod) {
        case 'GET':
            // Încarcă configurația
            if ($domain) {
                // Get specific domain config
                $config = $website_manager->get_website_config($domain);
            } else {
                // Get latest config
                $config = $website_manager->get_website_config();
            }

            if ($config === null) {
                logRequest('ERROR: Configuration not found', ['domain' => $domain]);
                http_response_code(404);
                echo json_encode([
                    'error' => 'Configuration not found',
                    'message' => 'No configuration found for the specified domain',
                    'timestamp' => date('c')
                ]);
                exit();
            }

            logRequest('SUCCESS: Configuration loaded', ['domain' => $domain, 'size' => strlen(json_encode($config))]);

            // Returnează configurația
            http_response_code(200);
            echo json_encode($config);
            break;

        case 'POST':
            // ETAPA 1: Verificări de securitate
            // 1. Verificare utilizator logat
            if (!is_user_logged_in()) {
                logRequest('ERROR: Unauthenticated POST request');
                http_response_code(401);
                echo json_encode([
                    'error' => 'Authentication required',
                    'message' => 'User must be logged in to save configuration',
                    'timestamp' => date('c')
                ]);
                exit();
            }

            // 2. Verificare nonce pentru protecție CSRF
            $headers = getallheaders();
            $nonce = $headers['X-WP-Nonce'] ?? $headers['x-wp-nonce'] ?? '';

            if (empty($nonce) || !wp_verify_nonce($nonce, 'save_site_config')) {
                logRequest('ERROR: Invalid or missing nonce', [
                    'user_id' => get_current_user_id(),
                    'nonce_received' => !empty($nonce) ? 'yes' : 'no'
                ]);
                http_response_code(403);
                echo json_encode([
                    'error' => 'Invalid security token',
                    'message' => 'Security verification failed. Please refresh the page and try again.',
                    'timestamp' => date('c')
                ]);
                exit();
            }

            logRequest('SUCCESS: Security checks passed', [
                'user_id' => get_current_user_id(),
                'user_login' => wp_get_current_user()->user_login
            ]);

            // Salvează configurația
            $input = file_get_contents('php://input');
            $inputData = json_decode($input, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                logRequest('ERROR: Invalid JSON in POST request', ['json_error' => json_last_error_msg()]);
                http_response_code(400);
                echo json_encode([
                    'error' => 'Invalid JSON in request',
                    'details' => json_last_error_msg(),
                    'timestamp' => date('c')
                ]);
                exit();
            }

            // Verifică dacă avem datele necesare
            if (!isset($inputData['config'])) {
                logRequest('ERROR: Missing config in POST request');
                http_response_code(400);
                echo json_encode([
                    'error' => 'Missing configuration data',
                    'message' => 'Expected "config" field in request body',
                    'timestamp' => date('c')
                ]);
                exit();
            }

            try {
                // Salvează configurația în baza de date
                $result = $website_manager->save_website_config($inputData);

                if ($result['success']) {
                    logRequest('SUCCESS: Configuration saved to database', [
                        'domain' => $inputData['domain'] ?? 'unknown',
                        'website_id' => $result['website_id'],
                        'size' => strlen(json_encode($inputData['config']))
                    ]);

                    http_response_code(200);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Configuration saved successfully',
                        'website_id' => $result['website_id'],
                        'timestamp' => date('c')
                    ]);
                } else {
                    logRequest('ERROR: Failed to save configuration', ['error' => $result['error']]);
                    http_response_code(400);
                    echo json_encode([
                        'error' => $result['error'],
                        'message' => $result['message'],
                        'timestamp' => date('c')
                    ]);
                }
            } catch (Exception $e) {
                logRequest('ERROR: Exception during save', ['exception' => $e->getMessage()]);
                http_response_code(500);
                echo json_encode([
                    'error' => 'Failed to save configuration',
                    'message' => $e->getMessage(),
                    'timestamp' => date('c')
                ]);
            }
            break;

        default:
            logRequest('ERROR: Unsupported HTTP method', ['method' => $requestMethod]);
            http_response_code(405);
            echo json_encode([
                'error' => 'Method not allowed',
                'allowed_methods' => ['GET', 'POST'],
                'timestamp' => date('c')
            ]);
            break;
    }

} catch (Exception $e) {
    logRequest('FATAL ERROR: Exception caught', ['exception' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => 'An unexpected error occurred',
        'timestamp' => date('c'),
        'debug' => $e->getMessage() // Elimină în producție
    ]);
}
