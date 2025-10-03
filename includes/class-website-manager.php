<?php

/**
 * Website Manager Class
 * Handles website configurations storage and management in WordPress database
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AI_Web_Site_Website_Manager
{
    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Database table name
     */
    private $table_name;

    /**
     * Get single instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ai_web_site_websites';
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        add_action('wp_ajax_save_website_config', array($this, 'ajax_save_website_config'));
        add_action('wp_ajax_nopriv_save_website_config', array($this, 'ajax_save_website_config'));
        add_action('wp_ajax_get_website_config', array($this, 'ajax_get_website_config'));
        add_action('wp_ajax_nopriv_get_website_config', array($this, 'ajax_get_website_config'));
        add_action('wp_ajax_delete_website', array($this, 'ajax_delete_website'));

        // REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // Bypass WordPress global nonce verification for our test nonce
        add_filter('rest_authentication_errors', array($this, 'bypass_nonce_for_test'));

        // Debug filter pentru a vedea toate requesturile REST
        add_filter('rest_request_before_callbacks', array($this, 'debug_rest_request'));
    }

    /**
     * Bypass WordPress global nonce verification for our test nonce
     */
    public function bypass_nonce_for_test($errors)
    {
        // Verifică dacă este request pentru endpoint-ul nostru
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/ai-web-site/v1/website-config') !== false) {
            // Verifică dacă este POST request
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Verifică header-ele pentru nonce-ul nostru de test
                $headers = getallheaders();
                $nonce = $headers['X-WP-Nonce'] ?? $headers['x-wp-nonce'] ?? '';

                if ($nonce === 'test-nonce-12345') {
                    error_log('AI-WEB-SITE: ✅ BYPASSING WordPress global nonce verification for test nonce');
                    return null; // Nu returnează eroare = permite requestul
                }
            }
        }

        return $errors; // Returnează erorile normale pentru alte requesturi
    }

    /**
     * Create database table
     */
    public function create_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            subdomain varchar(255) NOT NULL,
            domain varchar(255) NOT NULL,
            config longtext NOT NULL,
            status varchar(50) DEFAULT 'draft',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_subdomain (user_id, subdomain, domain),
            KEY user_id (user_id),
            KEY subdomain (subdomain),
            KEY domain (domain),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Log table creation
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('WEBSITE_MANAGER', 'TABLE_CREATED', 'Website configurations table created');
    }

    /**
     * Debug filter pentru a vedea toate requesturile REST
     */
    public function debug_rest_request($response, $handler = null, $request = null)
    {
        // Verifică dacă avem request-ul disponibil
        if ($request && strpos($request->get_route(), '/ai-web-site/v1/website-config') !== false) {
            error_log('=== AI-WEB-SITE: debug_rest_request() CALLED ===');
            error_log('AI-WEB-SITE: Request method: ' . $request->get_method());
            error_log('AI-WEB-SITE: Request route: ' . $request->get_route());
            if ($handler) {
                error_log('AI-WEB-SITE: Handler: ' . print_r($handler, true));
            }
        }
        
        return $response;
    }

    /**
     * Debug permission callback pentru a vedea dacă este apelat
     */
    public function debug_permission_callback($request)
    {
        error_log('=== AI-WEB-SITE: debug_permission_callback() CALLED ===');
        error_log('AI-WEB-SITE: Request method: ' . $request->get_method());
        error_log('AI-WEB-SITE: Request route: ' . $request->get_route());

        // Apelează funcția originală de verificare
        $result = $this->check_save_permissions($request);

        error_log('AI-WEB-SITE: Permission check result: ' . ($result === true ? 'TRUE' : 'FALSE'));
        if ($result !== true) {
            error_log('AI-WEB-SITE: Permission error: ' . print_r($result, true));
        }

        return $result;
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes()
    {
        // Log REST API registration
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('WEBSITE_MANAGER', 'REST_API', 'Registering REST API routes');

        // TODO: Remove old endpoints - kept for compatibility
        /*
        register_rest_route('ai-web-site/v1', '/website-config', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_website_config'),
            'permission_callback' => '__return_true', // Allow public access for editor
        ));

        register_rest_route('ai-web-site/v1', '/website-config', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_save_website_config'),
            'permission_callback' => '__return_true', // Allow public access for editor
        ));
        */

        register_rest_route('ai-web-site/v1', '/website-config/(?P<domain>[a-zA-Z0-9.-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_website_config_by_domain'),
            'permission_callback' => '__return_true',
        ));

        // POST endpoint pentru salvarea configurației cu verificări de securitate
        register_rest_route('ai-web-site/v1', '/website-config', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_save_website_config'),
            'permission_callback' => array($this, 'debug_permission_callback'), // Adaug loguri pentru debugging
            'args' => array(),
        ));

        // Test endpoint to verify REST API is working
        register_rest_route('ai-web-site/v1', '/test', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_test_endpoint'),
            'permission_callback' => '__return_true',
        ));

        // Debug endpoint to create default editor config
        register_rest_route('ai-web-site/v1', '/create-default-config', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_create_default_config'),
            'permission_callback' => '__return_true',
        ));

        // Debug endpoint to update existing editor config with original content
        register_rest_route('ai-web-site/v1', '/update-editor-config', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_update_editor_config'),
            'permission_callback' => '__return_true',
        ));

        $logger->info('WEBSITE_MANAGER', 'REST_API', 'REST API routes registered successfully');
    }

    /**
     * Check permissions for saving website config (ETAPA 1)
     */
    public function check_save_permissions($request)
    {
        // LOG DETALIAT PENTRU DEBUGGING
        error_log('=== AI-WEB-SITE: check_save_permissions() CALLED ===');
        error_log('AI-WEB-SITE: Request method: ' . $request->get_method());
        error_log('AI-WEB-SITE: Request route: ' . $request->get_route());

        // Pentru requesturi OPTIONS (preflight CORS), returnează true direct
        if ($request->get_method() === 'OPTIONS') {
            error_log('AI-WEB-SITE: OPTIONS request - allowing CORS preflight');
            return true;
        }

        // Log pentru toate requesturile non-OPTIONS
        error_log('AI-WEB-SITE: NON-OPTIONS request - method: ' . $request->get_method());

        error_log('AI-WEB-SITE: User logged in: ' . (is_user_logged_in() ? 'YES' : 'NO'));
        error_log('AI-WEB-SITE: User ID: ' . get_current_user_id());

        // 1. Verificare utilizator logat (pentru localhost, sărim această verificare)
        $headers = getallheaders();
        error_log('AI-WEB-SITE: All headers: ' . print_r($headers, true));

        $nonce = $headers['X-WP-Nonce'] ?? $headers['x-wp-nonce'] ?? '';
        error_log('AI-WEB-SITE: Nonce received: ' . $nonce);

        // Pentru localhost/testare cu nonce de testare, sărim verificarea de autentificare
        if ($nonce === 'test-nonce-12345') {
            // Log pentru debugging
            error_log('AI-WEB-SITE: ✅ TEST NONCE ACCEPTED - Skipping authentication');
            return true;
        }

        if (!is_user_logged_in()) {
            return new WP_Error('not_logged_in', 'Authentication required', array('status' => 401));
        }

        // 2. Verificare nonce pentru protecție CSRF (doar dacă nu folosim nonce de testare)
        error_log('AI-WEB-SITE: Verifying nonce with action: save_site_config');

        if (empty($nonce) || !wp_verify_nonce($nonce, 'save_site_config')) {
            error_log('AI-WEB-SITE: ❌ NONCE VERIFICATION FAILED');
            error_log('AI-WEB-SITE: Nonce empty: ' . (empty($nonce) ? 'YES' : 'NO'));
            if (!empty($nonce)) {
                error_log('AI-WEB-SITE: wp_verify_nonce result: ' . (wp_verify_nonce($nonce, 'save_site_config') ? 'SUCCESS' : 'FAILED'));
            }
            return new WP_Error('invalid_nonce', 'Invalid security token', array('status' => 403));
        }

        error_log('AI-WEB-SITE: ✅ NONCE VERIFICATION SUCCESS');
        error_log('AI-WEB-SITE: ✅ PERMISSION CHECK PASSED');
        return true;
    }

    /**
     * REST API: Save website config (ETAPA 1 - cu verificări de securitate)
     */
    public function rest_save_website_config($request)
    {
        error_log('=== AI-WEB-SITE: rest_save_website_config() CALLED ===');

        $this->set_cors_headers();

        // VERIFICARE MANUALĂ DE SECURITATE
        $security_check = $this->check_save_permissions($request);
        if ($security_check !== true) {
            error_log('AI-WEB-SITE: ❌ SECURITY CHECK FAILED');
            return $security_check;
        }
        error_log('AI-WEB-SITE: ✅ SECURITY CHECK PASSED');

        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('WEBSITE_MANAGER', 'REST_SAVE', 'REST API POST request received', array(
            'user_id' => get_current_user_id(),
            'user_login' => wp_get_current_user()->user_login
        ));

        try {
            $input_data = $request->get_json_params();
            error_log('AI-WEB-SITE: Input data received: ' . print_r($input_data, true));

            if (!$input_data || !isset($input_data['config'])) {
                $logger->error('WEBSITE_MANAGER', 'REST_SAVE', 'Missing config data');
                return new WP_REST_Response(array(
                    'error' => 'Missing configuration data',
                    'message' => 'Expected "config" field in request body',
                    'timestamp' => date('c')
                ), 400);
            }

            // Salvează configurația în baza de date
            $result = $this->save_website_config($input_data);

            if ($result['success']) {
                $logger->info('WEBSITE_MANAGER', 'REST_SAVE', 'Configuration saved successfully', array(
                    'user_id' => get_current_user_id(),
                    'website_id' => $result['website_id'],
                    'domain' => $input_data['domain'] ?? 'unknown'
                ));

                return new WP_REST_Response(array(
                    'success' => true,
                    'message' => 'Configuration saved successfully',
                    'website_id' => $result['website_id'],
                    'timestamp' => date('c')
                ), 200);
            } else {
                $logger->error('WEBSITE_MANAGER', 'REST_SAVE', 'Failed to save configuration', array(
                    'error' => $result['error']
                ));

                return new WP_REST_Response(array(
                    'error' => $result['error'],
                    'message' => $result['message'],
                    'timestamp' => date('c')
                ), 400);
            }

        } catch (Exception $e) {
            $logger->error('WEBSITE_MANAGER', 'REST_SAVE', 'Exception during save', array(
                'error' => $e->getMessage()
            ));

            return new WP_REST_Response(array(
                'error' => 'Failed to save configuration',
                'message' => $e->getMessage(),
                'timestamp' => date('c')
            ), 500);
        }
    }

    /**
     * REST API: Get website config
     */
    public function rest_get_website_config($request)
    {
        // Enable CORS
        $this->set_cors_headers();

        $params = $request->get_params();
        $domain = $params['domain'] ?? null;

        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('WEBSITE_MANAGER', 'REST_GET', 'REST API GET request received', array(
            'domain' => $domain,
            'params' => $params
        ));

        try {
            $config = $this->get_website_config($domain);

            if ($config === null) {
                $logger->info('WEBSITE_MANAGER', 'REST_GET', 'No configuration found', array('domain' => $domain));
                return new WP_REST_Response(array(
                    'error' => 'Configuration not found',
                    'message' => 'No configuration found for the specified domain',
                    'timestamp' => date('c')
                ), 404);
            }

            $logger->info('WEBSITE_MANAGER', 'REST_GET', 'Configuration found and returned', array(
                'domain' => $domain,
                'config_size' => strlen(json_encode($config))
            ));

            return new WP_REST_Response($config, 200);

        } catch (Exception $e) {
            $logger->error('WEBSITE_MANAGER', 'REST_GET', 'Exception in REST GET', array(
                'domain' => $domain,
                'error' => $e->getMessage()
            ));
            return new WP_REST_Response(array(
                'error' => 'Internal server error',
                'message' => 'An unexpected error occurred',
                'timestamp' => date('c')
            ), 500);
        }
    }


    /**
     * REST API: Test endpoint
     */
    public function rest_test_endpoint($request)
    {
        $this->set_cors_headers();

        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('WEBSITE_MANAGER', 'REST_TEST', 'Test endpoint accessed');

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'AI Web Site Plugin REST API is working',
            'timestamp' => date('c'),
            'plugin_version' => AI_WEB_SITE_PLUGIN_VERSION ?? 'unknown'
        ), 200);
    }

    /**
     * REST API: Create default editor configuration
     */
    public function rest_create_default_config($request)
    {
        $this->set_cors_headers();

        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('WEBSITE_MANAGER', 'CREATE_DEFAULT_CONFIG', 'Creating default editor configuration via REST API');

        try {
            // Verifică dacă configurația pentru editor.ai-web.site există deja
            $existing_config = $this->get_website_config_by_domain('editor.ai-web.site');

            if ($existing_config !== null) {
                return new WP_REST_Response(array(
                    'status' => 'exists',
                    'message' => 'Default configuration already exists for editor.ai-web.site',
                    'timestamp' => date('c')
                ), 200);
            }

            // Încarcă configurația din fișierul default-config.json din plugin
            $config_file = AI_WEB_SITE_PLUGIN_DIR . 'assets/default-config.json';

            if (!file_exists($config_file)) {
                $logger->error('WEBSITE_MANAGER', 'CREATE_DEFAULT_CONFIG', 'Default config file not found', array(
                    'config_file' => $config_file
                ));
                return new WP_REST_Response(array(
                    'error' => 'Default config file not found',
                    'message' => 'Could not locate default-config.json file in plugin assets',
                    'config_file' => $config_file,
                    'timestamp' => date('c')
                ), 404);
            }

            $config_content = file_get_contents($config_file);
            $config_data = json_decode($config_content, true);

            if (!$config_data) {
                $logger->error('WEBSITE_MANAGER', 'CREATE_DEFAULT_CONFIG', 'Failed to parse default config file', array(
                    'config_file' => $config_file
                ));
                return new WP_REST_Response(array(
                    'error' => 'Invalid default config file',
                    'message' => 'Could not parse default-config.json file',
                    'timestamp' => date('c')
                ), 400);
            }

            $logger->info('WEBSITE_MANAGER', 'CREATE_DEFAULT_CONFIG', 'Loaded default configuration from file', array(
                'config_file' => $config_file,
                'config_size' => strlen($config_content)
            ));

            // Salvează configurația pentru editor.ai-web.site
            $save_data = array(
                'config' => $config_data,
                'domain' => 'editor.ai-web.site',
                'subdomain' => 'editor'
            );

            $result = $this->save_website_config($save_data);

            $logger->info('WEBSITE_MANAGER', 'CREATE_DEFAULT_CONFIG', 'Default editor configuration created successfully', array(
                'website_id' => $result['website_id'],
                'config_type' => 'original_site_config',
                'config_file' => $config_file
            ));

            return new WP_REST_Response(array(
                'status' => 'success',
                'message' => 'Default configuration created for editor.ai-web.site',
                'website_id' => $result['website_id'],
                'config_type' => 'original_site_config',
                'config_file' => $config_file,
                'timestamp' => date('c')
            ), 200);

        } catch (Exception $e) {
            $logger->error('WEBSITE_MANAGER', 'CREATE_DEFAULT_CONFIG', 'Exception in create default config', array(
                'error' => $e->getMessage()
            ));
            return new WP_REST_Response(array(
                'error' => 'Internal server error',
                'message' => $e->getMessage(),
                'timestamp' => date('c')
            ), 500);
        }
    }

    /**
     * REST API: Update existing editor configuration with original content
     */
    public function rest_update_editor_config($request)
    {
        $this->set_cors_headers();

        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('WEBSITE_MANAGER', 'UPDATE_EDITOR_CONFIG', 'Updating existing editor configuration with original content');

        try {
            // Încarcă configurația din fișierul default-config.json din plugin
            $config_file = AI_WEB_SITE_PLUGIN_DIR . 'assets/default-config.json';

            if (!file_exists($config_file)) {
                $logger->error('WEBSITE_MANAGER', 'UPDATE_EDITOR_CONFIG', 'Default config file not found', array(
                    'config_file' => $config_file
                ));
                return new WP_REST_Response(array(
                    'error' => 'Default config file not found',
                    'message' => 'Could not locate default-config.json file in plugin assets',
                    'config_file' => $config_file,
                    'timestamp' => date('c')
                ), 404);
            }

            $config_content = file_get_contents($config_file);
            $config_data = json_decode($config_content, true);

            if (!$config_data) {
                $logger->error('WEBSITE_MANAGER', 'UPDATE_EDITOR_CONFIG', 'Failed to parse default config file', array(
                    'config_file' => $config_file
                ));
                return new WP_REST_Response(array(
                    'error' => 'Invalid default config file',
                    'message' => 'Could not parse default-config.json file',
                    'timestamp' => date('c')
                ), 400);
            }

            // Actualizează configurația existentă pentru editor.ai-web.site
            $result = $this->update_website_config_by_domain('editor.ai-web.site', $config_data);

            if (!$result) {
                $logger->error('WEBSITE_MANAGER', 'UPDATE_EDITOR_CONFIG', 'Failed to update configuration', array(
                    'domain' => 'editor.ai-web.site'
                ));
                return new WP_REST_Response(array(
                    'error' => 'Update failed',
                    'message' => 'Could not update configuration for editor.ai-web.site',
                    'timestamp' => date('c')
                ), 500);
            }

            $logger->info('WEBSITE_MANAGER', 'UPDATE_EDITOR_CONFIG', 'Editor configuration updated successfully', array(
                'domain' => 'editor.ai-web.site',
                'config_type' => 'original_site_config',
                'config_file' => $config_file
            ));

            return new WP_REST_Response(array(
                'status' => 'success',
                'message' => 'Editor configuration updated with original content',
                'domain' => 'editor.ai-web.site',
                'config_type' => 'original_site_config',
                'config_file' => $config_file,
                'timestamp' => date('c')
            ), 200);

        } catch (Exception $e) {
            $logger->error('WEBSITE_MANAGER', 'UPDATE_EDITOR_CONFIG', 'Exception in update editor config', array(
                'error' => $e->getMessage()
            ));
            return new WP_REST_Response(array(
                'error' => 'Internal server error',
                'message' => $e->getMessage(),
                'timestamp' => date('c')
            ), 500);
        }
    }

    /**
     * REST API: Get website config by domain
     */
    public function rest_get_website_config_by_domain($request)
    {
        $this->set_cors_headers();

        $domain = $request['domain'];

        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('WEBSITE_MANAGER', 'REST_GET_DOMAIN', 'REST API GET request for domain', array(
            'domain' => $domain
        ));

        try {
            // Încearcă să găsească configurația pentru domeniul complet
            $config = $this->get_website_config_by_domain($domain);

            if ($config === null) {
                $logger->info('WEBSITE_MANAGER', 'REST_GET_DOMAIN', 'No configuration found', array('domain' => $domain));
                return new WP_REST_Response(array(
                    'error' => 'Configuration not found',
                    'message' => "No configuration found for {$domain}",
                    'timestamp' => date('c')
                ), 404);
            }

            $logger->info('WEBSITE_MANAGER', 'REST_GET_DOMAIN', 'Configuration found and returned', array(
                'domain' => $domain,
                'config_size' => strlen(json_encode($config))
            ));

            return new WP_REST_Response($config, 200);

        } catch (Exception $e) {
            $logger->error('WEBSITE_MANAGER', 'REST_GET_DOMAIN', 'Exception in REST GET', array(
                'domain' => $domain,
                'error' => $e->getMessage()
            ));
            return new WP_REST_Response(array(
                'error' => 'Internal server error',
                'message' => 'An unexpected error occurred',
                'timestamp' => date('c')
            ), 500);
        }
    }

    /**
     * AJAX: Save website config
     */
    public function ajax_save_website_config()
    {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'ai_web_site_website_nonce')) {
            wp_die('Security check failed');
        }

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data || !isset($data['config'])) {
            wp_send_json_error('Missing configuration data');
        }

        try {
            $result = $this->save_website_config($data);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Get website config
     */
    public function ajax_get_website_config()
    {
        $domain = $_GET['domain'] ?? null;

        try {
            $config = $this->get_website_config($domain);
            wp_send_json_success($config);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Delete website
     */
    public function ajax_delete_website()
    {
        // Verify nonce and user permissions
        if (!wp_verify_nonce($_POST['nonce'], 'ai_web_site_website_nonce')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }

        $website_id = intval($_POST['website_id']);

        try {
            $result = $this->delete_website($website_id);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Save website configuration
     */
    public function save_website_config($data)
    {
        global $wpdb;

        $logger = AI_Web_Site_Debug_Logger::get_instance();

        // Validate required fields
        if (!isset($data['config'])) {
            throw new Exception('Configuration data is required');
        }

        // Get user ID (for logged in users) or create anonymous user
        $user_id = get_current_user_id();
        if ($user_id === 0) {
            // For anonymous users, we'll use a special approach
            // You might want to implement session-based tracking or require authentication
            $user_id = 0; // Anonymous user
        }

        // Extract domain and subdomain
        $domain = $data['domain'] ?? 'ai-web.site';
        $subdomain = $data['subdomain'] ?? 'my-site';

        // Validate subdomain format
        if (!preg_match('/^[a-zA-Z0-9-]+$/', $subdomain)) {
            throw new Exception('Invalid subdomain format');
        }

        // Prepare config data
        $config_json = json_encode($data['config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid configuration JSON: ' . json_last_error_msg());
        }

        // Check if website already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE user_id = %d AND subdomain = %s AND domain = %s",
            $user_id,
            $subdomain,
            $domain
        ));

        if ($existing) {
            // Update existing website
            $result = $wpdb->update(
                $this->table_name,
                array(
                    'config' => $config_json,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $existing->id),
                array('%s', '%s'),
                array('%d')
            );

            if ($result === false) {
                throw new Exception('Failed to update website configuration');
            }

            $website_id = $existing->id;
            $logger->info('WEBSITE_MANAGER', 'UPDATE', "Website config updated for {$subdomain}.{$domain}", array(
                'website_id' => $website_id,
                'user_id' => $user_id,
                'config_size' => strlen($config_json)
            ));

        } else {
            // Create new website
            $result = $wpdb->insert(
                $this->table_name,
                array(
                    'user_id' => $user_id,
                    'subdomain' => $subdomain,
                    'domain' => $domain,
                    'config' => $config_json,
                    'status' => 'draft'
                ),
                array('%d', '%s', '%s', '%s', '%s')
            );

            if ($result === false) {
                throw new Exception('Failed to save website configuration');
            }

            $website_id = $wpdb->insert_id;
            $logger->info('WEBSITE_MANAGER', 'CREATE', "Website config created for {$subdomain}.{$domain}", array(
                'website_id' => $website_id,
                'user_id' => $user_id,
                'config_size' => strlen($config_json)
            ));
        }

        return array(
            'success' => true,
            'website_id' => $website_id,
            'message' => 'Configuration saved successfully'
        );
    }

    /**
     * Get website configuration
     */
    public function get_website_config($domain = null)
    {
        global $wpdb;

        if ($domain) {
            $config = $wpdb->get_row($wpdb->prepare(
                "SELECT config FROM {$this->table_name} WHERE domain = %s ORDER BY updated_at DESC LIMIT 1",
                $domain
            ));
        } else {
            $config = $wpdb->get_row(
                "SELECT config FROM {$this->table_name} ORDER BY updated_at DESC LIMIT 1"
            );
        }

        if (!$config) {
            return null;
        }

        $config_data = json_decode($config->config, true);
        return $config_data ?: null;
    }

    /**
     * Update website configuration by domain
     */
    public function update_website_config_by_domain($full_domain, $config_data)
    {
        global $wpdb;

        // Actualizează configurația pentru domeniul complet
        $result = $wpdb->update(
            $this->table_name,
            array(
                'config' => json_encode($config_data),
                'updated_at' => current_time('mysql')
            ),
            array('domain' => $full_domain),
            array('%s', '%s'),
            array('%s')
        );

        if ($result === false) {
            // Dacă nu găsește pentru domeniul complet, încearcă să parseze subdomain
            $parts = explode('.', $full_domain);
            if (count($parts) >= 2) {
                $subdomain = $parts[0];
                $base_domain = implode('.', array_slice($parts, 1));

                $result = $wpdb->update(
                    $this->table_name,
                    array(
                        'config' => json_encode($config_data),
                        'updated_at' => current_time('mysql')
                    ),
                    array(
                        'subdomain' => $subdomain,
                        'domain' => $base_domain
                    ),
                    array('%s', '%s'),
                    array('%s', '%s')
                );
            }
        }

        return $result !== false;
    }

    /**
     * Get website configuration by subdomain
     */
    public function get_website_config_by_subdomain($subdomain, $domain = 'ai-web.site')
    {
        global $wpdb;

        $config = $wpdb->get_row($wpdb->prepare(
            "SELECT config FROM {$this->table_name} WHERE subdomain = %s AND domain = %s ORDER BY updated_at DESC LIMIT 1",
            $subdomain,
            $domain
        ));

        if (!$config) {
            return null;
        }

        $config_data = json_decode($config->config, true);
        return $config_data ?: null;
    }

    /**
     * Get website configuration by full domain
     */
    public function get_website_config_by_domain($full_domain)
    {
        global $wpdb;

        // Încearcă să găsească configurația pentru domeniul complet
        $config = $wpdb->get_row($wpdb->prepare(
            "SELECT config FROM {$this->table_name} WHERE domain = %s ORDER BY updated_at DESC LIMIT 1",
            $full_domain
        ));

        if (!$config) {
            // Dacă nu găsește pentru domeniul complet, încearcă să parseze subdomain
            $parts = explode('.', $full_domain);
            if (count($parts) >= 2) {
                $subdomain = $parts[0];
                $base_domain = implode('.', array_slice($parts, 1));

                $config = $wpdb->get_row($wpdb->prepare(
                    "SELECT config FROM {$this->table_name} WHERE subdomain = %s AND domain = %s ORDER BY updated_at DESC LIMIT 1",
                    $subdomain,
                    $base_domain
                ));
            }
        }

        if (!$config) {
            return null;
        }

        $config_data = json_decode($config->config, true);
        return $config_data ?: null;
    }

    /**
     * Delete website
     */
    public function delete_website($website_id)
    {
        global $wpdb;

        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $website_id),
            array('%d')
        );

        if ($result === false) {
            throw new Exception('Failed to delete website');
        }

        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('WEBSITE_MANAGER', 'DELETE', "Website deleted", array('website_id' => $website_id));

        return array('success' => true, 'message' => 'Website deleted successfully');
    }

    /**
     * Get user's websites
     */
    public function get_user_websites($user_id = null)
    {
        global $wpdb;

        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        $websites = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE user_id = %d ORDER BY updated_at DESC",
            $user_id
        ));

        return $websites ?: array();
    }

    /**
     * Set CORS headers
     */
    private function set_cors_headers()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, Origin');
        header('Access-Control-Allow-Credentials: true');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }

    /**
     * Get API endpoint URL
     */
    public function get_api_endpoint()
    {
        return rest_url('ai-web-site/v1/website-config');
    }

    /**
     * Get API endpoint for specific subdomain
     */
    public function get_subdomain_api_endpoint($subdomain, $domain = 'ai-web.site')
    {
        return rest_url("ai-web-site/v1/website-config/{$subdomain}?domain={$domain}");
    }
}
