<?php

/**
 * Admin interface class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Log that admin class file is loaded
if (function_exists('error_log')) {
    error_log('AI-Web-Site: Admin class file loaded');
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
        // Log admin class initialization
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('ADMIN', 'CLASS_INIT', 'AI_Web_Site_Admin class initialized');

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
        
        // Log hook registration
        $logger->info('ADMIN', 'FORM_HOOKS_REGISTERED', 'Form hooks registered', array(
            'save_hook' => 'admin_post_save_ai_web_site_options',
            'test_hook' => 'admin_post_test_cpanel_connection'
        ));

        // Also add for non-logged in users (if needed)
        add_action('wp_ajax_save_ai_web_site_options', array($this, 'save_options'));

        // Add global hook to catch all admin_post requests
        add_action('admin_post', array($this, 'debug_admin_post'));

        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Log hook registration
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('ADMIN', 'HOOKS_REGISTERED', 'Admin hooks registered successfully');
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook)
    {
        // Log all admin hooks for debugging
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('ADMIN', 'ENQUEUE_SCRIPTS_CALLED', 'enqueue_admin_scripts called', array(
            'hook' => $hook,
            'current_screen' => get_current_screen() ? get_current_screen()->id : 'unknown'
        ));

        // Always load scripts for debugging - remove the hook check temporarily
        $logger->info('ADMIN', 'ENQUEUE_SCRIPTS_LOADING', 'Loading scripts for hook', array(
            'hook' => $hook,
            'expected' => 'settings_page_ai-web-site-plugin',
            'is_correct_hook' => ($hook === 'settings_page_ai-web-site-plugin')
        ));

        // Load CSS inline
        $css_path = AI_WEB_SITE_PLUGIN_DIR . 'assets/admin.css';
        if (file_exists($css_path)) {
            $logger->info('ADMIN', 'CSS_LOAD', 'Loading CSS inline', array('css_path' => $css_path));
            echo '<style type="text/css">';
            echo file_get_contents($css_path);
            echo '</style>';
        } else {
            $logger->error('ADMIN', 'CSS_NOT_FOUND', 'CSS file not found', array('css_path' => $css_path));
        }

        // Load JavaScript inline
        $js_path = AI_WEB_SITE_PLUGIN_DIR . 'assets/admin.js';
        if (file_exists($js_path)) {
            $logger->info('ADMIN', 'JS_LOAD', 'Loading JS inline', array('js_path' => $js_path));
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
            $logger->error('ADMIN', 'JS_NOT_FOUND', 'JS file not found', array('js_path' => $js_path));
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('ADMIN', 'MENU_ADD', 'Adding admin menu');

        add_options_page(
            __('AI Web Site Plugin', 'ai-web-site-plugin'),
            __('AI Web Site Plugin', 'ai-web-site-plugin'),
            'manage_options',
            'ai-web-site-plugin',
            array($this, 'admin_page')
        );

        $logger->info('ADMIN', 'MENU_ADDED', 'Admin menu added successfully');
    }

    /**
     * Admin page callback
     */
    public function admin_page()
    {
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('ADMIN', 'PAGE_CALLBACK', 'Admin page callback called');

        include AI_WEB_SITE_PLUGIN_DIR . 'admin/admin-page.php';
    }

    /**
     * Save plugin options
     */
    public function save_options()
    {
        // Log that save_options was called
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('ADMIN', 'SAVE_OPTIONS_CALLED', 'save_options method called', array(
            'post_data' => $_POST,
            'nonce' => isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : 'not_set'
        ));

        // Check nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'ai_web_site_options')) {
            $logger->error('ADMIN', 'SAVE_OPTIONS_ERROR', 'Nonce verification failed');
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            $logger->error('ADMIN', 'SAVE_OPTIONS_ERROR', 'Insufficient permissions');
            wp_die('Insufficient permissions');
        }

        $logger->info('ADMIN', 'SAVE_OPTIONS_VALIDATION', 'Nonce and permissions check passed');

        // Get current options
        $options = get_option('ai_web_site_options', array());

        // Update options (only essential fields)
        $options['cpanel_username'] = sanitize_text_field($_POST['cpanel_username']);
        $options['cpanel_api_token'] = sanitize_text_field($_POST['cpanel_api_token']);
        $options['main_domain'] = sanitize_text_field($_POST['main_domain']);

        // Log the save operation
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('ADMIN', 'SAVE_OPTIONS', 'Saving plugin options', array(
            'username' => $options['cpanel_username'],
            'main_domain' => $options['main_domain'],
            'api_token_length' => strlen($options['cpanel_api_token'])
        ));

        // Save options
        $result = update_option('ai_web_site_options', $options);

        if ($result) {
            $logger->info('ADMIN', 'SAVE_OPTIONS_SUCCESS', 'Options saved successfully');
        } else {
            $logger->warning('ADMIN', 'SAVE_OPTIONS_WARNING', 'Options save returned false (no changes or error)');
        }

        // Redirect back with success message
        wp_redirect(add_query_arg('message', 'options_saved', admin_url('options-general.php?page=ai-web-site')));
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
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
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
        wp_redirect(add_query_arg('message', $message, admin_url('options-general.php?page=ai-web-site')));
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
                        'text' => __('Options saved successfully.', 'ai-web-site')
                    );
                    break;
                case 'connection_success':
                    $messages[] = array(
                        'type' => 'success',
                        'text' => __('cPanel connection test successful.', 'ai-web-site')
                    );
                    break;
                case 'connection_failed':
                    $messages[] = array(
                        'type' => 'error',
                        'text' => __('cPanel connection test failed. Please check your settings.', 'ai-web-site')
                    );
                    break;
            }
        }

        return $messages;
    }
}
