<?php

/**
 * Ultimate Membership Pro (UMP) Integration class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure UMP constants and classes are available
$ump_plugin_path = null;

// Check if UMP is installed in standard location
if (file_exists(ABSPATH . 'wp-content/plugins/indeed-membership-pro/indeed-membership-pro.php')) {
    $ump_plugin_path = ABSPATH . 'wp-content/plugins/indeed-membership-pro/';
} elseif (file_exists(AI_WEB_SITE_PLUGIN_DIR . '../temp_plugins/indeed-membership-pro/indeed-membership-pro.php')) {
    // Fallback to our temp_plugins directory
    $ump_plugin_path = AI_WEB_SITE_PLUGIN_DIR . '../temp_plugins/indeed-membership-pro/';
}

// Define UMP constants if not already defined
if ($ump_plugin_path && !defined('IHC_PATH')) {
    define('IHC_PATH', $ump_plugin_path);
}
if ($ump_plugin_path && !defined('IHC_URL')) {
    define('IHC_URL', plugins_url('/', $ump_plugin_path . 'indeed-membership-pro.php'));
}
if (!defined('IHC_PROTOCOL')) {
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&  $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
        define('IHC_PROTOCOL', 'https://');
    } else {
        define('IHC_PROTOCOL', 'http://');
    }
}
if (!defined('IUMP_VEND')) {
    define('IUMP_VEND', [
        'evt' => 'Envato Marketplace'
    ]);
}
if (!defined('IHC_DEV')) {
    define('IHC_DEV', "WPIndeed");
}

// Include UMP autoloader if available
if ($ump_plugin_path && file_exists($ump_plugin_path . 'autoload.php')) {
    require_once $ump_plugin_path . 'autoload.php';
}

// Include utilities if available
if ($ump_plugin_path && file_exists($ump_plugin_path . 'utilities.php')) {
    require_once $ump_plugin_path . 'utilities.php';
}

// Include database class if available (needed by Memberships class)
if ($ump_plugin_path && file_exists($ump_plugin_path . 'classes/Ihc_Db.class.php')) {
    require_once $ump_plugin_path . 'classes/Ihc_Db.class.php';
}

// Include specific classes we need if they're not autoloaded
if (!class_exists('\Indeed\Ihc\UserSubscriptions') && $ump_plugin_path) {
    if (file_exists($ump_plugin_path . 'classes/UserSubscriptions.php')) {
        require_once $ump_plugin_path . 'classes/UserSubscriptions.php';
    }
}

if (!class_exists('\Indeed\Ihc\Db\Memberships') && $ump_plugin_path) {
    if (file_exists($ump_plugin_path . 'classes/Db/Memberships.php')) {
        require_once $ump_plugin_path . 'classes/Db/Memberships.php';
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
     * Check if UMP plugin is available and properly loaded.
     * @return bool True if UMP is available, false otherwise.
     */
    public function is_ump_available()
    {
        return (defined('IHC_PATH') && class_exists('\Indeed\Ihc\UserSubscriptions'));
    }

    /**
     * Check if a user has an active UMP subscription for a specific level.
     * @param int $user_id The ID of the WordPress user.
     * @param int $ump_level_id The ID of the UMP membership level.
     * @return bool True if the user has an active subscription, false otherwise.
     */
    public function user_has_active_ump_level($user_id, $ump_level_id)
    {
        if (!$this->is_ump_available()) {
            // UMP plugin is not active or not properly loaded
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
        if (!$this->is_ump_available()) {
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
        if (!$this->is_ump_available() || !class_exists('\Indeed\Ihc\Db\Memberships')) {
            return [];
        }

        try {
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
        } catch (Exception $e) {
            // If there's an error getting UMP levels, return empty array
            return [];
        }
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
     * Only applies to API calls and license validation, not menu links.
     * @param string $value The original site URL.
     * @return string The modified URL if called from UMP API context, original otherwise.
     */
    public function filter_siteurl_for_ump($value)
    {
        // Check if we're in UMP context by examining the call stack
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
        
        $is_api_call = false;
        $is_menu_link = false;
        
        foreach ($backtrace as $trace) {
            if (isset($trace['file'])) {
                $file = $trace['file'];
                
                // Check if the call comes from UMP plugin files
                if (strpos($file, 'indeed-membership-pro') !== false || 
                    strpos($file, 'ultimate-membership-pro') !== false) {
                    
                    // Check if it's an API call (license validation, etc.)
                    if (strpos($file, 'classes/Levels.php') !== false ||
                        strpos($file, 'classes/services/ElCheck.php') !== false ||
                        strpos($file, 'classes/OldLogs.php') !== false ||
                        (isset($trace['function']) && (
                            strpos($trace['function'], 'wp_remote_get') !== false ||
                            strpos($trace['function'], 'wp_remote_post') !== false ||
                            $trace['function'] === 'n' || // The obfuscated license check method
                            $trace['function'] === 'ajax'
                        ))) {
                        $is_api_call = true;
                        break;
                    }
                    
                    // Check if it's a menu link generation (admin interface)
                    if (strpos($file, 'admin/') !== false ||
                        strpos($file, 'utilities.php') !== false ||
                        (isset($trace['function']) && (
                            strpos($trace['function'], 'admin_url') !== false ||
                            strpos($trace['function'], 'menu') !== false ||
                            strpos($trace['function'], 'add_menu') !== false
                        ))) {
                        $is_menu_link = true;
                        break;
                    }
                }
            }
        }
        
        // Only override domain for API calls, not for menu links
        if ($is_api_call && !$is_menu_link) {
            $domain_override = $this->get_ump_domain_override();
            if (!empty($domain_override)) {
                // Add protocol if not present
                if (!preg_match('/^https?:\/\//', $domain_override)) {
                    $domain_override = 'https://' . $domain_override;
                }
                return $domain_override;
            }
        }
        
        return $value;
    }
}
