<?php
/**
 * Subscription Manager Class
 * 
 * Manages user subscriptions and checks active membership status.
 * Integrates with InfoHub Membership (IHC) plugin for WordPress.
 * 
 * @package AI_Web_Site
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Web_Site_Subscription_Manager
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
     * IHC plugin slug
     */
    const IHC_PLUGIN_SLUG = 'indeed-membership-pro/indeed-membership-pro.php';

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
        $this->logger->info('SUBSCRIPTION_MANAGER', 'INIT', 'Subscription Manager initialized');
    }

    /**
     * Check if IHC plugin is active
     * 
     * @return bool True if IHC is active
     */
    public function is_ihc_active()
    {
        if (!function_exists('is_plugin_active')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        $is_active = is_plugin_active(self::IHC_PLUGIN_SLUG);
        
        $this->logger->info('SUBSCRIPTION_MANAGER', 'IHC_CHECK', 'IHC plugin status', array(
            'is_active' => $is_active
        ));

        return $is_active;
    }

    /**
     * Check if user has active subscription
     * 
     * @param int $user_id WordPress user ID
     * @return array Result with 'has_subscription' and 'details'
     */
    public function check_user_subscription($user_id)
    {
        $this->logger->info('SUBSCRIPTION_MANAGER', 'CHECK_SUBSCRIPTION', 'Checking subscription for user', array(
            'user_id' => $user_id
        ));

        // Verificare dacă utilizatorul există
        if (!$user_id || $user_id === 0) {
            $this->logger->warning('SUBSCRIPTION_MANAGER', 'CHECK_SUBSCRIPTION', 'Invalid user ID');
            return array(
                'has_subscription' => false,
                'reason' => 'invalid_user',
                'message' => 'Invalid user ID'
            );
        }

        // Verificare dacă IHC este activ
        if (!$this->is_ihc_active()) {
            $this->logger->warning('SUBSCRIPTION_MANAGER', 'CHECK_SUBSCRIPTION', 'IHC plugin not active');
            
            // FALLBACK: Dacă IHC nu este activ, verificăm rolul de admin
            $user = get_userdata($user_id);
            if ($user && in_array('administrator', $user->roles)) {
                $this->logger->info('SUBSCRIPTION_MANAGER', 'CHECK_SUBSCRIPTION', 'User is admin - allowed without IHC');
                return array(
                    'has_subscription' => true,
                    'reason' => 'admin_user',
                    'message' => 'Administrator user - no subscription check needed'
                );
            }

            return array(
                'has_subscription' => false,
                'reason' => 'ihc_not_active',
                'message' => 'Subscription system not available'
            );
        }

        // Verificare abonament IHC
        $subscription_data = $this->get_ihc_subscription_data($user_id);

        if ($subscription_data['has_active_subscription']) {
            $this->logger->info('SUBSCRIPTION_MANAGER', 'CHECK_SUBSCRIPTION', 'User has active subscription', array(
                'user_id' => $user_id,
                'subscription_levels' => $subscription_data['active_levels']
            ));

            return array(
                'has_subscription' => true,
                'reason' => 'active_subscription',
                'message' => 'Active subscription found',
                'levels' => $subscription_data['active_levels'],
                'details' => $subscription_data
            );
        } else {
            $this->logger->warning('SUBSCRIPTION_MANAGER', 'CHECK_SUBSCRIPTION', 'User has NO active subscription', array(
                'user_id' => $user_id
            ));

            return array(
                'has_subscription' => false,
                'reason' => 'no_active_subscription',
                'message' => 'No active subscription found',
                'details' => $subscription_data
            );
        }
    }

    /**
     * Get IHC subscription data for user
     * 
     * @param int $user_id WordPress user ID
     * @return array Subscription data
     */
    private function get_ihc_subscription_data($user_id)
    {
        $data = array(
            'has_active_subscription' => false,
            'active_levels' => array(),
            'expired_levels' => array(),
            'all_levels' => array()
        );

        // Verificare dacă funcțiile IHC există
        if (!function_exists('ihc_is_user_active')) {
            $this->logger->warning('SUBSCRIPTION_MANAGER', 'IHC_DATA', 'IHC functions not available');
            return $data;
        }

        // Verificare status general de membru activ
        $is_active = ihc_is_user_active($user_id);
        $data['has_active_subscription'] = $is_active;

        $this->logger->info('SUBSCRIPTION_MANAGER', 'IHC_DATA', 'IHC general status', array(
            'user_id' => $user_id,
            'is_active' => $is_active
        ));

        // Obține toate nivelurile de abonament
        if (function_exists('ihc_get_all_levels')) {
            $all_levels = ihc_get_all_levels();
            
            foreach ($all_levels as $level_id => $level_data) {
                // Verifică dacă userul are acest nivel
                if (function_exists('ihc_user_level_has_expired')) {
                    $has_expired = ihc_user_level_has_expired($user_id, $level_id);
                    
                    if (!$has_expired) {
                        $data['active_levels'][] = array(
                            'id' => $level_id,
                            'name' => $level_data['name'] ?? "Level $level_id",
                            'status' => 'active'
                        );
                    } else {
                        $data['expired_levels'][] = array(
                            'id' => $level_id,
                            'name' => $level_data['name'] ?? "Level $level_id",
                            'status' => 'expired'
                        );
                    }
                    
                    $data['all_levels'][] = array(
                        'id' => $level_id,
                        'name' => $level_data['name'] ?? "Level $level_id",
                        'status' => $has_expired ? 'expired' : 'active'
                    );
                }
            }
        }

        $this->logger->info('SUBSCRIPTION_MANAGER', 'IHC_DATA', 'IHC subscription details', array(
            'user_id' => $user_id,
            'active_count' => count($data['active_levels']),
            'expired_count' => count($data['expired_levels'])
        ));

        return $data;
    }

    /**
     * Verifică dacă utilizatorul poate salva configurații
     * 
     * @param int $user_id WordPress user ID
     * @return array Result cu 'allowed', 'reason', 'message'
     */
    public function can_save_configuration($user_id)
    {
        $this->logger->info('SUBSCRIPTION_MANAGER', 'CAN_SAVE', 'Checking save permission', array(
            'user_id' => $user_id
        ));

        // Verifică abonamentul
        $subscription = $this->check_user_subscription($user_id);

        if ($subscription['has_subscription']) {
            return array(
                'allowed' => true,
                'reason' => $subscription['reason'],
                'message' => 'User has permission to save configurations'
            );
        } else {
            // Mesaj clar pentru user
            $message = 'Pentru a salva configurații, trebuie să ai un abonament activ. ';
            $message .= 'Te rugăm să achiziționezi un abonament pentru a continua.';

            return array(
                'allowed' => false,
                'reason' => $subscription['reason'],
                'message' => $message,
                'action_required' => 'subscribe',
                'subscribe_url' => $this->get_subscription_url()
            );
        }
    }

    /**
     * Obține URL-ul pentru pagina de abonamente
     * 
     * @return string URL pentru abonamente
     */
    private function get_subscription_url()
    {
        // Încearcă să obții URL-ul IHC
        if (function_exists('ihc_get_subscription_page_url')) {
            return ihc_get_subscription_page_url();
        }

        // Fallback: URL generic
        return home_url('/abonamente/');
    }

    /**
     * Get user subscription info for REST API response
     * 
     * @param int $user_id WordPress user ID
     * @return array Formatted subscription info
     */
    public function get_subscription_info_for_api($user_id)
    {
        $subscription = $this->check_user_subscription($user_id);
        $can_save = $this->can_save_configuration($user_id);

        return array(
            'has_subscription' => $subscription['has_subscription'],
            'can_save' => $can_save['allowed'],
            'reason' => $subscription['reason'],
            'message' => $can_save['message'],
            'subscription_details' => isset($subscription['details']) ? $subscription['details'] : null,
            'action_required' => isset($can_save['action_required']) ? $can_save['action_required'] : null,
            'subscribe_url' => isset($can_save['subscribe_url']) ? $can_save['subscribe_url'] : null
        );
    }
}

