<?php
/**
 * Website Routes
 * 
 * Handles all website configuration related routes.
 * These are the CRITICAL routes used by the frontend editor.
 * 
 * CRITICAL ENDPOINTS (DO NOT REMOVE):
 * - GET  /website-config/{domain} - Load site configuration
 * - POST /website-config         - Save site configuration
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
 * Website routes class
 */
class AI_Web_Site_Website_Routes extends AI_Web_Site_Base_Routes {
    
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
            // CRITICAL: Get website configuration by domain
            // Used by: frontend/services/ConfigService.ts (line 126, 133)
            // URL: /wp-json/ai-web-site/v1/website-config/{domain}
            // ===================================================================
            '/website-config/(?P<domain>[a-zA-Z0-9.-]+)' => array(
                'methods' => 'GET',
                'callback' => array($this->manager, 'rest_get_website_config_by_domain'),
                'permission_callback' => '__return_true', // Public access for loading sites
                'args' => array(
                    'domain' => $this->get_domain_arg(),
                ),
            ),
            
            // ===================================================================
            // CRITICAL: Save website configuration
            // Used by: frontend/utils/api.ts (line 123, 190-196)
            // URL: /wp-json/ai-web-site/v1/website-config
            // Method: POST
            // Payload: { config: {...}, domain: string, subdomain: string }
            // Headers: X-WP-Nonce or X-Local-API-Key
            // ===================================================================
            '/website-config' => array(
                'methods' => 'POST',
                'callback' => array($this->manager, 'rest_save_website_config'),
                'permission_callback' => array($this->manager, 'check_save_permissions'),
                'args' => array(
                    'config' => array(
                        'description' => 'Website configuration object',
                        'type' => 'object',
                        'required' => true,
                    ),
                    'domain' => array(
                        'description' => 'Full domain name',
                        'type' => 'string',
                        'required' => true,
                    ),
                    'subdomain' => array(
                        'description' => 'Subdomain name',
                        'type' => 'string',
                        'required' => false,
                    ),
                ),
            ),
            
            // ===================================================================
            // BACKWARD COMPATIBILITY: Old endpoint redirects
            // These ensure old frontend versions still work
            // ===================================================================
            
            // Old endpoint: /website/{domain} -> redirect to /website-config/{domain}
            '/website/(?P<domain>[a-zA-Z0-9.-]+)' => array(
                'methods' => 'GET',
                'callback' => array($this->manager, 'rest_get_website_config_by_domain'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'domain' => $this->get_domain_arg(),
                ),
            ),
        );
    }
}
