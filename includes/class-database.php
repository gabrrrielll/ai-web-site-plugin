<?php

/**
 * Database management class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AI_Web_Site_Database
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
     * Constructor
     */
    private function __construct()
    {
        // Constructor is empty as we only need static methods
    }

    /**
     * Create database tables
     */
    public static function create_tables()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_web_sites';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            subdomain varchar(255) NOT NULL,
            domain varchar(255) NOT NULL,
            site_config longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status enum('active', 'inactive', 'suspended') DEFAULT 'active',
            PRIMARY KEY (id),
            UNIQUE KEY unique_subdomain (subdomain, domain),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Save subdomain configuration
     */
    public function save_subdomain($subdomain, $domain, $site_config, $user_id = null)
    {
        global $wpdb;

        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        $table_name = $wpdb->prefix . 'ai_web_sites';

        // Check if subdomain already exists (active or inactive)
        $existing_subdomain = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE subdomain = %s AND domain = %s",
            $subdomain,
            $domain
        ));

        if ($existing_subdomain) {
            // Update existing entry
            $result = $wpdb->update(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'site_config' => json_encode($site_config),
                    'status' => 'active',
                    'updated_at' => current_time('mysql')
                ),
                array(
                    'id' => $existing_subdomain->id
                ),
                array('%d', '%s', '%s', '%s'),
                array('%d')
            );

            return $result !== false ? $existing_subdomain->id : false;
        } else {
            // Insert new entry
            $result = $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'subdomain' => $subdomain,
                    'domain' => $domain,
                    'site_config' => json_encode($site_config),
                    'status' => 'active'
                ),
                array(
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s'
                )
            );

            return $result !== false ? $wpdb->insert_id : false;
        }
    }

    /**
     * Get subdomain configuration
     */
    public function get_subdomain($subdomain, $domain)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_web_sites';

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE subdomain = %s AND domain = %s AND status = 'active'",
            $subdomain,
            $domain
        ));

        if ($result) {
            $result->site_config = json_decode($result->site_config, true);
        }

        return $result;
    }

    /**
     * Update subdomain configuration
     */
    public function update_subdomain($subdomain, $domain, $site_config)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_web_sites';

        $result = $wpdb->update(
            $table_name,
            array(
                'site_config' => json_encode($site_config),
                'updated_at' => current_time('mysql')
            ),
            array(
                'subdomain' => $subdomain,
                'domain' => $domain
            ),
            array('%s', '%s'),
            array('%s', '%s')
        );

        return $result !== false;
    }

    /**
     * Delete subdomain
     */
    public function delete_subdomain($subdomain, $domain)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_web_sites';

        $result = $wpdb->update(
            $table_name,
            array('status' => 'inactive'),
            array(
                'subdomain' => $subdomain,
                'domain' => $domain
            ),
            array('%s'),
            array('%s', '%s')
        );

        return $result !== false;
    }

    /**
     * Get all subdomains for a user
     */
    public function get_user_subdomains($user_id = null)
    {
        global $wpdb;

        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        $table_name = $wpdb->prefix . 'ai_web_sites';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE user_id = %d AND status = 'active' ORDER BY created_at DESC",
            $user_id
        ));

        // Decode JSON for each result
        foreach ($results as $result) {
            $result->site_config = json_decode($result->site_config, true);
        }

        return $results;
    }

    /**
     * Get all subdomains (admin only)
     */
    public function get_all_subdomains()
    {
        global $wpdb;

        // Use the correct subdomains table
        $table_name = $wpdb->prefix . 'ai_web_sites';

        $results = $wpdb->get_results(
            "SELECT s.*, u.user_login, u.display_name 
             FROM {$table_name} s 
             LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID 
             WHERE s.status = 'active' 
             ORDER BY s.created_at DESC"
        );

        // Decode JSON for each result
        foreach ($results as $result) {
            if (isset($result->site_config)) {
                $result->site_config = json_decode($result->site_config, true);
            }
        }

        return $results;
    }

    /**
     * Check if subdomain exists
     */
    public function subdomain_exists($subdomain, $domain)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_web_sites';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE subdomain = %s AND domain = %s AND status = 'active'",
            $subdomain,
            $domain
        ));

        return $count > 0;
    }
}
