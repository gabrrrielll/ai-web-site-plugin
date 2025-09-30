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

        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook)
    {
        // Load CSS inline
        $css_path = AI_WEB_SITE_PLUGIN_DIR . 'assets/admin.css';
        if (file_exists($css_path)) {
            echo '<style type="text/css">';
            echo file_get_contents($css_path);
            echo '</style>';
        } else {
            // Log error if CSS not found
            $logger = AI_Web_Site_Debug_Logger::get_instance();
            $logger->error('ADMIN', 'CSS_NOT_FOUND', 'CSS file not found', array('css_path' => $css_path));
        }

        // Load JavaScript inline
        $js_path = AI_WEB_SITE_PLUGIN_DIR . 'assets/admin.js';
        if (file_exists($js_path)) {
            echo '<script type="text/javascript">';
            echo 'var aiWebSite = ' . json_encode(array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ai_web_site_nonce'),
                'strings' => array(
                    'confirmDelete' => __('Are you sure you want to delete this subdomain?', 'ai-web-site'),
                    'creating' => __('Creating...', 'ai-web-site'),
                    'deleting' => __('Deleting...', 'ai-web-site'),
                    'testing' => __('Testing...', 'ai-web-site')
                )
            )) . ';';
            echo file_get_contents($js_path);
            echo '</script>';
        } else {
            // Log error if JS not found
            $logger = AI_Web_Site_Debug_Logger::get_instance();
            $logger->error('ADMIN', 'JS_NOT_FOUND', 'JS file not found', array('js_path' => $js_path));
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

        // Check nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'ai_web_site_options')) {
            $logger->error('ADMIN', 'SAVE_OPTIONS_ERROR', 'Nonce verification failed', array('nonce_post' => $_POST['_wpnonce'] ?? 'not_set'));
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            $logger->error('ADMIN', 'SAVE_OPTIONS_ERROR', 'Insufficient permissions', array('user_id' => get_current_user_id()));
            wp_die('Insufficient permissions');
        }

        $logger->info('ADMIN', 'SAVE_OPTIONS_VALIDATION', 'Nonce and permissions check passed');

        // Get current options
        $options = get_option('ai_web_site_options', array());

        // Update options (only essential fields)
        $options['cpanel_username'] = sanitize_text_field($_POST['cpanel_username']);
        $options['cpanel_api_token'] = sanitize_text_field($_POST['cpanel_api_token']);
        $options['main_domain'] = sanitize_text_field($_POST['main_domain']);

        // Log the options before saving
        $logger->info('ADMIN', 'SAVE_OPTIONS_BEFORE_UPDATE', 'Options before update_option', array(
            'cpanel_username' => $options['cpanel_username'],
            'cpanel_api_token_length_options' => strlen($options['cpanel_api_token']),
            'main_domain' => $options['main_domain']
        ));

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
     * Debug all admin_post requests
     */
    public function debug_admin_post()
    {
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('ADMIN', 'ADMIN_POST_DEBUG', 'admin_post hook triggered', array(
            'action' => isset($_POST['action']) ? $_POST['action'] : 'not_set',
            'all_post_data' => $_POST,
            'request_method' => $_SERVER['REQUEST_METHOD']
        ));
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

        // Log test result
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
}
