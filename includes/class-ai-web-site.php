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

        // Check UMP subscription
        $ump_integration = AI_Web_Site_UMP_Integration::get_instance();
        $required_ump_level_id = $ump_integration->get_required_ump_level_id();
        $current_user_id = get_current_user_id();

        if ($required_ump_level_id > 0) {
            if (!$ump_integration->user_has_active_ump_level($current_user_id, $required_ump_level_id)) {
                wp_send_json_error(__('You need an active Ultimate Membership Pro subscription to perform this action.', 'ai-web-site-plugin'));
            }
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
        $logger = AI_Web_Site_Debug_Logger::get_instance();

        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ai_web_site_nonce')) {
            $logger->error('PLUGIN', 'HANDLE_DELETE_SUBDOMAIN_ERROR', 'Nonce verification failed');
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            $logger->error('PLUGIN', 'HANDLE_DELETE_SUBDOMAIN_ERROR', 'Insufficient permissions');
            wp_die('Insufficient permissions');
        }

        $subdomain = sanitize_text_field($_POST['subdomain']);
        $domain = sanitize_text_field($_POST['domain']);

        if (empty($subdomain) || empty($domain)) {
            $logger->error('PLUGIN', 'HANDLE_DELETE_SUBDOMAIN_ERROR', 'Subdomain and domain are required', array('subdomain' => $subdomain, 'domain' => $domain));
            wp_send_json_error('Subdomain and domain are required');
        }

        // Check UMP subscription
        $ump_integration = AI_Web_Site_UMP_Integration::get_instance();
        $required_ump_level_id = $ump_integration->get_required_ump_level_id();
        $current_user_id = get_current_user_id();

        if ($required_ump_level_id > 0) {
            if (!$ump_integration->user_has_active_ump_level($current_user_id, $required_ump_level_id)) {
                $logger->error('PLUGIN', 'HANDLE_DELETE_SUBDOMAIN_UMP_ERROR', 'User does not have required UMP subscription', array('user_id' => $current_user_id, 'required_level' => $required_ump_level_id));
                wp_send_json_error(__('You need an active Ultimate Membership Pro subscription to perform this action.', 'ai-web-site-plugin'));
            }
        }

        // Delete from database
        $database = AI_Web_Site_Database::get_instance();
        $db_result = $database->delete_subdomain($subdomain, $domain);

        if ($db_result) {
            $logger->info('PLUGIN', 'DB_DELETE_SUCCESS', 'Subdomain marked as inactive in database', array('subdomain' => $subdomain, 'domain' => $domain));
        } else {
            $logger->error('PLUGIN', 'DB_DELETE_FAILED', 'Failed to mark subdomain as inactive in database', array('subdomain' => $subdomain, 'domain' => $domain));
        }

        // Delete from cPanel using API
        $cpanel_api = AI_Web_Site_CPanel_API::get_instance();
        $api_result = $cpanel_api->delete_subdomain($subdomain, $domain);

        if ($api_result['success']) {
            $logger->info('PLUGIN', 'CPANEL_DELETE_SUCCESS', 'Subdomain deleted from cPanel', array('subdomain' => $subdomain, 'domain' => $domain));
            wp_send_json_success('Subdomain deleted successfully');
        } else {
            $logger->error('PLUGIN', 'CPANEL_DELETE_FAILED', 'Failed to delete subdomain from cPanel', array('subdomain' => $subdomain, 'domain' => $domain, 'message' => $api_result['message']));
            wp_send_json_error($api_result['message']);
        }
    }
}
