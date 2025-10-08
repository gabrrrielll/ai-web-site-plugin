<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AI_Web_Site_User_Site_Shortcode
{
    private static $instance = null;
    private $ump_integration;
    private $database;
    private $website_manager;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        if (!class_exists('AI_Web_Site_UMP_Integration')) {
            require_once AI_WEB_SITE_PLUGIN_DIR . 'includes/class-ump-integration.php';
        }
        if (!class_exists('AI_Web_Site_Database')) {
            require_once AI_WEB_SITE_PLUGIN_DIR . 'includes/class-database.php';
        }
        if (!class_exists('AI_Web_Site_Website_Manager')) {
            require_once AI_WEB_SITE_PLUGIN_DIR . 'includes/class-website-manager.php';
        }

        $this->ump_integration = AI_Web_Site_UMP_Integration::get_instance();
        $this->database = AI_Web_Site_Database::get_instance();
        $this->website_manager = AI_Web_Site_Website_Manager::get_instance();

        add_shortcode('ai_user_sites', array($this, 'render_user_sites_table'));
    }

    public function render_user_sites_table($atts)
    {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your websites.', 'ai-web-site-plugin') . '</p>';
        }

        $user_id = get_current_user_id();
        $required_ump_level_id = $this->ump_integration->get_required_ump_level_id();

        if ($required_ump_level_id > 0 && !$this->ump_integration->user_has_active_ump_level($user_id, $required_ump_level_id)) {
            return '<p>' . __('You need an active subscription to manage your websites.', 'ai-web-site-plugin') . '</p>';
        }

        $user_sites = $this->database->get_user_subdomains($user_id);

        ob_start();
        ?>
        <div class="ai-web-site-user-sites">
            <h2><?php _e('My Websites', 'ai-web-site-plugin'); ?></h2>
            <?php if (empty($user_sites)) : ?>
                <p><?php _e('You have not created any websites yet. Start by saving your configuration in the editor.', 'ai-web-site-plugin'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Site ID', 'ai-web-site-plugin'); ?></th>
                            <th><?php _e('Created At', 'ai-web-site-plugin'); ?></th>
                            <th><?php _e('Last Update', 'ai-web-site-plugin'); ?></th>
                            <th><?php _e('Subdomain', 'ai-web-site-plugin'); ?></th>
                            <th><?php _e('Actions', 'ai-web-site-plugin'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($user_sites as $site) : ?>
                            <tr>
                                <td><?php echo esc_html($site->id); ?></td>
                                <td><?php echo esc_html($site->created_at); ?></td>
                                <td><?php echo esc_html($site->updated_at); ?></td>
                                <td>
                                    <?php if (empty($site->subdomain) || $site->subdomain === 'my-site') : ?>
                                        <div class="ai-subdomain-form" data-site-id="<?php echo esc_attr($site->id); ?>">
                                            <input type="text" placeholder="<?php esc_attr_e('Enter subdomain', 'ai-web-site-plugin'); ?>" class="ai-subdomain-input" />
                                            <button class="button button-primary ai-add-subdomain"><?php _e('Add Subdomain', 'ai-web-site-plugin'); ?></button>
                                            <span class="ai-subdomain-message"></span>
                                        </div>
                                    <?php else : ?>
                                        <?php echo esc_html($site->subdomain . '.' . $site->domain); ?>
                                        <a href="<?php echo esc_url('https://' . $site->subdomain . '.' . $site->domain); ?>" target="_blank" class="button button-secondary"><?php _e('View Site', 'ai-web-site-plugin'); ?></a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url('https://editor.ai-web.site/?site_id=' . $site->id); ?>" target="_blank" class="button button-primary"><?php _e('Edit Website', 'ai-web-site-plugin'); ?></a>
                                    <button class="button button-danger ai-delete-site" data-site-id="<?php echo esc_attr($site->id); ?>"><?php _e('Delete Site', 'ai-web-site-plugin'); ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        // Adaugăm script-ul JavaScript pentru interacțiune
        wp_enqueue_script('ai-web-site-user-sites-script');
        wp_enqueue_style('ai-web-site-user-sites-style');
        return ob_get_clean();
    }
}

AI_Web_Site_User_Site_Shortcode::get_instance();



