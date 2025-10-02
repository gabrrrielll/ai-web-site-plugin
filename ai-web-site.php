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
require_once AI_WEB_SITE_PLUGIN_DIR . 'includes/class-website-manager.php'; // NEW
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

        AI_Web_Site_Admin::get_instance();

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
            // Încarcă configurația din fișierul public/site-config.json
            $config_file = AI_WEB_SITE_PLUGIN_DIR . '../frontend/public/site-config.json';
            
            if (file_exists($config_file)) {
                $config_content = file_get_contents($config_file);
                $config_data = json_decode($config_content, true);
                
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
                        $logger->info('PLUGIN', 'DEFAULT_CONFIG', 'Default editor configuration created', array(
                            'website_id' => $result['website_id']
                        ));
                        
                    } catch (Exception $e) {
                        $logger = AI_Web_Site_Debug_Logger::get_instance();
                        $logger->error('PLUGIN', 'DEFAULT_CONFIG', 'Failed to create default editor configuration', array(
                            'error' => $e->getMessage()
                        ));
                    }
                }
            }
        }
    }
}

// Initialize the plugin
AI_Web_Site_Plugin::get_instance();
