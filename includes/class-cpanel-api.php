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
        // Log class initialization
        if (function_exists('error_log')) {
            error_log('AI-Web-Site: AI_Web_Site_CPanel_API class initialized');
        }
        
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

        // Log configuration loading
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->debug('CPANEL_API', 'CONFIG_LOAD', 'Configuration loaded', array(
            'username' => $this->config['username'],
            'host' => $this->config['host'],
            'main_domain' => $this->config['main_domain'],
            'api_token_length' => strlen($this->config['api_token'])
        ));
    }

    /**
     * Create subdomain
     */
    public function create_subdomain($subdomain, $domain, $target_ip = null)
    {
        $logger = AI_Web_Site_Debug_Logger::get_instance();

        $logger->info('CPANEL_API', 'CREATE_SUBDOMAIN_START', 'Starting subdomain creation', array(
            'subdomain' => $subdomain,
            'domain' => $domain,
            'target_ip' => $target_ip
        ));

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

        $logger->debug('CPANEL_API', 'CREATE_SUBDOMAIN_REQUEST', 'Making API request', array(
            'api_url' => $api_url,
            'params' => $params,
            'username' => $this->config['username']
        ));

        // Make API request
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Authorization' => 'cpanel ' . $this->config['username'] . ':' . $this->config['api_token']
            ),
            'body' => $params,
            'sslverify' => false,
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $logger->error('CPANEL_API', 'CREATE_SUBDOMAIN_ERROR', 'HTTP request failed', array(
                'error' => $error_message,
                'error_code' => $response->get_error_code()
            ));
            return array(
                'success' => false,
                'message' => $error_message
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $logger->debug('CPANEL_API', 'CREATE_SUBDOMAIN_RESPONSE', 'API response received', array(
            'response_code' => $response_code,
            'body' => $body
        ));

        $result = json_decode($body, true);

        if (isset($result['status']) && $result['status'] === 1) {
            $logger->info('CPANEL_API', 'CREATE_SUBDOMAIN_SUCCESS', 'Subdomain created successfully');
            return array(
                'success' => true,
                'message' => 'Subdomain created successfully'
            );
        } else {
            $error_message = 'Unknown error';
            if (isset($result['errors']) && is_array($result['errors'])) {
                $error_message = implode(', ', $result['errors']);
            }

            $logger->error('CPANEL_API', 'CREATE_SUBDOMAIN_ERROR', 'API returned error', array(
                'result' => $result,
                'error_message' => $error_message
            ));

            return array(
                'success' => false,
                'message' => $error_message
            );
        }
    }

    /**
     * Delete subdomain
     */
    public function delete_subdomain($subdomain, $domain)
    {
        if (empty($this->config['api_token'])) {
            return array(
                'success' => false,
                'message' => 'cPanel API token not configured'
            );
        }

        // Prepare API URL
        $api_url = "https://{$this->config['host']}:2083/execute/SubDomain/delsubdomain";

        // Prepare parameters
        $params = array(
            'domain' => $subdomain,
            'rootdomain' => $domain
        );

        // Make API request
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Authorization' => 'cpanel ' . $this->config['username'] . ':' . $this->config['api_token']
            ),
            'body' => $params,
            'sslverify' => false,
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (isset($result['status']) && $result['status'] === 1) {
            return array(
                'success' => true,
                'message' => 'Subdomain deleted successfully'
            );
        } else {
            $error_message = 'Unknown error';
            if (isset($result['errors']) && is_array($result['errors'])) {
                $error_message = implode(', ', $result['errors']);
            }

            return array(
                'success' => false,
                'message' => $error_message
            );
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

        $logger->debug('CPANEL_API', 'TEST_CONNECTION_REQUEST', 'Making test API request', array(
            'api_url' => $api_url,
            'username' => $this->config['username']
        ));

        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'cpanel ' . $this->config['username'] . ':' . $this->config['api_token']
            ),
            'sslverify' => false,
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $logger->error('CPANEL_API', 'TEST_CONNECTION_ERROR', 'HTTP request failed', array(
                'error' => $error_message,
                'error_code' => $response->get_error_code()
            ));
            return array(
                'success' => false,
                'message' => $error_message
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $logger->debug('CPANEL_API', 'TEST_CONNECTION_RESPONSE', 'Test API response received', array(
            'response_code' => $response_code,
            'body' => $body
        ));

        $result = json_decode($body, true);

        if (isset($result['status']) && $result['status'] === 1) {
            $logger->info('CPANEL_API', 'TEST_CONNECTION_SUCCESS', 'Connection test successful');
            return array(
                'success' => true,
                'message' => 'API connection successful'
            );
        } else {
            $logger->error('CPANEL_API', 'TEST_CONNECTION_ERROR', 'Connection test failed', array(
                'result' => $result
            ));
            return array(
                'success' => false,
                'message' => 'API connection failed'
            );
        }
    }
}
