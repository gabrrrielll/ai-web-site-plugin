<?php

/**
 * AI Website Builder Home Page Shortcode Class
 *
 * Handles the [ai_website_builder_home] shortcode functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AI_Web_Site_Home_Page_Shortcode
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
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Register shortcode
        add_shortcode('ai_website_builder_home', array($this, 'render_shortcode'));

        // Enqueue styles for shortcode
        add_action('wp_enqueue_scripts', array($this, 'enqueue_shortcode_styles'));

        // Add styles to admin for preview
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
    }

    /**
     * Render the shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_shortcode($atts = array())
    {
        // Parse shortcode attributes with defaults
        $atts = shortcode_atts(array(
            'cta_url' => 'https://editor.ai-web.site',
            'show_features' => 'true',
            'show_pricing' => 'true',
            'show_how_it_works' => 'true',
            'title' => 'AI Website Free Live Frontend Builder',
            'subtitle' => 'Create stunning websites in just 5 minutes with AI assistance. Edit any element with a simple double-click. Forever free plan included.',
        ), $atts, 'ai_website_builder_home');

        // Sanitize attributes
        $cta_url = esc_url($atts['cta_url']);
        $show_features = sanitize_text_field($atts['show_features']);
        $show_pricing = sanitize_text_field($atts['show_pricing']);
        $show_how_it_works = sanitize_text_field($atts['show_how_it_works']);
        $title = sanitize_text_field($atts['title']);
        $subtitle = sanitize_textarea_field($atts['subtitle']);

        // Start output buffering
        ob_start();

        // Output inline styles first
        $this->output_inline_styles();

        // Include the template file with variables available
        $template_path = AI_WEB_SITE_PLUGIN_DIR . 'assets/home-page/home-page-template.php';

        if (file_exists($template_path)) {
            include $template_path;
        } else {
            return '<div class="ai-website-builder-error">Home page template not found.</div>';
        }

        // Get the buffered content
        $output = ob_get_clean();

        return $output;
    }

    /**
     * Enqueue shortcode styles for frontend
     */
    public function enqueue_shortcode_styles()
    {
        // Only enqueue if shortcode is being used on the current page
        global $post;

        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'ai_website_builder_home')) {
            $this->output_inline_styles();
        }
    }

    /**
     * Enqueue styles for admin (for shortcode preview)
     */
    public function enqueue_admin_styles($hook)
    {
        // Enqueue on post edit screens
        if (in_array($hook, array('post.php', 'post-new.php', 'widgets.php'))) {
            $this->output_inline_styles();
        }
    }

    /**
     * Output the CSS styles inline
     */
    private function output_inline_styles()
    {
        static $styles_loaded = false;

        // Prevent loading styles multiple times
        if ($styles_loaded) {
            return;
        }

        $css_path = AI_WEB_SITE_PLUGIN_DIR . 'assets/home-page/home-page-styles.css';

        if (file_exists($css_path)) {
            echo '<style type="text/css" id="ai-website-builder-home-styles">';
            echo file_get_contents($css_path);
            echo '</style>';
            $styles_loaded = true;
        }
    }

    /**
     * Get shortcode usage instructions
     *
     * @return string Instructions HTML
     */
    public static function get_usage_instructions()
    {
        return '
        <div class="ai-shortcode-instructions">
            <h3>AI Website Builder Home Page Shortcode</h3>
            <p>Use the shortcode <code>[ai_website_builder_home]</code> to display the home page content.</p>
            
            <h4>Available Parameters:</h4>
            <ul>
                <li><strong>cta_url</strong> - The URL for the "Start Building Now" button (default: https://editor.ai-web.site)</li>
                <li><strong>show_features</strong> - Show features section (true/false, default: true)</li>
                <li><strong>show_pricing</strong> - Show pricing section (true/false, default: true)</li>
                <li><strong>show_how_it_works</strong> - Show how it works section (true/false, default: true)</li>
                <li><strong>title</strong> - Main hero title (default: AI Website Free Live Frontend Builder)</li>
                <li><strong>subtitle</strong> - Hero subtitle text</li>
            </ul>
            
            <h4>Example Usage:</h4>
            <code>[ai_website_builder_home cta_url="https://your-editor.com" show_pricing="false"]</code>
        </div>';
    }

    /**
     * Check if shortcode is being used on current page
     *
     * @return bool
     */
    public static function is_shortcode_in_use()
    {
        global $post;

        if (is_a($post, 'WP_Post')) {
            return has_shortcode($post->post_content, 'ai_website_builder_home');
        }

        return false;
    }

    /**
     * Add shortcode button to editor (for future enhancement)
     */
    public function add_shortcode_button()
    {
        // This could be enhanced to add a button to the WordPress editor
        // for easier shortcode insertion
    }
}
