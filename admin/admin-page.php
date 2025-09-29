<?php
/**
 * Admin page template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Log admin page load
$logger = AI_Web_Site_Debug_Logger::get_instance();
$logger->info('ADMIN', 'PAGE_LOAD', 'Admin page loaded', array(
    'current_user' => get_current_user_id(),
    'user_can_manage_options' => current_user_can('manage_options'),
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
));

// Get current options
$options = get_option('ai_web_site_options', array());
$admin = AI_Web_Site_Admin::get_instance();
$messages = $admin->get_admin_messages();

// Get all subdomains
$database = AI_Web_Site_Database::get_instance();
$subdomains = $database->get_all_subdomains();
?>

<div class="wrap">
    <h1><?php _e('AI Web Site Plugin', 'ai-web-site-plugin-plugin'); ?></h1>
    
    <?php if (!empty($messages)): ?>
        <?php foreach ($messages as $message): ?>
            <div class="notice notice-<?php echo esc_attr($message['type']); ?> is-dismissible">
                <p><?php echo esc_html($message['text']); ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <div class="ai-web-site-plugin-admin">
        <!-- Settings Tab -->
        <div class="tab-content active" id="settings-tab">
            <h2><?php _e('cPanel Settings', 'ai-web-site-plugin-plugin'); ?></h2>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('ai_web_site_options'); ?>
                <input type="hidden" name="action" value="save_ai_web_site_options">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cpanel_username"><?php _e('cPanel Username', 'ai-web-site-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="cpanel_username" name="cpanel_username" 
                                   value="<?php echo esc_attr($options['cpanel_username'] ?? ''); ?>" 
                                   class="regular-text" required>
                            <p class="description"><?php _e('Your cPanel username', 'ai-web-site-plugin'); ?></p>
                        </td>
                    </tr>
                    
                    
                    <tr>
                        <th scope="row">
                            <label for="cpanel_api_token"><?php _e('cPanel API Token', 'ai-web-site-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="cpanel_api_token" name="cpanel_api_token" 
                                   value="<?php echo esc_attr($options['cpanel_api_token'] ?? ''); ?>" 
                                   class="regular-text" required>
                            <p class="description"><?php _e('Your cPanel API token (required)', 'ai-web-site-plugin'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="main_domain"><?php _e('Main Domain', 'ai-web-site-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="main_domain" name="main_domain" 
                                   value="<?php echo esc_attr($options['main_domain'] ?? 'ai-web.site'); ?>" 
                                   class="regular-text" required>
                            <p class="description"><?php _e('Main domain for subdomains (e.g., ai-web.site)', 'ai-web-site-plugin'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php _e('Save Settings', 'ai-web-site-plugin'); ?>">
                    <input type="submit" name="test_connection" class="button-secondary" 
                           value="<?php _e('Test Connection', 'ai-web-site-plugin'); ?>" 
                           formaction="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="test_cpanel_connection" formaction="<?php echo admin_url('admin-post.php'); ?>">
                </p>
            </form>
        </div>
        
        <!-- Subdomains Tab -->
        <div class="tab-content" id="subdomains-tab">
            <h2><?php _e('Subdomains Management', 'ai-web-site-plugin'); ?></h2>
            
            <!-- Add New Subdomain Form -->
            <div class="add-subdomain-form">
                <h3><?php _e('Add New Subdomain', 'ai-web-site-plugin'); ?></h3>
                <form id="add-subdomain-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="new_subdomain"><?php _e('Subdomain', 'ai-web-site-plugin'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="new_subdomain" name="subdomain" 
                                       class="regular-text" required>
                                <span class="domain-suffix">.<?php echo esc_html($options['main_domain'] ?? 'ai-web.site'); ?></span>
                                <p class="description"><?php _e('Enter subdomain name (without domain)', 'ai-web-site-plugin'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php _e('Create Subdomain', 'ai-web-site-plugin'); ?>">
                    </p>
                </form>
            </div>
            
            <!-- Existing Subdomains List -->
            <div class="subdomains-list">
                <h3><?php _e('Existing Subdomains', 'ai-web-site-plugin'); ?></h3>
                
                <?php if (empty($subdomains)): ?>
                    <p><?php _e('No subdomains found.', 'ai-web-site-plugin'); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Subdomain', 'ai-web-site-plugin'); ?></th>
                                <th><?php _e('Domain', 'ai-web-site-plugin'); ?></th>
                                <th><?php _e('User', 'ai-web-site-plugin'); ?></th>
                                <th><?php _e('Created', 'ai-web-site-plugin'); ?></th>
                                <th><?php _e('Actions', 'ai-web-site-plugin'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subdomains as $subdomain): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($subdomain->subdomain); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo esc_html($subdomain->domain); ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html($subdomain->display_name ?: $subdomain->user_login); ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($subdomain->created_at))); ?>
                                    </td>
                                    <td>
                                        <a href="http://<?php echo esc_attr($subdomain->subdomain . '.' . $subdomain->domain); ?>" 
                                           target="_blank" class="button button-small">
                                            <?php _e('View', 'ai-web-site-plugin'); ?>
                                        </a>
                                        <button type="button" class="button button-small button-link-delete delete-subdomain" 
                                                data-subdomain="<?php echo esc_attr($subdomain->subdomain); ?>"
                                                data-domain="<?php echo esc_attr($subdomain->domain); ?>">
                                            <?php _e('Delete', 'ai-web-site-plugin'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
