<?php

/**
 * Main plugin class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AI_Web_Site
{
    /**
     * Single instance
     */
    private static $instance = null;

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
        // Log class initialization
        if (function_exists('error_log')) {
            error_log('AI-Web-Site: AI_Web_Site class initialized');
        }

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Initialize logger and create table
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->create_table();

        // Log plugin initialization
        $logger->info('PLUGIN', 'INIT', 'AI Web Site plugin initialized');

        // Add REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));


        // Handle AJAX requests
        add_action('wp_ajax_create_subdomain', array($this, 'handle_create_subdomain'));
        add_action('wp_ajax_delete_subdomain', array($this, 'handle_delete_subdomain'));

        // Add logs API endpoint
        add_action('rest_api_init', array($this, 'register_logs_api'));
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes()
    {
        register_rest_route('ai-web-site/v1', '/site-config', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_site_config'),
            'permission_callback' => '__return_true',
            'args' => array(
                'subdomain' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
    }

    /**
     * Get site configuration for subdomain
     */
    public function get_site_config($request)
    {
        $subdomain = $request->get_param('subdomain');

        if (empty($subdomain)) {
            return new WP_Error('missing_subdomain', 'Subdomain parameter is required', array('status' => 400));
        }

        // Get site configuration from database
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_web_sites';

        $site_config = $wpdb->get_var($wpdb->prepare(
            "SELECT site_config FROM {$table_name} WHERE subdomain = %s AND status = 'active'",
            $subdomain
        ));

        if (!$site_config) {
            return new WP_Error('subdomain_not_found', 'Subdomain not found', array('status' => 404));
        }

        // Decode JSON
        $config = json_decode($site_config, true);

        if (!$config) {
            return new WP_Error('invalid_config', 'Invalid site configuration', array('status' => 500));
        }

        return rest_ensure_response($config);
    }

    /**
     * Register logs API routes
     */
    public function register_logs_api()
    {
        register_rest_route('ai-web-site/v1', '/logs', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_logs'),
            'permission_callback' => '__return_true', // Public for debugging
            'args' => array(
                'limit' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 50,
                ),
                'level' => array(
                    'required' => false,
                    'type' => 'string',
                    'enum' => array('DEBUG', 'INFO', 'WARNING', 'ERROR'),
                ),
                'component' => array(
                    'required' => false,
                    'type' => 'string',
                ),
            ),
        ));
    }

    /**
     * Get logs via API
     */
    public function get_logs($request)
    {
        $logger = AI_Web_Site_Debug_Logger::get_instance();

        $limit = $request->get_param('limit');
        $level = $request->get_param('level');
        $component = $request->get_param('component');

        $logs = $logger->get_logs_json($limit, $level, $component);

        return rest_ensure_response(array(
            'success' => true,
            'logs' => $logs,
            'count' => count($logs)
        ));
    }


    /**
     * Handle create subdomain AJAX request
     */
    public function handle_create_subdomain()
    {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ai_web_site_nonce')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $subdomain = sanitize_text_field($_POST['subdomain']);
        $domain = sanitize_text_field($_POST['domain']);

        if (empty($subdomain) || empty($domain)) {
            wp_send_json_error('Subdomain and domain are required');
        }

        // Create subdomain using cPanel API
        $cpanel_api = AI_Web_Site_CPanel_API::get_instance();
        $result = $cpanel_api->create_subdomain($subdomain, $domain);

        if ($result['success']) {
            // Save to database
            $database = AI_Web_Site_Database::get_instance();
            $database->save_subdomain($subdomain, $domain, array());

            wp_send_json_success('Subdomain created successfully');
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * Handle delete subdomain AJAX request
     */
    public function handle_delete_subdomain()
    {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ai_web_site_nonce')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $subdomain = sanitize_text_field($_POST['subdomain']);
        $domain = sanitize_text_field($_POST['domain']);

        if (empty($subdomain) || empty($domain)) {
            wp_send_json_error('Subdomain and domain are required');
        }

        // Delete from database
        $database = AI_Web_Site_Database::get_instance();
        $database->delete_subdomain($subdomain, $domain);

        wp_send_json_success('Subdomain deleted successfully');
    }
}
