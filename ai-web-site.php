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

        // Initialize UMP integration and domain override
        $ump_integration = AI_Web_Site_UMP_Integration::get_instance();
        $ump_integration->init_domain_override();

        // Initialize home page shortcode
        AI_Web_Site_Home_Page_Shortcode::get_instance();

        // Initialize website manager
        AI_Web_Site_Website_Manager::get_instance();

        // Initialize user site shortcode
        AI_Web_Site_User_Site_Shortcode::get_instance();

        AI_Web_Site_Admin::get_instance();

        // Enqueue frontend scripts and styles for shortcode
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

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

        // Create default configuration for editor.ai-web.site
        $this->create_default_editor_config();

        // Forțează recrearea configurației default (pentru a repara înregistrările corupte)
        $this->force_recreate_default_config();

        // Flush rewrite rules
        flush_rewrite_rules();
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
     * Create default configuration for editor.ai-web.site
     */
    private function create_default_editor_config()
    {
        $website_manager = AI_Web_Site_Website_Manager::get_instance();

        // Verifică dacă configurația pentru editor.ai-web.site există deja
        $existing_config = $website_manager->get_website_config_by_domain('editor.ai-web.site');

        if ($existing_config === null) {
            // Încarcă configurația din URL-ul pe server
            $config_url = 'https://ai-web.site/wp-content/uploads/site-config.json';

            $logger = AI_Web_Site_Debug_Logger::get_instance();
            $logger->info('PLUGIN', 'DEFAULT_CONFIG', 'Loading default config from URL: ' . $config_url);

            // Încarcă configurația din URL
            $config_content = wp_remote_get($config_url);

            if (is_wp_error($config_content)) {
                $logger->error('PLUGIN', 'DEFAULT_CONFIG', 'Failed to load config from URL', array(
                    'error' => $config_content->get_error_message(),
                    'url' => $config_url
                ));
                return;
            }

            $config_body = wp_remote_retrieve_body($config_content);
            $config_data = json_decode($config_body, true);

            if ($config_data) {
                // Salvează configurația pentru editor.ai-web.site
                $save_data = array(
                    'config' => $config_data,
                    'domain' => 'editor.ai-web.site',
                    'subdomain' => 'editor'
                );

                try {
                    $result = $website_manager->save_website_config($save_data);

                    $logger = AI_Web_Site_Debug_Logger::get_instance();
                    $logger->info('PLUGIN', 'DEFAULT_CONFIG', 'Default editor configuration created from URL', array(
                        'website_id' => $result['website_id'],
                        'config_url' => $config_url,
                        'config_size' => strlen($config_body)
                    ));

                } catch (Exception $e) {
                    $logger = AI_Web_Site_Debug_Logger::get_instance();
                    $logger->error('PLUGIN', 'DEFAULT_CONFIG', 'Failed to create default editor configuration', array(
                        'error' => $e->getMessage(),
                        'config_url' => $config_url
                    ));
                }
            } else {
                $logger = AI_Web_Site_Debug_Logger::get_instance();
                $logger->error('PLUGIN', 'DEFAULT_CONFIG', 'Failed to parse config from URL', array(
                    'config_url' => $config_url,
                    'response_code' => wp_remote_retrieve_response_code($config_content)
                ));
            }
        }
    }

    /**
     * Forțează recrearea configurației default (pentru a repara înregistrările corupte)
     */
    private function force_recreate_default_config()
    {
        $website_manager = AI_Web_Site_Website_Manager::get_instance();
        $logger = AI_Web_Site_Debug_Logger::get_instance();

        // URL-ul configurației default
        $config_url = 'https://ai-web.site/wp-content/uploads/site-config.json';

        $logger->info('PLUGIN', 'FORCE_RECREATE', 'Force recreating default config from URL: ' . $config_url);

        // Încarcă configurația din URL
        $config_content = wp_remote_get($config_url);

        if (is_wp_error($config_content)) {
            $logger->error('PLUGIN', 'FORCE_RECREATE', 'Failed to load config from URL', array(
                'error' => $config_content->get_error_message(),
                'url' => $config_url
            ));
            return;
        }

        $config_body = wp_remote_retrieve_body($config_content);
        $config_data = json_decode($config_body, true);

        if ($config_data) {
            // Șterge configurația existentă pentru editor.ai-web.site (dacă există)
            global $wpdb;
            $table_name = $wpdb->prefix . 'ai_web_site_websites';

            $deleted = $wpdb->delete(
                $table_name,
                array('domain' => 'editor.ai-web.site'),
                array('%s')
            );

            if ($deleted > 0) {
                $logger->info('PLUGIN', 'FORCE_RECREATE', 'Deleted existing corrupted config', array(
                    'deleted_count' => $deleted
                ));
            }

            // Salvează configurația nouă pentru editor.ai-web.site
            $save_data = array(
                'config' => $config_data,
                'domain' => 'editor.ai-web.site',
                'subdomain' => 'editor'
            );

            try {
                $result = $website_manager->save_website_config($save_data);

                $logger->info('PLUGIN', 'FORCE_RECREATE', 'Default editor configuration force recreated', array(
                    'website_id' => $result['website_id'],
                    'config_url' => $config_url,
                    'config_size' => strlen($config_body)
                ));

            } catch (Exception $e) {
                $logger->error('PLUGIN', 'FORCE_RECREATE', 'Failed to force recreate default editor configuration', array(
                    'error' => $e->getMessage(),
                    'config_url' => $config_url
                ));
            }
        } else {
            $logger->error('PLUGIN', 'FORCE_RECREATE', 'Failed to parse config from URL', array(
                'config_url' => $config_url,
                'response_code' => wp_remote_retrieve_response_code($config_content)
            ));
        }
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_assets()
    {
        // Enqueue styles for the admin
        wp_register_style('ai-web-site-admin-style', plugins_url('assets/admin.css', __FILE__), array(), AI_WEB_SITE_PLUGIN_VERSION, 'all');
        wp_enqueue_style('ai-web-site-admin-style');

        // Enqueue scripts for the admin
        wp_register_script('ai-web-site-admin-script', plugins_url('assets/admin.js', __FILE__), array('jquery'), AI_WEB_SITE_PLUGIN_VERSION, true);
        wp_enqueue_script('ai-web-site-admin-script');
    }

    /**
     * Enqueue frontend scripts and styles for shortcode
     * ✅ Dezactivat - fișierele se încarcă direct în HTML pentru a evita problemele de MIME type
     */
    public function enqueue_frontend_assets()
    {
        // ✅ Dezactivat - fișierele se încarcă direct în shortcode
        // Această metodă evita problemele de MIME type de pe server
        return;

        // Codul original comentat pentru referință:
        /*
        wp_register_style('ai-web-site-admin-style', plugins_url('assets/admin.css', __FILE__), array(), AI_WEB_SITE_PLUGIN_VERSION, 'all');
        wp_enqueue_style('ai-web-site-admin-style');
        wp_register_script('ai-web-site-admin-script', plugins_url('assets/admin.js', __FILE__), array('jquery'), AI_WEB_SITE_PLUGIN_VERSION, true);
        wp_enqueue_script('ai-web-site-admin-script');
        */
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes()
    {
        // Ensure the website manager is initialized
        $website_manager = AI_Web_Site_Website_Manager::get_instance();

        // Register a REST API route for getting a single website config by ID
        register_rest_route('ai-web-site/v1', '/website/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($website_manager, 'rest_get_website_config_by_id'),
            'permission_callback' => '__return_true',
        ));

        // Register a REST API route for getting a single website config by domain
        register_rest_route('ai-web-site/v1', '/website/(?P<domain>[a-zA-Z0-9.-]+)', array(
            'methods' => 'GET',
            'callback' => array($website_manager, 'rest_get_website_config_by_domain'),
            'permission_callback' => '__return_true',
        ));

        // Register a REST API route for adding a subdomain (user initiated from shortcode)
        register_rest_route('ai-web-site/v1', '/user-site/add-subdomain', array(
            'methods' => 'POST',
            'callback' => array($website_manager, 'rest_add_user_subdomain'),
            'permission_callback' => array($this, 'check_user_permissions'),
        ));

        // Register a REST API route for deleting a website (user initiated from shortcode)
        register_rest_route('ai-web-site/v1', '/user-site/delete', array(
            'methods' => 'POST',
            'callback' => array($website_manager, 'rest_delete_user_website'),
            'permission_callback' => array($this, 'check_user_permissions'),
        ));
    }

    /**
     * Check user permissions for REST API routes (logged in and active subscription)
     */
    public function check_user_permissions()
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }

        if (!class_exists('AI_Web_Site_UMP_Integration')) {
            require_once AI_WEB_SITE_PLUGIN_DIR . 'includes/class-ump-integration.php';
        }
        $ump_integration = AI_Web_Site_UMP_Integration::get_instance();
        $required_ump_level_id = $ump_integration->get_required_ump_level_id();

        // Allow if no UMP level is required, or if user has an active level
        return ($required_ump_level_id === 0 || $ump_integration->user_has_active_ump_level($user_id, $required_ump_level_id));
    }

    /**
     * Callback for adding a subdomain
     */
    public function add_subdomain_callback($request)
    {
        // This method is now handled by AI_Web_Site_Website_Manager::rest_add_user_subdomain
        // This placeholder method is kept for backwards compatibility or if called by old hooks
        return new WP_REST_Response(array('success' => false, 'message' => 'Deprecated: Use /user-site/add-subdomain endpoint.'), 400);
    }

    /**
     * Callback for deleting a website
     */
    public function delete_website_callback($request)
    {
        // This method is now handled by AI_Web_Site_Website_Manager::rest_delete_user_website
        // This placeholder method is kept for backwards compatibility or if called by old hooks
        return new WP_REST_Response(array('success' => false, 'message' => 'Deprecated: Use /user-site/delete endpoint.'), 400);
    }
}

// Initialize the plugin
AI_Web_Site_Plugin::get_instance();
