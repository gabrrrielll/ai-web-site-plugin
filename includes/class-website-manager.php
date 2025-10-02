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
     * Register REST API routes
     */
    public function register_rest_routes()
    {
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

        register_rest_route('ai-web-site/v1', '/website-config/(?P<subdomain>[a-zA-Z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_website_config_by_subdomain'),
            'permission_callback' => '__return_true',
        ));
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

        try {
            $config = $this->get_website_config($domain);
            
            if ($config === null) {
                return new WP_REST_Response(array(
                    'error' => 'Configuration not found',
                    'message' => 'No configuration found for the specified domain',
                    'timestamp' => date('c')
                ), 404);
            }

            return new WP_REST_Response($config, 200);

        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'error' => 'Internal server error',
                'message' => 'An unexpected error occurred',
                'timestamp' => date('c')
            ), 500);
        }
    }

    /**
     * REST API: Save website config
     */
    public function rest_save_website_config($request)
    {
        // Enable CORS
        $this->set_cors_headers();

        $body = $request->get_json_params();

        if (!$body || !isset($body['config'])) {
            return new WP_REST_Response(array(
                'error' => 'Missing configuration data',
                'message' => 'Expected "config" field in request body',
                'timestamp' => date('c')
            ), 400);
        }

        try {
            $result = $this->save_website_config($body);
            
            if ($result['success']) {
                return new WP_REST_Response(array(
                    'success' => true,
                    'message' => 'Configuration saved successfully',
                    'website_id' => $result['website_id'],
                    'timestamp' => date('c')
                ), 200);
            } else {
                return new WP_REST_Response(array(
                    'error' => $result['error'],
                    'message' => $result['message'],
                    'timestamp' => date('c')
                ), 400);
            }

        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'error' => 'Internal server error',
                'message' => 'An unexpected error occurred',
                'timestamp' => date('c')
            ), 500);
        }
    }

    /**
     * REST API: Get website config by subdomain
     */
    public function rest_get_website_config_by_subdomain($request)
    {
        $this->set_cors_headers();
        
        $subdomain = $request['subdomain'];
        $domain = $request['domain'] ?? 'ai-web.site';

        try {
            $config = $this->get_website_config_by_subdomain($subdomain, $domain);
            
            if ($config === null) {
                return new WP_REST_Response(array(
                    'error' => 'Configuration not found',
                    'message' => "No configuration found for {$subdomain}.{$domain}",
                    'timestamp' => date('c')
                ), 404);
            }

            return new WP_REST_Response($config, 200);

        } catch (Exception $e) {
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
