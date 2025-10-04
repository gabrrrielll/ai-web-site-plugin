<?php

/**
 * cPanel API integration class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AI_Web_Site_CPanel_API
{
    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * cPanel configuration
     */
    private $config;

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
        $this->load_config();
    }

    /**
     * Load cPanel configuration
     */
    private function load_config()
    {
        $options = get_option('ai_web_site_options', array());

        $this->config = array(
            'username' => $options['cpanel_username'] ?? '',
            'api_token' => $options['cpanel_api_token'] ?? '',
            'main_domain' => $options['main_domain'] ?? 'ai-web.site'
        );

        // Generate cPanel host automatically from main domain
        $this->config['host'] = $this->config['main_domain'];
    }

    /**
     * Create subdomain
     */
    public function create_subdomain($subdomain, $domain, $target_ip = null)
    {
        $logger = AI_Web_Site_Debug_Logger::get_instance();

        if (empty($this->config['api_token'])) {
            $logger->error('CPANEL_API', 'CREATE_SUBDOMAIN_ERROR', 'API token not configured');
            return array(
                'success' => false,
                'message' => 'cPanel API token not configured'
            );
        }

        // Prepare API URL
        $api_url = "https://{$this->config['host']}:2083/execute/SubDomain/addsubdomain";

        // Prepare parameters
        $params = array(
            'domain' => $subdomain,
            'rootdomain' => $domain,
            'dir' => '/editor.ai-web.site', // All subdomains point to editor
            'disallowdot' => 0
        );

        // Make API request
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Authorization' => 'cpanel ' . $this->config['username'] . ':' . $this->config['api_token']
            ),
            'body' => $params,
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $logger->error('CPANEL_API', 'CREATE_SUBDOMAIN_HTTP_ERROR', 'HTTP request failed', array('error' => $error_message));
            return array(
                'success' => false,
                'message' => $error_message
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $result = json_decode($body, true);
        if (isset($result['status']) && $result['status'] === 1) {
            $logger->info('CPANEL_API', 'CREATE_SUBDOMAIN_SUCCESS', 'Subdomain created successfully', array('subdomain' => $subdomain, 'domain' => $domain));
            return array('success' => true, 'message' => 'Subdomain created successfully');
        } else {
            $error_message = isset($result['errors']) && is_array($result['errors']) ? implode(', ', $result['errors']) : 'Unknown error';
            $logger->error('CPANEL_API', 'CREATE_SUBDOMAIN_API_ERROR', 'cPanel API returned error during creation', array('subdomain' => $subdomain, 'domain' => $domain, 'message' => $error_message));
            return array('success' => false, 'message' => $error_message);
        }
    }

    /**
     * Delete subdomain
     */
    public function delete_subdomain($subdomain, $domain)
    {
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        
        // cPanel converts subdomains to lowercase, so we need to do the same
        $subdomain_lower = strtolower($subdomain);
        
        $logger->info('CPANEL_API', 'DELETE_SUBDOMAIN_START', 'Starting subdomain deletion', array(
            'subdomain' => $subdomain,
            'subdomain_lower' => $subdomain_lower,
            'domain' => $domain
        ));

        if (empty($this->config['api_token'])) {
            $logger->error('CPANEL_API', 'DELETE_SUBDOMAIN_ERROR', 'API token not configured');
            return array(
                'success' => false,
                'message' => 'cPanel API token not configured'
            );
        }

        // Prepare API URL for cPanel API 2 (JSON API)
        $api_url = "https://{$this->config['host']}:2083/json-api/cpanel";

        // Prepare parameters for cPanel API 2 (use lowercase subdomain)
        $params = array(
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_module' => 'SubDomain',
            'cpanel_jsonapi_func' => 'delsubdomain',
            'domain' => "{$subdomain_lower}.{$domain}"
        );

        // Make API request
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Authorization' => 'cpanel ' . $this->config['username'] . ':' . $this->config['api_token']
            ),
            'body' => $params,
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $logger->error('CPANEL_API', 'DELETE_SUBDOMAIN_HTTP_ERROR', 'HTTP request failed', array('error' => $error_message));
            return array(
                'success' => false,
                'message' => $error_message
            );
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        // cPanel API 2 JSON API responses are structured differently. Check if result is 1.
        $is_success = isset($result['cpanelresult']['data'][0]['result']) && $result['cpanelresult']['data'][0]['result'] === 1;
        if ($is_success) {
            $logger->info('CPANEL_API', 'DELETE_SUBDOMAIN_SUCCESS', 'Subdomain deleted successfully', array('subdomain' => $subdomain, 'subdomain_lower' => $subdomain_lower, 'domain' => $domain));
            return array(
                'success' => true,
                'message' => $result['cpanelresult']['data'][0]['reason'] ?? 'Subdomain deleted successfully'
            );
        } else {
            $error_message = isset($result['cpanelresult']['errors']) && is_array($result['cpanelresult']['errors']) ? implode(', ', $result['cpanelresult']['errors']) : ($result['cpanelresult']['data'][0]['reason'] ?? 'Unknown error');
            $logger->error('CPANEL_API', 'DELETE_SUBDOMAIN_API_ERROR', 'cPanel API returned error during deletion', array('subdomain' => $subdomain, 'subdomain_lower' => $subdomain_lower, 'domain' => $domain, 'message' => $error_message, 'result' => $result));
            return array('success' => false, 'message' => $error_message);
        }
    }

    /**
     * Test API connection
     */
    public function test_connection()
    {
        $logger = AI_Web_Site_Debug_Logger::get_instance();

        $logger->info('CPANEL_API', 'TEST_CONNECTION_START', 'Starting connection test');

        if (empty($this->config['api_token'])) {
            $logger->error('CPANEL_API', 'TEST_CONNECTION_ERROR', 'API token not configured');
            return array(
                'success' => false,
                'message' => 'cPanel API token not configured'
            );
        }

        // Test with a simple API call
        $api_url = "https://{$this->config['host']}:2083/execute/StatsBar/get_stats";

        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'cpanel ' . $this->config['username'] . ':' . $this->config['api_token']
            ),
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $logger->error('CPANEL_API', 'TEST_CONNECTION_HTTP_ERROR', 'HTTP request failed', array('error' => $error_message));
            return array(
                'success' => false,
                'message' => $error_message
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $result = json_decode($body, true);

        if (isset($result['status']) && $result['status'] === 1) {
            $logger->info('CPANEL_API', 'TEST_CONNECTION_SUCCESS', 'Connection test successful');
            return array(
                'success' => true,
                'message' => 'API connection successful'
            );
        } else {
            $error_message = isset($result['errors']) && is_array($result['errors']) ? implode(', ', $result['errors']) : 'Unknown error';
            $logger->error('CPANEL_API', 'TEST_CONNECTION_API_ERROR', 'cPanel API connection failed', array('message' => $error_message, 'result' => $result));
            return array(
                'success' => false,
                'message' => 'API connection failed'
            );
        }
    }
}
