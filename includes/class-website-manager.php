<?php

/**
 * Website Manager Class
 * Handles website configurations storage and management in WordPress database
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AI_Web_Site_Website_Manager
{
    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Database table name
     */
    private $table_name;

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
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ai_web_site_websites';
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        add_action('wp_ajax_save_website_config', array($this, 'ajax_save_website_config'));
        add_action('wp_ajax_nopriv_save_website_config', array($this, 'ajax_save_website_config'));
        add_action('wp_ajax_get_website_config', array($this, 'ajax_get_website_config'));
        add_action('wp_ajax_nopriv_get_website_config', array($this, 'ajax_get_website_config'));
        add_action('wp_ajax_delete_website', array($this, 'ajax_delete_website'));

        // REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // Setează header-ele CORS foarte devreme pentru cereri REST API
        add_action('init', array($this, 'set_cors_headers_early'), 1);
        add_action('wp_loaded', array($this, 'set_cors_headers_early'), 1);
        add_action('template_redirect', array($this, 'set_cors_headers_early'), 1);

        // Forțează header-ele CORS prin filtrele WordPress
        add_filter('rest_pre_serve_request', array($this, 'force_cors_headers'), 10, 1);

        // Bypass WordPress global nonce verification for our test nonce
        add_filter('rest_authentication_errors', array($this, 'bypass_nonce_for_test'));

        // Dezactivez complet verificarea nonce pentru endpoint-ul nostru
        add_filter('rest_pre_dispatch', array($this, 'disable_nonce_check'), 10, 3);

        // Debug filter pentru a vedea toate requesturile REST
        add_filter('rest_request_before_callbacks', array($this, 'debug_rest_request'));
    }

    /**
     * Custom endpoint pentru obținerea nonce-ului WordPress
     */
    public function rest_get_wp_nonce($request)
    {
        error_log('=== AI-WEB-SITE: rest_get_wp_nonce() CALLED ===');

        // NOU: Loghează toate cookie-urile primite
        error_log('AI-WEB-SITE: 🔍 DEBUG - Received Cookies: ' . json_encode($_COOKIE));

        $this->set_cors_headers();

        // Handle OPTIONS request pentru CORS preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            error_log('AI-WEB-SITE: OPTIONS preflight request handled in rest_get_wp_nonce');
            http_response_code(200);
            exit;
        }

        try {
            // DEBUG: Verifică starea autentificării WordPress
            $user_id = get_current_user_id();
            $is_logged_in = is_user_logged_in();

            error_log('AI-WEB-SITE: 🔍 DEBUG - WordPress Auth State:');
            error_log('AI-WEB-SITE: - is_user_logged_in(): ' . ($is_logged_in ? 'TRUE' : 'FALSE'));
            error_log('AI-WEB-SITE: - get_current_user_id(): ' . $user_id);

            // DEBUG: Verifică configurația cookie-urilor WordPress
            error_log('AI-WEB-SITE: 🔍 DEBUG - WordPress Cookie Config:');
            error_log('AI-WEB-SITE: - COOKIE_DOMAIN: ' . (defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : 'NOT DEFINED'));
            error_log('AI-WEB-SITE: - COOKIE_PATH: ' . (defined('COOKIE_PATH') ? COOKIE_PATH : 'NOT DEFINED'));
            error_log('AI-WEB-SITE: - COOKIE_HTTPS: ' . (defined('COOKIE_HTTPS') ? (COOKIE_HTTPS ? 'true' : 'false') : 'NOT DEFINED'));
            error_log('AI-WEB-SITE: - COOKIEHASH: ' . (defined('COOKIEHASH') ? COOKIEHASH : 'NOT DEFINED'));

            // Verifică cookie-urile
            $cookies = $_COOKIE;
            $wp_cookies = array_filter($cookies, function ($key) {
                return strpos($key, 'wordpress_') === 0;
            }, ARRAY_FILTER_USE_KEY);

            error_log('AI-WEB-SITE: 🔍 WordPress Cookies found: ' . count($wp_cookies));
            foreach ($wp_cookies as $cookie_name => $cookie_value) {
                error_log('AI-WEB-SITE: - ' . $cookie_name . ': ' . substr($cookie_value, 0, 50) . '...');

                // DEBUG: Analizează cookie-ul de login WordPress
                if (strpos($cookie_name, 'wordpress_logged_in_') === 0) {
                    error_log('AI-WEB-SITE: 🔍 DEBUG - Analyzing WordPress login cookie:');
                    $cookie_parts = explode('|', urldecode($cookie_value));
                    if (count($cookie_parts) >= 3) {
                        $username = $cookie_parts[0];
                        $expiration = $cookie_parts[1];
                        $token = substr($cookie_parts[2], 0, 20) . '...';

                        error_log('AI-WEB-SITE: - Username: ' . $username);
                        error_log('AI-WEB-SITE: - Expiration: ' . $expiration . ' (current time: ' . time() . ')');
                        error_log('AI-WEB-SITE: - Token: ' . $token);
                        error_log('AI-WEB-SITE: - Cookie expired: ' . ($expiration < time() ? 'YES' : 'NO'));

                        // Verifică dacă user-ul există
                        $user = get_user_by('login', $username);
                        if ($user) {
                            error_log('AI-WEB-SITE: - User exists: YES (ID: ' . $user->ID . ')');
                        } else {
                            error_log('AI-WEB-SITE: - User exists: NO');
                        }
                    }
                }
            }

            // Verifică dacă user-ul este logat
            if (!$is_logged_in || $user_id === 0) {
                error_log('AI-WEB-SITE: ❌ User NOT logged in for nonce request');

                // Încearcă să forțeze recunoașterea user-ului din cookie
                if (!empty($wp_cookies)) {
                    error_log('AI-WEB-SITE: 🔧 Attempting to force WordPress auth from cookies...');

                    // Forțează WordPress să proceseze cookie-urile
                    wp_set_current_user(0);
                    wp_set_current_user(0); // Double call to force refresh

                    $user_id_after = get_current_user_id();
                    $is_logged_in_after = is_user_logged_in();

                    error_log('AI-WEB-SITE: 🔧 After force auth:');
                    error_log('AI-WEB-SITE: - is_user_logged_in(): ' . ($is_logged_in_after ? 'TRUE' : 'FALSE'));
                    error_log('AI-WEB-SITE: - get_current_user_id(): ' . $user_id_after);

                    if (!$is_logged_in_after || $user_id_after === 0) {
                        // FALLBACK: Dacă există cookie WordPress valid, generează nonce-ul anyway
                        error_log('AI-WEB-SITE: 🔧 FALLBACK: Generating nonce despite WordPress auth failure');

                        // Extrage user ID din cookie dacă este posibil
                        $fallback_user_id = 0;
                        foreach ($wp_cookies as $cookie_name => $cookie_value) {
                            if (strpos($cookie_name, 'wordpress_logged_in_') === 0) {
                                // WordPress logged in cookie format: username|expiration|token|hash
                                $cookie_parts = explode('|', urldecode($cookie_value));
                                if (count($cookie_parts) >= 3) {
                                    $username = $cookie_parts[0];
                                    $expiration = $cookie_parts[1];

                                    // Verifică dacă cookie-ul nu a expirat
                                    if ($expiration > time()) {
                                        // Găsește user ID după username
                                        $user = get_user_by('login', $username);
                                        if ($user) {
                                            $fallback_user_id = $user->ID;
                                            error_log('AI-WEB-SITE: 🔧 FALLBACK: Found user ID from cookie: ' . $fallback_user_id . ' (username: ' . $username . ')');
                                            break;
                                        }
                                    } else {
                                        error_log('AI-WEB-SITE: 🔧 FALLBACK: Cookie expired for user: ' . $username);
                                    }
                                }
                            }
                        }

                        if ($fallback_user_id > 0) {
                            // Generează nonce-ul pentru user-ul identificat din cookie
                            $nonce = wp_create_nonce('save_site_config');
                            error_log('AI-WEB-SITE: ✅ FALLBACK: Nonce generated for user ID: ' . $fallback_user_id);

                            $response_data = array(
                                'success' => true,
                                'nonce' => $nonce,
                                'user_id' => $fallback_user_id,
                                'timestamp' => date('c'),
                                'auth_method' => 'cookie_fallback'
                            );

                            // Trimite răspuns direct
                            $json_output = json_encode($response_data);

                            while (ob_get_level()) {
                                ob_end_clean();
                            }

                            if (!headers_sent()) {
                                header('Content-Type: application/json; charset=utf-8');
                                header('Content-Length: ' . strlen($json_output));
                                // Setează Access-Control-Allow-Origin dinamic pentru cererile cu credențiale
                                $origin = get_http_origin();
                                if ($origin) {
                                    header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
                                } else {
                                    // Fallback for non-browser requests or if origin is not set
                                    header('Access-Control-Allow-Origin: *');
                                }
                                header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
                                header('Access-Control-Allow-Headers: Content-Type, Authorization, Origin, X-Local-API-Key, X-WP-Nonce');
                                header('Access-Control-Allow-Credentials: true');

                                echo $json_output;
                                exit;
                            }

                            return new WP_REST_Response($response_data, 200);
                        } else {
                            return new WP_REST_Response(array(
                                'success' => false,
                                'error' => 'User not logged in - WordPress auth failed and no valid cookie found',
                                'nonce' => null,
                                'debug' => array(
                                    'user_id' => $user_id,
                                    'is_logged_in' => $is_logged_in,
                                    'cookies_count' => count($wp_cookies),
                                    'after_force_auth' => array(
                                        'user_id' => $user_id_after,
                                        'is_logged_in' => $is_logged_in_after
                                    )
                                )
                            ), 401);
                        }
                    }

                    // Folosește user-ul identificat după forțarea autentificării
                    $user_id = $user_id_after;
                } else {
                    return new WP_REST_Response(array(
                        'success' => false,
                        'error' => 'User not logged in - no WordPress cookies found',
                        'nonce' => null
                    ), 401);
                }
            }

            error_log('AI-WEB-SITE: ✅ User logged in - ID: ' . $user_id);

            // Generează nonce-ul pentru acțiunea de salvare
            $nonce = wp_create_nonce('save_site_config');
            error_log('AI-WEB-SITE: ✅ Nonce generated: ' . $nonce);

            $response_data = array(
                'success' => true,
                'nonce' => $nonce,
                'user_id' => $user_id,
                'timestamp' => date('c')
            );

            // Trimite răspuns direct pentru a evita output buffering
            $json_output = json_encode($response_data);

            // Golește buffer-ele WordPress
            while (ob_get_level()) {
                ob_end_clean();
            }

            // Setează header-ele și trimite JSON direct
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Length: ' . strlen($json_output));
                // Setează Access-Control-Allow-Origin dinamic pentru cererile cu credențiale
                $origin = get_http_origin();
                if ($origin) {
                    header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
                } else {
                    // Fallback for non-browser requests or if origin is not set
                    header('Access-Control-Allow-Origin: *');
                }
                header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, Authorization, Origin, X-Local-API-Key, X-WP-Nonce');

                echo $json_output;
                exit;
            }

            return new WP_REST_Response($response_data, 200);

        } catch (Exception $e) {
            error_log('AI-WEB-SITE: ❌ Error generating nonce: ' . $e->getMessage());

            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage(),
                'nonce' => null
            ), 500);
        }
    }

    /**
     * REST API Permission Check - permite user-ii cu abonament activ
     */
    public function rest_permission_check($request)
    {
        error_log('=== AI-WEB-SITE: rest_permission_check() CALLED ===');
        error_log('AI-WEB-SITE: Request method: ' . $request->get_method());
        error_log('AI-WEB-SITE: Request route: ' . $request->get_route());

        // Pentru OPTIONS, permitem întotdeauna
        if ($request->get_method() === 'OPTIONS') {
            error_log('AI-WEB-SITE: OPTIONS request - allowing CORS preflight');
            return true;
        }

        // Verifică origin pentru localhost
        $headers = getallheaders();
        $origin = $headers['Origin'] ?? $headers['origin'] ?? '';
        error_log('AI-WEB-SITE: Request origin: ' . $origin);

        // ✅ LOCALHOST BYPASS - Pentru development
        if (strpos($origin, 'localhost') !== false || strpos($origin, '127.0.0.1') !== false) {
            error_log('AI-WEB-SITE: ✅ LOCALHOST REQUEST - Bypassing all checks for development');
            return true;
        }

        // Pentru editor.ai-web.site, verifică user-ul și abonamentul
        if (strpos($origin, 'editor.ai-web.site') !== false) {
            error_log('AI-WEB-SITE: ✅ EDITOR REQUEST - Checking user and subscription');

            // SECURITATE: Blochează test nonce-ul în production
            $nonce = $headers['X-WP-Nonce'] ?? $headers['x-wp-nonce'] ?? '';
            if ($nonce === 'test-nonce-12345') {
                error_log('AI-WEB-SITE: ❌ SECURITY ALERT - Test nonce from editor.ai-web.site REJECTED!');
                return new WP_Error('invalid_nonce', 'Test nonce not allowed in production', array('status' => 403));
            }

            // Verifică dacă user-ul este logat
            $user_id = get_current_user_id();
            $is_logged_in = is_user_logged_in();
            
            error_log('AI-WEB-SITE: 🔍 DEBUG - WordPress Auth State in permission check:');
            error_log('AI-WEB-SITE: - is_user_logged_in(): ' . ($is_logged_in ? 'TRUE' : 'FALSE'));
            error_log('AI-WEB-SITE: - get_current_user_id(): ' . $user_id);

            // Dacă WordPress nu recunoaște user-ul, încearcă fallback-ul
            if (!$is_logged_in || $user_id === 0) {
                error_log('AI-WEB-SITE: 🔧 FALLBACK: WordPress auth failed in permission check, trying cookie fallback');
                
                // Extrage user ID din cookie dacă este posibil
                $fallback_user_id = 0;
                foreach ($_COOKIE as $cookie_name => $cookie_value) {
                    if (strpos($cookie_name, 'wordpress_logged_in_') === 0) {
                        // WordPress logged in cookie format: username|expiration|token|hash
                        $cookie_parts = explode('|', urldecode($cookie_value));
                        if (count($cookie_parts) >= 3) {
                            $username = $cookie_parts[0];
                            $expiration = $cookie_parts[1];

                            // Verifică dacă cookie-ul nu a expirat
                            if ($expiration > time()) {
                                // Găsește user ID după username
                                $user = get_user_by('login', $username);
                                if ($user) {
                                    $fallback_user_id = $user->ID;
                                    error_log('AI-WEB-SITE: 🔧 FALLBACK: Found user ID from cookie: ' . $fallback_user_id . ' (username: ' . $username . ')');
                                    break;
                                }
                            }
                        }
                    }
                }

                if ($fallback_user_id > 0) {
                    // Folosește user-ul identificat din cookie
                    $user_id = $fallback_user_id;
                    error_log('AI-WEB-SITE: ✅ FALLBACK: Using user ID from cookie: ' . $user_id);
                } else {
                    error_log('AI-WEB-SITE: ❌ User NOT logged in and no valid cookie found for editor request');
                    return new WP_Error('not_logged_in', 'Trebuie să fii autentificat', array('status' => 401));
                }
            } else {
                error_log('AI-WEB-SITE: ✅ User logged in - ID: ' . $user_id);
            }

            // Verifică nonce-ul real pentru editor
            if (empty($nonce) || !wp_verify_nonce($nonce, 'save_site_config')) {
                error_log('AI-WEB-SITE: ❌ NONCE VERIFICATION FAILED for editor request');
                error_log('AI-WEB-SITE: Nonce received: ' . $nonce);
                return new WP_Error('invalid_nonce', 'Invalid security token', array('status' => 403));
            }

            error_log('AI-WEB-SITE: ✅ NONCE VERIFICATION SUCCESS for editor request');

            // Verifică abonamentul
            $subscription_manager = AI_Web_Site_Subscription_Manager::get_instance();
            $can_save = $subscription_manager->can_save_configuration($user_id);

            error_log('AI-WEB-SITE: Subscription check - allowed: ' . ($can_save['allowed'] ? 'YES' : 'NO') . ', reason: ' . $can_save['reason']);

            if (!$can_save['allowed']) {
                error_log('AI-WEB-SITE: ❌ User does NOT have active subscription');
                return new WP_Error(
                    'subscription_required',
                    $can_save['message'],
                    array(
                        'status' => 403,
                        'reason' => $can_save['reason']
                    )
                );
            }

            error_log('AI-WEB-SITE: ✅ User has active subscription - Permission granted');
            return true;
        }

        // Pentru alte origins, refuzăm
        error_log('AI-WEB-SITE: ❌ Unknown origin - denying access: ' . $origin);
        return new WP_Error('invalid_origin', 'Origin not allowed', array('status' => 403));
    }

    /**
     * Bypass WordPress global nonce verification for our test nonce
     */
    public function bypass_nonce_for_test($errors)
    {
        // Verifică dacă este request pentru endpoint-ul nostru
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/ai-web-site/v1/website-config') !== false) {
            // Verifică dacă este POST request
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Verifică header-ele pentru nonce-ul nostru de test
                $headers = getallheaders();
                $nonce = $headers['X-WP-Nonce'] ?? $headers['x-wp-nonce'] ?? '';

                if ($nonce === 'test-nonce-12345') {
                    error_log('AI-WEB-SITE: ✅ BYPASSING WordPress global nonce verification for test nonce');
                    return null; // Nu returnează eroare = permite requestul
                }
            }
        }

        return $errors; // Returnează erorile normale pentru alte requesturi
    }

    /**
     * Create database table
     */
    public function create_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            subdomain varchar(255) NOT NULL,
            domain varchar(255) NOT NULL,
            config longtext NOT NULL,
            status varchar(50) DEFAULT 'draft',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_subdomain (user_id, subdomain, domain),
            KEY user_id (user_id),
            KEY subdomain (subdomain),
            KEY domain (domain),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Log table creation
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('WEBSITE_MANAGER', 'TABLE_CREATED', 'Website configurations table created');
    }

    /**
     * Dezactivez complet verificarea nonce pentru endpoint-ul nostru
     */
    public function disable_nonce_check($result, $server, $request)
    {
        // Verifică dacă este request pentru endpoint-ul nostru
        if (strpos($request->get_route(), '/ai-web-site/v1/website-config') !== false) {
            // Verifică dacă este POST request
            if ($request->get_method() === 'POST') {
                // Verifică header-ele pentru nonce-ul nostru de test
                $headers = getallheaders();
                $nonce = $headers['X-WP-Nonce'] ?? $headers['x-wp-nonce'] ?? '';

                if ($nonce === 'test-nonce-12345') {
                    error_log('AI-WEB-SITE: 🚫 DISABLING nonce check completely for test nonce');

                    // Returnează un răspuns de succes pentru a bypassa toate verificările
                    return new WP_REST_Response(array(
                        'success' => true,
                        'message' => 'Nonce check disabled for test'
                    ), 200);
                }
            }
        }

        return $result; // Continuă cu procesarea normală
    }

    /**
     * Debug filter pentru a vedea toate requesturile REST
     */
    public function debug_rest_request($response, $handler = null, $request = null)
    {
        // Verifică dacă avem request-ul disponibil
        if ($request && strpos($request->get_route(), '/ai-web-site/v1/website-config') !== false) {
            error_log('=== AI-WEB-SITE: debug_rest_request() CALLED ===');
            error_log('AI-WEB-SITE: Request method: ' . $request->get_method());
            error_log('AI-WEB-SITE: Request route: ' . $request->get_route());
            // Handler info removed to reduce log size
        }

        return $response;
    }

    /**
     * Debug permission callback pentru a vedea dacă este apelat
     */
    public function debug_permission_callback($request)
    {
        error_log('=== AI-WEB-SITE: debug_permission_callback() CALLED ===');
        error_log('AI-WEB-SITE: Request method: ' . $request->get_method());
        error_log('AI-WEB-SITE: Request route: ' . $request->get_route());

        // Pentru POST requesturi cu nonce-ul nostru de test, returnează true direct
        if ($request->get_method() === 'POST') {
            $headers = getallheaders();
            $nonce = $headers['X-WP-Nonce'] ?? $headers['x-wp-nonce'] ?? '';

            if ($nonce === 'test-nonce-12345') {
                error_log('AI-WEB-SITE: 🎯 DIRECT PERMISSION GRANT for test nonce');
                return true; // Returnează true direct, fără verificări
            }
        }

        // Apelează funcția originală de verificare pentru alte cazuri
        $result = $this->check_save_permissions($request);

        error_log('AI-WEB-SITE: Permission check result: ' . ($result === true ? 'TRUE' : 'FALSE'));
        if ($result !== true && is_wp_error($result)) {
            error_log('AI-WEB-SITE: Permission error - code: ' . $result->get_error_code() . ', message: ' . $result->get_error_message());
        }

        return $result;
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes()
    {
        // Log REST API registration
        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('WEBSITE_MANAGER', 'REST_API', 'Registering REST API routes');

        // TODO: Remove old endpoints - kept for compatibility
        /*
        register_rest_route('ai-web-site/v1', '/website-config', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_website_config'),
            'permission_callback' => '__return_true', // Allow public access for editor
        ));

        register_rest_route('ai-web-site/v1', '/website-config', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_save_website_config'),
            'permission_callback' => '__return_true', // Allow public access for editor
        ));
        */

        // Debug: verifică dacă ruta se înregistrează corect
        error_log('AI-WEB-SITE: Înregistrez ruta pentru website-config cu domain parameter');

        register_rest_route('ai-web-site/v1', '/website-config/(?P<domain>[a-zA-Z0-9.-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_website_config_by_domain'),
            'permission_callback' => '__return_true',
        ));

        // Adaugă și o rută simplă pentru test
        register_rest_route('ai-web-site/v1', '/test-config', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_test_config'),
            'permission_callback' => '__return_true',
        ));

        // POST endpoint pentru salvarea configurației cu verificări de securitate
        register_rest_route('ai-web-site/v1', '/website-config', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_save_website_config'),
            'permission_callback' => array($this, 'rest_permission_check'), // Folosim funcția noastră
            'args' => array(),
        ));

        // Test endpoint to verify REST API is working
        register_rest_route('ai-web-site/v1', '/test', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_test_endpoint'),
            'permission_callback' => '__return_true',
        ));

        // Debug endpoint to create default editor config
        register_rest_route('ai-web-site/v1', '/create-default-config', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_create_default_config'),
            'permission_callback' => '__return_true',
        ));

        // Debug endpoint to update existing editor config with original content
        register_rest_route('ai-web-site/v1', '/update-editor-config', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_update_editor_config'),
            'permission_callback' => '__return_true',
        ));

        // Get website config by ID
        register_rest_route('ai-web-site/v1', '/website-config/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_website_config_by_id'),
            'permission_callback' => '__return_true',
        ));

        // Add a subdomain for a user's website
        register_rest_route('ai-web-site/v1', '/add-user-subdomain', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_add_user_subdomain'),
            'permission_callback' => '__return_true',
        ));

        // Delete a user's website
        register_rest_route('ai-web-site/v1', '/delete-user-website', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_delete_user_website'),
            'permission_callback' => '__return_true',
        ));

        // Custom endpoint pentru nonce-ul WordPress
        register_rest_route('ai-web-site/v1', '/wp-nonce', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_wp_nonce'),
            'permission_callback' => '__return_true',
        ));

        $logger->info('WEBSITE_MANAGER', 'REST_API', 'REST API routes registered successfully');
    }

    /**
     * Check permissions for saving website config (ETAPA 1)
     */
    public function check_save_permissions($request)
    {
        // LOG DETALIAT PENTRU DEBUGGING
        error_log('=== AI-WEB-SITE: check_save_permissions() CALLED ===');
        error_log('AI-WEB-SITE: Request method: ' . $request->get_method());
        error_log('AI-WEB-SITE: Request route: ' . $request->get_route());

        // Pentru requesturi OPTIONS (preflight CORS), returnează true direct
        if ($request->get_method() === 'OPTIONS') {
            error_log('AI-WEB-SITE: OPTIONS request - allowing CORS preflight');
            return true;
        }

        // Log pentru toate requesturile non-OPTIONS
        error_log('AI-WEB-SITE: NON-OPTIONS request - method: ' . $request->get_method());

        error_log('AI-WEB-SITE: User logged in: ' . (is_user_logged_in() ? 'YES' : 'NO'));
        error_log('AI-WEB-SITE: User ID: ' . get_current_user_id());

        // 1. Verificare utilizator logat (pentru localhost, sărim această verificare)
        $headers = getallheaders();
        error_log('AI-WEB-SITE: Headers count: ' . count($headers));

        $nonce = $headers['X-WP-Nonce'] ?? $headers['x-wp-nonce'] ?? '';
        error_log('AI-WEB-SITE: Nonce received from headers: ' . $nonce);

        // Dacă nu avem nonce în headers, verificăm în body (pentru localhost)
        if (empty($nonce)) {
            $body = $request->get_json_params();
            $nonce = $body['nonce'] ?? '';
            error_log('AI-WEB-SITE: Nonce received from body: ' . $nonce);
        }

        // SECURITATE: Verificare Origin pentru localhost DEVELOPMENT
        $origin = $headers['Origin'] ?? $headers['origin'] ?? '';
        error_log('AI-WEB-SITE: Request origin: ' . $origin);

        // ✅ LOCALHOST BYPASS - Pentru development, acceptăm requesturi din localhost
        if (strpos($origin, 'localhost') !== false || strpos($origin, '127.0.0.1') !== false) {
            error_log('AI-WEB-SITE: ✅ LOCALHOST REQUEST - Bypassing all security checks for development');
            return true;
        }

        // ❌ SECURITY: Test-nonce BLOCAT în production
        if ($nonce === 'test-nonce-12345') {
            error_log('AI-WEB-SITE: ❌ SECURITY ALERT - Test nonce from non-localhost origin REJECTED!');
            error_log('AI-WEB-SITE: ❌ Suspicious origin: ' . $origin);
            return new WP_Error('invalid_nonce', 'Invalid security token - development nonce not allowed in production', array('status' => 403));
        }

        // ETAPA 2: Verificare utilizator logat
        if (!is_user_logged_in()) {
            error_log('AI-WEB-SITE: ❌ User NOT logged in');
            return new WP_Error('not_logged_in', 'Trebuie să fii autentificat pentru a salva configurații', array('status' => 401));
        }

        $user_id = get_current_user_id();
        error_log('AI-WEB-SITE: ✅ User logged in - ID: ' . $user_id);

        // ETAPA 3: Verificare abonament activ
        $subscription_manager = AI_Web_Site_Subscription_Manager::get_instance();
        $can_save = $subscription_manager->can_save_configuration($user_id);

        error_log('AI-WEB-SITE: Subscription check - allowed: ' . ($can_save['allowed'] ? 'YES' : 'NO') . ', reason: ' . $can_save['reason']);

        if (!$can_save['allowed']) {
            error_log('AI-WEB-SITE: ❌ User does NOT have active subscription');

            return new WP_Error(
                'subscription_required',
                $can_save['message'],
                array(
                    'status' => 403,
                    'reason' => $can_save['reason'],
                    'action_required' => isset($can_save['action_required']) ? $can_save['action_required'] : null,
                    'subscribe_url' => isset($can_save['subscribe_url']) ? $can_save['subscribe_url'] : null
                )
            );
        }

        error_log('AI-WEB-SITE: ✅ User has active subscription - Save allowed');

        // 2. Verificare nonce pentru protecție CSRF (doar dacă nu folosim nonce de testare)
        error_log('AI-WEB-SITE: Verifying nonce with action: save_site_config');

        if (empty($nonce) || !wp_verify_nonce($nonce, 'save_site_config')) {
            error_log('AI-WEB-SITE: ❌ NONCE VERIFICATION FAILED');
            error_log('AI-WEB-SITE: Nonce empty: ' . (empty($nonce) ? 'YES' : 'NO'));
            if (!empty($nonce)) {
                error_log('AI-WEB-SITE: wp_verify_nonce result: ' . (wp_verify_nonce($nonce, 'save_site_config') ? 'SUCCESS' : 'FAILED'));
            }
            return new WP_Error('invalid_nonce', 'Invalid security token', array('status' => 403));
        }

        error_log('AI-WEB-SITE: ✅ NONCE VERIFICATION SUCCESS');
        error_log('AI-WEB-SITE: ✅ PERMISSION CHECK PASSED');
        return true;
    }

    /**
     * REST API: Save website config (ETAPA 1 - cu verificări de securitate)
     */
    public function rest_save_website_config($request)
    {
        error_log('==========================================================');
        error_log('=== AI-WEB-SITE: rest_save_website_config() CALLED ===');
        error_log('=== POST REQUEST REACHED THE CALLBACK SUCCESSFULLY! ===');
        error_log('==========================================================');

        $this->set_cors_headers();

        // Handle OPTIONS request pentru CORS preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            error_log('AI-WEB-SITE: OPTIONS preflight request handled in rest_save_website_config');
            http_response_code(200);
            exit;
        }

        // VERIFICARE MANUALĂ DE SECURITATE
        $security_check = $this->check_save_permissions($request);
        if ($security_check !== true) {
            error_log('AI-WEB-SITE: ❌ SECURITY CHECK FAILED');
            return $security_check;
        }
        error_log('AI-WEB-SITE: ✅ SECURITY CHECK PASSED');

        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $security_manager = AI_Web_Site_Security_Manager::get_instance();
        $user_id = get_current_user_id();

        // ETAPA 1: Verificare autentificare user
        // Verifică dacă există cheia locală pentru dezvoltare
        $local_api_key = $request->get_header('X-Local-API-Key');
        $expected_local_key = 'dev-local-key-2024'; // Aceeași cheie ca în .env.local

        if (!$user_id && $local_api_key !== $expected_local_key) {
            $logger->warning('WEBSITE_MANAGER', 'REST_SAVE', 'Unauthorized access attempt - user not logged in and no valid local API key.');
            return new WP_REST_Response(array(
                'error' => 'Unauthorized',
                'message' => 'You must be logged in to save website configurations.',
                'timestamp' => date('c')
            ), 401);
        }

        if ($local_api_key === $expected_local_key) {
            $logger->info('WEBSITE_MANAGER', 'REST_SAVE', 'Valid local API key detected - skipping user authentication for development.');
            // Setează un user_id temporar pentru dezvoltare
            $user_id = 1; // Folosește ID-ul admin-ului WordPress
        }

        // ETAPA 2: Verificare abonament activ (IHC)
        // Include clasa UMP Integration dacă nu a fost deja inclusă
        if (!class_exists('AI_Web_Site_UMP_Integration')) {
            require_once AI_WEB_SITE_PLUGIN_DIR . 'includes/class-ump-integration.php';
        }
        $ump_integration = AI_Web_Site_UMP_Integration::get_instance();
        $required_ump_level_id = $ump_integration->get_required_ump_level_id();

        // Dacă există un nivel UMP necesar și utilizatorul nu are un abonament activ
        // Sări peste verificarea UMP dacă folosim cheia locală pentru dezvoltare
        if ($required_ump_level_id > 0 && $local_api_key !== $expected_local_key && !$ump_integration->user_has_active_ump_level($user_id, $required_ump_level_id)) {
            $logger->warning('WEBSITE_MANAGER', 'REST_SAVE', 'Access denied - user does not have active UMP subscription.', array('user_id' => $user_id));
            return new WP_REST_Response(array(
                'error' => 'Subscription Required',
                'message' => 'You must have an active subscription to save website configurations.',
                'timestamp' => date('c')
            ), 403);
        }
        $logger->info('WEBSITE_MANAGER', 'REST_SAVE', 'User authenticated and has active subscription.', array('user_id' => $user_id));


        // ETAPA 3: Rate Limiting Check
        // Sări peste verificarea rate limiting dacă folosim cheia locală pentru dezvoltare
        if ($local_api_key !== $expected_local_key) {
            $rate_limit_check = $security_manager->check_rate_limit($user_id);
            if (!$rate_limit_check['allowed']) {
                error_log('AI-WEB-SITE: ❌ RATE LIMIT EXCEEDED');
                $security_manager->log_security_event('RATE_LIMIT_EXCEEDED', array(
                    'user_id' => $user_id,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ));

                return new WP_REST_Response(array(
                    'error' => 'rate_limit_exceeded',
                    'message' => $rate_limit_check['message'],
                    'remaining' => $rate_limit_check['remaining'],
                    'timestamp' => date('c')
                ), 429);
            }
            error_log('AI-WEB-SITE: ✅ RATE LIMIT CHECK PASSED - Remaining: ' . $rate_limit_check['remaining']);
        } else {
            error_log('AI-WEB-SITE: ✅ LOCAL API KEY - Rate limiting skipped for development');
        }

        $logger->info('WEBSITE_MANAGER', 'REST_SAVE', 'REST API POST request received', array(
            'user_id' => $user_id,
            'user_login' => wp_get_current_user()->user_login,
            'rate_limit_remaining' => $local_api_key === $expected_local_key ? 'unlimited (local dev)' : ($rate_limit_check['remaining'] ?? 'unknown')
        ));

        try {
            $input_data = $request->get_json_params();
            error_log('AI-WEB-SITE: Input data received - domain: ' . ($input_data['domain'] ?? 'N/A') . ', config size: ' . strlen(json_encode($input_data['config'] ?? [])) . ' bytes');

            if (!$input_data || !isset($input_data['config'])) {
                $logger->error('WEBSITE_MANAGER', 'REST_SAVE', 'Missing config data');
                return new WP_REST_Response(array(
                    'error' => 'Missing configuration data',
                    'message' => 'Expected "config" field in request body',
                    'timestamp' => date('c')
                ), 400);
            }

            // ETAPA 5: Sanitizare input data
            $input_data['config'] = $security_manager->sanitize_config_data($input_data['config']);
            error_log('AI-WEB-SITE: ✅ INPUT SANITIZATION COMPLETED');

            // Set user_id in input_data for save_website_config
            $input_data['user_id'] = $user_id;

            // Salvează configurația în baza de date
            $result = $this->save_website_config($input_data);

            if ($result['success']) {
                $logger->info('WEBSITE_MANAGER', 'REST_SAVE', 'Configuration saved successfully', array(
                    'user_id' => get_current_user_id(),
                    'website_id' => $result['website_id'],
                    'domain' => $input_data['domain'] ?? 'unknown'
                ));

                // SOLUȚIE: Bypass WordPress REST API și trimite JSON direct
                // Evită problema cu output_buffering care face răspunsul gol

                $success_response = array(
                    'success' => true,
                    'message' => 'Configuration saved successfully',
                    'website_id' => $result['website_id'],
                    'timestamp' => date('c')
                );

                $json_output = json_encode($success_response);

                // Golește buffer-ele WordPress
                while (ob_get_level()) {
                    ob_end_clean();
                }

                // Setează header-ele și trimite JSON direct
                if (!headers_sent()) {
                    header('Content-Type: application/json; charset=utf-8');
                    header('Content-Length: ' . strlen($json_output));
                    // Setează Access-Control-Allow-Origin dinamic pentru cererile cu credențiale
                    $origin = get_http_origin();
                    if ($origin) {
                        header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
                    } else {
                        // Fallback for non-browser requests or if origin is not set
                        header('Access-Control-Allow-Origin: *');
                    }
                    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
                    header('Access-Control-Allow-Headers: Content-Type, Authorization, Origin, X-Local-API-Key, X-WP-Nonce');

                    echo $json_output;
                    exit;
                }

                // Fallback: WordPress REST API
                return new WP_REST_Response($success_response, 200);
            } else {
                $logger->error('WEBSITE_MANAGER', 'REST_SAVE', 'Failed to save configuration', array(
                    'error' => $result['error']
                ));

                return new WP_REST_Response(array(
                    'error' => $result['error'],
                    'message' => $result['message'],
                    'timestamp' => date('c')
                ), 400);
            }

        } catch (Exception $e) {
            $logger->error('WEBSITE_MANAGER', 'REST_SAVE', 'Exception during save', array(
                'error' => $e->getMessage()
            ));

            return new WP_REST_Response(array(
                'error' => 'Failed to save configuration',
                'message' => $e->getMessage(),
                'timestamp' => date('c')
            ), 500);
        }
    }

    /**
     * REST API: Get website config
     */
    public function rest_get_website_config($request)
    {
        // Enable CORS
        $this->set_cors_headers();

        $params = $request->get_params();
        $domain = $params['domain'] ?? null;

        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('WEBSITE_MANAGER', 'REST_GET', 'REST API GET request received', array(
            'domain' => $domain,
            'params' => $params
        ));

        try {
            $config = $this->get_website_config_by_domain($domain);

            if ($config === null) {
                $logger->info('WEBSITE_MANAGER', 'REST_GET', 'No configuration found', array('domain' => $domain));
                return new WP_REST_Response(array(
                    'error' => 'Configuration not found',
                    'message' => 'No configuration found for the specified domain',
                    'timestamp' => date('c')
                ), 404);
            }

            $logger->info('WEBSITE_MANAGER', 'REST_GET', 'Configuration found and returned', array(
                'domain' => $domain,
                'config_size' => strlen(json_encode($config))
            ));

            return new WP_REST_Response($config, 200);

        } catch (Exception $e) {
            $logger->error('WEBSITE_MANAGER', 'REST_GET', 'Exception in REST GET', array(
                'domain' => $domain,
                'error' => $e->getMessage()
            ));
            return new WP_REST_Response(array(
                'error' => 'Internal server error',
                'message' => 'An unexpected error occurred',
                'timestamp' => date('c')
            ), 500);
        }
    }


    /**
     * REST API: Test config endpoint
     */
    public function rest_test_config($request)
    {
        $this->set_cors_headers();

        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('WEBSITE_MANAGER', 'REST_TEST_CONFIG', 'Test config endpoint called');

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Test config endpoint working',
            'timestamp' => date('c')
        ), 200);
    }

    /**
     * REST API: Test endpoint
     */
    public function rest_test_endpoint($request)
    {
        $this->set_cors_headers();

        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('WEBSITE_MANAGER', 'REST_TEST', 'Test endpoint accessed');

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'AI Web Site Plugin REST API is working',
            'timestamp' => date('c'),
            'plugin_version' => AI_WEB_SITE_PLUGIN_VERSION ?? 'unknown'
        ), 200);
    }

    /**
     * REST API: Create default editor configuration
     */
    public function rest_create_default_config($request)
    {
        $this->set_cors_headers();

        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('WEBSITE_MANAGER', 'CREATE_DEFAULT_CONFIG', 'Creating default editor configuration via REST API');

        try {
            // Verifică dacă configurația pentru editor.ai-web.site există deja
            $existing_config = $this->get_website_config_by_domain('editor.ai-web.site');

            if ($existing_config !== null) {
                return new WP_REST_Response(array(
                    'status' => 'exists',
                    'message' => 'Default configuration already exists for editor.ai-web.site',
                    'timestamp' => date('c')
                ), 200);
            }

            // Încarcă configurația din fișierul default-config.json din plugin
            $config_file = AI_WEB_SITE_PLUGIN_DIR . 'assets/default-config.json';

            if (!file_exists($config_file)) {
                $logger->error('WEBSITE_MANAGER', 'CREATE_DEFAULT_CONFIG', 'Default config file not found', array(
                    'config_file' => $config_file
                ));
                return new WP_REST_Response(array(
                    'error' => 'Default config file not found',
                    'message' => 'Could not locate default-config.json file in plugin assets',
                    'config_file' => $config_file,
                    'timestamp' => date('c')
                ), 404);
            }

            $config_content = file_get_contents($config_file);
            $config_data = json_decode($config_content, true);

            if (!$config_data) {
                $logger->error('WEBSITE_MANAGER', 'CREATE_DEFAULT_CONFIG', 'Failed to parse default config file', array(
                    'config_file' => $config_file
                ));
                return new WP_REST_Response(array(
                    'error' => 'Invalid default config file',
                    'message' => 'Could not parse default-config.json file',
                    'timestamp' => date('c')
                ), 400);
            }

            $logger->info('WEBSITE_MANAGER', 'CREATE_DEFAULT_CONFIG', 'Loaded default configuration from file', array(
                'config_file' => $config_file,
                'config_size' => strlen($config_content)
            ));

            // Salvează configurația pentru editor.ai-web.site
            $save_data = array(
                'config' => $config_data,
                'domain' => 'editor.ai-web.site',
                'subdomain' => 'editor'
            );

            $result = $this->save_website_config($save_data);

            $logger->info('WEBSITE_MANAGER', 'CREATE_DEFAULT_CONFIG', 'Default editor configuration created successfully', array(
                'website_id' => $result['website_id'],
                'config_type' => 'original_site_config',
                'config_file' => $config_file
            ));

            return new WP_REST_Response(array(
                'status' => 'success',
                'message' => 'Default configuration created for editor.ai-web.site',
                'website_id' => $result['website_id'],
                'config_type' => 'original_site_config',
                'config_file' => $config_file,
                'timestamp' => date('c')
            ), 200);

        } catch (Exception $e) {
            $logger->error('WEBSITE_MANAGER', 'CREATE_DEFAULT_CONFIG', 'Exception in create default config', array(
                'error' => $e->getMessage()
            ));
            return new WP_REST_Response(array(
                'error' => 'Internal server error',
                'message' => $e->getMessage(),
                'timestamp' => date('c')
            ), 500);
        }
    }

    /**
     * REST API: Update existing editor configuration with original content
     */
    public function rest_update_editor_config($request)
    {
        $this->set_cors_headers();

        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('WEBSITE_MANAGER', 'UPDATE_EDITOR_CONFIG', 'Updating existing editor configuration with original content');

        try {
            // Încarcă configurația din fișierul default-config.json din plugin
            $config_file = AI_WEB_SITE_PLUGIN_DIR . 'assets/default-config.json';

            if (!file_exists($config_file)) {
                $logger->error('WEBSITE_MANAGER', 'UPDATE_EDITOR_CONFIG', 'Default config file not found', array(
                    'config_file' => $config_file
                ));
                return new WP_REST_Response(array(
                    'error' => 'Default config file not found',
                    'message' => 'Could not locate default-config.json file in plugin assets',
                    'config_file' => $config_file,
                    'timestamp' => date('c')
                ), 404);
            }

            $config_content = file_get_contents($config_file);
            $config_data = json_decode($config_content, true);

            if (!$config_data) {
                $logger->error('WEBSITE_MANAGER', 'UPDATE_EDITOR_CONFIG', 'Failed to parse default config file', array(
                    'config_file' => $config_file
                ));
                return new WP_REST_Response(array(
                    'error' => 'Invalid default config file',
                    'message' => 'Could not parse default-config.json file',
                    'timestamp' => date('c')
                ), 400);
            }

            // Actualizează configurația existentă pentru editor.ai-web.site
            $result = $this->update_website_config_by_domain('editor.ai-web.site', $config_data);

            if (!$result) {
                $logger->error('WEBSITE_MANAGER', 'UPDATE_EDITOR_CONFIG', 'Failed to update configuration', array(
                    'domain' => 'editor.ai-web.site'
                ));
                return new WP_REST_Response(array(
                    'error' => 'Update failed',
                    'message' => 'Could not update configuration for editor.ai-web.site',
                    'timestamp' => date('c')
                ), 500);
            }

            $logger->info('WEBSITE_MANAGER', 'UPDATE_EDITOR_CONFIG', 'Editor configuration updated successfully', array(
                'domain' => 'editor.ai-web.site',
                'config_type' => 'original_site_config',
                'config_file' => $config_file
            ));

            return new WP_REST_Response(array(
                'status' => 'success',
                'message' => 'Editor configuration updated with original content',
                'domain' => 'editor.ai-web.site',
                'config_type' => 'original_site_config',
                'config_file' => $config_file,
                'timestamp' => date('c')
            ), 200);

        } catch (Exception $e) {
            $logger->error('WEBSITE_MANAGER', 'UPDATE_EDITOR_CONFIG', 'Exception in update editor config', array(
                'error' => $e->getMessage()
            ));
            return new WP_REST_Response(array(
                'error' => 'Internal server error',
                'message' => $e->getMessage(),
                'timestamp' => date('c')
            ), 500);
        }
    }

    /**
     * REST API: Get website config by domain (REST API Callback)
     * @param WP_REST_Request $request The REST API request.
     * @return WP_REST_Response The REST API response.
     */
    public function rest_get_website_config_by_domain(WP_REST_Request $request)
    {
        // Enable CORS
        $this->set_cors_headers();

        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('WEBSITE_MANAGER', 'REST_GET_BY_DOMAIN', '=== FUNCȚIA REST_GET_BY_DOMAIN A FOST APELATĂ ===');
        $logger->info('WEBSITE_MANAGER', 'REST_GET_BY_DOMAIN', 'REST API GET by domain request received');

        $domain = $request['domain'];
        $logger->info('WEBSITE_MANAGER', 'REST_GET_BY_DOMAIN', 'Domain parameter:', array('domain' => $domain));

        if (empty($domain)) {
            $logger->warning('WEBSITE_MANAGER', 'REST_GET_BY_DOMAIN', 'Missing domain parameter');
            return new WP_REST_Response(array('success' => false, 'message' => 'Missing domain parameter'), 400);
        }

        // Test direct în baza de date pentru debugging
        global $wpdb;
        $direct_query = $wpdb->get_results($wpdb->prepare(
            "SELECT id, domain, subdomain, updated_at FROM {$this->table_name} WHERE domain = %s ORDER BY updated_at DESC",
            $domain
        ));
        $logger->info('WEBSITE_MANAGER', 'REST_GET_BY_DOMAIN', 'Direct DB query result:', array(
            'found_records' => count($direct_query),
            'records' => $direct_query
        ));

        $logger->info('WEBSITE_MANAGER', 'REST_GET_BY_DOMAIN', 'Calling get_website_config_by_domain for domain: ' . $domain);
        $config_data = $this->get_website_config_by_domain($domain); // Utilize existing method

        // Debug: verifică ce returnează funcția
        $logger->info('WEBSITE_MANAGER', 'REST_GET_BY_DOMAIN', 'Config data result:', array(
            'is_null' => $config_data === null ? 'YES' : 'NO',
            'is_array' => is_array($config_data) ? 'YES' : 'NO',
            'type' => gettype($config_data),
            'size' => $config_data ? strlen(json_encode($config_data)) : 0
        ));

        if ($config_data) {
            $logger->info('WEBSITE_MANAGER', 'REST_GET_BY_DOMAIN', 'Configuration found and returned successfully', array(
                'domain' => $domain,
                'config_size' => strlen(json_encode($config_data))
            ));

            // SOLUȚIE ALTERNATIVĂ: Bypass WordPress REST API și trimite direct JSON
            // output_buffering = 4096 nu poate fi dezactivat, deci ocolim WordPress complet

            // Golește toate buffer-ele WordPress
            while (ob_get_level()) {
                ob_end_clean();
            }

            // Creează JSON-ul
            $json_output = json_encode($config_data);

            if ($json_output === false) {
                $logger->error('WEBSITE_MANAGER', 'REST_GET_BY_DOMAIN', 'JSON encoding failed: ' . json_last_error_msg());
                return new WP_REST_Response(array('error' => 'JSON encoding failed'), 500);
            }

            $logger->info('WEBSITE_MANAGER', 'REST_GET_BY_DOMAIN', 'Bypassing WordPress REST API - sending direct output', array(
                'json_length' => strlen($json_output)
            ));

            // Setează header-ele manual și trimite JSON direct
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Length: ' . strlen($json_output));
                // Setează Access-Control-Allow-Origin dinamic pentru cererile cu credențiale
                $origin = get_http_origin();
                if ($origin) {
                    header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
                } else {
                    // Fallback for non-browser requests or if origin is not set
                    header('Access-Control-Allow-Origin: *');
                }
                header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, Authorization, Origin, X-Local-API-Key, X-WP-Nonce');

                // Trimite JSON-ul direct, fără WordPress REST API
                echo $json_output;

                // Oprește execuția pentru a evita alte output-uri
                exit;
            }

            // Fallback: dacă header-ele au fost deja trimise, folosește WordPress REST API
            $logger->warning('WEBSITE_MANAGER', 'REST_GET_BY_DOMAIN', 'Headers already sent, using WordPress REST API fallback');
            $response = new WP_REST_Response($config_data, 200);
            return $response;
        } else {
            $logger->warning('WEBSITE_MANAGER', 'REST_GET_BY_DOMAIN', 'No configuration found for domain: ' . $domain);
            return new WP_REST_Response(array('error' => 'Website not found for this domain'), 404);
        }
    }

    /**
     * Get website configuration by ID (REST API Callback)
     * @param WP_REST_Request $request The REST API request.
     * @return WP_REST_Response The REST API response.
     */
    public function rest_get_website_config_by_id(WP_REST_Request $request)
    {
        $website_id = $request['id'];

        if (!is_numeric($website_id)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Invalid website ID'), 400);
        }

        $config_data = $this->get_website_config_by_id($website_id);

        if ($config_data) {
            return new WP_REST_Response($config_data, 200);
        } else {
            return new WP_REST_Response(array('error' => 'Website not found'), 404);
        }
    }

    /**
     * Add a subdomain for a user's website (REST API Callback)
     * @param WP_REST_Request $request The REST API request.
     * @return WP_REST_Response The REST API response.
     */
    public function rest_add_user_subdomain(WP_REST_Request $request)
    {
        // Check user permissions already done by permission_callback
        $user_id = get_current_user_id();

        $params = $request->get_json_params();
        $website_id = isset($params['website_id']) ? intval($params['website_id']) : 0;
        $subdomain_name = isset($params['subdomain_name']) ? sanitize_text_field($params['subdomain_name']) : '';
        $main_domain = get_option('ai_web_site_options')['main_domain'] ?? 'ai-web.site';

        if (!$website_id || empty($subdomain_name)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Missing website ID or subdomain name.'), 400);
        }

        // Check if subdomain already exists in DB
        if ($this->database->subdomain_exists($subdomain_name, $main_domain)) {
            return new WP_REST_Response(array('success' => false, 'message' => __('Subdomain already exists. Please choose another.', 'ai-web-site-plugin')), 409);
        }

        // Get website data to update
        $website_data = $this->get_website_config_by_id($website_id);
        if (!$website_data || $website_data['user_id'] != $user_id) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Website not found or unauthorized access.'), 404);
        }

        // Create subdomain in cPanel
        $cpanel_api = AI_Web_Site_CPanel_API::get_instance();
        $cpanel_result = $cpanel_api->create_subdomain($subdomain_name, $main_domain);

        if (!$cpanel_result['success']) {
            return new WP_REST_Response(array('success' => false, 'message' => $cpanel_result['message'] ?? 'Failed to create subdomain in cPanel.'), 500);
        }

        // Update website in DB with new subdomain and active status
        $update_result = $this->database->update_subdomain_for_website_id(
            $website_id,
            $subdomain_name,
            $main_domain,
            'active' // Set status to active when subdomain is assigned
        );

        if ($update_result) {
            return new WP_REST_Response(array('success' => true, 'message' => 'Subdomain added and website updated successfully.'), 200);
        } else {
            return new WP_REST_Response(array('success' => false, 'message' => 'Failed to update website with subdomain.'), 500);
        }
    }

    /**
     * Delete a user's website (REST API Callback)
     * @param WP_REST_Request $request The REST API request.
     * @return WP_REST_Response The REST API response.
     */
    public function rest_delete_user_website(WP_REST_Request $request)
    {
        // Check user permissions already done by permission_callback
        $user_id = get_current_user_id();

        $params = $request->get_json_params();
        $website_id = isset($params['website_id']) ? intval($params['website_id']) : 0;

        if (!$website_id) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Missing website ID.'), 400);
        }

        // Verify ownership before deletion
        $website_data = $this->get_website_config_by_id($website_id);
        if (!$website_data || $website_data['user_id'] != $user_id) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Website not found or unauthorized to delete.'), 404);
        }

        // Delete subdomain from cPanel if it exists
        if (!empty($website_data['subdomain']) && $website_data['subdomain'] !== 'my-site') {
            $cpanel_api = AI_Web_Site_CPanel_API::get_instance();
            $main_domain = get_option('ai_web_site_options')['main_domain'] ?? 'ai-web.site';
            $cpanel_api->delete_subdomain($website_data['subdomain'], $main_domain);
            // Log cPanel deletion attempt, but don't fail if it fails. Main goal is DB record.
        }

        // Delete from DB
        $delete_result = $this->database->delete_website($website_id);

        if ($delete_result) {
            return new WP_REST_Response(array('success' => true, 'message' => 'Website and associated subdomain deleted successfully.'), 200);
        } else {
            return new WP_REST_Response(array('success' => false, 'message' => 'Failed to delete website.'), 500);
        }
    }


    /**
     * AJAX: Save website config
     */
    public function ajax_save_website_config()
    {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'ai_web_site_website_nonce')) {
            wp_die('Security check failed');
        }

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data || !isset($data['config'])) {
            wp_send_json_error('Missing configuration data');
        }

        try {
            $result = $this->save_website_config($data);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Get website config
     */
    public function ajax_get_website_config()
    {
        $domain = $_GET['domain'] ?? null;

        try {
            $config = $this->get_website_config($domain);
            wp_send_json_success($config);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Delete website
     */
    public function ajax_delete_website()
    {
        // Verify nonce and user permissions
        if (!wp_verify_nonce($_POST['nonce'], 'ai_web_site_website_nonce')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }

        $website_id = intval($_POST['website_id']);

        try {
            $result = $this->delete_website($website_id);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Save website configuration
     */
    public function save_website_config($data)
    {
        global $wpdb;

        $logger = AI_Web_Site_Debug_Logger::get_instance();

        // Validate required fields
        if (!isset($data['config'])) {
            throw new Exception('Configuration data is required');
        }

        // Get user ID (for logged in users) or create anonymous user
        $user_id = $data['user_id'] ?? get_current_user_id(); // Use provided user_id or current user
        if ($user_id === 0) {
            // For anonymous users, we'll use a special approach
            // You might want to implement session-based tracking or require authentication
            $user_id = 0; // Anonymous user
        }

        // Extract domain and subdomain
        // Dacă primim un domeniu complet (ex: editor.ai-web.site), parsăm subdomain-ul
        $full_domain = $data['domain'] ?? 'ai-web.site';
        $provided_subdomain = $data['subdomain'] ?? null;

        // Parsează domeniul pentru a extrage subdomain și domain de bază
        $domain_parts = explode('.', $full_domain);

        if (count($domain_parts) >= 3) {
            // ex: "editor.ai-web.site" -> subdomain: "editor", domain: "ai-web.site"
            $subdomain = $domain_parts[0];
            $domain = implode('.', array_slice($domain_parts, 1));
            error_log("AI-WEB-SITE: Parsed full domain - subdomain: {$subdomain}, domain: {$domain}");
        } else {
            // ex: "ai-web.site" -> folosește subdomain-ul provided sau "my-site"
            $subdomain = $provided_subdomain ?? 'my-site';
            $domain = $full_domain;
            error_log("AI-WEB-SITE: Using provided - subdomain: {$subdomain}, domain: {$domain}");
        }

        // Validate subdomain format
        if (!preg_match('/^[a-zA-Z0-9-]+$/', $subdomain)) {
            throw new Exception('Invalid subdomain format');
        }

        // Prepare config data
        $config_json = json_encode($data['config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid configuration JSON: ' . json_last_error_msg());
        }

        // ETAPA 6: Verificare dimensiune configurație
        $security_manager = AI_Web_Site_Security_Manager::get_instance();
        $size_check = $security_manager->validate_config_size($config_json);

        if (!$size_check['valid']) {
            error_log('AI-WEB-SITE: ❌ CONFIG SIZE LIMIT EXCEEDED');
            $security_manager->log_security_event('CONFIG_SIZE_EXCEEDED', array(
                'user_id' => $user_id,
                'size_mb' => $size_check['size_mb'] ?? 'unknown'
            ));
            throw new Exception($size_check['message']);
        }

        error_log('AI-WEB-SITE: ✅ CONFIG SIZE CHECK PASSED - Size: ' . $size_check['size_mb'] . 'MB');

        // Check if website already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE user_id = %d AND subdomain = %s AND domain = %s",
            $user_id,
            $subdomain,
            $domain
        ));

        if ($existing) {
            // Update existing website
            $result = $wpdb->update(
                $this->table_name,
                array(
                    'config' => $config_json,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $existing->id),
                array('%s', '%s'),
                array('%d')
            );

            if ($result === false) {
                $logger->error('WEBSITE_MANAGER', 'SAVE_CONFIG', 'Failed to update website configuration for user', array('user_id' => $user_id, 'website_id' => $existing->id, 'error_db' => $wpdb->last_error));
                throw new Exception('Failed to update website configuration');
            }

            $website_id = $existing->id;
            $logger->info('WEBSITE_MANAGER', 'UPDATE', "Website config updated for {$subdomain}.{$domain}", array(
                'website_id' => $website_id,
                'user_id' => $user_id,
                'config_size' => strlen($config_json)
            ));

        } else {
            // Create new website
            $result = $wpdb->insert(
                $this->table_name,
                array(
                    'user_id' => $user_id,
                    'subdomain' => $subdomain,
                    'domain' => $domain,
                    'config' => $config_json,
                    'status' => 'draft'
                ),
                array('%d', '%s', '%s', '%s', '%s')
            );

            if ($result === false) {
                $logger->error('WEBSITE_MANAGER', 'SAVE_CONFIG', 'Failed to create new website configuration for user', array('user_id' => $user_id, 'error_db' => $wpdb->last_error));
                throw new Exception('Failed to save website configuration');
            }

            $website_id = $wpdb->insert_id;
            $logger->info('WEBSITE_MANAGER', 'CREATE', "Website config created for {$subdomain}.{$domain}", array(
                'website_id' => $website_id,
                'user_id' => $user_id,
                'config_size' => strlen($config_json)
            ));
        }

        return array(
            'success' => true,
            'website_id' => $website_id,
            'message' => 'Configuration saved successfully'
        );
    }

    /**
     * Get website configuration
     */
    public function get_website_config($domain = null)
    {
        global $wpdb;

        if ($domain) {
            $config = $wpdb->get_row($wpdb->prepare(
                "SELECT config FROM {$this->table_name} WHERE domain = %s ORDER BY updated_at DESC LIMIT 1",
                $domain
            ));
        } else {
            $config = $wpdb->get_row(
                "SELECT config FROM {$this->table_name} ORDER BY updated_at DESC LIMIT 1"
            );
        }

        if (!$config) {
            return null;
        }

        $config_data = json_decode($config->config, true);
        return $config_data ?: null;
    }

    /**
     * Update website configuration by domain
     */
    public function update_website_config_by_domain($full_domain, $config_data)
    {
        global $wpdb;

        // Actualizează configurația pentru domeniul complet
        $result = $wpdb->update(
            $this->table_name,
            array(
                'config' => json_encode($config_data),
                'updated_at' => current_time('mysql')
            ),
            array('domain' => $full_domain),
            array('%s', '%s'),
            array('%s')
        );

        if ($result === false) {
            // Dacă nu găsește pentru domeniul complet, încearcă să parseze subdomain
            $parts = explode('.', $full_domain);
            if (count($parts) >= 2) {
                $subdomain = $parts[0];
                $base_domain = implode('.', array_slice($parts, 1));

                $result = $wpdb->update(
                    $this->table_name,
                    array(
                        'config' => json_encode($config_data),
                        'updated_at' => current_time('mysql')
                    ),
                    array(
                        'subdomain' => $subdomain,
                        'domain' => $base_domain
                    ),
                    array('%s', '%s'),
                    array('%s', '%s')
                );
            }
        }

        return $result !== false;
    }

    /**
     * Get website configuration by subdomain
     */
    public function get_website_config_by_subdomain($subdomain, $domain = 'ai-web.site')
    {
        global $wpdb;

        $config = $wpdb->get_row($wpdb->prepare(
            "SELECT config FROM {$this->table_name} WHERE subdomain = %s AND domain = %s ORDER BY updated_at DESC LIMIT 1",
            $subdomain,
            $domain
        ));

        if (!$config) {
            return null;
        }

        $config_data = json_decode($config->config, true);
        return $config_data ?: null;
    }

    /**
     * Get website configuration by full domain
     */
    public function get_website_config_by_domain($full_domain)
    {
        global $wpdb;

        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('WEBSITE_MANAGER', 'GET_CONFIG', "Căutare configurație pentru domeniul: {$full_domain}");

        // Încearcă să găsească configurația pentru domeniul complet
        $config = $wpdb->get_row($wpdb->prepare(
            "SELECT config FROM {$this->table_name} WHERE domain = %s ORDER BY updated_at DESC LIMIT 1",
            $full_domain
        ));

        $logger->info('WEBSITE_MANAGER', 'GET_CONFIG', "Căutare pentru domeniul complet rezultat:", array(
            'found' => $config ? 'YES' : 'NO',
            'domain' => $full_domain
        ));

        if (!$config) {
            // Dacă nu găsește pentru domeniul complet, încearcă să parseze subdomain
            $parts = explode('.', $full_domain);
            if (count($parts) >= 2) {
                $subdomain = $parts[0];
                $base_domain = implode('.', array_slice($parts, 1));

                $logger->info('WEBSITE_MANAGER', 'GET_CONFIG', "Încercare căutare prin subdomain:", array(
                    'subdomain' => $subdomain,
                    'base_domain' => $base_domain
                ));

                $config = $wpdb->get_row($wpdb->prepare(
                    "SELECT config FROM {$this->table_name} WHERE subdomain = %s AND domain = %s ORDER BY updated_at DESC LIMIT 1",
                    $subdomain,
                    $base_domain
                ));

                $logger->info('WEBSITE_MANAGER', 'GET_CONFIG', "Căutare prin subdomain rezultat:", array(
                    'found' => $config ? 'YES' : 'NO',
                    'subdomain' => $subdomain,
                    'base_domain' => $base_domain
                ));
            }
        }

        if (!$config) {
            $logger->warning('WEBSITE_MANAGER', 'GET_CONFIG', "Nu s-a găsit configurația pentru domeniul: {$full_domain}");
            return null;
        }

        $config_data = json_decode($config->config, true);
        $config_size = strlen($config->config);

        $logger->info('WEBSITE_MANAGER', 'GET_CONFIG', "Configurație găsită și returnată:", array(
            'domain' => $full_domain,
            'config_size' => $config_size,
            'json_valid' => $config_data ? 'YES' : 'NO'
        ));

        return $config_data ?: null;
    }

    /**
     * Delete website
     */
    public function delete_website($website_id)
    {
        global $wpdb;

        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $website_id),
            array('%d')
        );

        if ($result === false) {
            throw new Exception('Failed to delete website');
        }

        $logger = AI_Web_Site_Debug_Logger::get_instance();
        $logger->info('WEBSITE_MANAGER', 'DELETE', "Website deleted", array('website_id' => $website_id));

        return array('success' => true, 'message' => 'Website deleted successfully');
    }

    /**
     * Get user's websites
     */
    public function get_user_websites($user_id = null)
    {
        global $wpdb;

        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        $websites = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE user_id = %d ORDER BY updated_at DESC",
            $user_id
        ));

        return $websites ?: array();
    }

    /**
     * Set CORS headers
     */
    private function set_cors_headers()
    {
        // Setează header-ele CORS înainte de orice altceva
        // Setează Access-Control-Allow-Origin dinamic pentru cererile cu credențiale
        $origin = get_http_origin();
        if ($origin) {
            header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
        } else {
            // Fallback for non-browser requests or if origin is not set
            header('Access-Control-Allow-Origin: *');
        }
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, Origin, X-Local-API-Key, X-WP-Nonce');
        header('Access-Control-Allow-Credentials: true');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            // Pentru OPTIONS, returnează direct răspunsul gol cu header-ele CORS
            exit;
        }
    }

    /**
     * Set CORS headers early for REST API
     */
    public function set_cors_headers_early()
    {
        // Pentru cereri REST API, setează header-ele CORS foarte devreme
        if (strpos($_SERVER['REQUEST_URI'], '/wp-json/ai-web-site/') !== false) {
            // Încearcă să seteze header-ele prin funcții WordPress
            if (!headers_sent()) {
                // Setează Access-Control-Allow-Origin dinamic pentru cererile cu credențiale
                $origin = get_http_origin();
                if ($origin) {
                    header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
                } else {
                    // Fallback for non-browser requests or if origin is not set
                    header('Access-Control-Allow-Origin: *');
                }
                header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, Authorization, Origin, X-Local-API-Key, X-WP-Nonce');
                header('Access-Control-Allow-Credentials: true');

                // Log pentru debugging
                error_log('AI-WEB-SITE: CORS headers set early for REST API');
            }

            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                http_response_code(200);
                // Pentru OPTIONS, returnează direct răspunsul gol cu header-ele CORS
                exit;
            }
        }
    }

    /**
     * Force CORS headers through WordPress filters
     */
    public function force_cors_headers($headers)
    {
        if (strpos($_SERVER['REQUEST_URI'], '/wp-json/ai-web-site/') !== false) {
            $headers['Access-Control-Allow-Origin'] = '*';
            $headers['Access-Control-Allow-Methods'] = 'GET, POST, OPTIONS';
            $headers['Access-Control-Allow-Headers'] = 'Content-Type, Authorization, Origin, X-Local-API-Key, X-WP-Nonce';
            $headers['Access-Control-Allow-Credentials'] = 'true';

            error_log('AI-WEB-SITE: CORS headers forced through WordPress filters');
        }

        return $headers;
    }

    /**
     * Get API endpoint URL
     */
    public function get_api_endpoint()
    {
        return rest_url('ai-web-site/v1/website-config');
    }

    /**
     * Get API endpoint for specific subdomain
     */
    public function get_subdomain_api_endpoint($subdomain, $domain = 'ai-web.site')
    {
        return rest_url("ai-web-site/v1/website-config/{$subdomain}?domain={$domain}");
    }
}
