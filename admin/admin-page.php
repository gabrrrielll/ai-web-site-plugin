<?php
/**
 * Admin page template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

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
        <!-- Tab Navigation -->
        <nav class="nav-tab-wrapper">
            <a href="#settings-tab" class="nav-tab nav-tab-active" data-tab="settings-tab">
                <?php _e('Settings', 'ai-web-site-plugin'); ?>
            </a>
            <a href="#subdomains-tab" class="nav-tab" data-tab="subdomains-tab">
                <?php _e('Subdomains', 'ai-web-site-plugin'); ?>
            </a>
            <a href="#shortcode-tab" class="nav-tab" data-tab="shortcode-tab">
                <?php _e('Home Page Shortcode', 'ai-web-site-plugin'); ?>
            </a>
        </nav>

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

                    <tr>
                        <th scope="row">
                            <label for="required_ump_level_id"><?php _e('Required UMP Level', 'ai-web-site-plugin'); ?></label>
                        </th>
                        <td>
                            <?php
                            $ump_integration = AI_Web_Site_UMP_Integration::get_instance();
$ump_levels = $ump_integration->get_all_ump_levels();
$current_ump_level = (int)($options['required_ump_level_id'] ?? 0);
?>
                            <select id="required_ump_level_id" name="required_ump_level_id" class="regular-text">
                                <option value="0"><?php _e('No specific level required', 'ai-web-site-plugin'); ?></option>
                                <?php foreach ($ump_levels as $level_id => $level_label): ?>
                                    <option value="<?php echo esc_attr($level_id); ?>" <?php selected($current_ump_level, $level_id); ?> >
                                        <?php echo esc_html($level_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                           <p class="description"><?php _e('Select the Ultimate Membership Pro level required to create and manage subdomains.', 'ai-web-site-plugin'); ?></p>
                       </td>
                   </tr>

                   <tr>
                       <th scope="row">
                           <label for="ump_domain_override"><?php _e('UMP License Domain', 'ai-web-site-plugin'); ?></label>
                       </th>
                       <td>
                           <input type="text" id="ump_domain_override" name="ump_domain_override" 
                                  value="<?php echo esc_attr($options['ump_domain_override'] ?? 'andradadan.com'); ?>" 
                                  class="regular-text" placeholder="andradadan.com">
                           <p class="description"><?php _e('Domain to report to Ultimate Membership Pro for license validation (use the domain where your UMP license was purchased).', 'ai-web-site-plugin'); ?></p>
                       </td>
                   </tr>

                   <tr>
                       <th scope="row">
                           <label><?php _e('UMP License Activation', 'ai-web-site-plugin'); ?></label>
                       </th>
                       <td>
                           <button type="button" id="activate_ump_license" class="button button-secondary">
                               <?php _e('Activate UMP License', 'ai-web-site-plugin'); ?>
                           </button>
                           <p class="description"><?php _e('Click to activate your Ultimate Membership Pro license. Make sure the UMP License Domain above is correct before activating.', 'ai-web-site-plugin'); ?></p>
                           <div id="ump_license_status" style="margin-top: 10px;"></div>
                       </td>
                   </tr>

                   <tr>
                       <th scope="row">
                           <label for="disable_ump_tracking"><?php _e('Disable UMP Tracking', 'ai-web-site-plugin'); ?></label>
                       </th>
                       <td>
                           <input type="checkbox" id="disable_ump_tracking" name="disable_ump_tracking" value="1" 
                                  <?php checked(($options['disable_ump_tracking'] ?? 1), 1); ?>>
                           <label for="disable_ump_tracking"><?php _e('Disable Ultimate Membership Pro tracking and annoying popups', 'ai-web-site-plugin'); ?></label>
                           <p class="description"><?php _e('This will prevent UMP from collecting technical information and showing tracking consent popups.', 'ai-web-site-plugin'); ?></p>
                       </td>
                   </tr>
               </table>
                
                <p class="submit">
                    <button type="submit" class="button-primary" name="action" value="save_ai_web_site_options">
                           <?php _e('Save Settings', 'ai-web-site-plugin'); ?>
                    </button>
                    <button type="submit" name="action" value="test_cpanel_connection" class="button-secondary">
                           <?php _e('Test Connection', 'ai-web-site-plugin'); ?>
                    </button>
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

        <!-- Shortcode Usage Tab -->
        <div class="tab-content" id="shortcode-tab">
            <h2><?php _e('Home Page Shortcode', 'ai-web-site-plugin'); ?></h2>
            
            <div class="card">
                <h3><?php _e('Usage Instructions', 'ai-web-site-plugin'); ?></h3>
                <p><?php _e('Use the following shortcode to display the AI Website Builder home page content on any page or post:', 'ai-web-site-plugin'); ?></p>
                
                <div class="shortcode-example">
                    <code>[ai_website_builder_home]</code>
                    <button type="button" class="button button-small copy-shortcode" data-shortcode="[ai_website_builder_home]">
                        <?php _e('Copy', 'ai-web-site-plugin'); ?>
                    </button>
                </div>
                
                <h4><?php _e('Available Parameters:', 'ai-web-site-plugin'); ?></h4>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Parameter', 'ai-web-site-plugin'); ?></th>
                            <th><?php _e('Description', 'ai-web-site-plugin'); ?></th>
                            <th><?php _e('Default Value', 'ai-web-site-plugin'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>cta_url</code></td>
                            <td><?php _e('The URL for the "Start Building Now" button', 'ai-web-site-plugin'); ?></td>
                            <td>https://editor.ai-web.site</td>
                        </tr>
                        <tr>
                            <td><code>show_features</code></td>
                            <td><?php _e('Show features section (true/false)', 'ai-web-site-plugin'); ?></td>
                            <td>true</td>
                        </tr>
                        <tr>
                            <td><code>show_pricing</code></td>
                            <td><?php _e('Show pricing section (true/false)', 'ai-web-site-plugin'); ?></td>
                            <td>true</td>
                        </tr>
                        <tr>
                            <td><code>show_how_it_works</code></td>
                            <td><?php _e('Show how it works section (true/false)', 'ai-web-site-plugin'); ?></td>
                            <td>true</td>
                        </tr>
                        <tr>
                            <td><code>title</code></td>
                            <td><?php _e('Main hero title text', 'ai-web-site-plugin'); ?></td>
                            <td>AI Website Free Live Frontend Builder</td>
                        </tr>
                        <tr>
                            <td><code>subtitle</code></td>
                            <td><?php _e('Hero subtitle text', 'ai-web-site-plugin'); ?></td>
                            <td>Create stunning websites in just 5 minutes...</td>
                        </tr>
                    </tbody>
                </table>
                
                <h4><?php _e('Example Usage:', 'ai-web-site-plugin'); ?></h4>
                <div class="shortcode-example">
                    <code>[ai_website_builder_home cta_url="https://your-editor.com" show_pricing="false"]</code>
                    <button type="button" class="button button-small copy-shortcode" data-shortcode='[ai_website_builder_home cta_url="https://your-editor.com" show_pricing="false"]'>
                        <?php _e('Copy', 'ai-web-site-plugin'); ?>
                    </button>
                </div>
                
                <div class="shortcode-preview">
                    <h4><?php _e('Preview:', 'ai-web-site-plugin'); ?></h4>
                    <p><?php _e('To see how the shortcode looks, add it to any page or post and view it on the frontend.', 'ai-web-site-plugin'); ?></p>
                    <a href="<?php echo admin_url('post-new.php?post_type=page'); ?>" class="button button-primary">
                        <?php _e('Create New Page', 'ai-web-site-plugin'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
