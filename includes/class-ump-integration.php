<?php

/**
 * Ultimate Membership Pro (UMP) Integration class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure UMP classes are available (autoloader should handle this, but for safety)
if (!class_exists('\Indeed\Ihc\UserSubscriptions')) {
    // Optionally include UMP autoload.php if not already loaded by WordPress
    // require_once trailingslashit(WPMU_PLUGIN_DIR) . 'ultimate-membership-pro/autoload.php';
    // For our temp_plugins setup, we'll try to rely on general availability or include directly
    if (file_exists(ABSPATH . 'wp-content/plugins/indeed-membership-pro/classes/UserSubscriptions.php')) {
        require_once ABSPATH . 'wp-content/plugins/indeed-membership-pro/classes/UserSubscriptions.php';
    } elseif (file_exists(AI_WEB_SITE_PLUGIN_DIR . '../temp_plugins/indeed-membership-pro/classes/UserSubscriptions.php')) {
        require_once AI_WEB_SITE_PLUGIN_DIR . '../temp_plugins/indeed-membership-pro/classes/UserSubscriptions.php';
    }
}

if (!class_exists('\Indeed\Ihc\Db\Memberships')) {
    if (file_exists(ABSPATH . 'wp-content/plugins/indeed-membership-pro/classes/Db/Memberships.php')) {
        require_once ABSPATH . 'wp-content/plugins/indeed-membership-pro/classes/Db/Memberships.php';
    } elseif (file_exists(AI_WEB_SITE_PLUGIN_DIR . '../temp_plugins/indeed-membership-pro/classes/Db/Memberships.php')) {
        require_once AI_WEB_SITE_PLUGIN_DIR . '../temp_plugins/indeed-membership-pro/classes/Db/Memberships.php';
    }
}

class AI_Web_Site_UMP_Integration
{
    /**
     * Single instance
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
     * Check if a user has an active UMP subscription for a specific level.
     * @param int $user_id The ID of the WordPress user.
     * @param int $ump_level_id The ID of the UMP membership level.
     * @return bool True if the user has an active subscription, false otherwise.
     */
    public function user_has_active_ump_level($user_id, $ump_level_id)
    {
        if (!class_exists('\Indeed\Ihc\UserSubscriptions')) {
            // UMP plugin is not active or class not found
            return false;
        }

        // The isActive method already handles expiry and grace periods
        return \Indeed\Ihc\UserSubscriptions::isActive($user_id, $ump_level_id);
    }

    /**
     * Get the status of a user's UMP subscription for a specific level.
     * @param int $user_id The ID of the WordPress user.
     * @param int $ump_level_id The ID of the UMP membership level.
     * @return array|false An array with subscription status details, or false if not found.
     */
    public function get_user_ump_level_status($user_id, $ump_level_id)
    {
        if (!class_exists('\Indeed\Ihc\UserSubscriptions')) {
            return false;
        }
        return \Indeed\Ihc\UserSubscriptions::getStatus($user_id, $ump_level_id);
    }

    /**
     * Get all available UMP membership levels.
     * @return array An associative array of UMP levels (ID => label).
     */
    public function get_all_ump_levels()
    {
        if (!class_exists('\Indeed\Ihc\Db\Memberships')) {
            return [];
        }
        $levels_data = \Indeed\Ihc\Db\Memberships::getAll();
        $formatted_levels = [];
        if (!empty($levels_data)) {
            foreach ($levels_data as $level_id => $level_info) {
                if (isset($level_info['label'])) {
                    $formatted_levels[$level_id] = $level_info['label'];
                }
            }
        }
        return $formatted_levels;
    }

    /**
     * Get the ID of the UMP level required for our plugin's functionalities.
     * This ID is stored in our plugin's options.
     * @return int The UMP level ID, or 0 if not configured.
     */
    public function get_required_ump_level_id()
    {
        $options = get_option('ai_web_site_options', array());
        return (int)($options['required_ump_level_id'] ?? 0);
    }

    /**
     * Get the domain override for UMP license validation.
     * @return string The domain to report to UMP, or empty string if not configured.
     */
    public function get_ump_domain_override()
    {
        $options = get_option('ai_web_site_options', array());
        return trim($options['ump_domain_override'] ?? 'andradadan.com');
    }

    /**
     * Initialize UMP domain override hook.
     * This will intercept siteurl requests from UMP and return the configured domain.
     */
    public function init_domain_override()
    {
        $domain_override = $this->get_ump_domain_override();
        
        if (!empty($domain_override)) {
            add_filter('option_siteurl', array($this, 'filter_siteurl_for_ump'), 10, 1);
            add_filter('option_home', array($this, 'filter_siteurl_for_ump'), 10, 1);
        }
    }

    /**
     * Filter siteurl option when called from UMP context.
     * @param string $value The original site URL.
     * @return string The modified URL if called from UMP, original otherwise.
     */
    public function filter_siteurl_for_ump($value)
    {
        // Check if we're in UMP context by examining the call stack
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
        
        foreach ($backtrace as $trace) {
            if (isset($trace['file'])) {
                // Check if the call comes from UMP plugin files
                if (strpos($trace['file'], 'indeed-membership-pro') !== false || 
                    strpos($trace['file'], 'ultimate-membership-pro') !== false) {
                    
                    $domain_override = $this->get_ump_domain_override();
                    if (!empty($domain_override)) {
                        // Add protocol if not present
                        if (!preg_match('/^https?:\/\//', $domain_override)) {
                            $domain_override = 'https://' . $domain_override;
                        }
                        return $domain_override;
                    }
                }
            }
        }
        
        return $value;
    }
}
