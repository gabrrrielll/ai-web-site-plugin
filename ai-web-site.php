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
require_once AI_WEB_SITE_PLUGIN_DIR . 'includes/class-ai-web-site.php';
require_once AI_WEB_SITE_PLUGIN_DIR . 'includes/class-cpanel-api.php';
require_once AI_WEB_SITE_PLUGIN_DIR . 'includes/class-database.php';
require_once AI_WEB_SITE_PLUGIN_DIR . 'includes/class-ump-integration.php';
require_once AI_WEB_SITE_PLUGIN_DIR . 'includes/class-home-page-shortcode.php';
require_once AI_WEB_SITE_PLUGIN_DIR . 'includes/class-subscription-manager.php'; // NEW: Subscription management
require_once AI_WEB_SITE_PLUGIN_DIR . 'includes/class-security-manager.php'; // NEW: Security management
require_once AI_WEB_SITE_PLUGIN_DIR . 'includes/class-website-manager.php'; // NEW: Website management
require_once AI_WEB_SITE_PLUGIN_DIR . 'includes/class-user-site-shortcode.php';
require_once AI_WEB_SITE_PLUGIN_DIR . 'admin/class-admin.php';

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
    }

    /**
     * Initialize plugin
     */
    public function init()
    {
        // Initialize main classes
        AI_Web_Site::get_instance();
        AI_Web_Site_CPanel_API::get_instance();
        AI_Web_Site_Database::get_instance();
        AI_Web_Site_Website_Manager::get_instance();
        AI_Web_Site_Admin::get_instance();

        // Initialize UMP integration and domain override
        $ump_integration = AI_Web_Site_UMP_Integration::get_instance();
        $ump_integration->init_domain_override();

        // Initialize shortcodes
        AI_Web_Site_Home_Page_Shortcode::get_instance();
        AI_Web_Site_User_Site_Shortcode::get_instance();

        // Register REST API routes
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // Load text domain for translations
        load_plugin_textdomain('ai-web-site-plugin', false, dirname(plugin_basename(__FILE__)) . '/languages');
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

        // Create website manager table
        $website_manager = AI_Web_Site_Website_Manager::get_instance();
        $website_manager->create_table();

        // Set default options
        $default_options = array(
            'cpanel_username' => '',
            'cpanel_api_token' => '',
            'main_domain' => 'ai-web.site'
        );
        add_option('ai_web_site_options', $default_options);

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        flush_rewrite_rules();
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes()
    {
        $website_manager = AI_Web_Site_Website_Manager::get_instance();

        // Register REST API route for getting website config by ID
        register_rest_route('ai-web-site/v1', '/website/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($website_manager, 'rest_get_website_config_by_id'),
            'permission_callback' => '__return_true',
        ));

        // Register REST API route for getting website config by domain
        register_rest_route('ai-web-site/v1', '/website/(?P<domain>[a-zA-Z0-9.-]+)', array(
            'methods' => 'GET',
            'callback' => array($website_manager, 'rest_get_website_config_by_domain'),
            'permission_callback' => '__return_true',
        ));

        // Register REST API route for adding a subdomain
        register_rest_route('ai-web-site/v1', '/user-site/add-subdomain', array(
            'methods' => 'POST',
            'callback' => array($website_manager, 'rest_add_user_subdomain'),
            'permission_callback' => array($this, 'check_user_permissions'),
        ));

        // Register simpler endpoint for admin compatibility
        register_rest_route('ai-web-site/v1', '/add-subdomain', array(
            'methods' => 'POST',
            'callback' => array($website_manager, 'rest_add_user_subdomain'),
            'permission_callback' => array($this, 'check_user_permissions'),
        ));

        // Register REST API route for deleting a website
        register_rest_route('ai-web-site/v1', '/user-site/delete', array(
            'methods' => 'POST',
            'callback' => array($website_manager, 'rest_delete_user_website'),
            'permission_callback' => array($this, 'check_user_permissions'),
        ));
    }

    /**
     * Check user permissions for REST API routes
     */
    public function check_user_permissions()
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }

        $ump_integration = AI_Web_Site_UMP_Integration::get_instance();
        $required_ump_level_id = $ump_integration->get_required_ump_level_id();

        return ($required_ump_level_id === 0 || $ump_integration->user_has_active_ump_level($user_id, $required_ump_level_id));
    }
}

// Initialize the plugin
AI_Web_Site_Plugin::get_instance();
