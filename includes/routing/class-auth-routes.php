<?php
/**
 * Authentication Routes
 * 
 * Handles authentication and security related routes.
 * 
 * CRITICAL ENDPOINT:
 * - GET /wp-nonce - Get WordPress nonce for authentication
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
 * Authentication routes class
 */
class AI_Web_Site_Auth_Routes extends AI_Web_Site_Base_Routes {
    
    /**
     * Website manager instance
     * @var AI_Web_Site_Website_Manager
     */
    private $manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Get website manager instance
        $this->manager = AI_Web_Site_Website_Manager::get_instance();
    }
    
    /**
     * Get all route definitions
     * 
     * @return array Route definitions
     */
    public function get_routes() {
        return array(
            // ===================================================================
            // CRITICAL: Get WordPress nonce for authentication
            // Used by: frontend/utils/api.ts (line 51)
            // URL: /wp-json/ai-web-site/v1/wp-nonce
            // Returns: { success: true, nonce: "..." }
            // ===================================================================
            '/wp-nonce' => array(
                'methods' => 'GET',
                'callback' => array($this->manager, 'rest_get_wp_nonce'),
                'permission_callback' => '__return_true', // Public access to get nonce
            ),
        );
    }
}
