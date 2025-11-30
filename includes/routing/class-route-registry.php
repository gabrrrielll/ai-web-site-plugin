<?php
/**
 * Route Registry
 * 
 * Central orchestrator for all REST API routes in the plugin.
 * Manages route registration and provides a single source of truth for all endpoints.
 * 
 * @package AI_Web_Site_Plugin
 * @subpackage Routing
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main route registry class
 */
class AI_Web_Site_Route_Registry {
    
    /**
     * Single instance
     * @var AI_Web_Site_Route_Registry|null
     */
    private static $instance = null;
    
    /**
     * Registered route classes
     * @var array
     */
    private $route_classes = array();
    
    /**
     * Get singleton instance
     * 
     * @return AI_Web_Site_Route_Registry
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Hook into rest_api_init to register all routes
        add_action('rest_api_init', array($this, 'register_all_routes'));
    }
    
    /**
     * Register all routes from all route classes
     * 
     * @return void
     */
    public function register_all_routes() {
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('ROUTING', 'REGISTRY_INIT', 'Route Registry initializing...');
        
        // Load route class files
        $this->load_route_classes();
        
        // V1 Routes - current version
        $this->register_version_routes('v1', array(
            new AI_Web_Site_Website_Routes(),
            new AI_Web_Site_Auth_Routes(),
        ));
        
        $logger->info('ROUTING', 'REGISTRY_COMPLETE', 'All routes registered successfully');
        
        // Debug: Log all registered routes
        if (defined('AI_WEB_SITE_DEBUG') && AI_WEB_SITE_DEBUG) {
            $this->log_registered_routes();
        }
    }
    
    /**
     * Load all route class files
     * 
     * @return void
     */
    private function load_route_classes() {
        $routing_dir = AI_WEB_SITE_PLUGIN_DIR . 'includes/routing/';
        
        // Load base class first
        require_once $routing_dir . 'class-base-routes.php';
        
        // Load specific route classes
        require_once $routing_dir . 'class-website-routes.php';
        require_once $routing_dir . 'class-auth-routes.php';
    }
    
    /**
     * Register routes for a specific API version
     * 
     * @param string $version API version (e.g., 'v1')
     * @param array $route_classes Array of route class instances
     * @return void
     */
    private function register_version_routes($version, $route_classes) {
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        
        foreach ($route_classes as $route_class) {
            $class_name = get_class($route_class);
            $logger->info('ROUTING', 'REGISTERING_CLASS', "Registering routes from: {$class_name}");
            
            $route_class->register($version);
            
            // Store reference
            $this->route_classes[$class_name] = $route_class;
        }
    }
    
    /**
     * Get all registered routes (for debugging)
     * 
     * @return array List of all registered routes
     */
    public function get_registered_routes() {
        global $wp_rest_server;
        
        if (!$wp_rest_server) {
            return array();
        }
        
        $routes = $wp_rest_server->get_routes();
        
        // Filter only our routes
        return array_filter($routes, function($route) {
            return strpos($route, '/ai-web-site/') === 0;
        }, ARRAY_FILTER_USE_KEY);
    }
    
    /**
     * Log all registered routes (debugging)
     * 
     * @return void
     */
    private function log_registered_routes() {
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        
        add_action('rest_api_init', function() use ($logger) {
            $routes = $this->get_registered_routes();
            
            $logger->info('ROUTING', 'ROUTES_LIST', 'Registered routes:', array(
                'count' => count($routes),
                'routes' => array_keys($routes)
            ));
        }, 999);
    }
    
    /**
     * Get route class instance by name
     * 
     * @param string $class_name Class name
     * @return object|null Route class instance or null
     */
    public function get_route_class($class_name) {
        return isset($this->route_classes[$class_name]) 
            ? $this->route_classes[$class_name] 
            : null;
    }
}
