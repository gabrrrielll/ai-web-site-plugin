<?php

/**
 * Admin interface class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AI_Web_Site_Admin
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
        // Add admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Handle form submissions
        add_action('admin_post_save_ai_web_site_options', array($this, 'save_options'));
        add_action('admin_post_test_cpanel_connection', array($this, 'test_connection'));

        // Also add for non-logged in users (if needed)
        add_action('wp_ajax_save_ai_web_site_options', array($this, 'save_options'));

        // Handle UMP license activation
        add_action('wp_ajax_activate_ump_license', array($this, 'activate_ump_license'));

        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook)
    {
        // Only load on our plugin page
        if ($hook !== 'settings_page_ai-web-site-plugin') {
            return;
        }

        // Load CSS inline
        $css_path = AI_WEB_SITE_PLUGIN_DIR . 'assets/admin.css';
        if (file_exists($css_path)) {
            echo '<style type="text/css">';
            echo file_get_contents($css_path);
            echo '</style>';
        }

        // Load JavaScript inline
        $js_path = AI_WEB_SITE_PLUGIN_DIR . 'assets/admin.js';
        if (file_exists($js_path)) {
            echo '<script type="text/javascript">';
            echo 'var aiWebSite = ' . json_encode(array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ai_web_site_nonce'),
                'options' => get_option('ai_web_site_options', array()), // Add plugin options
                'strings' => array(
                    'confirmDelete' => __('Are you sure you want to delete this subdomain?', 'ai-web-site-plugin'),
                    'creating' => __('Creating...', 'ai-web-site-plugin'),
                    'deleting' => __('Deleting...', 'ai-web-site-plugin'),
                    'testing' => __('Testing...', 'ai-web-site-plugin'),
                    'activating' => __('Activating...', 'ai-web-site-plugin')
                )
            )) . ';';
            echo file_get_contents($js_path);
            echo '</script>';
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_options_page(
            __('AI Web Site Plugin', 'ai-web-site-plugin'),
            __('AI Web Site Plugin', 'ai-web-site-plugin'),
            'manage_options',
            'ai-web-site-plugin',
            array($this, 'admin_page')
        );
    }

    /**
     * Admin page callback
     */
    public function admin_page()
    {
        include AI_WEB_SITE_PLUGIN_DIR . 'admin/admin-page.php';
    }

    /**
     * Save plugin options
     */
    public function save_options()
    {
        // NEW LOG: Confirm this method is called and capture all POST data
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('ADMIN', 'SAVE_OPTIONS_ENTRY', 'Entering save_options method and capturing POST data', array(
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'all_post_data' => $_POST
        ));

        // Nonce and permissions check
        if (!wp_verify_nonce($_POST['_wpnonce'], 'ai_web_site_options')) {
            $logger->error('ADMIN', 'SAVE_OPTIONS_ERROR', 'Nonce verification failed', array('nonce_post' => $_POST['_wpnonce'] ?? 'not_set'));
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            $logger->error('ADMIN', 'SAVE_OPTIONS_ERROR', 'Insufficient permissions', array('user_id' => get_current_user_id()));
            wp_die('Insufficient permissions');
        }

        // Get current options
        $options = get_option('ai_web_site_options', array());

        // Update options (only essential fields)
        $options['cpanel_username'] = sanitize_text_field($_POST['cpanel_username']);
        $options['cpanel_api_token'] = sanitize_text_field($_POST['cpanel_api_token']);
        $options['main_domain'] = sanitize_text_field($_POST['main_domain']);
        $options['required_ump_level_id'] = (int)sanitize_text_field($_POST['required_ump_level_id']);
        $options['ump_domain_override'] = sanitize_text_field($_POST['ump_domain_override']);
        $options['disable_ump_tracking'] = isset($_POST['disable_ump_tracking']) ? 1 : 0;
        
        // Security settings
        $options['rate_limit_requests'] = max(1, min(10000, (int)sanitize_text_field($_POST['rate_limit_requests'] ?? 100)));
        $options['rate_limit_period'] = (int)sanitize_text_field($_POST['rate_limit_period'] ?? 3600);
        $options['max_config_size'] = max(1, min(50, (float)sanitize_text_field($_POST['max_config_size'] ?? 5)));
        $options['enable_input_sanitization'] = isset($_POST['enable_input_sanitization']) ? 1 : 0;
        $options['enable_security_logging'] = isset($_POST['enable_security_logging']) ? 1 : 0;

        // Save options
        $result = update_option('ai_web_site_options', $options);

        if ($result) {
            $logger->info('ADMIN', 'SAVE_OPTIONS_SUCCESS', 'Options saved successfully');
        } else {
            $logger->warning('ADMIN', 'SAVE_OPTIONS_WARNING', 'Options save returned false (no changes or error)');
        }

        // Redirect back with success message
        wp_redirect(add_query_arg('message', 'options_saved', admin_url('options-general.php?page=ai-web-site-plugin')));
        exit;
    }

    /**
     * Test cPanel connection
     */
    public function test_connection()
    {
        // Check nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'ai_web_site_options')) {
            $logger->error('ADMIN', 'TEST_CONNECTION_ERROR', 'Nonce verification failed', array('nonce_post' => $_POST['_wpnonce'] ?? 'not_set'));
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            $logger->error('ADMIN', 'TEST_CONNECTION_ERROR', 'Insufficient permissions', array('user_id' => get_current_user_id()));
            wp_die('Insufficient permissions');
        }

        // Log test connection attempt
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('ADMIN', 'TEST_CONNECTION_START', 'Admin initiated connection test');

        // Test connection
        $cpanel_api = AI_Web_Site_CPanel_API::get_instance();
        $result = $cpanel_api->test_connection();

        if ($result['success']) {
            $logger->info('ADMIN', 'TEST_CONNECTION_SUCCESS', 'Admin test connection successful');
        } else {
            $logger->error('ADMIN', 'TEST_CONNECTION_FAILED', 'Admin test connection failed', array(
                'message' => $result['message']
            ));
        }

        if ($result['success']) {
            $message = 'connection_success';
        } else {
            $message = 'connection_failed';
        }

        // Redirect back with result message
        wp_redirect(add_query_arg('message', $message, admin_url('options-general.php?page=ai-web-site-plugin')));
        exit;
    }

    /**
     * Get admin messages
     */
    public function get_admin_messages()
    {
        $messages = array();

        if (isset($_GET['message'])) {
            switch ($_GET['message']) {
                case 'options_saved':
                    $messages[] = array(
                        'type' => 'success',
                        'text' => __('Options saved successfully.', 'ai-web-site-plugin')
                    );
                    break;
                case 'connection_success':
                    $messages[] = array(
                        'type' => 'success',
                        'text' => __('cPanel connection test successful.', 'ai-web-site-plugin')
                    );
                    break;
                case 'connection_failed':
                    $messages[] = array(
                        'type' => 'error',
                        'text' => __('cPanel connection test failed. Please check your settings.', 'ai-web-site-plugin')
                    );
                    break;
            }
        }

        return $messages;
    }

    /**
     * Handle UMP license activation
     */
    public function activate_ump_license()
    {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ai_web_site_nonce')) {
            wp_send_json_error('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Get UMP integration instance
        $ump_integration = AI_Web_Site_UMP_Integration::get_instance();

        if (!$ump_integration->is_ump_available()) {
            wp_send_json_error('Ultimate Membership Pro plugin is not available or not properly loaded');
        }

        // Get the configured domain override
        $domain_override = $ump_integration->get_ump_domain_override();

        if (empty($domain_override)) {
            wp_send_json_error('UMP License Domain is not configured. Please set it in the settings above.');
        }

        // Try to redirect to UMP license activation page
        $ump_admin_url = admin_url('admin.php?page=ihc_manage&tab=help');

        wp_send_json_success(array(
            'message' => 'Please go to Ultimate Membership Pro → Help to activate your license.',
            'redirect_url' => $ump_admin_url,
            'domain' => $domain_override
        ));
    }
}
