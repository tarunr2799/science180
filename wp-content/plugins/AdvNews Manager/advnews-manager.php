<?php
/**
* Plugin Name: Science180 Mail
* Plugin URI: https://science180.net/
* Description: A powerful, enterprise-grade newsletter management system for WordPress with advanced tracking, segmentation, and analytics capabilities.
* Version: 1.0.23
* Author: Science180
* Author URI: https://science180.net/
* Text Domain: advnews-manager
* Domain Path: /languages
* License: GPL v2 or later
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ADVNEWS_VERSION', '1.0.23');
define('ADVNEWS_DB_VERSION', '1.0.14');
define('ADVNEWS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ADVNEWS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ADVNEWS_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('ADVNEWS_TABLE_PREFIX', 'emails_advnews_');

// Include required files
require_once ADVNEWS_PLUGIN_DIR . 'includes/class-database.php';
require_once ADVNEWS_PLUGIN_DIR . 'includes/class-security.php';
require_once ADVNEWS_PLUGIN_DIR . 'includes/class-subscriber.php';
require_once ADVNEWS_PLUGIN_DIR . 'includes/class-campaign.php';
require_once ADVNEWS_PLUGIN_DIR . 'includes/class-queue.php';
require_once ADVNEWS_PLUGIN_DIR . 'includes/class-tracking.php';
require_once ADVNEWS_PLUGIN_DIR . 'includes/class-admin.php';
require_once ADVNEWS_PLUGIN_DIR . 'includes/class-ajax.php';
require_once ADVNEWS_PLUGIN_DIR . 'includes/class-cron.php';
require_once ADVNEWS_PLUGIN_DIR . 'includes/class-frontend.php';
require_once ADVNEWS_PLUGIN_DIR . 'includes/class-geolocation.php';
require_once ADVNEWS_PLUGIN_DIR . 'includes/class-category.php';
require_once ADVNEWS_PLUGIN_DIR . 'includes/functions.php';

/**
* Main AdvNews_Manager class
*/
class AdvNews_Manager
{
    private static $instance = null;
    private $database;
    private $security;
    private $subscriber;
    private $campaign;
    private $queue;
    private $tracking;
    private $admin;
    private $ajax;
    private $cron;
    private $frontend;
    private $geolocation;
    private $category;

    /**
    * Singleton instance
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
        $this->init_objects();
    }

    /**
    * Initialize hooks
    */
    private function init_hooks()
    {
        // Activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));

        // Load text domain for internationalization
        add_action('init', array($this, 'load_textdomain'));

        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // Admin init for handling POST requests
        add_action('admin_init', array($this, 'handle_admin_actions'));

        // Add rewrite rules for tracking
        add_action('init', array($this, 'add_rewrite_rules'));

        // Filter wp_mail to use SMTP if configured - INCREASED PRIORITY
        add_action('phpmailer_init', array($this, 'configure_smtp'), 9999);

        // Check for database upgrades on admin init
        add_action('admin_init', array($this, 'check_database_upgrade'));

        // IMPORTANT: Ensure cron jobs are scheduled on init (backup for existing installations)
        add_action('init', array($this, 'ensure_cron_scheduled'), 1);

        // Keep migrated .NET homepage menu/footer/carousel links pointed at the public .COM site.
        add_action('template_redirect', array($this, 'rewrite_homepage_science180_links'), 0);
    }

    /**
    * Initialize objects
    */
    private function init_objects()
    {
        $this->database = new AdvNews_Database();
        $this->security = new AdvNews_Security();
        $this->subscriber = new AdvNews_Subscriber();
        $this->campaign = new AdvNews_Campaign();
        $this->queue = new AdvNews_Queue();
        $this->tracking = new AdvNews_Tracking();
        $this->admin = new AdvNews_Admin();
        $this->ajax = new AdvNews_Ajax();
        $this->cron = new AdvNews_Cron();
        $this->frontend = new AdvNews_Frontend();
        $this->geolocation = new AdvNews_Geolocation();
        $this->category = new AdvNews_Category();
    }

    /**
    * Plugin activation
    */
    public function activate()
    {
        // Create database tables
        $this->database->create_tables();

        // Set default options
        $this->set_default_options();

        // Schedule cron jobs
        AdvNews_Cron::schedule_events();

        // Create necessary pages
        $this->create_pages();

        // Set database version
        update_option('advnews_db_version', ADVNEWS_DB_VERSION);

        // Store that we need to flush rewrite rules
        set_transient('advnews_flush_rewrite_rules', true);

        // Log activation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews] Plugin activated - Cron events scheduled');
        }
    }

    /**
    * Plugin deactivation
    */
    public function deactivate()
    {
        // Clear cron jobs
        AdvNews_Cron::clear_scheduled_events();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Log deactivation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews] Plugin deactivated - Cron events cleared');
        }
    }

    /**
    * Ensure cron jobs are scheduled (for existing installations)
    */
    public function ensure_cron_scheduled()
    {
        // Register custom cron schedules first
        add_filter('cron_schedules', array('AdvNews_Cron', 'add_cron_schedules'));

        // Check and schedule each cron job if not already scheduled
        if (!wp_next_scheduled('advnews_process_queue')) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, 'advnews_every_minute', 'advnews_process_queue');
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews] Scheduled advnews_process_queue');
            }
        }

        if (!wp_next_scheduled('advnews_daily_maintenance')) {
            wp_schedule_event(AdvNews_Cron::next_daily_maintenance_timestamp(), 'daily', 'advnews_daily_maintenance');
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews] Scheduled advnews_daily_maintenance');
            }
        }

        AdvNews_Cron::ensure_weekly_report_schedule();

        AdvNews_Cron::ensure_maxmind_update_schedule();
    }

    /**
    * Initialize plugin
    */
    public function init()
    {
        // Check if tables exist, create if not
        if (!$this->database->tables_exist()) {
            $this->database->create_tables();
        }

        // Keep legacy Science.net email settings corrected on existing installs.
        $this->apply_science180_option_migrations();
    }

    public function rewrite_homepage_science180_links()
    {
        if (is_admin() || wp_doing_ajax() || !(is_front_page() || is_home())) {
            return;
        }

        ob_start(array($this, 'rewrite_homepage_science180_link_output'));
    }

    public function rewrite_homepage_science180_link_output($html)
    {
        if (!is_string($html) || $html === '') {
            return $html;
        }

        return preg_replace_callback(
            '/<a\b([^>]*?)\bhref=(["\'])https?:\/\/(?:www\.)?science180\.net([^"\']*)\2([^>]*)>/i',
            function ($matches) {
                $path = $matches[3] !== '' ? $matches[3] : '/';
                if (preg_match('#/(wp-admin|wp-login\.php|wp-json|advnews-track|zsaztyyyuiui02lk)\b#i', $path)) {
                    return $matches[0];
                }

                return '<a' . $matches[1] . 'href=' . $matches[2] . 'https://science180.com' . $path . $matches[2] . $matches[4] . '>';
            },
            $html
        );
    }

    /**
    * Check for database upgrades
    */
    public function check_database_upgrade()
    {
        $current_db_version = get_option('advnews_db_version', '0');

        if (version_compare($current_db_version, ADVNEWS_DB_VERSION, '<')) {
            // Run database upgrade
            $this->database->create_tables();
            $this->apply_science180_option_migrations();
            update_option('advnews_db_version', ADVNEWS_DB_VERSION);

            // Log the upgrade
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'Science180 Mail: Database upgraded from %s to %s',
                    $current_db_version,
                    ADVNEWS_DB_VERSION
                ));
            }

            // Show admin notice
            add_action('admin_notices', array($this, 'show_upgrade_notice'));
        }

        // Check if we need to flush rewrite rules
        if (get_transient('advnews_flush_rewrite_rules')) {
            flush_rewrite_rules();
            delete_transient('advnews_flush_rewrite_rules');
        }
    }

    private function apply_science180_option_migrations()
    {
        $current_db_version = (string) get_option('advnews_db_version', '0');
        if (
            $current_db_version !== '0'
            && version_compare($current_db_version, '1.0.14', '<')
            && !get_option('advnews_weekly_report_guard_migrated')
        ) {
            $report_week = wp_date('o-W');
            add_option(
                'advnews_weekly_report_claim_' . sanitize_key($report_week),
                current_time('mysql'),
                '',
                false
            );
            update_option('advnews_last_weekly_report_week', $report_week, false);
            if (!get_option('advnews_last_weekly_report_sent_at')) {
                update_option('advnews_last_weekly_report_sent_at', current_time('mysql'), false);
            }
            update_option('advnews_weekly_report_guard_migrated', 1, false);
        }

        $reply_to = sanitize_email(get_option('advnews_reply_to', ''));
        if (!$reply_to || preg_match('/@science\.net$/i', $reply_to)) {
            update_option('advnews_reply_to', $reply_to ? preg_replace('/@science\.net$/i', '@science180.net', $reply_to) : 'contact@science180.net');
        }

        foreach (array('admin_email', 'new_admin_email', 'advnews_from_email', 'advnews_smtp_from_email', 'advnews_smtp_username') as $option) {
            $email = sanitize_email(get_option($option, ''));
            if ($email && preg_match('/@science\.net$/i', $email)) {
                update_option($option, preg_replace('/@science\.net$/i', '@science180.net', $email));
            }
        }

        foreach (array('advnews_company_name', 'advnews_from_name') as $option) {
            if (strcasecmp(trim((string) get_option($option, '')), 'AdvNews Manager') === 0) {
                update_option($option, 'Science180 Mail');
            }
        }
    }

    /**
    * Show upgrade notice
    */
    public function show_upgrade_notice()
    {
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php _e('Science180 Mail database updated successfully.', 'advnews-manager'); ?>
            </p>
        </div>
        <?php
    }

    /**
    * Load text domain for internationalization
    */
    public function load_textdomain()
    {
        load_plugin_textdomain(
            'advnews-manager',
            false,
            dirname(ADVNEWS_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
    * Add rewrite rules for tracking
    */
    public function add_rewrite_rules()
    {
        add_rewrite_rule(
            'advnews-track/([^/]+)/?',
            'index.php?advnews_action=$matches[1]',
            'top'
        );
        add_rewrite_tag('%advnews_action%', '([^&]+)');
        add_rewrite_tag('%advnews_log_id%', '([0-9]+)');
        add_rewrite_tag('%advnews_campaign_id%', '([0-9]+)');
        add_rewrite_tag('%advnews_subscriber_id%', '([0-9]+)');
        add_rewrite_tag('%advnews_hash%', '([a-f0-9]+)');
    }

    /**
    * Handle admin actions (POST requests)
    */
    public function handle_admin_actions()
    {
        // Handle save campaign
        if (isset($_POST['action']) && wp_unslash($_POST['action']) === 'advnews_save_campaign') {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'advnews_save_campaign')) {
                wp_die(__('Security check failed.', 'advnews-manager'));
            }
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions.', 'advnews-manager'));
            }
            $this->admin->handle_save_campaign();
        }

        // Handle save category
        if (isset($_POST['action']) && $_POST['action'] === 'advnews_save_category') {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'advnews_save_category')) {
                wp_die(__('Security check failed.', 'advnews-manager'));
            }
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions.', 'advnews-manager'));
            }
            $this->admin->handle_save_category();
        }

        // Handle save subscriber
        if (isset($_POST['action']) && $_POST['action'] === 'advnews_save_subscriber') {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'advnews_save_subscriber')) {
                wp_die(__('Security check failed.', 'advnews-manager'));
            }
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions.', 'advnews-manager'));
            }
            $this->admin->handle_save_subscriber();
        }

        // Handle save template
        if (isset($_POST['action']) && $_POST['action'] === 'advnews_save_template') {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'advnews_save_template')) {
                wp_die(__('Security check failed.', 'advnews-manager'));
            }
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions.', 'advnews-manager'));
            }
            $this->admin->handle_save_template();
        }

        // Handle export subscribers
        if (isset($_POST['action']) && $_POST['action'] === 'advnews_export_subscribers') {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'advnews_export_subscribers')) {
                wp_die(__('Security check failed.', 'advnews-manager'));
            }
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions.', 'advnews-manager'));
            }
            $this->admin->handle_export_subscribers();
        }
    }

    /**
    * Configure SMTP if settings are present
    * FIXED: Always ensure a valid From address is set with fallback chain
    */
    public function configure_smtp($phpmailer)
    {
        $smtp_host = get_option('advnews_smtp_host');

        // Skip if no SMTP configured
        if (empty($smtp_host)) {
            return;
        }

        // Skip if test config is being used (to prevent conflict with AJAX test)
        if (get_transient('advnews_smtp_test_config')) {
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews] Configuring global SMTP for: ' . $smtp_host);
            error_log('[AdvNews] Current context: ' . (defined('DOING_CRON') ? 'CRON' : (defined('DOING_AJAX') ? 'AJAX' : 'NORMAL')));
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = $smtp_host;
        $phpmailer->Port = get_option('advnews_smtp_port', 587);
        $encryption = get_option('advnews_smtp_encryption', 'tls');

        if ($encryption !== 'none') {
            $phpmailer->SMTPSecure = $encryption;
            $phpmailer->SMTPAutoTLS = false;  // FIXED: Prevent auto-TLS issues
        }

        if (get_option('advnews_smtp_authentication', 1)) {
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = get_option('advnews_smtp_username');

            // Decrypt password with error handling
            $encrypted_password = get_option('advnews_smtp_password', '');
            $decrypted_password = AdvNews_Security::decrypt($encrypted_password);

            if (empty($decrypted_password) && !empty($encrypted_password)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[AdvNews] WARNING: Password decryption returned empty. Encryption key may not be loaded.');
                    error_log('[AdvNews] Encrypted password length: ' . strlen($encrypted_password));
                    error_log('[AdvNews] Encryption key exists: ' . (get_option('advnews_encryption_key') ? 'YES' : 'NO'));
                }
                // Fallback: try to use encrypted password as-is (will likely fail, but logs the issue)
                $phpmailer->Password = $encrypted_password;
            } else {
                $phpmailer->Password = $decrypted_password;
            }

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews] SMTP Auth configured - Username: ' . $phpmailer->Username);
                error_log('[AdvNews] SMTP Password length: ' . strlen($phpmailer->Password));
            }
        }

        // FIXED: Always set a valid From address (fallback chain)
        $from_email = get_option('advnews_smtp_from_email');

        // Fallback 1: Use SMTP username if From email is empty
        if (empty($from_email)) {
            $from_email = get_option('advnews_smtp_username');
        }

        // Fallback 2: Use WordPress admin email if still empty
        if (empty($from_email)) {
            $from_email = get_option('admin_email');
        }

        // Validate and set From address
        if (!empty($from_email) && is_email($from_email)) {
            $phpmailer->From = $from_email;

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews] SMTP From address set to: ' . $from_email);
            }
        } else {
            // Critical error - cannot send without valid From
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews] ERROR: No valid From email available for SMTP');
                error_log('[AdvNews] Tried: advnews_smtp_from_email="' . get_option('advnews_smtp_from_email') . '"');
                error_log('[AdvNews] Tried: advnews_smtp_username="' . get_option('advnews_smtp_username') . '"');
                error_log('[AdvNews] Tried: admin_email="' . get_option('admin_email') . '"');
            }
            return;
        }

        // Set From name with fallbacks
        $from_name = get_option('advnews_smtp_from_name');
        if (empty($from_name)) {
            $from_name = get_option('advnews_from_name');
        }
        if (empty($from_name)) {
            $from_name = get_bloginfo('name');
        }
        if (!empty($from_name)) {
            $phpmailer->FromName = $from_name;
        }

        $phpmailer->SMTPKeepAlive = false;
        $phpmailer->SingleTo = false;

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews] SMTP configuration complete - From: ' . $phpmailer->From . ', FromName: ' . $phpmailer->FromName);
        }
    }

    /**
    * Enqueue frontend assets
    */
    public function enqueue_frontend_assets()
    {
        wp_enqueue_style(
            'advnews-frontend-css',
            ADVNEWS_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            ADVNEWS_VERSION
        );

        // Some installations do not include a standalone frontend script. Registering
        // without a source keeps the localized data available to the public forms
        // while preventing a broken script request on every public page.
        $frontend_script = ADVNEWS_PLUGIN_DIR . 'assets/js/frontend.js';
        wp_enqueue_script(
            'advnews-frontend-js',
            is_readable($frontend_script) ? ADVNEWS_PLUGIN_URL . 'assets/js/frontend.js' : false,
            array('jquery'),
            ADVNEWS_VERSION,
            true
        );

        wp_localize_script('advnews-frontend-js', 'advnews_frontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('advnews_frontend_ajax'),
            'i18n' => array(
                'processing' => __('Processing...', 'advnews-manager'),
                'success' => __('Success!', 'advnews-manager'),
                'error' => __('An error occurred. Please try again.', 'advnews-manager'),
                'confirm_unsubscribe' => __('Are you sure you want to unsubscribe?', 'advnews-manager'),
                'confirm_resubscribe' => __('Are you sure you want to resubscribe?', 'advnews-manager'),
                'confirm_delete' => __('Are you sure you want to permanently delete your data? This action cannot be undone.', 'advnews-manager'),
                'invalid_email' => __('Please enter a valid email address.', 'advnews-manager'),
                'subscribing' => __('Subscribing...', 'advnews-manager'),
                'saving' => __('Saving...', 'advnews-manager'),
                'sending' => __('Sending...', 'advnews-manager'),
                'loading' => __('Loading...', 'advnews-manager'),
                'load_more' => __('Load More', 'advnews-manager')
            )
        ));
    }

    /**
    * Set default options
    */
    private function set_default_options()
    {
        $defaults = array(
            'company_name' => get_bloginfo('name'),
            'from_name' => get_bloginfo('name'),
            'from_email' => get_bloginfo('admin_email'),
            'reply_to' => 'contact@science180.com',
            'timezone' => get_option('timezone_string', 'UTC'),
            'emails_per_batch' => 50,
            'minutes_between_batches' => 20,
            'cooldown_days' => 5,
            'max_emails_per_day' => 0,
            'pause_start_hour' => '',
            'pause_end_hour' => '',
            'pause_timezone' => wp_timezone_string(),
            'cron_method' => 'wp_cron',
            'enable_open_tracking' => true,
            'enable_click_tracking' => true,
            'track_geolocation' => true,
            'track_device' => true,
            'anonymize_ip' => true,
            'tracking_retention_days' => 365,
            'enable_utm_tracking' => false,
            'utm_parameters' => 'utm_source,utm_medium,utm_campaign,utm_term,utm_content',
            'geolocation_service' => 'maxmind',
            'geolocation_api_key' => '',
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_from_email' => '',
            'smtp_from_name' => '',
            'smtp_authentication' => 1,
            'enable_debug_log' => false,
            'show_credit_link' => true,
            'double_optin' => false,
            'welcome_email' => false,
            'welcome_template' => 0,
            'confirmation_template' => 0,
            'auto_clean_bounced' => true,
            'bounce_attempts' => 3,
            'default_category' => 0,
            'duplicate_handling' => 'skip',
            'email_verification' => true,
            'disposable_block' => false,
            'role_based_block' => true,
            'blacklist' => '',
            'max_subscribers' => 0,
            'block_when_full' => false,
            'gdpr_compliance' => true,
            'consent_checkbox' => true,
            'consent_text' => __('I agree to receive newsletters and accept the privacy policy.', 'advnews-manager'),
            'privacy_policy_url' => get_privacy_policy_url(),
            'data_retention_days' => 365,
            'export_data' => true,
            'delete_data' => true,
            'cookie_consent' => false,
            'cookie_message' => __('This website uses cookies to ensure you get the best experience.', 'advnews-manager'),
            'age_verification' => false,
            'minimum_age' => 16
        );

        foreach ($defaults as $key => $value) {
            if (!get_option('advnews_' . $key)) {
                update_option('advnews_' . $key, $value);
            }
        }
    }

    /**
    * Create necessary pages
    */
    private function create_pages()
    {
        $pages = array(
            'unsubscribe' => array(
                'title' => __('Unsubscribe', 'advnews-manager'),
                'content' => '[advnews_unsubscribe]',
                'option_name' => 'advnews_unsubscribe_page_id'
            ),
            'subscription_management' => array(
                'title' => __('Manage Email Preferences', 'advnews-manager'),
                'content' => '[advnews_manage_subscription]',
                'option_name' => 'advnews_management_page_id'
            ),
            'newsletter_archive' => array(
                'title' => __('Newsletter Archive', 'advnews-manager'),
                'content' => '[advnews_archive]',
                'option_name' => 'advnews_archive_page_id'
            )
        );

        foreach ($pages as $key => $page_data) {
            $existing_page = get_page_by_title($page_data['title']);

            if (!$existing_page) {
                $page_id = wp_insert_post(array(
                    'post_title' => $page_data['title'],
                    'post_content' => $page_data['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_name' => $key,
                    'comment_status' => 'closed',
                    'ping_status' => 'closed'
                ));

                if ($page_id && !is_wp_error($page_id)) {
                    update_option($page_data['option_name'], $page_id);
                }
            } else {
                update_option($page_data['option_name'], $existing_page->ID);
            }
        }
    }

    /**
    * Get database instance
    */
    public function get_database()
    {
        return $this->database;
    }

    /**
    * Get security instance
    */
    public function get_security()
    {
        return $this->security;
    }

    /**
    * Get subscriber instance
    */
    public function get_subscriber()
    {
        return $this->subscriber;
    }

    /**
    * Get campaign instance
    */
    public function get_campaign()
    {
        return $this->campaign;
    }

    /**
    * Get queue instance
    */
    public function get_queue()
    {
        return $this->queue;
    }

    /**
    * Get tracking instance
    */
    public function get_tracking()
    {
        return $this->tracking;
    }

    /**
    * Get admin instance
    */
    public function get_admin()
    {
        return $this->admin;
    }

    /**
    * Get ajax instance
    */
    public function get_ajax()
    {
        return $this->ajax;
    }

    /**
    * Get cron instance
    */
    public function get_cron()
    {
        return $this->cron;
    }

    /**
    * Get frontend instance
    */
    public function get_frontend()
    {
        return $this->frontend;
    }

    /**
    * Get geolocation instance
    */
    public function get_geolocation()
    {
        return $this->geolocation;
    }

    /**
    * Get category instance
    */
    public function get_category()
    {
        return $this->category;
    }

}
// Initialize the plugin
AdvNews_Manager::get_instance();
