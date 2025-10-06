<?php
/**
 * Security Manager Class
 * 
 * Handles security features like rate limiting, input sanitization, and file size validation
 * 
 * @package AI_Web_Site
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Web_Site_Security_Manager
{
    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Debug logger instance
     */
    private $logger;

    /**
     * Plugin options
     */
    private $options;

    /**
     * Get singleton instance
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
        $this->logger = AI_Web_Site_Debug_Logger::get_instance();
        $this->options = get_option('ai_web_site_options', array());
        $this->logger->info('SECURITY_MANAGER', 'INIT', 'Security Manager initialized');
    }

    /**
     * Check rate limit for user
     * 
     * @param int $user_id WordPress user ID
     * @return array Result with 'allowed' boolean and 'message'
     */
    public function check_rate_limit($user_id)
    {
        // Get rate limit settings
        $max_requests = (int)($this->options['rate_limit_requests'] ?? 100);
        $time_period = (int)($this->options['rate_limit_period'] ?? 3600);

        // Transient key for this user
        $transient_key = 'ai_web_site_rate_limit_' . $user_id;
        
        // Get current count
        $current_count = get_transient($transient_key);
        
        if ($current_count === false) {
            // First request in this period
            set_transient($transient_key, 1, $time_period);
            
            $this->logger->info('SECURITY_MANAGER', 'RATE_LIMIT_START', 'Rate limit tracking started', array(
                'user_id' => $user_id,
                'period' => $time_period . 's',
                'max_requests' => $max_requests
            ));
            
            return array(
                'allowed' => true,
                'remaining' => $max_requests - 1,
                'message' => 'Request allowed'
            );
        }
        
        $current_count = (int)$current_count;
        
        if ($current_count >= $max_requests) {
            // Rate limit exceeded
            if ($this->is_security_logging_enabled()) {
                error_log("AI-WEB-SITE SECURITY: ❌ RATE LIMIT EXCEEDED - User: {$user_id}, Count: {$current_count}/{$max_requests}");
            }
            
            $this->logger->warning('SECURITY_MANAGER', 'RATE_LIMIT_EXCEEDED', 'Rate limit exceeded', array(
                'user_id' => $user_id,
                'current_count' => $current_count,
                'max_requests' => $max_requests,
                'period' => $time_period . 's'
            ));
            
            return array(
                'allowed' => false,
                'remaining' => 0,
                'message' => sprintf(
                    'Rate limit exceeded. Maximum %d requests per %s. Please try again later.',
                    $max_requests,
                    $this->format_time_period($time_period)
                )
            );
        }
        
        // Increment counter
        set_transient($transient_key, $current_count + 1, $time_period);
        
        return array(
            'allowed' => true,
            'remaining' => $max_requests - ($current_count + 1),
            'message' => 'Request allowed'
        );
    }

    /**
     * Validate configuration file size
     * 
     * @param string $config_json JSON string of configuration
     * @return array Result with 'valid' boolean and 'message'
     */
    public function validate_config_size($config_json)
    {
        $max_size_mb = (float)($this->options['max_config_size'] ?? 5);
        $max_size_bytes = $max_size_mb * 1024 * 1024; // Convert MB to bytes
        
        $config_size = strlen($config_json);
        
        if ($config_size > $max_size_bytes) {
            if ($this->is_security_logging_enabled()) {
                error_log("AI-WEB-SITE SECURITY: ❌ CONFIG SIZE EXCEEDED - Size: " . number_format($config_size / 1024 / 1024, 2) . "MB / Max: {$max_size_mb}MB");
            }
            
            $this->logger->warning('SECURITY_MANAGER', 'CONFIG_SIZE_EXCEEDED', 'Configuration size limit exceeded', array(
                'size_bytes' => $config_size,
                'size_mb' => number_format($config_size / 1024 / 1024, 2),
                'max_size_mb' => $max_size_mb
            ));
            
            return array(
                'valid' => false,
                'message' => sprintf(
                    'Configuration too large. Size: %sMB, Maximum allowed: %sMB',
                    number_format($config_size / 1024 / 1024, 2),
                    $max_size_mb
                )
            );
        }
        
        return array(
            'valid' => true,
            'size_mb' => number_format($config_size / 1024 / 1024, 2),
            'message' => 'Configuration size is valid'
        );
    }

    /**
     * Sanitize configuration data
     * 
     * @param mixed $data Configuration data (can be array or string)
     * @return mixed Sanitized data
     */
    public function sanitize_config_data($data)
    {
        // Check if sanitization is enabled
        $sanitization_enabled = (int)($this->options['enable_input_sanitization'] ?? 1);
        
        if (!$sanitization_enabled) {
            return $data;
        }
        
        if (is_array($data)) {
            // Recursively sanitize array
            return array_map(array($this, 'sanitize_config_data'), $data);
        }
        
        if (is_string($data)) {
            // Sanitize string - allow safe HTML but strip dangerous tags
            return wp_kses_post($data);
        }
        
        // Return other types as-is (numbers, booleans, etc.)
        return $data;
    }

    /**
     * Log security event
     * 
     * @param string $event_type Type of security event
     * @param array $data Event data
     */
    public function log_security_event($event_type, $data = array())
    {
        if (!$this->is_security_logging_enabled()) {
            return;
        }
        
        error_log("AI-WEB-SITE SECURITY: [{$event_type}] " . json_encode($data));
        
        $this->logger->warning('SECURITY_MANAGER', $event_type, 'Security event logged', $data);
    }

    /**
     * Check if security logging is enabled
     * 
     * @return bool
     */
    private function is_security_logging_enabled()
    {
        return (int)($this->options['enable_security_logging'] ?? 0) === 1;
    }

    /**
     * Format time period in human-readable format
     * 
     * @param int $seconds Time in seconds
     * @return string Formatted time
     */
    private function format_time_period($seconds)
    {
        if ($seconds < 60) {
            return $seconds . ' second' . ($seconds != 1 ? 's' : '');
        }
        
        if ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            return $minutes . ' minute' . ($minutes != 1 ? 's' : '');
        }
        
        if ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            return $hours . ' hour' . ($hours != 1 ? 's' : '');
        }
        
        $days = floor($seconds / 86400);
        return $days . ' day' . ($days != 1 ? 's' : '');
    }

    /**
     * Get rate limit status for user
     * 
     * @param int $user_id WordPress user ID
     * @return array Status information
     */
    public function get_rate_limit_status($user_id)
    {
        $transient_key = 'ai_web_site_rate_limit_' . $user_id;
        $current_count = get_transient($transient_key);
        
        $max_requests = (int)($this->options['rate_limit_requests'] ?? 100);
        $time_period = (int)($this->options['rate_limit_period'] ?? 3600);
        
        if ($current_count === false) {
            return array(
                'requests_used' => 0,
                'requests_remaining' => $max_requests,
                'max_requests' => $max_requests,
                'period' => $this->format_time_period($time_period)
            );
        }
        
        return array(
            'requests_used' => (int)$current_count,
            'requests_remaining' => max(0, $max_requests - (int)$current_count),
            'max_requests' => $max_requests,
            'period' => $this->format_time_period($time_period)
        );
    }

    /**
     * Reset rate limit for user (admin function)
     * 
     * @param int $user_id WordPress user ID
     * @return bool Success status
     */
    public function reset_rate_limit($user_id)
    {
        $transient_key = 'ai_web_site_rate_limit_' . $user_id;
        delete_transient($transient_key);
        
        $this->logger->info('SECURITY_MANAGER', 'RATE_LIMIT_RESET', 'Rate limit reset by admin', array(
            'user_id' => $user_id
        ));
        
        return true;
    }
}

