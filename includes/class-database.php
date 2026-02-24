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
        // Use canonical table managed by Website Manager to avoid legacy duplicate table creation
        $website_manager = AI_Web_Site_Website_Manager::get_instance();
        $website_manager->create_table();
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

        $table_name = $wpdb->prefix . 'ai_web_site_websites';

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
                    'config' => json_encode($site_config),
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
                    'config' => json_encode($site_config),
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
     * Update subdomain and domain for a given website ID
     * @param int $website_id The ID of the website to update.
     * @param string $subdomain The new subdomain.
     * @param string $domain The new domain.
     * @param string $status The new status.
     * @return bool True on success, false on failure.
     */
    public function update_subdomain_for_website_id($website_id, $subdomain, $domain, $status = 'active')
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_web_site_websites';

        $result = $wpdb->update(
            $table_name,
            array(
                'subdomain' => $subdomain,
                'domain' => $domain,
                'status' => $status,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $website_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Delete a website by its ID.
     * @param int $website_id The ID of the website to delete.
     * @return bool True on success, false on failure.
     */
    public function delete_website($website_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_web_site_websites';

        $result = $wpdb->delete(
            $table_name,
            array('id' => $website_id),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Get subdomain configuration
     */
    public function get_subdomain($subdomain, $domain)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_web_site_websites';

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE subdomain = %s AND domain = %s AND status = 'active'",
            $subdomain,
            $domain
        ));

        if ($result) {
            $result->config = json_decode($result->config, true);
        }

        return $result;
    }

    /**
     * Update subdomain configuration
     */
    public function update_subdomain($subdomain, $domain, $site_config)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_web_site_websites';

        $result = $wpdb->update(
            $table_name,
            array(
                'config' => json_encode($site_config),
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

        $table_name = $wpdb->prefix . 'ai_web_site_websites';

        // DELETE real from database (not just marking as inactive)
        $result = $wpdb->delete(
            $table_name,
            array(
                'subdomain' => $subdomain,
                'domain' => $domain
            ),
            array('%s', '%s')
        );

        return $result !== false;
    }

    /**
     * Get all websites for a user (subdomain is optional - can be added later)
     *
     * @param int|null $user_id User ID, defaults to current user
     * @return array Array of user's websites
     *
     * Note: Subdomain is optional and can be empty. User can add subdomain later
     * through the management interface. This function returns ALL websites
     * belonging to the user, regardless of subdomain status.
     */
    public function get_user_subdomains($user_id = null)
    {
        global $wpdb;

        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        // ✅ Folosește tabela corectă din Website Manager
        $table_name = $wpdb->prefix . 'ai_web_site_websites';

        // ✅ Returnează TOATE site-urile user-ului (subdomain este optional)
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ));

        // Decode JSON for each result
        foreach ($results as $result) {
            $result->config = json_decode($result->config, true);
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
        $table_name = $wpdb->prefix . 'ai_web_site_websites';

        $results = $wpdb->get_results(
            "SELECT s.*, u.user_login, u.display_name 
             FROM {$table_name} s 
             LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID 
             ORDER BY s.updated_at DESC"
        );

        // Decode JSON for each result
        foreach ($results as $result) {
            if (isset($result->config)) {
                $result->config = json_decode($result->config, true);
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

        $table_name = $wpdb->prefix . 'ai_web_site_websites';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE subdomain = %s AND domain = %s AND status = 'active'",
            $subdomain,
            $domain
        ));

        return $count > 0;
    }
}
