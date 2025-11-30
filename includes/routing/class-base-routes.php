<?php
/**
 * Base Route Class
 * 
 * Abstract class for all route classes in the plugin.
 * Provides common functionality for route registration and permission handling.
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
 * Base class for all route handlers
 */
abstract class AI_Web_Site_Base_Routes {
    
    /**
     * API namespace
     * @var string
     */
    protected $namespace = 'ai-web-site';
    
    /**
     * API version
     * @var string
     */
    protected $version = 'v1';
    
    /**
     * Get all routes defined by this class
     * Each child class must implement this method
     * 
     * @return array Array of route definitions
     */
    abstract public function get_routes();
    
    /**
     * Register all routes for the specified version
     * 
     * @param string $version API version (default: 'v1')
     * @return void
     */
    public function register($version = 'v1') {
        $this->version = $version;
        $namespace = "{$this->namespace}/{$version}";
        
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        
        foreach ($this->get_routes() as $route => $config) {
            $result = register_rest_route($namespace, $route, $config);
            
            if ($result) {
                $logger->info('ROUTING', 'ROUTE_REGISTERED', "Route registered: /{$namespace}{$route}");
            } else {
                $logger->error('ROUTING', 'ROUTE_FAILED', "Failed to register route: /{$namespace}{$route}");
            }
        }
    }
    
    /**
     * Get permission callback based on type
     * 
     * @param string $type Permission type: 'public', 'authenticated', 'admin'
     * @return callable Permission callback
     */
    protected function get_permission_callback($type = 'authenticated') {
        switch ($type) {
            case 'public':
                return '__return_true';
            
            case 'authenticated':
                // Use the main plugin's permission check
                return array($this, 'check_authenticated_permission');
            
            case 'admin':
                return array($this, 'check_admin_permission');
            
            default:
                return '__return_false';
        }
    }
    
    /**
     * Check if user is authenticated (logged in and has active subscription)
     * 
     * @param WP_REST_Request $request The request object
     * @return bool|WP_Error True if authenticated, WP_Error otherwise
     */
    public function check_authenticated_permission($request) {
        // Get user ID
        $user_id = get_current_user_id();
        
        // Allow OPTIONS requests for CORS preflight
        if ($request->get_method() === 'OPTIONS') {
            return true;
        }
        
        // Check origin for localhost bypass
        $headers = getallheaders();
        $origin = $headers['Origin'] ?? $headers['origin'] ?? '';
        
        // Localhost bypass for development
        if (strpos($origin, 'localhost') !== false || strpos($origin, '127.0.0.1') !== false) {
            return true;
        }
        
        // Local API key bypass
        $local_api_key = $headers['X-Local-API-Key'] ?? $headers['x-local-api-key'] ?? '';
        if ($local_api_key === 'dev-local-key-2024') {
            return true;
        }
        
        // Check if user is logged in
        if (!$user_id || $user_id <= 0) {
            return new WP_Error(
                'not_logged_in',
                'You must be logged in to access this resource',
                array('status' => 401)
            );
        }
        
        // Check subscription if needed
        if (class_exists('AI_Web_Site_Subscription_Manager')) {
            $subscription_manager = AI_Web_Site_Subscription_Manager::get_instance();
            $can_access = $subscription_manager->can_save_configuration($user_id);
            
            if (!$can_access['allowed']) {
                return new WP_Error(
                    'subscription_required',
                    $can_access['message'],
                    array(
                        'status' => 403,
                        'reason' => $can_access['reason']
                    )
                );
            }
        }
        
        return true;
    }
    
    /**
     * Check if user is admin
     * 
     * @param WP_REST_Request $request The request object
     * @return bool|WP_Error True if admin, WP_Error otherwise
     */
    public function check_admin_permission($request) {
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'forbidden',
                'You do not have permission to access this resource',
                array('status' => 403)
            );
        }
        
        return true;
    }
    
    /**
     * Validate domain parameter
     * 
     * @param string $param Domain parameter value
     * @param WP_REST_Request $request Request object
     * @param string $key Parameter key
     * @return bool True if valid
     */
    public function validate_domain($param, $request, $key) {
        // Basic domain validation
        if (!preg_match('/^[a-zA-Z0-9.-]+$/', $param)) {
            return false;
        }
        
        // Additional validation can be added here
        return true;
    }
    
    /**
     * Sanitize domain parameter
     * 
     * @param string $param Domain parameter value
     * @param WP_REST_Request $request Request object
     * @param string $key Parameter key
     * @return string Sanitized domain
     */
    public function sanitize_domain($param, $request, $key) {
        return sanitize_text_field($param);
    }
    
    /**
     * Get common domain argument schema
     * 
     * @return array Argument schema
     */
    protected function get_domain_arg() {
        return array(
            'description' => 'Domain name',
            'type' => 'string',
            'required' => true,
            'validate_callback' => array($this, 'validate_domain'),
            'sanitize_callback' => array($this, 'sanitize_domain')
        );
    }
    
    /**
     * Get common ID argument schema
     * 
     * @return array Argument schema
     */
    protected function get_id_arg() {
        return array(
            'description' => 'Unique identifier',
            'type' => 'integer',
            'required' => true,
            'minimum' => 1,
            'validate_callback' => function($param) {
                return is_numeric($param) && $param > 0;
            },
            'sanitize_callback' => 'absint'
        );
    }
}
