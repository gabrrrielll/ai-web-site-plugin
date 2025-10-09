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
                            <th><?php _e('Website URL', 'ai-web-site-plugin'); ?></th>
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
                                    <?php
                                    // Debug: să vedem ce valoare are subdomain-ul
                                    error_log('AI-WEB-SITE DEBUG: Site ID ' . $site->id . ' - subdomain: "' . $site->subdomain . '" (length: ' . strlen($site->subdomain) . '), domain: "' . $site->domain . '"');
                            error_log('AI-WEB-SITE DEBUG: empty($site->subdomain) = ' . (empty($site->subdomain) ? 'TRUE' : 'FALSE'));
                            error_log('AI-WEB-SITE DEBUG: $site->subdomain === "" = ' . ($site->subdomain === '' ? 'TRUE' : 'FALSE'));

                            if (empty($site->subdomain) || $site->subdomain === '' || $site->subdomain === 'my-site') : ?>
                                        <div class="ai-subdomain-form" data-site-id="<?php echo esc_attr($site->id); ?>">
                                            <strong><?php _e('No subdomain assigned', 'ai-web-site-plugin'); ?></strong><br/>
                                            <input type="text" placeholder="<?php esc_attr_e('Enter subdomain (e.g., my-site)', 'ai-web-site-plugin'); ?>" class="ai-subdomain-input" />
                                            <button class="button button-primary ai-add-subdomain"><?php _e('Add Subdomain', 'ai-web-site-plugin'); ?></button>
                                            <span class="ai-subdomain-message"></span>
                                        </div>
                                    <?php else : ?>
                                        <strong><?php echo esc_html($site->subdomain . '.' . $site->domain); ?></strong><br/>
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
        // ✅ Metodă alternativă: CSS inline pentru a evita problemele de MIME type
        $css_content = file_get_contents(plugin_dir_path(dirname(__FILE__)) . 'assets/admin.css');
        ?>
        
        <!-- CSS inline pentru a evita problemele de MIME type -->
        <style type="text/css">
        <?php echo $css_content; ?>
        </style>
        
        <!-- JavaScript inline pentru funcționalități -->
        <script type="text/javascript">
        // ✅ JavaScript inline pentru website management
        document.addEventListener('DOMContentLoaded', function () {
            console.log('AI Web Site: Website management loaded');
            
            // Helper function to show subdomain messages
            function showSubdomainMessage(element, type, message) {
                if (!element) return;
                
                element.textContent = message;
                element.className = 'ai-subdomain-message';
                
                if (type === 'success') {
                    element.classList.add('success');
                } else if (type === 'error') {
                    element.classList.add('error');
                }
                
                if (type === 'success') {
                    setTimeout(() => {
                        element.textContent = '';
                        element.className = 'ai-subdomain-message';
                    }, 3000);
                }
            }
            
            // Handle add subdomain for existing websites
            document.addEventListener('click', function (e) {
                // ✅ Verificări robuste pentru a evita erorile
                if (!e.target || !e.target.classList || !e.target.classList.contains('ai-add-subdomain')) {
                    return;
                }
                
                e.preventDefault();
                
                var button = e.target;
                var form = button.closest('.ai-subdomain-form');
                
                if (!form) {
                    console.error('AI Web Site: Form not found');
                    return;
                }
                
                var siteId = form.getAttribute('data-site-id');
                var input = form.querySelector('.ai-subdomain-input');
                var messageSpan = form.querySelector('.ai-subdomain-message');
                
                if (!siteId || !input || !messageSpan) {
                    console.error('AI Web Site: Missing required elements', {
                        siteId: siteId,
                        input: !!input,
                        messageSpan: !!messageSpan
                    });
                    return;
                }
                
                var subdomain = input.value.trim();
                
                if (!subdomain) {
                    showSubdomainMessage(messageSpan, 'error', 'Please enter a subdomain name');
                    return;
                }
                
                // Validate subdomain format
                if (!/^[a-zA-Z0-9][a-zA-Z0-9-]*[a-zA-Z0-9]$/.test(subdomain)) {
                    showSubdomainMessage(messageSpan, 'error', 'Invalid subdomain format. Use only letters, numbers, and hyphens.');
                    return;
                }
                
                // Show loading state
                button.disabled = true;
                button.textContent = 'Adding...';
                showSubdomainMessage(messageSpan, '', 'Adding subdomain...');
                
                // Make AJAX request
                fetch('/wp-json/ai-web-site/v1/user-site/add-subdomain', {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.aiWebSiteUserSites?.nonce || ''
                    },
                    body: JSON.stringify({
                        website_id: parseInt(siteId),
                        subdomain_name: subdomain
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showSubdomainMessage(messageSpan, 'success', 'Subdomain added successfully!');
                        input.value = '';
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showSubdomainMessage(messageSpan, 'error', data.message || 'Failed to add subdomain');
                    }
                })
                .catch(error => {
                    console.error('Error adding subdomain:', error);
                    showSubdomainMessage(messageSpan, 'error', 'Network error occurred: ' + error.message);
                })
                .finally(() => {
                    button.disabled = false;
                    button.textContent = 'Add Subdomain';
                });
            });
        });
        </script>
        
        <!-- Nonce pentru JavaScript -->
        <script type="text/javascript">
        if (typeof window.aiWebSiteUserSites === 'undefined') {
            window.aiWebSiteUserSites = {
                nonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
                ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
                base_domain: '<?php echo preg_replace('#^https?://#', '', get_option('siteurl')); ?>',
                editor_url: 'https://editor.ai-web.site/',
                add_subdomain_success: '<?php echo __('Subdomain added successfully. Refreshing...', 'ai-web-site-plugin'); ?>',
                add_subdomain_error: '<?php echo __('Error adding subdomain. Please try again.', 'ai-web-site-plugin'); ?>',
                delete_site_success: '<?php echo __('Website deleted successfully.', 'ai-web-site-plugin'); ?>',
                delete_site_error: '<?php echo __('Error deleting website. Please try again.', 'ai-web-site-plugin'); ?>',
                confirm_delete: '<?php echo __('Are you sure you want to delete this website? This action cannot be undone.', 'ai-web-site-plugin'); ?>',
                invalid_subdomain: '<?php echo __('Invalid subdomain format. Use only letters, numbers, and hyphens.', 'ai-web-site-plugin'); ?>',
                subdomain_required: '<?php echo __('Please enter a subdomain name.', 'ai-web-site-plugin'); ?>'
            };
        }
        </script>
        <?php

        return ob_get_clean();
    }
}

AI_Web_Site_User_Site_Shortcode::get_instance();
