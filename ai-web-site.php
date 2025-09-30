<?php

/**
 * Plugin Name: AI Web Site Plugin
 * Plugin URI: https://ai-web.site
 * Description: Gestionează subdomeniile și site-urile create cu AI Website Builder
 * Version: 1.0.0
 * Author: AI Web Site
 * License: GPL v2 or later
 * Text Domain: ai-web-site-plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AI_WEB_SITE_PLUGIN_VERSION', '1.0.0');
define('AI_WEB_SITE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_WEB_SITE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_WEB_SITE_PLUGIN_FILE', __FILE__);

// Include required files
require_once AI_WEB_SITE_PLUGIN_DIR . 'includes/class-debug-logger.php';
if (function_exists('error_log')) {
    error_log('AI-Web-Site: class-debug-logger.php loaded');
}
require_once AI_WEB_SITE_PLUGIN_DIR . 'includes/class-ai-web-site.php';
if (function_exists('error_log')) {
    error_log('AI-Web-Site: class-ai-web-site.php loaded');
}
require_once AI_WEB_SITE_PLUGIN_DIR . 'includes/class-cpanel-api.php';
if (function_exists('error_log')) {
    error_log('AI-Web-Site: class-cpanel-api.php loaded');
}
require_once AI_WEB_SITE_PLUGIN_DIR . 'includes/class-database.php';
if (function_exists('error_log')) {
    error_log('AI-Web-Site: class-database.php loaded');
}
require_once AI_WEB_SITE_PLUGIN_DIR . 'admin/class-admin.php';
if (function_exists('error_log')) {
    error_log('AI-Web-Site: class-admin.php loaded');
}

/**
 * Main plugin class
 */
class AI_Web_Site_Plugin
{
    /**
     * Single instance of the plugin
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
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));

        // Add global hooks for debugging
        add_action('admin_init', array($this, 'debug_admin_init'));
        add_action('wp_ajax_save_ai_web_site_options', array($this, 'debug_ajax_save'));
        add_action('wp_ajax_nopriv_save_ai_web_site_options', array($this, 'debug_ajax_save'));
    }

    /**
     * Initialize plugin
     */
    public function init()
    {
        // Log plugin initialization
        if (function_exists('error_log')) {
            error_log('AI-Web-Site: Plugin init() method called');
        }

        // Initialize main classes
        if (function_exists('error_log')) {
            error_log('AI-Web-Site: Initializing AI_Web_Site class');
        }
        AI_Web_Site::get_instance();

        if (function_exists('error_log')) {
            error_log('AI-Web-Site: Initializing AI_Web_Site_CPanel_API class');
        }
        AI_Web_Site_CPanel_API::get_instance();

        if (function_exists('error_log')) {
            error_log('AI-Web-Site: Initializing AI_Web_Site_Database class');
        }
        AI_Web_Site_Database::get_instance();

        // Log before initializing admin class
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('PLUGIN', 'INIT_ADMIN', 'Initializing admin class');

        if (function_exists('error_log')) {
            error_log('AI-Web-Site: Initializing AI_Web_Site_Admin class');
        }
        AI_Web_Site_Admin::get_instance();

        // Load text domain for translations
        load_plugin_textdomain('ai-web-site', false, dirname(plugin_basename(__FILE__)) . '/languages');

        if (function_exists('error_log')) {
            error_log('AI-Web-Site: Plugin initialization completed');
        }
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        // Create database tables
        AI_Web_Site_Database::create_tables();

        // Create logs table
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->create_table();

        // Set default options
        $default_options = array(
            'cpanel_username' => '',
            'cpanel_api_token' => '',
            'main_domain' => 'ai-web.site'
        );

        add_option('ai_web_site_options', $default_options);

        // Flush rewrite rules
        flush_rewrite_rules();

        // Log activation
        $logger->info('PLUGIN', 'ACTIVATION', 'Plugin activated successfully');
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Debug admin_init hook
     */
    public function debug_admin_init()
    {
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('PLUGIN', 'ADMIN_INIT', 'admin_init hook triggered', array(
            'current_screen' => get_current_screen() ? get_current_screen()->id : 'unknown',
            'is_admin' => is_admin(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ));
    }


    /**
     * Debug AJAX save hook
     */
    public function debug_ajax_save()
    {
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('PLUGIN', 'AJAX_SAVE', 'AJAX save hook triggered', array(
            'action' => $_POST['action'] ?? 'not_set',
            'all_post_data' => $_POST,
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'user_id' => get_current_user_id(),
            'is_ajax' => wp_doing_ajax()
        ));
    }
}

// Initialize the plugin
if (function_exists('error_log')) {
    error_log('AI-Web-Site: About to initialize main plugin class');
}
AI_Web_Site_Plugin::get_instance();
if (function_exists('error_log')) {
    error_log('AI-Web-Site: Main plugin class initialized');
}
