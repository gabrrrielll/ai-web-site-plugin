<?php
/**
 * User Site Routes
 * 
 * Handles user-initiated site management routes (usually from shortcodes).
 * 
 * ENDPOINTS:
 * - POST /user-site/add-subdomain - Add a subdomain
 * - POST /user-site/delete        - Delete a website
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
 * User site routes class
 */
class AI_Web_Site_User_Routes extends AI_Web_Site_Base_Routes {
    
    /**
     * Website manager instance
     * @var AI_Web_Site_Website_Manager
     */
    private $manager;
    
    /**
     * Main plugin instance (for permission checks)
     * @var AI_Web_Site_Plugin
     */
    private $plugin;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->manager = AI_Web_Site_Website_Manager::get_instance();
        $this->plugin = AI_Web_Site_Plugin::get_instance();
    }
    
    /**
     * Get all route definitions
     * 
     * @return array Route definitions
     */
    public function get_routes() {
        return array(
            // ===================================================================
            // Add a subdomain (user initiated from shortcode)
            // URL: /wp-json/ai-web-site/v1/user-site/add-subdomain
            // ===================================================================
            '/user-site/add-subdomain' => array(
                'methods' => 'POST',
                'callback' => array($this->manager, 'rest_add_user_subdomain'),
                'permission_callback' => array($this->plugin, 'check_user_permissions'),
            ),
            
            // ===================================================================
            // Delete a website (user initiated from shortcode)
            // URL: /wp-json/ai-web-site/v1/user-site/delete
            // ===================================================================
            '/user-site/delete' => array(
                'methods' => 'POST',
                'callback' => array($this->manager, 'rest_delete_user_website'),
                'permission_callback' => array($this->plugin, 'check_user_permissions'),
            ),
        );
    }
}
