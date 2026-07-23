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
        $max_requests = max(1, (int) ($this->options['rate_limit_requests'] ?? 100));
        $time_period = max(1, (int) ($this->options['rate_limit_period'] ?? 3600));
        $transient_key = 'ai_web_site_rate_limit_' . absint($user_id);
        $now = time();
        $state = get_transient($transient_key);

        // Preserve a fixed time window instead of extending the transient on
        // every request. Legacy numeric values are safely treated as expired.
        if (!is_array($state) || !isset($state['count'], $state['expires_at']) || (int) $state['expires_at'] <= $now) {
            $state = array(
                'count' => 0,
                'expires_at' => $now + $time_period,
            );
        }

        $current_count = max(0, (int) $state['count']);
        $remaining_ttl = max(1, (int) $state['expires_at'] - $now);

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
        
        $state['count'] = $current_count + 1;
        set_transient($transient_key, $state, $remaining_ttl);
        
        return array(
            'allowed' => true,
            'remaining' => $max_requests - $state['count'],
            'message' => 'Request allowed'
        );
    }

    /**
     * Check and record a user's daily AI usage.
     *
     * @param int    $user_id WordPress user ID.
     * @param string $service One of generate_text or generate_image.
     * @return array Usage decision and counters.
     */
    public function check_and_increment_ai_usage($user_id, $service)
    {
        $limits = array(
            'generate_text' => max(1, (int) ($this->options['ai_text_daily_limit'] ?? 50)),
            'generate_image' => max(1, (int) ($this->options['ai_image_daily_limit'] ?? 20)),
        );

        if (!isset($limits[$service])) {
            return array(
                'allowed' => false,
                'remaining' => 0,
                'message' => 'Unknown AI service.',
                'limit' => 0,
                'used' => 0,
            );
        }

        $limit = $limits[$service];
        $date = gmdate('Y-m-d');
        $transient_key = 'ai_web_site_ai_usage_' . absint($user_id) . '_' . $service . '_' . $date;
        $now = time();
        $tomorrow_utc = strtotime(gmdate('Y-m-d 00:00:00', $now) . ' UTC +1 day');
        $ttl = max(1, $tomorrow_utc - $now);
        $used = max(0, (int) get_transient($transient_key));

        if ($used >= $limit) {
            return array(
                'allowed' => false,
                'remaining' => 0,
                'message' => 'Daily AI usage limit reached. Please try again tomorrow.',
                'limit' => $limit,
                'used' => $used,
            );
        }

        // Transients provide the portable read-check-increment fallback for
        // standard WordPress installations.
        $updated = $used + 1;
        set_transient($transient_key, $updated, $ttl);

        return array(
            'allowed' => true,
            'remaining' => $limit - $updated,
            'message' => 'AI request allowed.',
            'limit' => $limit,
            'used' => $updated,
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
            $sanitized = array();
            foreach ($data as $key => $value) {
                $sanitized[$key] = $this->sanitize_config_value($value, (string) $key);
            }
            return $sanitized;
        }

        return $this->sanitize_config_value($data);
    }

    /**
     * Reject oversized embedded images before configuration persistence.
     *
     * @param mixed $data Configuration data.
     * @return array WP-style validation result.
     */
    public function reject_oversized_data_uris($data)
    {
        if (is_array($data)) {
            foreach ($data as $value) {
                $result = $this->reject_oversized_data_uris($value);
                if (!$result['valid']) {
                    return $result;
                }
            }
        } elseif (is_string($data) && stripos($data, 'data:image') === 0 && strlen($data) > 100000) {
            return array(
                'valid' => false,
                'message' => 'Embedded image data must not exceed 100KB.',
            );
        }

        return array('valid' => true, 'message' => 'Embedded image data is valid.');
    }

    /**
     * Validate configuration embeds before saving.
     *
     * @param array $config_array Configuration data.
     * @return array WP-style validation result.
     */
    public function validate_no_oversized_embeds($config_array)
    {
        return $this->reject_oversized_data_uris($config_array);
    }

    /**
     * Sanitize one configuration value while preserving valid image sources.
     *
     * @param mixed  $value Value to sanitize.
     * @param string $key   Parent configuration key.
     * @return mixed
     */
    private function sanitize_config_value($value, $key = '')
    {
        if (is_array($value)) {
            return $this->sanitize_config_data($value);
        }

        if (!is_string($value)) {
            return $value;
        }

        if (stripos($value, 'data:') === 0) {
            return stripos($value, 'data:image') === 0 && strlen($value) > 100000 ? '' : $value;
        }

        $is_image_url_key = (bool) preg_match('/(?:image|img|logo|thumbnail|avatar|background).*(?:url|src)?|(?:url|src)$/i', $key);
        if ($is_image_url_key && preg_match('#^https?://#i', $value)) {
            return $value;
        }

        return preg_match('/<[^>]+>/', $value) ? wp_kses_post($value) : sanitize_text_field($value);
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
        $state = get_transient($transient_key);
        
        $max_requests = (int)($this->options['rate_limit_requests'] ?? 100);
        $time_period = (int)($this->options['rate_limit_period'] ?? 3600);
        
        if ($state === false) {
            return array(
                'requests_used' => 0,
                'requests_remaining' => $max_requests,
                'max_requests' => $max_requests,
                'period' => $this->format_time_period($time_period)
            );
        }

        $current_count = is_array($state) ? (int) ($state['count'] ?? 0) : (int) $state;

        return array(
            'requests_used' => $current_count,
            'requests_remaining' => max(0, $max_requests - $current_count),
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

