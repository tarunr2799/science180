<?php
// File: includes/class-admin.php
class AdvNews_Admin
{
    private $wpdb;
    private $table_prefix;
    private $plugin_url;
    private $plugin_path;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = ADVNEWS_TABLE_PREFIX;
        $this->plugin_url = ADVNEWS_PLUGIN_URL;
        $this->plugin_path = ADVNEWS_PLUGIN_DIR;
        $this->init_hooks();
    }

    private function init_hooks()
    {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        // Add method to register the cron action
        add_action('advnews_process_scheduled_exports', array($this, 'process_scheduled_exports'));
        // Admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        // Admin init
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        // Admin notices
        add_action('admin_notices', array($this, 'show_admin_notices'));
        // Admin post actions
        add_action('admin_post_advnews_save_campaign', array($this, 'handle_save_campaign'));
        add_action('admin_post_advnews_save_template', array($this, 'handle_save_template'));
        add_action('admin_post_advnews_bulk_campaigns', array($this, 'handle_bulk_campaigns'));
        add_action('admin_post_advnews_bulk_templates', array($this, 'handle_bulk_templates'));
        add_action('admin_post_advnews_export_subscribers', array($this, 'handle_export_subscribers'));
        add_action('admin_post_advnews_save_category', array($this, 'handle_save_category'));
        add_action('admin_post_advnews_save_subscriber', array($this, 'handle_save_subscriber'));
        // Dashboard widgets
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
        // Plugin action links
        add_filter('plugin_action_links_' . ADVNEWS_PLUGIN_BASENAME, array($this, 'add_plugin_action_links'));
        // Admin footer text
        add_filter('admin_footer_text', array($this, 'admin_footer_text'));
        // display maxmind notice
        add_action('admin_notices', array($this, 'show_maxmind_database_notice'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        // Main menu
        add_menu_page(
            __('AdvNews Manager', 'advnews-manager'),
            __('AdvNews', 'advnews-manager'),
            'manage_options',
            'advnews-manager',
            array($this, 'render_dashboard'),
            'dashicons-email-alt',
            30
        );

        // Submenus
        add_submenu_page(
            'advnews-manager',
            __('Dashboard', 'advnews-manager'),
            __('Dashboard', 'advnews-manager'),
            'manage_options',
            'advnews-manager',
            array($this, 'render_dashboard')
        );

        add_submenu_page(
            'advnews-manager',
            __('Campaigns', 'advnews-manager'),
            __('Campaigns', 'advnews-manager'),
            'manage_options',
            'advnews-campaigns',
            array($this, 'render_campaigns')
        );

        add_submenu_page(
            'advnews-manager',
            __('Subscribers', 'advnews-manager'),
            __('Subscribers', 'advnews-manager'),
            'manage_options',
            'advnews-subscribers',
            array($this, 'render_subscribers')
        );

        add_submenu_page(
            'advnews-manager',
            __('Categories', 'advnews-manager'),
            __('Categories', 'advnews-manager'),
            'manage_options',
            'advnews-categories',
            array($this, 'render_categories')
        );

        add_submenu_page(
            'advnews-manager',
            __('Templates', 'advnews-manager'),
            __('Templates', 'advnews-manager'),
            'manage_options',
            'advnews-templates',
            array($this, 'render_templates')
        );

        add_submenu_page(
            'advnews-manager',
            __('Analytics', 'advnews-manager'),
            __('Analytics', 'advnews-manager'),
            'manage_options',
            'advnews-analytics',
            array($this, 'render_analytics')
        );

        // NEW: Email Logs Menu Item
        add_submenu_page(
            'advnews-manager',
            __('Email Logs', 'advnews-manager'),
            __('Email Logs', 'advnews-manager'),
            'manage_options',
            'advnews-email-logs',
            array($this, 'render_email_logs')
        );

        add_submenu_page(
            'advnews-manager',
            __('Settings', 'advnews-manager'),
            __('Settings', 'advnews-manager'),
            'manage_options',
            'advnews-settings',
            array($this, 'render_settings')
        );

        // Tools submenu (hidden)
        add_submenu_page(
            'advnews-manager',
            __('Import Subscribers', 'advnews-manager'),
            __('Import', 'advnews-manager'),
            'manage_options',
            'advnews-import',
            array($this, 'render_subscriber_import')
        );

        add_submenu_page(
            'advnews-manager',
            __('Export Subscribers', 'advnews-manager'),
            __('Export', 'advnews-manager'),
            'manage_options',
            'advnews-export',
            array($this, 'render_subscriber_export')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook)
    {
        if (strpos($hook, 'advnews') === false) {
            return;
        }

        // Enqueue styles
        wp_enqueue_style(
            'advnews-admin-css',
            $this->plugin_url . 'assets/css/admin.css',
            array(),
            ADVNEWS_VERSION
        );

        // Enqueue Chart.js for analytics
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            array(),
            '3.9.1',
            true
        );

        // Enqueue admin script
        wp_enqueue_script(
            'advnews-admin-js',
            $this->plugin_url . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-datepicker', 'jquery-ui-tabs', 'chart-js'),
            ADVNEWS_VERSION,
            true
        );

        // Enqueue TinyMCE fix script
        wp_enqueue_script(
            'advnews-tinymce-fix',
            $this->plugin_url . 'assets/js/admin-tinymce-fix.js',
            array('jquery'),
            ADVNEWS_VERSION,
            true
        );

        // Localize script for AJAX
        wp_localize_script('advnews-admin-js', 'advnews_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('advnews_ajax_nonce'),
            'i18n' => array(
                'confirm_delete' => __('Are you sure you want to delete this?', 'advnews-manager'),
                'confirm_bulk_action' => __('Are you sure you want to perform this bulk action?', 'advnews-manager'),
                'processing' => __('Processing...', 'advnews-manager'),
                'saving' => __('Saving...', 'advnews-manager'),
                'saved' => __('Saved!', 'advnews-manager'),
                'error' => __('An error occurred. Please try again.', 'advnews-manager'),
                'select_file' => __('Please select a file to import.', 'advnews-manager'),
                'enter_email' => __('Please enter a test email address.', 'advnews-manager'),
                'confirm_send' => __('Are you sure you want to send this campaign now?', 'advnews-manager'),
                'confirm_pause' => __('Are you sure you want to pause sending?', 'advnews-manager'),
                'confirm_resume' => __('Are you sure you want to resume sending?', 'advnews-manager'),
                'confirm_duplicate' => __('Are you sure you want to duplicate this campaign?', 'advnews-manager'),
                'missing_fields' => __('Please fill in all required fields.', 'advnews-manager'),
                'no_categories' => __('Please select at least one category.', 'advnews-manager'),
                'no_recipients' => __('No subscribers found for the selected category.', 'advnews-manager'),
                'confirm_template_load' => __('Loading this template will replace your current content. Continue?', 'advnews-manager')
            )
        ));

        // Enqueue datepicker style
        wp_enqueue_style('jquery-ui-style', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
        // Enqueue media uploader
        wp_enqueue_media();
        // Enqueue WordPress editor
        wp_enqueue_editor();
    }

    /**
     * Register plugin settings
     */
    public function register_settings()
    {
        // General settings
        register_setting('advnews_general_settings', 'advnews_company_name', 'sanitize_text_field');
        register_setting('advnews_general_settings', 'advnews_from_name', 'sanitize_text_field');
        register_setting('advnews_general_settings', 'advnews_from_email', 'sanitize_email');
        register_setting('advnews_general_settings', 'advnews_reply_to', 'sanitize_email');
        register_setting('advnews_general_settings', 'advnews_timezone', 'sanitize_text_field');
        register_setting('advnews_general_settings', 'advnews_show_credit_link', 'intval');
        register_setting('advnews_general_settings', 'advnews_enable_debug_log', 'intval');

        // SMTP settings
        register_setting('advnews_smtp_settings', 'advnews_smtp_host', 'sanitize_text_field');
        register_setting('advnews_smtp_settings', 'advnews_smtp_port', 'intval');
        register_setting('advnews_smtp_settings', 'advnews_smtp_encryption', 'sanitize_text_field');
        register_setting('advnews_smtp_settings', 'advnews_smtp_username', 'sanitize_text_field');
        register_setting('advnews_smtp_settings', 'advnews_smtp_password', array($this, 'encrypt_setting'));
        register_setting('advnews_smtp_settings', 'advnews_smtp_from_email', 'sanitize_email');
        register_setting('advnews_smtp_settings', 'advnews_smtp_from_name', 'sanitize_text_field');
        register_setting('advnews_smtp_settings', 'advnews_smtp_authentication', 'intval');

        // Cron settings
        register_setting('advnews_cron_settings', 'advnews_emails_per_batch', 'intval');
        register_setting('advnews_cron_settings', 'advnews_minutes_between_batches', 'intval');
        register_setting('advnews_cron_settings', 'advnews_cooldown_days', 'intval');
        register_setting('advnews_cron_settings', 'advnews_max_emails_per_day', 'intval');
        register_setting('advnews_cron_settings', 'advnews_pause_start_hour', 'sanitize_text_field');
        register_setting('advnews_cron_settings', 'advnews_pause_end_hour', 'sanitize_text_field');
        register_setting('advnews_cron_settings', 'advnews_pause_timezone', 'sanitize_text_field');
        register_setting('advnews_cron_settings', 'advnews_cron_method', 'sanitize_text_field');

        // Tracking settings
        register_setting('advnews_tracking_settings', 'advnews_enable_open_tracking', 'intval');
        register_setting('advnews_tracking_settings', 'advnews_enable_click_tracking', 'intval');
        register_setting('advnews_tracking_settings', 'advnews_track_geolocation', 'intval');
        register_setting('advnews_tracking_settings', 'advnews_track_device', 'intval');
        register_setting('advnews_tracking_settings', 'advnews_anonymize_ip', 'intval');
        register_setting('advnews_tracking_settings', 'advnews_geolocation_service', 'sanitize_text_field');
        register_setting('advnews_tracking_settings', 'advnews_geolocation_api_key', 'sanitize_text_field');
        register_setting('advnews_tracking_settings', 'advnews_tracking_retention_days', 'intval');
        register_setting('advnews_tracking_settings', 'advnews_enable_utm_tracking', 'intval');
        register_setting('advnews_tracking_settings', 'advnews_utm_parameters', 'sanitize_text_field');

        // Subscriber settings
        register_setting('advnews_subscriber_settings', 'advnews_double_optin', 'intval');
        register_setting('advnews_subscriber_settings', 'advnews_welcome_email', 'intval');
        register_setting('advnews_subscriber_settings', 'advnews_welcome_template', 'intval');
        register_setting('advnews_subscriber_settings', 'advnews_confirmation_template', 'intval');
        register_setting('advnews_subscriber_settings', 'advnews_auto_clean_bounced', 'intval');
        register_setting('advnews_subscriber_settings', 'advnews_bounce_attempts', 'intval');
        register_setting('advnews_subscriber_settings', 'advnews_default_category', 'intval');
        register_setting('advnews_subscriber_settings', 'advnews_duplicate_handling', 'sanitize_text_field');
        register_setting('advnews_subscriber_settings', 'advnews_email_verification', 'intval');
        register_setting('advnews_subscriber_settings', 'advnews_disposable_block', 'intval');
        register_setting('advnews_subscriber_settings', 'advnews_role_based_block', 'intval');
        register_setting('advnews_subscriber_settings', 'advnews_blacklist', 'sanitize_textarea_field');
        register_setting('advnews_subscriber_settings', 'advnews_max_subscribers', 'intval');
        register_setting('advnews_subscriber_settings', 'advnews_block_when_full', 'intval');

        // GDPR settings
        register_setting('advnews_gdpr_settings', 'advnews_gdpr_compliance', 'intval');
        register_setting('advnews_gdpr_settings', 'advnews_consent_checkbox', 'intval');
        register_setting('advnews_gdpr_settings', 'advnews_consent_text', 'sanitize_textarea_field');
        register_setting('advnews_gdpr_settings', 'advnews_privacy_policy_url', 'esc_url_raw');
        register_setting('advnews_gdpr_settings', 'advnews_data_retention_days', 'intval');
        register_setting('advnews_gdpr_settings', 'advnews_export_data', 'intval');
        register_setting('advnews_gdpr_settings', 'advnews_delete_data', 'intval');
        register_setting('advnews_gdpr_settings', 'advnews_cookie_consent', 'intval');
        register_setting('advnews_gdpr_settings', 'advnews_cookie_message', 'sanitize_text_field');
        register_setting('advnews_gdpr_settings', 'advnews_age_verification', 'intval');
        register_setting('advnews_gdpr_settings', 'advnews_minimum_age', 'intval');


        register_setting('advnews_tracking_settings', 'advnews_maxmind_license_key', 'sanitize_text_field');
        register_setting('advnews_tracking_settings', 'advnews_maxmind_auto_update', 'intval');
        register_setting('advnews_tracking_settings', 'advnews_maxmind_db_path', 'sanitize_text_field');

        // Add settings sections
        $this->add_settings_sections();
    }

    /**
     * Add settings sections
     */
    private function add_settings_sections()
    {
        // General settings section
        add_settings_section(
            'advnews_general_section',
            __('General Settings', 'advnews-manager'),
            array($this, 'render_general_section'),
            'advnews_general_settings'
        );

        // SMTP settings section
        add_settings_section(
            'advnews_smtp_section',
            __('SMTP Configuration', 'advnews-manager'),
            array($this, 'render_smtp_section'),
            'advnews_smtp_settings'
        );

        // Cron settings section
        add_settings_section(
            'advnews_cron_section',
            __('Cron & Scheduling Settings', 'advnews-manager'),
            array($this, 'render_cron_section'),
            'advnews_cron_settings'
        );

        // Tracking settings section
        add_settings_section(
            'advnews_tracking_section',
            __('Tracking & Analytics Settings', 'advnews-manager'),
            array($this, 'render_tracking_section'),
            'advnews_tracking_settings'
        );

        // Subscriber settings section
        add_settings_section(
            'advnews_subscriber_section',
            __('Subscriber Management Settings', 'advnews-manager'),
            array($this, 'render_subscriber_section'),
            'advnews_subscriber_settings'
        );

        // GDPR settings section
        add_settings_section(
            'advnews_gdpr_section',
            __('GDPR & Privacy Settings', 'advnews-manager'),
            array($this, 'render_gdpr_section'),
            'advnews_gdpr_settings'
        );

        // Add settings fields
        $this->add_settings_fields();
    }

    /**
     * Add settings fields
     */
    private function add_settings_fields()
    {
        // ===== GENERAL SETTINGS FIELDS =====
        add_settings_field(
            'advnews_company_name',
            __('Company Name', 'advnews-manager'),
            array($this, 'render_text_field'),
            'advnews_general_settings',
            'advnews_general_section',
            array(
                'label_for' => 'advnews_company_name',
                'option' => 'advnews_company_name',
                'description' => __('Your company name used in email footers.', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_from_name',
            __('From Name', 'advnews-manager'),
            array($this, 'render_text_field'),
            'advnews_general_settings',
            'advnews_general_section',
            array(
                'label_for' => 'advnews_from_name',
                'option' => 'advnews_from_name',
                'description' => __('The name emails will be sent from.', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_from_email',
            __('From Email', 'advnews-manager'),
            array($this, 'render_text_field'),
            'advnews_general_settings',
            'advnews_general_section',
            array(
                'label_for' => 'advnews_from_email',
                'option' => 'advnews_from_email',
                'type' => 'email',
                'description' => __('The email address emails will be sent from.', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_reply_to',
            __('Reply-To Email', 'advnews-manager'),
            array($this, 'render_text_field'),
            'advnews_general_settings',
            'advnews_general_section',
            array(
                'label_for' => 'advnews_reply_to',
                'option' => 'advnews_reply_to',
                'type' => 'email',
                'description' => __('The email address for replies.', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_timezone',
            __('Timezone', 'advnews-manager'),
            array($this, 'render_timezone_field'),
            'advnews_general_settings',
            'advnews_general_section',
            array(
                'label_for' => 'advnews_timezone',
                'option' => 'advnews_timezone'
            )
        );
        add_settings_field(
            'advnews_show_credit_link',
            __('Show Credit Link', 'advnews-manager'),
            array($this, 'render_checkbox_field'),
            'advnews_general_settings',
            'advnews_general_section',
            array(
                'label_for' => 'advnews_show_credit_link',
                'option' => 'advnews_show_credit_link',
                'label' => __('Show "Powered by AdvNews" link in email footers', 'advnews-manager'),
                'default' => 1
            )
        );
        add_settings_field(
            'advnews_enable_debug_log',
            __('Debug Mode', 'advnews-manager'),
            array($this, 'render_checkbox_field'),
            'advnews_general_settings',
            'advnews_general_section',
            array(
                'label_for' => 'advnews_enable_debug_log',
                'option' => 'advnews_enable_debug_log',
                'label' => __('Enable debug logging', 'advnews-manager'),
                'description' => __('Log plugin events to WordPress debug log.', 'advnews-manager')
            )
        );

        // ===== SMTP SETTINGS FIELDS =====
        add_settings_field(
            'advnews_smtp_host',
            __('SMTP Host', 'advnews-manager'),
            array($this, 'render_text_field'),
            'advnews_smtp_settings',
            'advnews_smtp_section',
            array(
                'label_for' => 'advnews_smtp_host',
                'option' => 'advnews_smtp_host',
                'placeholder' => 'smtp.gmail.com',
                'description' => __('Your SMTP server address', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_smtp_port',
            __('SMTP Port', 'advnews-manager'),
            array($this, 'render_number_field'),
            'advnews_smtp_settings',
            'advnews_smtp_section',
            array(
                'label_for' => 'advnews_smtp_port',
                'option' => 'advnews_smtp_port',
                'min' => 1,
                'max' => 65535,
                'default' => 587,
                'description' => __('Common ports: 25, 465 (SSL), 587 (TLS)', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_smtp_encryption',
            __('Encryption', 'advnews-manager'),
            array($this, 'render_select_field'),
            'advnews_smtp_settings',
            'advnews_smtp_section',
            array(
                'label_for' => 'advnews_smtp_encryption',
                'option' => 'advnews_smtp_encryption',
                'options' => array(
                    'none' => __('None', 'advnews-manager'),
                    'ssl' => __('SSL', 'advnews-manager'),
                    'tls' => __('TLS', 'advnews-manager')
                ),
                'default' => 'tls',
                'description' => __('Encryption method for secure connection', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_smtp_authentication',
            __('Authentication', 'advnews-manager'),
            array($this, 'render_checkbox_field'),
            'advnews_smtp_settings',
            'advnews_smtp_section',
            array(
                'label_for' => 'advnews_smtp_authentication',
                'option' => 'advnews_smtp_authentication',
                'label' => __('Use SMTP authentication', 'advnews-manager'),
                'default' => 1
            )
        );
        add_settings_field(
            'advnews_smtp_username',
            __('Username', 'advnews-manager'),
            array($this, 'render_text_field'),
            'advnews_smtp_settings',
            'advnews_smtp_section',
            array(
                'label_for' => 'advnews_smtp_username',
                'option' => 'advnews_smtp_username',
                'description' => __('Your SMTP username (usually email address)', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_smtp_password',
            __('Password', 'advnews-manager'),
            array($this, 'render_password_field'),
            'advnews_smtp_settings',
            'advnews_smtp_section',
            array(
                'label_for' => 'advnews_smtp_password',
                'option' => 'advnews_smtp_password',
                'description' => __('Your SMTP password or API key. Stored encrypted.', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_smtp_from_email',
            __('From Email (Optional)', 'advnews-manager'),
            array($this, 'render_text_field'),
            'advnews_smtp_settings',
            'advnews_smtp_section',
            array(
                'label_for' => 'advnews_smtp_from_email',
                'option' => 'advnews_smtp_from_email',
                'type' => 'email',
                'description' => __('Override the default from email for SMTP', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_smtp_from_name',
            __('From Name (Optional)', 'advnews-manager'),
            array($this, 'render_text_field'),
            'advnews_smtp_settings',
            'advnews_smtp_section',
            array(
                'label_for' => 'advnews_smtp_from_name',
                'option' => 'advnews_smtp_from_name',
                'description' => __('Override the default from name for SMTP', 'advnews-manager')
            )
        );

        // ===== CRON SETTINGS FIELDS =====
        add_settings_field(
            'advnews_emails_per_batch',
            __('Emails per Batch', 'advnews-manager'),
            array($this, 'render_number_field'),
            'advnews_cron_settings',
            'advnews_cron_section',
            array(
                'label_for' => 'advnews_emails_per_batch',
                'option' => 'advnews_emails_per_batch',
                'min' => 1,
                'max' => 500,
                'default' => 50,
                'description' => __('Number of emails to send in each batch. Lower for shared hosting, higher for dedicated servers.', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_minutes_between_batches',
            __('Minutes Between Batches', 'advnews-manager'),
            array($this, 'render_number_field'),
            'advnews_cron_settings',
            'advnews_cron_section',
            array(
                'label_for' => 'advnews_minutes_between_batches',
                'option' => 'advnews_minutes_between_batches',
                'min' => 1,
                'max' => 120,
                'default' => 20,
                'description' => __('Wait time between batches to prevent server overload.', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_cooldown_days',
            __('Days Between Emails', 'advnews-manager'),
            array($this, 'render_number_field'),
            'advnews_cron_settings',
            'advnews_cron_section',
            array(
                'label_for' => 'advnews_cooldown_days',
                'option' => 'advnews_cooldown_days',
                'min' => 0,
                'max' => 30,
                'default' => 5,
                'description' => __('Minimum days a subscriber must wait between emails from different campaigns.', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_max_emails_per_day',
            __('Max Emails Per Day', 'advnews-manager'),
            array($this, 'render_number_field'),
            'advnews_cron_settings',
            'advnews_cron_section',
            array(
                'label_for' => 'advnews_max_emails_per_day',
                'option' => 'advnews_max_emails_per_day',
                'min' => 0,
                'max' => 100000,
                'step' => 100,
                'default' => 0,
                'description' => __('Maximum emails to send per day (0 for unlimited). Respects your SMTP provider limits.', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_pause_start_hour',
            __('Pause Schedule', 'advnews-manager'),
            array($this, 'render_pause_schedule_field'),
            'advnews_cron_settings',
            'advnews_cron_section',
            array(
                'label_for' => 'advnews_pause_start_hour',
                'option_start' => 'advnews_pause_start_hour',
                'option_end' => 'advnews_pause_end_hour',
                'option_timezone' => 'advnews_pause_timezone',
                'description' => __('Pause sending during specific hours (useful for respecting local quiet hours).', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_cron_method',
            __('Cron Method', 'advnews-manager'),
            array($this, 'render_cron_method_field'),
            'advnews_cron_settings',
            'advnews_cron_section',
            array(
                'label_for' => 'advnews_cron_method',
                'option' => 'advnews_cron_method',
                'description' => __('Choose how to process the email queue.', 'advnews-manager')
            )
        );

        // ===== TRACKING SETTINGS FIELDS =====
        add_settings_field(
            'advnews_enable_open_tracking',
            __('Open Tracking', 'advnews-manager'),
            array($this, 'render_checkbox_field'),
            'advnews_tracking_settings',
            'advnews_tracking_section',
            array(
                'label_for' => 'advnews_enable_open_tracking',
                'option' => 'advnews_enable_open_tracking',
                'label' => __('Track email opens (uses tracking pixel)', 'advnews-manager'),
                'default' => 1,
                'description' => __('Know when and how many times subscribers open your emails.', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_enable_click_tracking',
            __('Click Tracking', 'advnews-manager'),
            array($this, 'render_checkbox_field'),
            'advnews_tracking_settings',
            'advnews_tracking_section',
            array(
                'label_for' => 'advnews_enable_click_tracking',
                'option' => 'advnews_enable_click_tracking',
                'label' => __('Track link clicks (rewrites URLs)', 'advnews-manager'),
                'default' => 1,
                'description' => __('Track which links subscribers click and how often.', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_track_geolocation',
            __('Geolocation', 'advnews-manager'),
            array($this, 'render_checkbox_field'),
            'advnews_tracking_settings',
            'advnews_tracking_section',
            array(
                'label_for' => 'advnews_track_geolocation',
                'option' => 'advnews_track_geolocation',
                'label' => __('Track subscriber location (country, city)', 'advnews-manager'),
                'default' => 1
            )
        );
        add_settings_field(
            'advnews_track_device',
            __('Device Tracking', 'advnews-manager'),
            array($this, 'render_checkbox_field'),
            'advnews_tracking_settings',
            'advnews_tracking_section',
            array(
                'label_for' => 'advnews_track_device',
                'option' => 'advnews_track_device',
                'label' => __('Track device type, browser, and operating system', 'advnews-manager'),
                'default' => 1
            )
        );
        add_settings_field(
            'advnews_anonymize_ip',
            __('IP Anonymization', 'advnews-manager'),
            array($this, 'render_checkbox_field'),
            'advnews_tracking_settings',
            'advnews_tracking_section',
            array(
                'label_for' => 'advnews_anonymize_ip',
                'option' => 'advnews_anonymize_ip',
                'label' => __('Anonymize IP addresses (GDPR compliance)', 'advnews-manager'),
                'default' => 1,
                'description' => __('Remove the last octet of IPv4 addresses before storing.', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_geolocation_service',
            __('Geolocation Service', 'advnews-manager'),
            array($this, 'render_geolocation_service_field'),
            'advnews_tracking_settings',
            'advnews_tracking_section',
            array(
                'label_for' => 'advnews_geolocation_service',
                'option' => 'advnews_geolocation_service',
                'option_key' => 'advnews_geolocation_api_key',
                'description' => __('Select which geolocation service to use.', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_tracking_retention_days',
            __('Data Retention', 'advnews-manager'),
            array($this, 'render_number_field'),
            'advnews_tracking_settings',
            'advnews_tracking_section',
            array(
                'label_for' => 'advnews_tracking_retention_days',
                'option' => 'advnews_tracking_retention_days',
                'min' => 30,
                'max' => 3650,
                'step' => 30,
                'default' => 365,
                'description' => __('How long to keep tracking data before automatic cleanup (days).', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_enable_utm_tracking',
            __('UTM Tracking', 'advnews-manager'),
            array($this, 'render_checkbox_field'),
            'advnews_tracking_settings',
            'advnews_tracking_section',
            array(
                'label_for' => 'advnews_enable_utm_tracking',
                'option' => 'advnews_enable_utm_tracking',
                'label' => __('Automatically add UTM parameters to links', 'advnews-manager'),
                'default' => 0
            )
        );
        add_settings_field(
            'advnews_utm_parameters',
            __('UTM Parameters', 'advnews-manager'),
            array($this, 'render_text_field'),
            'advnews_tracking_settings',
            'advnews_tracking_section',
            array(
                'label_for' => 'advnews_utm_parameters',
                'option' => 'advnews_utm_parameters',
                'default' => 'utm_source,utm_medium,utm_campaign,utm_term,utm_content',
                'description' => __('Comma-separated list of UTM parameters to track.', 'advnews-manager')
            )
        );

        // ===== SUBSCRIBER SETTINGS FIELDS =====
        add_settings_field(
            'advnews_double_optin',
            __('Double Opt-in', 'advnews-manager'),
            array($this, 'render_checkbox_field'),
            'advnews_subscriber_settings',
            'advnews_subscriber_section',
            array(
                'label_for' => 'advnews_double_optin',
                'option' => 'advnews_double_optin',
                'label' => __('Require email confirmation before adding to list', 'advnews-manager'),
                'default' => 0,
                'description' => __('Recommended for GDPR compliance and higher quality lists.', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_welcome_email',
            __('Welcome Email', 'advnews-manager'),
            array($this, 'render_checkbox_field'),
            'advnews_subscriber_settings',
            'advnews_subscriber_section',
            array(
                'label_for' => 'advnews_welcome_email',
                'option' => 'advnews_welcome_email',
                'label' => __('Send welcome email to new subscribers', 'advnews-manager'),
                'default' => 0
            )
        );
        add_settings_field(
            'advnews_welcome_template',
            __('Welcome Template', 'advnews-manager'),
            array($this, 'render_template_select_field'),
            'advnews_subscriber_settings',
            'advnews_subscriber_section',
            array(
                'label_for' => 'advnews_welcome_template',
                'option' => 'advnews_welcome_template',
                'description' => __('Template for welcome emails.', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_confirmation_template',
            __('Confirmation Template', 'advnews-manager'),
            array($this, 'render_template_select_field'),
            'advnews_subscriber_settings',
            'advnews_subscriber_section',
            array(
                'label_for' => 'advnews_confirmation_template',
                'option' => 'advnews_confirmation_template',
                'description' => __('Template for double opt-in confirmation emails.', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_auto_clean_bounced',
            __('Auto Clean Bounced', 'advnews-manager'),
            array($this, 'render_checkbox_field'),
            'advnews_subscriber_settings',
            'advnews_subscriber_section',
            array(
                'label_for' => 'advnews_auto_clean_bounced',
                'option' => 'advnews_auto_clean_bounced',
                'label' => __('Automatically mark subscribers as bounced after multiple failures', 'advnews-manager'),
                'default' => 1
            )
        );
        add_settings_field(
            'advnews_bounce_attempts',
            __('Bounce Attempts', 'advnews-manager'),
            array($this, 'render_number_field'),
            'advnews_subscriber_settings',
            'advnews_subscriber_section',
            array(
                'label_for' => 'advnews_bounce_attempts',
                'option' => 'advnews_bounce_attempts',
                'min' => 1,
                'max' => 10,
                'default' => 3,
                'description' => __('Number of failed attempts before marking as bounced', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_default_category',
            __('Default Category', 'advnews-manager'),
            array($this, 'render_category_select_field'),
            'advnews_subscriber_settings',
            'advnews_subscriber_section',
            array(
                'label_for' => 'advnews_default_category',
                'option' => 'advnews_default_category',
                'description' => __('Default category for imported subscribers when not specified.', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_duplicate_handling',
            __('Duplicate Handling', 'advnews-manager'),
            array($this, 'render_duplicate_handling_field'),
            'advnews_subscriber_settings',
            'advnews_subscriber_section',
            array(
                'label_for' => 'advnews_duplicate_handling',
                'option' => 'advnews_duplicate_handling',
                'description' => __('How to handle duplicate emails during import.', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_email_verification',
            __('Email Verification', 'advnews-manager'),
            array($this, 'render_checkbox_field'),
            'advnews_subscriber_settings',
            'advnews_subscriber_section',
            array(
                'label_for' => 'advnews_email_verification',
                'option' => 'advnews_email_verification',
                'label' => __('Verify email format and domain MX records', 'advnews-manager'),
                'default' => 1
            )
        );
        add_settings_field(
            'advnews_disposable_block',
            __('Block Disposable Emails', 'advnews-manager'),
            array($this, 'render_checkbox_field'),
            'advnews_subscriber_settings',
            'advnews_subscriber_section',
            array(
                'label_for' => 'advnews_disposable_block',
                'option' => 'advnews_disposable_block',
                'label' => __('Block disposable email addresses (tempmail, throwaway)', 'advnews-manager'),
                'default' => 0
            )
        );
        add_settings_field(
            'advnews_role_based_block',
            __('Block Role-Based Emails', 'advnews-manager'),
            array($this, 'render_checkbox_field'),
            'advnews_subscriber_settings',
            'advnews_subscriber_section',
            array(
                'label_for' => 'advnews_role_based_block',
                'option' => 'advnews_role_based_block',
                'label' => __('Block role-based emails (admin@, info@, support@)', 'advnews-manager'),
                'default' => 1
            )
        );
        add_settings_field(
            'advnews_blacklist',
            __('Email Blacklist', 'advnews-manager'),
            array($this, 'render_textarea_field'),
            'advnews_subscriber_settings',
            'advnews_subscriber_section',
            array(
                'label_for' => 'advnews_blacklist',
                'option' => 'advnews_blacklist',
                'rows' => 5,
                'description' => __('One email or domain per line. Examples: spam@example.com or @spamdomain.com', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_max_subscribers',
            __('Maximum Subscribers', 'advnews-manager'),
            array($this, 'render_number_field'),
            'advnews_subscriber_settings',
            'advnews_subscriber_section',
            array(
                'label_for' => 'advnews_max_subscribers',
                'option' => 'advnews_max_subscribers',
                'min' => 0,
                'step' => 100,
                'default' => 0,
                'description' => __('Maximum number of active subscribers (0 for unlimited).', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_block_when_full',
            __('Block When Full', 'advnews-manager'),
            array($this, 'render_checkbox_field'),
            'advnews_subscriber_settings',
            'advnews_subscriber_section',
            array(
                'label_for' => 'advnews_block_when_full',
                'option' => 'advnews_block_when_full',
                'label' => __('Block new subscriptions when limit is reached', 'advnews-manager'),
                'default' => 0
            )
        );

        // ===== GDPR SETTINGS FIELDS =====
        add_settings_field(
            'advnews_gdpr_compliance',
            __('GDPR Mode', 'advnews-manager'),
            array($this, 'render_checkbox_field'),
            'advnews_gdpr_settings',
            'advnews_gdpr_section',
            array(
                'label_for' => 'advnews_gdpr_compliance',
                'option' => 'advnews_gdpr_compliance',
                'label' => __('Enable GDPR compliance features', 'advnews-manager'),
                'default' => 1,
                'description' => __('Enables consent checkboxes, data export, and right to be forgotten.', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_consent_checkbox',
            __('Consent Checkbox', 'advnews-manager'),
            array($this, 'render_checkbox_field'),
            'advnews_gdpr_settings',
            'advnews_gdpr_section',
            array(
                'label_for' => 'advnews_consent_checkbox',
                'option' => 'advnews_consent_checkbox',
                'label' => __('Show consent checkbox on subscription forms', 'advnews-manager'),
                'default' => 1
            )
        );
        add_settings_field(
            'advnews_consent_text',
            __('Consent Text', 'advnews-manager'),
            array($this, 'render_textarea_field'),
            'advnews_gdpr_settings',
            'advnews_gdpr_section',
            array(
                'label_for' => 'advnews_consent_text',
                'option' => 'advnews_consent_text',
                'rows' => 3,
                'default' => __('I agree to receive newsletters and accept the privacy policy.', 'advnews-manager'),
                'description' => __('Text displayed next to the consent checkbox.', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_privacy_policy_url',
            __('Privacy Policy URL', 'advnews-manager'),
            array($this, 'render_text_field'),
            'advnews_gdpr_settings',
            'advnews_gdpr_section',
            array(
                'label_for' => 'advnews_privacy_policy_url',
                'option' => 'advnews_privacy_policy_url',
                'type' => 'url',
                'description' => __('Link to your privacy policy page.', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_data_retention_days',
            __('Data Retention Period', 'advnews-manager'),
            array($this, 'render_number_field'),
            'advnews_gdpr_settings',
            'advnews_gdpr_section',
            array(
                'label_for' => 'advnews_data_retention_days',
                'option' => 'advnews_data_retention_days',
                'min' => 30,
                'max' => 3650,
                'step' => 30,
                'default' => 365,
                'description' => __('How long to keep subscriber data after unsubscribing before anonymization (days).', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_export_data',
            __('Right to Access', 'advnews-manager'),
            array($this, 'render_checkbox_field'),
            'advnews_gdpr_settings',
            'advnews_gdpr_section',
            array(
                'label_for' => 'advnews_export_data',
                'option' => 'advnews_export_data',
                'label' => __('Allow subscribers to export their data', 'advnews-manager'),
                'default' => 1
            )
        );
        add_settings_field(
            'advnews_delete_data',
            __('Right to be Forgotten', 'advnews-manager'),
            array($this, 'render_checkbox_field'),
            'advnews_gdpr_settings',
            'advnews_gdpr_section',
            array(
                'label_for' => 'advnews_delete_data',
                'option' => 'advnews_delete_data',
                'label' => __('Allow subscribers to request data deletion', 'advnews-manager'),
                'default' => 1,
                'description' => __('Note: Email addresses will be anonymized, not permanently deleted, to prevent re-subscription.', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_cookie_consent',
            __('Cookie Consent', 'advnews-manager'),
            array($this, 'render_checkbox_field'),
            'advnews_gdpr_settings',
            'advnews_gdpr_section',
            array(
                'label_for' => 'advnews_cookie_consent',
                'option' => 'advnews_cookie_consent',
                'label' => __('Show cookie consent notice (for tracking pixels)', 'advnews-manager'),
                'default' => 0
            )
        );
        add_settings_field(
            'advnews_cookie_message',
            __('Cookie Message', 'advnews-manager'),
            array($this, 'render_text_field'),
            'advnews_gdpr_settings',
            'advnews_gdpr_section',
            array(
                'label_for' => 'advnews_cookie_message',
                'option' => 'advnews_cookie_message',
                'default' => __('This website uses cookies to ensure you get the best experience.', 'advnews-manager'),
                'description' => __('Message displayed in cookie consent notice.', 'advnews-manager')
            )
        );
        add_settings_field(
            'advnews_age_verification',
            __('Age Verification', 'advnews-manager'),
            array($this, 'render_checkbox_field'),
            'advnews_gdpr_settings',
            'advnews_gdpr_section',
            array(
                'label_for' => 'advnews_age_verification',
                'option' => 'advnews_age_verification',
                'label' => __('Require age verification for subscription', 'advnews-manager'),
                'default' => 0
            )
        );
        add_settings_field(
            'advnews_minimum_age',
            __('Minimum Age', 'advnews-manager'),
            array($this, 'render_number_field'),
            'advnews_gdpr_settings',
            'advnews_gdpr_section',
            array(
                'label_for' => 'advnews_minimum_age',
                'option' => 'advnews_minimum_age',
                'min' => 13,
                'max' => 21,
                'default' => 16,
                'description' => __('Minimum age required to subscribe (years).', 'advnews-manager')
            )
        );
    }

    // ===== RENDER METHODS FOR DIFFERENT FIELD TYPES =====
    /**
     * Render text field
     */
    public function render_text_field($args)
    {
        $option = $args['option'];
        $value = get_option($option, isset($args['default']) ? $args['default'] : '');
        $type = isset($args['type']) ? $args['type'] : 'text';
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';
        $description = isset($args['description']) ? $args['description'] : '';
        ?>
        <input type="<?php echo esc_attr($type); ?>"
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="<?php echo esc_attr($option); ?>"
               value="<?php echo esc_attr($value); ?>"
               placeholder="<?php echo esc_attr($placeholder); ?>"
               class="regular-text">
        <?php if ($description): ?>
            <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render number field
     */
    public function render_number_field($args)
    {
        $option = $args['option'];
        $value = get_option($option, isset($args['default']) ? $args['default'] : '');
        $min = isset($args['min']) ? $args['min'] : 0;
        $max = isset($args['max']) ? $args['max'] : 999999;
        $step = isset($args['step']) ? $args['step'] : 1;
        $description = isset($args['description']) ? $args['description'] : '';
        ?>
        <input type="number"
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="<?php echo esc_attr($option); ?>"
               value="<?php echo esc_attr($value); ?>"
               min="<?php echo esc_attr($min); ?>"
               max="<?php echo esc_attr($max); ?>"
               step="<?php echo esc_attr($step); ?>"
               class="small-text">
        <?php if ($description): ?>
            <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render checkbox field
     */
    public function render_checkbox_field($args)
    {
        $option = $args['option'];
        $value = get_option($option, isset($args['default']) ? $args['default'] : 0);
        $label = isset($args['label']) ? $args['label'] : '';
        $description = isset($args['description']) ? $args['description'] : '';
        ?>
        <label>
            <input type="checkbox"
                   id="<?php echo esc_attr($args['label_for']); ?>"
                   name="<?php echo esc_attr($option); ?>"
                   value="1"
                <?php checked($value, 1); ?>>
            <?php echo esc_html($label); ?>
        </label>
        <?php if ($description): ?>
            <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render select field
     */
    public function render_select_field($args)
    {
        $option = $args['option'];
        $value = get_option($option, isset($args['default']) ? $args['default'] : '');
        $options = $args['options'];
        $description = isset($args['description']) ? $args['description'] : '';
        ?>
        <select id="<?php echo esc_attr($args['label_for']); ?>" name="<?php echo esc_attr($option); ?>">
            <?php foreach ($options as $key => $label): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($description): ?>
            <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render password field
     */
    public function render_password_field($args)
    {
        $option = $args['option'];
        $value = get_option($option, '');
        $description = isset($args['description']) ? $args['description'] : '';
        ?>
        <input type="password"
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="<?php echo esc_attr($option); ?>"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text"
               autocomplete="off">
        <?php if ($description): ?>
            <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
        <?php if (!empty($value)): ?>
            <p class="description"><strong><?php _e('Password is stored encrypted.', 'advnews-manager'); ?></strong></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render textarea field
     */
    public function render_textarea_field($args)
    {
        $option = $args['option'];
        $value = get_option($option, isset($args['default']) ? $args['default'] : '');
        $rows = isset($args['rows']) ? $args['rows'] : 5;
        $description = isset($args['description']) ? $args['description'] : '';
        ?>
        <textarea id="<?php echo esc_attr($args['label_for']); ?>"
                  name="<?php echo esc_attr($option); ?>"
                  rows="<?php echo esc_attr($rows); ?>"
                  class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <?php if ($description): ?>
            <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render timezone field
     */
    public function render_timezone_field($args)
    {
        $option = $args['option'];
        $value = get_option($option, wp_timezone_string());
        $timezones = timezone_identifiers_list();
        $timestamp = time();

        try {
            $selected_timezone = new DateTimeZone($value);
        } catch (Exception $e) {
            $selected_timezone = wp_timezone();
        }

        $wordpress_timezone = wp_timezone();
        $selected_time = wp_date(get_option('time_format'), $timestamp, $selected_timezone);
        $wordpress_time = wp_date(get_option('time_format'), $timestamp, $wordpress_timezone);
        $timezone_offsets_match = $selected_timezone->getOffset(new DateTimeImmutable('@' . $timestamp)) === $wordpress_timezone->getOffset(new DateTimeImmutable('@' . $timestamp));
        ?>
        <select id="<?php echo esc_attr($args['label_for']); ?>" name="<?php echo esc_attr($option); ?>">
            <?php foreach ($timezones as $timezone): ?>
                <option value="<?php echo esc_attr($timezone); ?>" <?php selected($value, $timezone); ?>>
                    <?php echo esc_html(str_replace('_', ' ', $timezone)); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php _e('Current time in selected AdvNews timezone:', 'advnews-manager'); ?>
            <strong><?php echo esc_html($selected_time); ?></strong>
        </p>
        <p class="description">
            <?php _e('Campaign scheduling uses the WordPress timezone:', 'advnews-manager'); ?>
            <strong><?php echo esc_html(wp_timezone_string()); ?></strong>
            (<?php echo esc_html($wordpress_time); ?>)
        </p>
        <?php if (!$timezone_offsets_match): ?>
            <p class="description" style="color:#996800;">
                <?php _e('This AdvNews timezone is different from the WordPress timezone. Keep them aligned unless reporting should use a different timezone.', 'advnews-manager'); ?>
            </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render pause schedule field
     */
    public function render_pause_schedule_field($args)
    {
        $start = get_option($args['option_start'], '');
        $end = get_option($args['option_end'], '');
        $timezone = get_option($args['option_timezone'], wp_timezone_string());
        $description = isset($args['description']) ? $args['description'] : '';
        ?>
        <input type="time"
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="<?php echo esc_attr($args['option_start']); ?>"
               value="<?php echo esc_attr($start); ?>"
               step="3600">
        <span><?php _e('to', 'advnews-manager'); ?></span>
        <input type="time"
               name="<?php echo esc_attr($args['option_end']); ?>"
               value="<?php echo esc_attr($end); ?>"
               step="3600">
        <select name="<?php echo esc_attr($args['option_timezone']); ?>" style="margin-top:10px; display:block;">
            <?php
            $timezones = timezone_identifiers_list();
            foreach ($timezones as $tz): ?>
                <option value="<?php echo esc_attr($tz); ?>" <?php selected($timezone, $tz); ?>>
                    <?php echo esc_html(str_replace('_', ' ', $tz)); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($description): ?>
            <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render cron method field
     */
    public function render_cron_method_field($args)
    {
        $option = $args['option'];
        $value = get_option($option, 'wp_cron');
        $description = isset($args['description']) ? $args['description'] : '';
        ?>
        <fieldset>
            <label style="display:block; margin-bottom:10px;">
                <input type="radio" name="<?php echo esc_attr($option); ?>" value="wp_cron" <?php checked($value, 'wp_cron'); ?>>
                <strong><?php _e('WordPress Cron (Default)', 'advnews-manager'); ?></strong>
                <p class="description" style="margin-left:25px;"><?php _e('Runs on page loads. Good for low to medium volume.', 'advnews-manager'); ?></p>
            </label>
            <label style="display:block; margin-bottom:10px;">
                <input type="radio" name="<?php echo esc_attr($option); ?>" value="system_cron" <?php checked($value, 'system_cron'); ?>>
                <strong><?php _e('System Cron (Recommended for high volume)', 'advnews-manager'); ?></strong>
                <p class="description" style="margin-left:25px;"><?php _e('More reliable for large lists. Requires server configuration.', 'advnews-manager'); ?></p>
            </label>
            <?php if ($value === 'system_cron'): ?>
                <div style="background:#f0f0f1; padding:15px; border-radius:4px; margin-top:10px;">
                    <p><strong><?php _e('Cron Command:', 'advnews-manager'); ?></strong></p>
                    <code style="display:block; padding:10px; background:#fff;">
                        */5 * * * * wget -q -O /dev/null <?php echo esc_url(site_url('wp-cron.php?doing_wp_cron')); ?>
                    </code>
                    <p class="description"><?php _e('Add this to your server crontab (usually via crontab -e command)', 'advnews-manager'); ?></p>
                </div>
            <?php endif; ?>
        </fieldset>
        <?php if ($description): ?>
            <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render geolocation service field
     */
    public function render_geolocation_service_field($args)
    {
        $service = get_option($args['option'], 'ipapi');
        $api_key = get_option($args['option_key'], '');
        $description = isset($args['description']) ? $args['description'] : '';

        // MaxMind specific options
        $maxmind_license_key = get_option('advnews_maxmind_license_key', '');
        $maxmind_auto_update = get_option('advnews_maxmind_auto_update', true);
        $maxmind_db_path = get_option('advnews_maxmind_db_path', '');

        // ROBUST CHECK: Verify file exists, fallback to default path if stored path fails
        $db_exists = !empty($maxmind_db_path) && file_exists($maxmind_db_path);
        if (!$db_exists) {
            $upload_dir = wp_upload_dir();
            $default_path = $upload_dir['basedir'] . '/advnews-maxmind/GeoLite2-City.mmdb';
            if (file_exists($default_path)) {
                $db_exists = true;
                $maxmind_db_path = $default_path;
                // Auto-fix the option if the file is in the default location
                update_option('advnews_maxmind_db_path', $default_path);
            }
        }

        $db_date = $db_exists ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($maxmind_db_path)) : __('Not downloaded yet', 'advnews-manager');
        ?>
        <select id="<?php echo esc_attr($args['label_for']); ?>" name="<?php echo esc_attr($args['option']); ?>" class="geolocation-service-select">
            <option value="ipapi" <?php selected($service, 'ipapi'); ?>><?php _e('ip-api.com (Free, no key required)', 'advnews-manager'); ?></option>
            <option value="ipstack" <?php selected($service, 'ipstack'); ?>><?php _e('ipstack.com (Requires API key)', 'advnews-manager'); ?></option>
            <option value="ipinfo" <?php selected($service, 'ipinfo'); ?>><?php _e('ipinfo.io (Requires API key)', 'advnews-manager'); ?></option>
            <option value="abstract" <?php selected($service, 'abstract'); ?>><?php _e('AbstractAPI (Requires API key)', 'advnews-manager'); ?></option>
            <option value="maxmind" <?php selected($service, 'maxmind'); ?>><?php _e('MaxMind (Local database)', 'advnews-manager'); ?></option>
        </select>
        <div id="api-key-field" style="margin-top:10px; <?php echo in_array($service, ['ipapi', 'maxmind']) ? 'display:none;' : ''; ?>">
            <label for="<?php echo esc_attr($args['option_key']); ?>"><?php _e('API Key:', 'advnews-manager'); ?></label>
            <input type="text" id="<?php echo esc_attr($args['option_key']); ?>" name="<?php echo esc_attr($args['option_key']); ?>"
            value="<?php echo esc_attr($api_key); ?>" class="regular-text">
        </div>

        <!-- MAXMIND SPECIFIC SETTINGS -->
        <div id="maxmind-settings-field" style="margin-top:15px; background:#f8f9fa; padding:15px; border-radius:4px; border:1px solid #e9ecef; <?php echo $service !== 'maxmind' ? 'display:none;' : ''; ?>">
            <h4 style="margin-top:0;"><?php _e('MaxMind Configuration', 'advnews-manager'); ?></h4>
            <div style="margin-bottom:15px;">
                <label for="advnews_maxmind_license_key"><strong><?php _e('License Key:', 'advnews-manager'); ?></strong></label>
                <input type="text" id="advnews_maxmind_license_key" name="advnews_maxmind_license_key"
                value="<?php echo esc_attr($maxmind_license_key); ?>"
                class="regular-text" placeholder="<?php _e('Enter your MaxMind License Key', 'advnews-manager'); ?>">
                <p class="description">
                <?php _e('Required for downloading the GeoLite2 database. Get a free key at maxmind.com.', 'advnews-manager'); ?>
                <a href="https://www.maxmind.com/en/geolite2/signup" target="_blank"><?php _e('Get Free Key', 'advnews-manager'); ?></a>
                </p>
            </div>
            <div style="margin-bottom:15px;">
                <label>
                <input type="checkbox" name="advnews_maxmind_auto_update" value="1"
                <?php checked($maxmind_auto_update, 1); ?>>
                <?php _e('Automatically update database every 24 hours via Cron', 'advnews-manager'); ?>
                </label>
            </div>
            <div>
                <button type="button" class="button button-primary" id="update-maxmind-now">
                <?php _e('Download / Update Database Now', 'advnews-manager'); ?>
                </button>
                <span id="maxmind-update-spinner" class="spinner" style="float:none; margin-left:5px;"></span>
                <span id="maxmind-update-result" style="margin-left:10px; font-weight:bold;"></span>
                <p class="description" style="margin-top:10px;">
                <?php _e('Current DB Status: ', 'advnews-manager'); ?>
                <?php if ($db_exists): ?>
                <span style="color:green;">✔ <?php _e('Database Found', 'advnews-manager'); ?> (<?php echo $db_date; ?>)</span>
                <?php else: ?>
                <span style="color:red;">✘ <?php _e('No Database Found', 'advnews-manager'); ?></span>
                <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                <br><small style="color:#666;">Checked path: <?php echo esc_html($maxmind_db_path); ?></small>
                <?php endif; ?>
                <?php endif; ?>
                </p>
            </div>
        </div>
        <!-- END MAXMIND SETTINGS -->

        <?php if ($description): ?>
        <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
        <script>
        jQuery(document).ready(function($) {
            $('.geolocation-service-select').on('change', function() {
                var service = $(this).val();
                // Handle Generic API Key Field
                if (service === 'ipapi' || service === 'maxmind') {
                    $('#api-key-field').hide();
                } else {
                    $('#api-key-field').show();
                }
                // Handle MaxMind Specific Field
                if (service === 'maxmind') {
                    $('#maxmind-settings-field').show();
                } else {
                    $('#maxmind-settings-field').hide();
                }
            });

            // MaxMind Manual Update Handler
            $('#update-maxmind-now').on('click', function() {
                var btn = $(this);
                var spinner = $('#maxmind-update-spinner');
                var res = $('#maxmind-update-result');
                var licenseKey = $('#advnews_maxmind_license_key').val();
                if (!licenseKey) {
                    res.html('<span style="color:#d63638;">✘ <?php _e('Please enter a License Key first.', 'advnews-manager'); ?></span>');
                    return;
                }
                btn.prop('disabled', true);
                spinner.addClass('is-active');
                res.html('<?php _e('Downloading & Decompressing...', 'advnews-manager'); ?>');
                $.ajax({
                    url: advnews_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'advnews_update_maxmind_db',
                        nonce: advnews_ajax.nonce,
                        license_key: licenseKey
                    },
                    success: function(response) {
                        if (response.success) {
                            res.html('<span style="color:#00a32a;">✔ ' + response.data.message + '</span>');
                            setTimeout(function(){ location.reload(); }, 2000);
                        } else {
                            res.html('<span style="color:#d63638;">✘ ' + response.data.message + '</span>');
                        }
                    },
                    error: function() {
                        res.html('<span style="color:#d63638;">✘ <?php _e('Connection failed or server timeout.', 'advnews-manager'); ?></span>');
                    },
                    complete: function() {
                        btn.prop('disabled', false);
                        spinner.removeClass('is-active');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render template select field
     */
    public function render_template_select_field($args)
    {
        $option = $args['option'];
        $value = get_option($option, '');
        $description = isset($args['description']) ? $args['description'] : '';
        $templates = $this->wpdb->get_results("SELECT id, name FROM {$this->wpdb->prefix}{$this->table_prefix}templates WHERE is_active = 1 ORDER BY name");
        ?>
        <select id="<?php echo esc_attr($args['label_for']); ?>" name="<?php echo esc_attr($option); ?>">
            <option value=""><?php _e('Default Template', 'advnews-manager'); ?></option>
            <?php foreach ($templates as $template): ?>
                <option value="<?php echo esc_attr($template->id); ?>" <?php selected($value, $template->id); ?>>
                    <?php echo esc_html($template->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($description): ?>
            <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render category select field
     */
    public function render_category_select_field($args)
    {
        $option = $args['option'];
        $value = get_option($option, '');
        $description = isset($args['description']) ? $args['description'] : '';
        $categories = $this->wpdb->get_results("SELECT id, name FROM {$this->wpdb->prefix}{$this->table_prefix}categories ORDER BY name");
        ?>
        <select id="<?php echo esc_attr($args['label_for']); ?>" name="<?php echo esc_attr($option); ?>">
            <option value=""><?php _e('None', 'advnews-manager'); ?></option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo esc_attr($category->id); ?>" <?php selected($value, $category->id); ?>>
                    <?php echo esc_html($category->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($description): ?>
            <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render duplicate handling field
     */
    public function render_duplicate_handling_field($args)
    {
        $option = $args['option'];
        $value = get_option($option, 'skip');
        $description = isset($args['description']) ? $args['description'] : '';
        ?>
        <select id="<?php echo esc_attr($args['label_for']); ?>" name="<?php echo esc_attr($option); ?>">
            <option value="skip" <?php selected($value, 'skip'); ?>><?php _e('Skip duplicates (keep existing)', 'advnews-manager'); ?></option>
            <option value="update" <?php selected($value, 'update'); ?>><?php _e('Update existing subscribers', 'advnews-manager'); ?></option>
            <option value="ignore" <?php selected($value, 'ignore'); ?>><?php _e('Ignore (allow duplicates)', 'advnews-manager'); ?></option>
        </select>
        <?php if ($description): ?>
            <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
        <?php
    }

    // ===== SECTION RENDER METHODS =====
    /**
     * Render general section
     */
    public function render_general_section()
    {
        echo '<p>' . __('Configure general settings for your newsletter system.', 'advnews-manager') . '</p>';
    }

    /**
     * Render SMTP section
     */
    public function render_smtp_section()
    {
        echo '<p>' . __('Configure SMTP settings for reliable email delivery. Leave empty to use WordPress default mail function.', 'advnews-manager') . '</p>';
        ?>
        <div class="smtp-test-area" style="background:#f8f9fa; border:1px solid #ccd0d4; border-radius:4px; padding:20px; margin:20px 0;">
            <h3><?php _e('Test SMTP Connection', 'advnews-manager'); ?></h3>
            <p><?php _e('Send a test email to verify your SMTP settings.', 'advnews-manager'); ?></p>
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <input type="email" id="advnews_test_email" class="regular-text"
                       placeholder="<?php _e('Enter test email address', 'advnews-manager'); ?>"
                       value="<?php echo esc_attr(get_option('admin_email')); ?>">
                <button type="button" id="advnews_test_smtp" class="button"><?php _e('Send Test Email', 'advnews-manager'); ?></button>
                <span id="test-spinner" class="spinner" style="float:none;"></span>
            </div>
            <div id="test-result" style="display:none; margin-top:15px;"></div>
        </div>
        <?php
    }


    /**
    * Show MaxMind database notice
    */
    public function show_maxmind_database_notice() {
        $screen = get_current_screen();

        // Only show on AdvNews pages
        if (strpos($screen->id, 'advnews') === false) {
            return;
        }

        // Check if MaxMind is selected but database is missing
        $geolocation_service = get_option('advnews_geolocation_service', 'ipapi');
        $maxmind_db_path = get_option('advnews_maxmind_db_path', '');

        if ($geolocation_service === 'maxmind' && empty($maxmind_db_path)) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php _e('AdvNews Manager:', 'advnews-manager'); ?></strong>
                    <?php _e('MaxMind geolocation is selected but database not found.', 'advnews-manager'); ?>
                    <a href="<?php echo admin_url('admin.php?page=advnews-settings&tab=tracking#maxmind-settings'); ?>">
                        <?php _e('Download database now', 'advnews-manager'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }


    /**
     * Render cron section
     */
    public function render_cron_section()
    {
        echo '<p>' . __('Configure cron job settings for email queue processing.', 'advnews-manager') . '</p>';
        // Show current queue status
        $queue_class = new AdvNews_Queue();
        $queue_status = $queue_class->get_queue_status();
        ?>
        <div class="queue-status" style="background:#f8f9fa; border:1px solid #ccd0d4; border-radius:4px; padding:20px; margin:20px 0;">
            <h3><?php _e('Current Queue Status', 'advnews-manager'); ?></h3>
            <div style="display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:10px; margin:15px 0;">
                <div style="text-align:center;">
                    <span style="font-size:24px; font-weight:600; color:#2271b1;"><?php echo esc_html($queue_status['queued']); ?></span>
                    <span style="display:block; font-size:12px; color:#666;"><?php _e('Queued', 'advnews-manager'); ?></span>
                </div>
                <div style="text-align:center;">
                    <span style="font-size:24px; font-weight:600; color:#f0c33c;"><?php echo esc_html($queue_status['on_cooldown']); ?></span>
                    <span style="display:block; font-size:12px; color:#666;"><?php _e('On Cooldown', 'advnews-manager'); ?></span>
                </div>
                <div style="text-align:center;">
                    <span style="font-size:24px; font-weight:600; color:#f0c33c;"><?php echo esc_html($queue_status['sending']); ?></span>
                    <span style="display:block; font-size:12px; color:#666;"><?php _e('Sending', 'advnews-manager'); ?></span>
                </div>
                <div style="text-align:center;">
                    <span style="font-size:24px; font-weight:600; color:#00a32a;"><?php echo esc_html($queue_status['delivered']); ?></span>
                    <span style="display:block; font-size:12px; color:#666;"><?php _e('Delivered', 'advnews-manager'); ?></span>
                </div>
                <div style="text-align:center;">
                    <span style="font-size:24px; font-weight:600; color:#d63638;"><?php echo esc_html($queue_status['failed']); ?></span>
                    <span style="display:block; font-size:12px; color:#666;"><?php _e('Failed', 'advnews-manager'); ?></span>
                </div>
            </div>
            <?php if (!empty($queue_status['on_cooldown'])): ?>
                <p style="margin:8px 0 15px; color:#996800;">
                    <?php
                    printf(
                        esc_html(_n(
                            '%d queued email is waiting for the cooldown period to expire.',
                            '%d queued emails are waiting for the cooldown period to expire.',
                            intval($queue_status['on_cooldown']),
                            'advnews-manager'
                        )),
                        esc_html(number_format_i18n($queue_status['on_cooldown']))
                    );
                    ?>
                </p>
            <?php endif; ?>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="button" id="advnews_process_queue_now"><?php _e('Process Queue Now', 'advnews-manager'); ?></button>
                <button type="button" class="button" id="advnews_clear_cooldown_delays"><?php _e('Clear Cooldown Delays', 'advnews-manager'); ?></button>
                <button type="button" class="button" id="advnews_clear_stuck_queue"><?php _e('Clear Stuck', 'advnews-manager'); ?></button>
            </div>
        </div>
        <?php
    }

    /**
     * Render tracking section
     */
    public function render_tracking_section()
    {
        echo '<p>' . __('Configure tracking and analytics settings.', 'advnews-manager') . '</p>';
        // Show current tracking stats
        global $wpdb;
        $open_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$this->table_prefix}tracking_opens");
        $click_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$this->table_prefix}tracking_clicks");
        ?>
        <div style="background:#f8f9fa; border:1px solid #ccd0d4; border-radius:4px; padding:20px; margin:20px 0;">
            <h3><?php _e('Tracking Statistics', 'advnews-manager'); ?></h3>
            <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:20px;">
                <div>
                    <strong><?php _e('Total Opens:', 'advnews-manager'); ?></strong> <?php echo esc_html(number_format($open_count)); ?><br>
                    <strong><?php _e('Total Clicks:', 'advnews-manager'); ?></strong> <?php echo esc_html(number_format($click_count)); ?>
                </div>
                <div>
                    <button type="button" class="button" id="advnews_clear_tracking_data"><?php _e('Clear Old Tracking Data', 'advnews-manager'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render subscriber section
     */
    public function render_subscriber_section()
    {
        echo '<p>' . __('Configure subscriber management settings.', 'advnews-manager') . '</p>';
        // Show subscriber count
        $subscriber_class = new AdvNews_Subscriber();
        $total = $subscriber_class->count_subscribers();
        $active = $subscriber_class->count_subscribers(array('status' => 'active'));
        ?>
        <div style="background:#f8f9fa; border:1px solid #ccd0d4; border-radius:4px; padding:20px; margin:20px 0;">
            <h3><?php _e('Subscriber Overview', 'advnews-manager'); ?></h3>
            <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:20px;">
                <div>
                    <strong><?php _e('Total Subscribers:', 'advnews-manager'); ?></strong> <?php echo esc_html(number_format($total)); ?><br>
                    <strong><?php _e('Active Subscribers:', 'advnews-manager'); ?></strong> <?php echo esc_html(number_format($active)); ?>
                </div>
                <div>
                    <button type="button" class="button" id="advnews_test_subscription"><?php _e('Test Subscription Flow', 'advnews-manager'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render GDPR section
     */
    public function render_gdpr_section()
    {
        echo '<p>' . __('Configure GDPR and privacy settings.', 'advnews-manager') . '</p>';
        if (get_privacy_policy_url()) {
            echo '<div class="notice notice-info inline"><p>' .
                 sprintf(__('Your privacy policy is set: <a href="%s" target="_blank">%s</a>', 'advnews-manager'),
                     esc_url(get_privacy_policy_url()),
                     esc_html(get_privacy_policy_url())) .
                 '</p></div>';
        } else {
            echo '<div class="notice notice-warning inline"><p>' .
                 __('You haven\'t set a privacy policy page yet. Please create one and set it in WordPress settings.', 'advnews-manager') .
                 '</p></div>';
        }
    }

    // ===== DASHBOARD METHODS =====
    /**
     * Safely round a value, handling null
     */
    private function safe_round($value, $precision = 2, $default = 0)
    {
        if ($value === null || $value === '') {
            return floatval($default);
        }
        return round(floatval($value), $precision);
    }

    /**
     * Add dashboard widgets
     */
    public function add_dashboard_widgets()
    {
        if (current_user_can('manage_options')) {
            wp_add_dashboard_widget(
                'advnews_dashboard_widget',
                __('Newsletter Summary', 'advnews-manager'),
                array($this, 'render_dashboard_widget')
            );
        }
    }

    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget()
    {
        $stats = $this->get_dashboard_stats();
        ?>
        <div class="advnews-dashboard-widget">
            <div class="advnews-widget-stats">
                <div class="stat">
                    <span class="stat-label"><?php _e('Total Subscribers:', 'advnews-manager'); ?></span>
                    <span class="stat-value"><?php echo esc_html(number_format($stats['total_subscribers'])); ?></span>
                </div>
                <div class="stat">
                    <span class="stat-label"><?php _e('Active Campaigns:', 'advnews-manager'); ?></span>
                    <span class="stat-value"><?php echo esc_html($stats['active_campaigns']); ?></span>
                </div>
                <div class="stat">
                    <span class="stat-label"><?php _e('Emails Today:', 'advnews-manager'); ?></span>
                    <span class="stat-value"><?php echo esc_html(number_format($stats['emails_sent_today'])); ?></span>
                </div>
                <div class="stat">
                    <span class="stat-label"><?php _e('Avg Open Rate:', 'advnews-manager'); ?></span>
                    <span class="stat-value"><?php echo esc_html($stats['avg_open_rate']); ?>%</span>
                </div>
            </div>
            <div class="advnews-widget-actions">
                <a href="<?php echo admin_url('admin.php?page=advnews-campaigns&action=add'); ?>" class="button">
                    <?php _e('New Campaign', 'advnews-manager'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=advnews-subscribers&action=add'); ?>" class="button">
                    <?php _e('Add Subscriber', 'advnews-manager'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=advnews-analytics'); ?>" class="button">
                    <?php _e('View Analytics', 'advnews-manager'); ?>
                </a>
            </div>
        </div>
        <style>
            .advnews-dashboard-widget .advnews-widget-stats {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 10px;
                margin-bottom: 15px;
            }
            .advnews-dashboard-widget .stat {
                background: #f8f9fa;
                padding: 10px;
                border-radius: 4px;
                text-align: center;
            }
            .advnews-dashboard-widget .stat-label {
                display: block;
                font-size: 12px;
                color: #646970;
                margin-bottom: 5px;
            }
            .advnews-dashboard-widget .stat-value {
                display: block;
                font-size: 18px;
                font-weight: 600;
                color: #1d2327;
            }
            .advnews-dashboard-widget .advnews-widget-actions {
                display: flex;
                gap: 5px;
                justify-content: space-between;
            }
            .advnews-dashboard-widget .advnews-widget-actions .button {
                flex: 1;
                text-align: center;
            }
        </style>
        <?php
    }

    /**
     * Get dashboard statistics
     */
    public function get_dashboard_stats()
    {
        $subscriber_class = new AdvNews_Subscriber();
        $campaign_class = new AdvNews_Campaign();
        $queue_class = new AdvNews_Queue();
        $stats = array(
            'total_subscribers' => $subscriber_class->count_subscribers(),
            'active_campaigns' => $campaign_class->count_campaigns(array('status' => 'scheduled')),
            'emails_sent_today' => 0,
            'avg_open_rate' => 0,
            'avg_click_rate' => 0,
            'subscriber_growth' => 0,
            'recent_activity' => array(),
            'queue_status' => $queue_class->get_queue_status(),
            'last_backup' => __('Never', 'advnews-manager')
        );

        // Calculate emails sent today
        $table_logs = $this->wpdb->prefix . $this->table_prefix . 'campaign_logs';
        $today = date('Y-m-d');
        $emails_sent = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $table_logs
             WHERE DATE(sent_at) = %s AND status IN ('sent', 'delivered')",
            $today
        ));
        $stats['emails_sent_today'] = $emails_sent ? intval($emails_sent) : 0;

        // Calculate average rates
        $table_campaigns = $this->wpdb->prefix . $this->table_prefix . 'campaigns';
        $rates = $this->wpdb->get_row(
            "SELECT AVG(open_rate) as avg_open, AVG(click_rate) as avg_click
             FROM $table_campaigns
             WHERE status = 'sent'"
        );
        if ($rates) {
            $stats['avg_open_rate'] = $this->safe_round($rates->avg_open, 2, 0);
            $stats['avg_click_rate'] = $this->safe_round($rates->avg_click, 2, 0);
        } else {
            $stats['avg_open_rate'] = 0;
            $stats['avg_click_rate'] = 0;
        }

        // Calculate subscriber growth (last 7 days)
        $table_subscribers = $this->wpdb->prefix . $this->table_prefix . 'subscribers';
        $week_ago = date('Y-m-d', strtotime('-7 days'));
        $subscribers_week_ago = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $table_subscribers
             WHERE DATE(subscribed_at) <= %s AND status = 'active'",
            $week_ago
        ));
        if ($subscribers_week_ago && $subscribers_week_ago > 0) {
            $growth = (($stats['total_subscribers'] - $subscribers_week_ago) / $subscribers_week_ago) * 100;
            $stats['subscriber_growth'] = $this->safe_round($growth, 2, 0);
        } else {
            $stats['subscriber_growth'] = 0;
        }

        // Get recent activity
        $stats['recent_activity'] = $this->get_recent_activity();

        // Check last backup
        $backup_time = get_option('advnews_last_backup');
        if ($backup_time) {
            $stats['last_backup'] = human_time_diff($backup_time, current_time('timestamp')) . ' ' . __('ago', 'advnews-manager');
        }

        return $stats;
    }

    /**
     * Get recent activity
     */
    private function get_recent_activity()
    {
        $activity = array();

        // Recent campaigns
        $table_campaigns = $this->wpdb->prefix . $this->table_prefix . 'campaigns';
        $recent_campaigns = $this->wpdb->get_results(
            "SELECT name, created_at, status
             FROM $table_campaigns
             ORDER BY created_at DESC
             LIMIT 5"
        );
        foreach ($recent_campaigns as $campaign) {
            $activity[] = array(
                'date' => $campaign->created_at,
                'activity' => sprintf(__('Campaign created: %s', 'advnews-manager'), $campaign->name),
                'type' => 'campaign'
            );
        }

        // Recent subscribers
        $table_subscribers = $this->wpdb->prefix . $this->table_prefix . 'subscribers';
        $recent_subscribers = $this->wpdb->get_results(
            "SELECT email, subscribed_at
             FROM $table_subscribers
             WHERE status = 'active'
             ORDER BY subscribed_at DESC
             LIMIT 5"
        );
        foreach ($recent_subscribers as $subscriber) {
            $activity[] = array(
                'date' => $subscriber->subscribed_at,
                'activity' => sprintf(__('New subscriber: %s', 'advnews-manager'), $subscriber->email),
                'type' => 'subscriber'
            );
        }

        // Sort by date
        usort($activity, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return array_slice($activity, 0, 10);
    }

    // ===== RENDER CATEGORIES PAGE =====
    /**
     * Render categories page
     */
    public function render_categories()
    {
        AdvNews_Security::check_capability();
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        switch ($action) {
            case 'add':
            case 'edit':
                $this->render_category_editor($category_id);
                break;
            default:
                $this->render_categories_list();
                break;
        }
    }

    /**
     * Render categories list
     */
    private function render_categories_list()
    {
        $category_class = new AdvNews_Category();
        $categories = $category_class->get_all_categories();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Categories', 'advnews-manager'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=advnews-categories&action=add'); ?>" class="page-title-action">
                <?php _e('Add New Category', 'advnews-manager'); ?>
            </a>
            <hr class="wp-header-end">
            <?php if (empty($categories)): ?>
                <div class="notice notice-info">
                    <p><?php _e('No categories found. Create your first category to start organizing your subscribers.', 'advnews-manager'); ?></p>
                </div>
            <?php else: ?>
                <div class="advnews-category-filter-bar">
                    <div class="advnews-multiselect" data-placeholder="<?php esc_attr_e('Filter categories', 'advnews-manager'); ?>" data-selected-singular="<?php esc_attr_e('category selected', 'advnews-manager'); ?>" data-selected-plural="<?php esc_attr_e('categories selected', 'advnews-manager'); ?>">
                        <button type="button" class="advnews-multiselect-toggle" aria-haspopup="listbox" aria-expanded="false">
                            <span class="advnews-multiselect-label"><?php _e('Filter categories', 'advnews-manager'); ?></span>
                            <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                        </button>
                        <div class="advnews-multiselect-menu" role="listbox" aria-multiselectable="true">
                            <label class="advnews-multiselect-option advnews-multiselect-select-all">
                                <input type="checkbox" class="advnews-multiselect-select-all-input">
                                <span class="advnews-multiselect-check" aria-hidden="true"></span>
                                <span class="advnews-multiselect-text"><?php _e('Select all categories', 'advnews-manager'); ?></span>
                            </label>
                            <?php foreach ($categories as $category): ?>
                                <label class="advnews-multiselect-option">
                                    <input type="checkbox" class="advnews-category-filter-option" value="<?php echo esc_attr($category->id); ?>">
                                    <span class="advnews-multiselect-check" aria-hidden="true"></span>
                                    <span class="advnews-category-swatch" style="background-color: <?php echo esc_attr($category->color); ?>;" aria-hidden="true"></span>
                                    <span class="advnews-multiselect-text"><?php echo esc_html($category->name); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <input type="search" class="regular-text advnews-category-search" placeholder="<?php esc_attr_e('Search categories...', 'advnews-manager'); ?>">
                    <button type="button" class="button advnews-clear-category-filter"><?php _e('Clear Filters', 'advnews-manager'); ?></button>
                    <span class="advnews-category-filter-count" aria-live="polite"></span>
                </div>
                <div class="advnews-categories-grid">
                    <?php foreach ($categories as $category):
                        $stats = $category_class->get_category_stats($category->id);
                        ?>
                        <div class="advnews-category-card" data-category-id="<?php echo esc_attr($category->id); ?>" data-category-name="<?php echo esc_attr(strtolower($category->name)); ?>" style="border-top: 4px solid <?php echo esc_attr($category->color); ?>;">
                            <div class="category-header">
                                <h3><?php echo esc_html($category->name); ?></h3>
                                <span class="category-color" style="background-color: <?php echo esc_attr($category->color); ?>;"></span>
                            </div>
                            <?php if (!empty($category->description)): ?>
                                <p class="category-description"><?php echo esc_html($category->description); ?></p>
                            <?php endif; ?>
                            <div class="category-stats">
                                <div class="stat">
                                    <span class="stat-value"><?php echo esc_html($stats['subscribers']); ?></span>
                                    <span class="stat-label"><?php _e('Subscribers', 'advnews-manager'); ?></span>
                                </div>
                                <div class="stat">
                                    <span class="stat-value"><?php echo esc_html($stats['campaigns']); ?></span>
                                    <span class="stat-label"><?php _e('Campaigns', 'advnews-manager'); ?></span>
                                </div>
                                <div class="stat">
                                    <span class="stat-value"><?php echo esc_html($stats['avg_open_rate']); ?>%</span>
                                    <span class="stat-label"><?php _e('Avg Open', 'advnews-manager'); ?></span>
                                </div>
                            </div>
                            <div class="category-actions">
                                <a href="<?php echo admin_url('admin.php?page=advnews-categories&action=edit&id=' . $category->id); ?>" class="button button-small">
                                    <?php _e('Edit', 'advnews-manager'); ?>
                                </a>
                                <a href="<?php echo admin_url('admin.php?page=advnews-subscribers&category_id=' . intval($category->id)); ?>" class="button button-small">
                                    <?php _e('View Subscribers', 'advnews-manager'); ?>
                                </a>
                                <?php if ($stats['subscribers'] == 0 && $stats['campaigns'] == 0): ?>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=advnews-categories&action=delete&id=' . $category->id), 'advnews_delete_category'); ?>"
                                       class="button button-small button-link-delete"
                                       onclick="return confirm('<?php _e('Are you sure you want to delete this category?', 'advnews-manager'); ?>');">
                                        <?php _e('Delete', 'advnews-manager'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <script>
            jQuery(document).ready(function($) {
                function getAdvNewsMultiSelectOptions($select) {
                    return $select.find('input[type="checkbox"]').not(':disabled').not('.advnews-multiselect-select-all-input');
                }

                function updateAdvNewsMultiSelect($select) {
                    var options = getAdvNewsMultiSelectOptions($select);
                    var checked = options.filter(':checked');
                    var label = $select.find('.advnews-multiselect-label');
                    var placeholder = $select.data('placeholder') || '';
                    var plural = $select.data('selected-plural') || 'selected';
                    var selectAll = $select.find('.advnews-multiselect-select-all-input');
                    var names = checked.map(function() {
                        return $.trim($(this).closest('.advnews-multiselect-option').find('.advnews-multiselect-text').first().text());
                    }).get();

                    if (selectAll.length) {
                        selectAll.prop('checked', options.length > 0 && checked.length === options.length);
                        selectAll.prop('indeterminate', checked.length > 0 && checked.length < options.length);
                    }

                    if (!checked.length) {
                        label.text(placeholder);
                    } else if (checked.length === 1) {
                        label.text(names[0]);
                    } else {
                        label.text(checked.length + ' ' + plural);
                    }
                }

                function filterCategoryCards() {
                    var selectedIds = $('.advnews-category-filter-option:checked').map(function() {
                        return $(this).val();
                    }).get();
                    var search = $.trim($('.advnews-category-search').val()).toLowerCase();
                    var visibleCount = 0;

                    $('.advnews-category-card').each(function() {
                        var $card = $(this);
                        var idMatches = selectedIds.length === 0 || selectedIds.indexOf(String($card.data('category-id'))) !== -1;
                        var nameMatches = search === '' || String($card.data('category-name')).indexOf(search) !== -1;
                        var showCard = idMatches && nameMatches;
                        $card.toggle(showCard);
                        if (showCard) {
                            visibleCount++;
                        }
                    });

                    $('.advnews-category-filter-count').text(
                        visibleCount + ' <?php echo esc_js(__('shown', 'advnews-manager')); ?>'
                    );
                }

                $('.advnews-multiselect').each(function() {
                    updateAdvNewsMultiSelect($(this));
                });
                filterCategoryCards();

                $(document).on('click', '.advnews-multiselect-toggle', function(e) {
                    e.preventDefault();
                    var $select = $(this).closest('.advnews-multiselect');
                    $('.advnews-multiselect').not($select).removeClass('is-open').find('.advnews-multiselect-toggle').attr('aria-expanded', 'false');
                    $select.toggleClass('is-open');
                    $(this).attr('aria-expanded', $select.hasClass('is-open') ? 'true' : 'false');
                });

                $(document).on('change', '.advnews-multiselect input[type="checkbox"]', function() {
                    var $select = $(this).closest('.advnews-multiselect');
                    if ($(this).hasClass('advnews-multiselect-select-all-input')) {
                        getAdvNewsMultiSelectOptions($select).prop('checked', $(this).is(':checked'));
                    }
                    updateAdvNewsMultiSelect($select);
                    filterCategoryCards();
                });

                $('.advnews-category-search').on('input', filterCategoryCards);

                $('.advnews-clear-category-filter').on('click', function() {
                    $('.advnews-category-filter-option').prop('checked', false);
                    $('.advnews-multiselect-select-all-input').prop('checked', false).prop('indeterminate', false);
                    $('.advnews-category-search').val('');
                    $('.advnews-multiselect').each(function() {
                        updateAdvNewsMultiSelect($(this));
                    });
                    filterCategoryCards();
                });

                $(document).on('click', function(e) {
                    if (!$(e.target).closest('.advnews-multiselect').length) {
                        $('.advnews-multiselect').removeClass('is-open').find('.advnews-multiselect-toggle').attr('aria-expanded', 'false');
                    }
                });

                $(document).on('keydown', function(e) {
                    if (e.key === 'Escape') {
                        $('.advnews-multiselect').removeClass('is-open').find('.advnews-multiselect-toggle').attr('aria-expanded', 'false');
                    }
                });
            });
        </script>
        <style>
            .advnews-category-filter-bar {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
                margin-top: 16px;
                padding: 12px;
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
            }
            .advnews-category-filter-count {
                color: #50575e;
                font-size: 12px;
            }
            .advnews-multiselect {
                position: relative;
                width: 320px;
                max-width: 100%;
            }
            .advnews-multiselect-toggle {
                width: 100%;
                min-height: 36px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                padding: 0 10px;
                border: 1px solid #8c8f94;
                border-radius: 4px;
                background: #fff;
                color: #2c3338;
                cursor: pointer;
                text-align: left;
            }
            .advnews-multiselect-toggle:focus {
                border-color: #2271b1;
                box-shadow: 0 0 0 1px #2271b1;
                outline: 2px solid transparent;
            }
            .advnews-multiselect-label {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .advnews-multiselect-menu {
                display: none;
                position: absolute;
                z-index: 1000;
                top: calc(100% + 4px);
                left: 0;
                right: 0;
                max-height: 240px;
                overflow-y: auto;
                padding: 6px;
                border: 1px solid #8c8f94;
                border-radius: 4px;
                background: #fff;
                box-shadow: 0 8px 18px rgba(0, 0, 0, 0.12);
            }
            .advnews-multiselect.is-open .advnews-multiselect-menu {
                display: block;
            }
            .advnews-multiselect-option {
                display: flex;
                align-items: center;
                gap: 8px;
                min-height: 30px;
                padding: 5px 6px;
                border-radius: 3px;
                cursor: pointer;
            }
            .advnews-multiselect-option:hover {
                background: #f0f6fc;
            }
            .advnews-multiselect-select-all {
                border-bottom: 1px solid #dcdcde;
                font-weight: 600;
                margin-bottom: 4px;
                padding-bottom: 8px;
            }
            .advnews-multiselect-option input {
                position: absolute;
                opacity: 0;
                pointer-events: none;
            }
            .advnews-multiselect-check {
                width: 16px;
                height: 16px;
                border: 1px solid #8c8f94;
                border-radius: 3px;
                background: #fff;
                box-sizing: border-box;
                flex: 0 0 auto;
            }
            .advnews-multiselect-option input:checked + .advnews-multiselect-check {
                border-color: #2271b1;
                background: #2271b1;
            }
            .advnews-multiselect-option input:checked + .advnews-multiselect-check::after {
                content: "";
                display: block;
                width: 4px;
                height: 8px;
                margin: 1px 0 0 5px;
                border: solid #fff;
                border-width: 0 2px 2px 0;
                transform: rotate(45deg);
            }
            .advnews-category-swatch {
                width: 12px;
                height: 12px;
                border-radius: 3px;
                flex: 0 0 auto;
            }
            .advnews-multiselect-text {
                line-height: 1.3;
            }
            .advnews-categories-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }
            .advnews-category-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 6px;
                padding: 20px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            }
            .category-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 15px;
            }
            .category-header h3 {
                margin: 0;
                font-size: 18px;
            }
            .category-color {
                width: 24px;
                height: 24px;
                border-radius: 4px;
                border: 2px solid #fff;
                box-shadow: 0 1px 3px rgba(0,0,0,0.2);
            }
            .category-description {
                color: #666;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 1px solid #f0f0f0;
            }
            .category-stats {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
                margin-bottom: 20px;
                padding: 15px 0;
                border-top: 1px solid #f0f0f0;
                border-bottom: 1px solid #f0f0f0;
            }
            .stat {
                text-align: center;
            }
            .stat-value {
                display: block;
                font-size: 20px;
                font-weight: 600;
                color: #2271b1;
                line-height: 1.2;
            }
            .stat-label {
                display: block;
                font-size: 11px;
                color: #666;
                text-transform: uppercase;
            }
            .category-actions {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }
            .button-link-delete {
                color: #d63638;
                border-color: #d63638;
            }
            .button-link-delete:hover {
                background: #d63638;
                color: #fff;
                border-color: #d63638;
            }
            @media screen and (max-width: 782px) {
                .advnews-category-filter-bar .regular-text,
                .advnews-category-filter-bar .advnews-multiselect {
                    width: 100%;
                }
            }
        </style>
        <?php
    }

    /**
     * Render category editor
     */
    private function render_category_editor($category_id = 0)
    {
        $category_class = new AdvNews_Category();
        $category = $category_id ? $category_class->get_category($category_id) : null;
        ?>
        <div class="wrap">
            <h1><?php echo $category_id ? __('Edit Category', 'advnews-manager') : __('Add New Category', 'advnews-manager'); ?></h1>
            <div class="postbox">
                <div class="inside">
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <input type="hidden" name="action" value="advnews_save_category">
                        <?php wp_nonce_field('advnews_save_category'); ?>
                        <input type="hidden" name="category_id" value="<?php echo esc_attr($category_id); ?>">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="category_name"><?php _e('Category Name', 'advnews-manager'); ?> <span class="required">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="category_name" name="name"
                                           value="<?php echo $category ? esc_attr($category->name) : ''; ?>"
                                           class="regular-text" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="category_slug"><?php _e('Category Slug', 'advnews-manager'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="category_slug" name="slug"
                                           value="<?php echo $category ? esc_attr($category->slug) : ''; ?>"
                                           class="regular-text">
                                    <p class="description">
                                        <?php _e('Unique identifier for the category. Leave empty to auto-generate.', 'advnews-manager'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="category_description"><?php _e('Description', 'advnews-manager'); ?></label>
                                </th>
                                <td>
                                    <textarea id="category_description" name="description" rows="3" class="large-text"><?php
                                        echo $category ? esc_textarea($category->description) : '';
                                        ?></textarea>
                                    <p class="description">
                                        <?php _e('Optional description of what this category is for.', 'advnews-manager'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="category_color"><?php _e('Color', 'advnews-manager'); ?></label>
                                </th>
                                <td>
                                    <input type="color" id="category_color" name="color"
                                           value="<?php echo $category ? esc_attr($category->color) : '#3498db'; ?>"
                                           class="regular-text" style="width: 100px; height: 35px;">
                                    <p class="description">
                                        <?php _e('Color used for visual identification.', 'advnews-manager'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" class="button button-primary" value="<?php _e('Save Category', 'advnews-manager'); ?>">
                            <a href="<?php echo admin_url('admin.php?page=advnews-categories'); ?>" class="button"><?php _e('Cancel', 'advnews-manager'); ?></a>
                        </p>
                    </form>
                </div>
            </div>
            <?php if ($category_id): ?>
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Category Usage', 'advnews-manager'); ?></h2>
                    <div class="inside">
                        <?php
                        $stats = $category_class->get_category_stats($category_id);
                        ?>
                        <table class="widefat">
                            <tr>
                                <td><strong><?php _e('Subscribers:', 'advnews-manager'); ?></strong></td>
                                <td><?php echo esc_html($stats['subscribers']); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Campaigns:', 'advnews-manager'); ?></strong></td>
                                <td><?php echo esc_html($stats['campaigns']); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Average Open Rate:', 'advnews-manager'); ?></strong></td>
                                <td><?php echo esc_html($stats['avg_open_rate']); ?>%</td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Average Click Rate:', 'advnews-manager'); ?></strong></td>
                                <td><?php echo esc_html($stats['avg_click_rate']); ?>%</td>
                            </tr>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Handle save category
     */
    public function handle_save_category()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'advnews-manager'));
        }

        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'slug' => isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '',
            'description' => sanitize_textarea_field($_POST['description']),
            'color' => sanitize_hex_color($_POST['color'])
        );

        $category_class = new AdvNews_Category();
        if ($category_id) {
            $result = $category_class->update_category($category_id, $data);
            $message = 'updated';
        } else {
            $result = $category_class->create_category($data);
            $message = 'created';
        }

        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }

        wp_redirect(add_query_arg(array(
            'page' => 'advnews-categories',
            'message' => 'category_' . $message
        ), admin_url('admin.php')));
        exit;
    }

    /**
     * Handle delete category
     */
    public function handle_delete_category()
    {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'advnews_delete_category')) {
            wp_die(__('Security check failed.', 'advnews-manager'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'advnews-manager'));
        }

        $category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$category_id) {
            wp_die(__('Invalid category ID.', 'advnews-manager'));
        }

        $category_class = new AdvNews_Category();
        $result = $category_class->delete_category($category_id);
        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }

        wp_redirect(add_query_arg(array(
            'page' => 'advnews-categories',
            'message' => 'category_deleted'
        ), admin_url('admin.php')));
        exit;
    }

    /**
     * Handle admin actions
     */
    public function handle_admin_actions()
    {
        if (isset($_GET['page']) && $_GET['page'] === 'advnews-categories' && isset($_GET['action']) && $_GET['action'] === 'delete') {
            $this->handle_delete_category();
        }
        if (isset($_GET['page']) && $_GET['page'] === 'advnews-subscribers' && isset($_GET['action']) && $_GET['action'] === 'delete') {
            $this->handle_delete_subscriber();
        }
        if (isset($_GET['page']) && $_GET['page'] === 'advnews-subscribers' && isset($_GET['action']) && $_GET['action'] === 'unsubscribe') {
            $this->handle_unsubscribe_subscriber();
        }
        if (isset($_GET['page']) && $_GET['page'] === 'advnews-subscribers' && isset($_GET['action']) && $_GET['action'] === 'resubscribe') {
            $this->handle_resubscribe_subscriber();
        }

        // ✅ NEW: Reset cooldown handler
        if (isset($_GET['page']) && $_GET['page'] === 'advnews-subscribers' && isset($_GET['action']) && $_GET['action'] === 'reset_cooldown') {
            $this->handle_reset_subscriber_cooldown();
        }

        // Add template delete handler
        if (isset($_GET['page']) && $_GET['page'] === 'advnews-templates' && isset($_GET['action']) && $_GET['action'] === 'delete') {
            $this->handle_delete_template();
        }
        if (isset($_GET['page']) && $_GET['page'] === 'advnews-templates' && isset($_GET['action']) && $_GET['action'] === 'duplicate') {
            $this->handle_duplicate_template();
        }
    }

    /**
     * Handle reset cooldown for a specific subscriber
     */
    public function handle_reset_subscriber_cooldown()
    {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'advnews_reset_cooldown_subscriber')) {
            wp_die(__('Security check failed.', 'advnews-manager'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'advnews-manager'));
        }

        $subscriber_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$subscriber_id) {
            wp_die(__('Invalid subscriber ID.', 'advnews-manager'));
        }

        global $wpdb;
        $table_prefix = ADVNEWS_TABLE_PREFIX;

        // 1. Reset 'last_email_sent' to NULL so future queue additions don't get delayed
        $wpdb->update(
            $wpdb->prefix . $table_prefix . 'subscribers',
            array('last_email_sent' => null),
            array('id' => $subscriber_id)
        );

        // 2. Clear any existing 'send_after' delays in the campaign_logs for this subscriber
        $wpdb->update(
            $wpdb->prefix . $table_prefix . 'campaign_logs',
            array('send_after' => null),
            array('subscriber_id' => $subscriber_id, 'status' => 'queued')
        );

        wp_redirect(add_query_arg(array(
            'page' => 'advnews-subscribers',
            'message' => 'cooldown_reset'
        ), admin_url('admin.php')));
        exit;
    }

    /**
     * Handle save subscriber
     */
    public function handle_save_subscriber()
    {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'advnews_save_subscriber')) {
            wp_die(__('Security check failed.', 'advnews-manager'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'advnews-manager'));
        }

        $subscriber_id = isset($_POST['subscriber_id']) ? intval($_POST['subscriber_id']) : 0;
        $data = array(
            'email' => sanitize_email($_POST['email']),
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'organization' => sanitize_text_field($_POST['organization']),
            'title' => isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '',
            'website_url' => isset($_POST['website_url']) ? sanitize_text_field($_POST['website_url']) : '',
            'description' => isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '',
            'country' => isset($_POST['country']) ? sanitize_text_field($_POST['country']) : ''
        );

        $data['categories'] = (isset($_POST['categories']) && is_array($_POST['categories']))
            ? array_map('intval', $_POST['categories'])
            : array();

        // Add status if editing
        if ($subscriber_id && isset($_POST['status'])) {
            $data['status'] = sanitize_text_field($_POST['status']);
        }

        $subscriber_class = new AdvNews_Subscriber();
        if ($subscriber_id) {
            // Update existing subscriber
            $result = $subscriber_class->update_subscriber($subscriber_id, $data);
            // Update categories
            if (isset($data['categories'])) {
                $subscriber_class->add_categories_to_subscriber($subscriber_id, $data['categories']);
            }
            $message = 'updated';
            $redirect_id = $subscriber_id;
        } else {
            // Create new subscriber
            $result = $subscriber_class->add_subscriber($data);
            if (!is_wp_error($result)) {
                $redirect_id = $result;
                $message = 'created';
            } else {
                $redirect_id = 0;
                $message = 'error';
            }
        }

        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }

        wp_redirect(add_query_arg(array(
            'page' => 'advnews-subscribers',
            'message' => $redirect_id ? $message : 'error'
        ), admin_url('admin.php')));
        exit;
    }

    /**
     * Handle delete subscriber - FIXED: Now permanently deletes instead of anonymizing
     */
    public function handle_delete_subscriber()
    {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'advnews_delete_subscriber')) {
            wp_die(__('Security check failed.', 'advnews-manager'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'advnews-manager'));
        }

        $subscriber_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$subscriber_id) {
            wp_die(__('Invalid subscriber ID.', 'advnews-manager'));
        }

        $subscriber_class = new AdvNews_Subscriber();
        $subscriber = $subscriber_class->get_subscriber($subscriber_id);
        if (!$subscriber) {
            wp_die(__('Subscriber not found.', 'advnews-manager'));
        }

        // PERMANENTLY DELETE subscriber instead of anonymizing
        global $wpdb;
        $table_prefix = ADVNEWS_TABLE_PREFIX;
        // Delete from subscribers table
        $wpdb->delete(
            $wpdb->prefix . $table_prefix . 'subscribers',
            array('id' => $subscriber_id)
        );
        // Delete from subscriber_categories table
        $wpdb->delete(
            $wpdb->prefix . $table_prefix . 'subscriber_categories',
            array('subscriber_id' => $subscriber_id)
        );

        wp_redirect(add_query_arg(array(
            'page' => 'advnews-subscribers',
            'message' => 'deleted'
        ), admin_url('admin.php')));
        exit;
    }

    /**
     * Handle unsubscribe subscriber
     */
    public function handle_unsubscribe_subscriber()
    {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'advnews_unsubscribe_subscriber')) {
            wp_die(__('Security check failed.', 'advnews-manager'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'advnews-manager'));
        }

        $subscriber_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$subscriber_id) {
            wp_die(__('Invalid subscriber ID.', 'advnews-manager'));
        }

        $subscriber_class = new AdvNews_Subscriber();
        $subscriber = $subscriber_class->get_subscriber($subscriber_id);
        if (!$subscriber) {
            wp_die(__('Subscriber not found.', 'advnews-manager'));
        }

        $result = $subscriber_class->unsubscribe($subscriber->email, __('Unsubscribed by admin', 'advnews-manager'));
        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }

        wp_redirect(add_query_arg(array(
            'page' => 'advnews-subscribers',
            'action' => 'edit',
            'id' => $subscriber_id,
            'message' => 'unsubscribed'
        ), admin_url('admin.php')));
        exit;
    }

    /**
     * Handle resubscribe subscriber
     */
    public function handle_resubscribe_subscriber()
    {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'advnews_resubscribe_subscriber')) {
            wp_die(__('Security check failed.', 'advnews-manager'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'advnews-manager'));
        }

        $subscriber_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$subscriber_id) {
            wp_die(__('Invalid subscriber ID.', 'advnews-manager'));
        }

        $subscriber_class = new AdvNews_Subscriber();
        $result = $subscriber_class->resubscribe($subscriber_id);
        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }

        wp_redirect(add_query_arg(array(
            'page' => 'advnews-subscribers',
            'action' => 'edit',
            'id' => $subscriber_id,
            'message' => 'resubscribed'
        ), admin_url('admin.php')));
        exit;
    }

    /**
     * Handle bulk subscriber actions - FIXED: Now permanently deletes instead of anonymizing
     */
    private function handle_bulk_subscriber_actions()
    {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'advnews_bulk_subscribers')) {
            return;
        }

        $bulk_action = sanitize_text_field($_POST['bulk_action']);
        if (empty($bulk_action)) {
            $bulk_action = sanitize_text_field($_POST['bulk_action2'] ?? '');
        }

        $subscriber_ids = isset($_POST['subscriber_ids']) ? array_map('intval', $_POST['subscriber_ids']) : array();
        if (empty($subscriber_ids)) {
            return;
        }

        $subscriber_class = new AdvNews_Subscriber();
        $processed = 0;

        switch ($bulk_action) {
            case 'delete':
                // PERMANENTLY DELETE instead of anonymize
                global $wpdb;
                $table_prefix = ADVNEWS_TABLE_PREFIX;
                foreach ($subscriber_ids as $subscriber_id) {
                    $subscriber = $subscriber_class->get_subscriber($subscriber_id);
                    if ($subscriber) {
                        // Delete from subscribers table
                        $wpdb->delete(
                            $wpdb->prefix . $table_prefix . 'subscribers',
                            array('id' => $subscriber_id)
                        );
                        // Delete from subscriber_categories table
                        $wpdb->delete(
                            $wpdb->prefix . $table_prefix . 'subscriber_categories',
                            array('subscriber_id' => $subscriber_id)
                        );
                        $processed++;
                    }
                }
                $message = sprintf(__('%d subscribers deleted.', 'advnews-manager'), $processed);
                break;

            case 'unsubscribe':
                foreach ($subscriber_ids as $subscriber_id) {
                    $subscriber = $subscriber_class->get_subscriber($subscriber_id);
                    if ($subscriber && $subscriber->status === 'active') {
                        $subscriber_class->unsubscribe($subscriber->email, __('Unsubscribed via bulk action', 'advnews-manager'));
                        $processed++;
                    }
                }
                $message = sprintf(__('%d subscribers unsubscribed.', 'advnews-manager'), $processed);
                break;

            case 'activate':
                foreach ($subscriber_ids as $subscriber_id) {
                    $subscriber = $subscriber_class->get_subscriber($subscriber_id);
                    if ($subscriber && $subscriber->status === 'unsubscribed') {
                        $subscriber_class->resubscribe($subscriber_id);
                        $processed++;
                    }
                }
                $message = sprintf(__('%d subscribers activated.', 'advnews-manager'), $processed);
                break;

            case 'export':
                // Handle export via AJAX
                return;
            default:
                return;
        }

        if (!empty($message)) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }

    /**
     * Encrypt setting
     * FIXED: Only encrypt if value is not already encrypted
     */
    public function encrypt_setting($value)
    {
        if (empty($value)) {
            return $value;
        }
        // Check if already encrypted to prevent double encryption
        if (AdvNews_Security::is_encrypted($value)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews Admin] Setting already encrypted, skipping');
            }
            return $value;
        }
        $encrypted = AdvNews_Security::encrypt($value);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews Admin] Setting encrypted, original length: ' . strlen($value) . ', encrypted length: ' . strlen($encrypted));
        }
        return $encrypted;
    }

    // ===== TEMPLATE METHODS =====
    /**
     * Get template by ID
     */
    private function get_template($template_id)
    {
        if (!$template_id) {
            return null;
        }
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'templates';
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $template_id
        ));
    }

    /**
     * Get default template content
     */
    private function get_default_template_content()
    {
        return '<div class="container">
<div class="header">
<h1>[site_name]</h1>
</div>
<div class="content">
<h2>Hello [first_name],</h2>
<p>This is your email content. Replace this with your own content.</p>
<p>You can use merge tags like:</p>
<ul>
<li><strong>First Name:</strong> [first_name]</li>
<li><strong>Last Name:</strong> [last_name]</li>
<li><strong>Full Name:</strong> [full_name]</li>
<li><strong>Email:</strong> [email]</li>
<li><strong>Organization:</strong> [organization]</li>
<li><strong>Current Date:</strong> [current_date]</li>
</ul>
<p style="text-align: center;">
<a href="#" class="button">Call to Action</a>
</p>
</div>
<div class="footer">
<p>&copy; [current_year] [site_name]. All rights reserved.</p>
<p><a href="[unsubscribe_link]">Unsubscribe</a></p>
</div>
</div>';
    }

    /**
     * Get default template CSS
     */
    private function get_default_template_css()
    {
        return 'body {
font-family: Arial, sans-serif;
line-height: 1.6;
color: #333;
margin: 0;
padding: 0;
}
.container {
max-width: 600px;
margin: 0 auto;
padding: 20px;
}
.header {
background: #0073aa;
color: white;
padding: 20px;
text-align: center;
}
.content {
padding: 20px;
background: #f9f9f9;
}
.footer {
padding: 20px;
text-align: center;
font-size: 12px;
color: #666;
}
.button {
display: inline-block;
padding: 10px 20px;
background: #0073aa;
color: white;
text-decoration: none;
border-radius: 4px;
}';
    }

    /**
     * Handle save template
     */
    public function handle_save_template()
    {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'advnews_save_template')) {
            wp_die(__('Security check failed.', 'advnews-manager'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'advnews-manager'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_prefix . 'templates';
        $rel_table  = $wpdb->prefix . $this->table_prefix . 'template_categories';

        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;

        // Prepare template data (category_id set to NULL since junction table handles relationships)
        $data = array(
            'name' => sanitize_text_field($_POST['template_name']),
            'subject' => sanitize_text_field($_POST['template_subject']),
            'content' => isset($_POST['template_html']) ? $this->sanitize_email_html($_POST['template_html']) : '',
            'css' => isset($_POST['template_css']) ? wp_strip_all_tags($_POST['template_css']) : '',
            //'category_id' => null,
            'is_active' => isset($_POST['template_active']) ? 1 : 0
        );

        // Save to main templates table
        if ($template_id) {
            $result = $wpdb->update($table_name, $data, array('id' => $template_id));
            $message = 'template_updated';
        } else {
            $result = $wpdb->insert($table_name, $data);
            $template_id = $wpdb->insert_id;
            $message = 'template_created';
        }

        if ($result === false) {
            wp_die(__('Failed to save template.', 'advnews-manager'));
        }

        $categories = array();
        if (isset($_POST['template_categories']) && is_array($_POST['template_categories'])) {
            $categories = array_filter(array_map('intval', $_POST['template_categories']));
        }

        if ($template_id) {
            $wpdb->delete($rel_table, array('template_id' => $template_id));
            foreach ($categories as $cat_id) {
                $wpdb->insert($rel_table, array(
                    'template_id' => $template_id,
                    'category_id' => $cat_id
                ));
            }
        }

        if (isset($_POST['send_template_now'])) {
            if (empty($categories)) {
                wp_die(__('Select at least one template category before sending.', 'advnews-manager'));
            }

            $campaign_class = new AdvNews_Campaign();
            $campaign_id = $campaign_class->create_campaign(array(
                'name' => sprintf('%s - %s', $data['name'], current_time('mysql')),
                'subject' => $data['subject'],
                'category_ids' => $categories,
                'content' => $data['content'],
                'template_id' => $template_id,
                'status' => 'draft',
                'priority' => 'normal',
                'track_opens' => 1,
                'track_clicks' => 1,
                'respect_cooldown' => 1
            ));

            if (is_wp_error($campaign_id)) {
                wp_die($campaign_id->get_error_message());
            }

            $send_result = $campaign_class->send_campaign($campaign_id);
            if (is_wp_error($send_result)) {
                wp_die($send_result->get_error_message());
            }

            wp_redirect(add_query_arg(array(
                'page' => 'advnews-campaigns',
                'action' => 'edit',
                'id' => $campaign_id,
                'message' => 'campaign_sent'
            ), admin_url('admin.php')));
            exit;
        }

        wp_redirect(add_query_arg(array(
            'page' => 'advnews-templates',
            'action' => 'edit',
            'id' => $template_id,
            'message' => $message
        ), admin_url('admin.php')));
        exit;
    }

    /**
     * Build a safe redirect URL for list-table bulk actions.
     */
    private function get_bulk_action_redirect_url($page, $message, $processed = 0)
    {
        $fallback = admin_url('admin.php?page=' . $page);
        $redirect_url = isset($_POST['_wp_http_referer']) ? wp_unslash($_POST['_wp_http_referer']) : $fallback;
        $redirect_url = wp_validate_redirect($redirect_url, $fallback);
        $redirect_url = remove_query_arg(array('message', 'processed'), $redirect_url);

        return add_query_arg(array(
            'message' => $message,
            'processed' => max(0, intval($processed))
        ), $redirect_url);
    }

    /**
     * Handle campaign list bulk actions.
     */
    public function handle_bulk_campaigns()
    {
        check_admin_referer('advnews_bulk_campaigns');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'advnews-manager'));
        }

        $bulk_action = isset($_POST['selected_bulk_action']) ? sanitize_key(wp_unslash($_POST['selected_bulk_action'])) : '';
        if (empty($bulk_action) && isset($_POST['bulk_action'])) {
            $bulk_action = sanitize_key(wp_unslash($_POST['bulk_action']));
        }
        if (empty($bulk_action) && isset($_POST['bulk_action2'])) {
            $bulk_action = sanitize_key(wp_unslash($_POST['bulk_action2']));
        }

        $campaign_ids = isset($_POST['campaign_ids']) ? (array) wp_unslash($_POST['campaign_ids']) : array();
        $campaign_ids = array_values(array_unique(array_filter(array_map('intval', $campaign_ids))));

        if (empty($bulk_action)) {
            wp_safe_redirect($this->get_bulk_action_redirect_url('advnews-campaigns', 'bulk_action_missing'));
            exit;
        }

        if (empty($campaign_ids)) {
            wp_safe_redirect($this->get_bulk_action_redirect_url('advnews-campaigns', 'bulk_campaigns_none'));
            exit;
        }

        $processed = 0;
        switch ($bulk_action) {
            case 'delete':
                $campaign_class = new AdvNews_Campaign();
                foreach ($campaign_ids as $campaign_id) {
                    $result = $campaign_class->delete_campaign($campaign_id);
                    if (!is_wp_error($result) && $result !== false) {
                        $processed++;
                    }
                }
                $message = 'bulk_campaigns_deleted';
                break;

            default:
                wp_safe_redirect($this->get_bulk_action_redirect_url('advnews-campaigns', 'bulk_action_missing'));
                exit;
        }

        wp_safe_redirect($this->get_bulk_action_redirect_url('advnews-campaigns', $message, $processed));
        exit;
    }

    /**
     * Delete a template and unlink campaigns that reference it.
     */
    private function delete_template_by_id($template_id)
    {
        $template_id = intval($template_id);
        if (!$template_id) {
            return false;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_prefix . 'templates';
        $campaigns_table = $wpdb->prefix . $this->table_prefix . 'campaigns';
        $rel_table = $wpdb->prefix . $this->table_prefix . 'template_categories';

        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE id = %d", $template_id));
        if (!$exists) {
            return false;
        }

        $wpdb->update($campaigns_table, array('template_id' => null), array('template_id' => $template_id));
        $wpdb->delete($rel_table, array('template_id' => $template_id));

        $result = $wpdb->delete($table_name, array('id' => $template_id));

        return $result !== false;
    }

    /**
     * Handle template list bulk actions.
     */
    public function handle_bulk_templates()
    {
        check_admin_referer('advnews_bulk_templates');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'advnews-manager'));
        }

        $bulk_action = isset($_POST['selected_bulk_action']) ? sanitize_key(wp_unslash($_POST['selected_bulk_action'])) : '';
        if (empty($bulk_action) && isset($_POST['bulk_action'])) {
            $bulk_action = sanitize_key(wp_unslash($_POST['bulk_action']));
        }
        if (empty($bulk_action) && isset($_POST['bulk_action2'])) {
            $bulk_action = sanitize_key(wp_unslash($_POST['bulk_action2']));
        }

        $template_ids = isset($_POST['template_ids']) ? (array) wp_unslash($_POST['template_ids']) : array();
        $template_ids = array_values(array_unique(array_filter(array_map('intval', $template_ids))));

        if (empty($bulk_action)) {
            wp_safe_redirect($this->get_bulk_action_redirect_url('advnews-templates', 'bulk_action_missing'));
            exit;
        }

        if (empty($template_ids)) {
            wp_safe_redirect($this->get_bulk_action_redirect_url('advnews-templates', 'bulk_templates_none'));
            exit;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_prefix . 'templates';
        $processed = 0;

        switch ($bulk_action) {
            case 'delete':
                foreach ($template_ids as $template_id) {
                    if ($this->delete_template_by_id($template_id)) {
                        $processed++;
                    }
                }
                $message = 'bulk_templates_deleted';
                break;

            case 'activate':
            case 'deactivate':
                $is_active = $bulk_action === 'activate' ? 1 : 0;
                foreach ($template_ids as $template_id) {
                    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE id = %d", $template_id));
                    if ($exists) {
                        $wpdb->update($table_name, array('is_active' => $is_active), array('id' => $template_id));
                        $processed++;
                    }
                }
                $message = $bulk_action === 'activate' ? 'bulk_templates_activated' : 'bulk_templates_deactivated';
                break;

            default:
                wp_safe_redirect($this->get_bulk_action_redirect_url('advnews-templates', 'bulk_action_missing'));
                exit;
        }

        wp_safe_redirect($this->get_bulk_action_redirect_url('advnews-templates', $message, $processed));
        exit;
    }

    /**
     * Handle delete template
     */
    public function handle_delete_template()
    {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'advnews_delete_template')) {
            wp_die(__('Security check failed.', 'advnews-manager'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'advnews-manager'));
        }

        $template_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$template_id) {
            wp_die(__('Invalid template ID.', 'advnews-manager'));
        }

        $result = $this->delete_template_by_id($template_id);
        if ($result === false) {
            wp_die(__('Failed to delete template.', 'advnews-manager'));
        }

        wp_redirect(add_query_arg(array(
            'page' => 'advnews-templates',
            'message' => 'template_deleted'
        ), admin_url('admin.php')));
        exit;
    }

    /**
     * Handle duplicate template
     */
    public function handle_duplicate_template()
    {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'advnews_duplicate_template')) {
            wp_die(__('Security check failed.', 'advnews-manager'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'advnews-manager'));
        }

        $template_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$template_id) {
            wp_die(__('Invalid template ID.', 'advnews-manager'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_prefix . 'templates';
        $rel_table = $wpdb->prefix . $this->table_prefix . 'template_categories';

        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $template_id
        ));

        if (!$template) {
            wp_die(__('Template not found.', 'advnews-manager'));
        }

        $result = $wpdb->insert($table_name, array(
            'name' => $template->name . ' - ' . __('Copy', 'advnews-manager'),
            'subject' => $template->subject,
            'content' => $template->content,
            'css' => $template->css,
            'category_id' => $template->category_id,
            'thumbnail' => $template->thumbnail,
            'is_responsive' => $template->is_responsive,
            'is_active' => $template->is_active,
            'usage_count' => 0
        ));

        if (!$result) {
            wp_die(__('Failed to duplicate template.', 'advnews-manager'));
        }

        $new_template_id = $wpdb->insert_id;
        $category_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT category_id FROM $rel_table WHERE template_id = %d",
            $template_id
        ));

        foreach ($category_ids as $cat_id) {
            $wpdb->insert($rel_table, array(
                'template_id' => $new_template_id,
                'category_id' => intval($cat_id)
            ));
        }

        wp_redirect(add_query_arg(array(
            'page' => 'advnews-templates',
            'action' => 'edit',
            'id' => $new_template_id,
            'message' => 'template_duplicated'
        ), admin_url('admin.php')));
        exit;
    }

    // ===== RENDER DASHBOARD PAGE =====
    /**
     * Render dashboard page
     */
    public function render_dashboard()
    {
        AdvNews_Security::check_capability();
        $stats = $this->get_dashboard_stats();
        ?>
        <div class="wrap advnews-dashboard">
            <h1><?php _e('AdvNews Manager Dashboard', 'advnews-manager'); ?></h1>
            <div class="advnews-stats-grid">
                <div class="advnews-stat-card">
                    <h3><?php _e('Total Subscribers', 'advnews-manager'); ?></h3>
                    <div class="stat-number"><?php echo esc_html(number_format($stats['total_subscribers'])); ?></div>
                    <div class="stat-trend <?php echo $stats['subscriber_growth'] >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo $stats['subscriber_growth'] >= 0 ? '+' : ''; ?>
                        <?php echo esc_html($stats['subscriber_growth']); ?>% <?php _e('vs last week', 'advnews-manager'); ?>
                    </div>
                </div>
                <div class="advnews-stat-card">
                    <h3><?php _e('Active Campaigns', 'advnews-manager'); ?></h3>
                    <div class="stat-number"><?php echo esc_html($stats['active_campaigns']); ?></div>
                    <div class="stat-detail">
                        <?php echo esc_html($stats['queue_status']['queued']); ?> <?php _e('queued', 'advnews-manager'); ?>
                    </div>
                </div>
                <div class="advnews-stat-card">
                    <h3><?php _e('Emails Sent Today', 'advnews-manager'); ?></h3>
                    <div class="stat-number"><?php echo esc_html(number_format($stats['emails_sent_today'])); ?></div>
                </div>
                <div class="advnews-stat-card">
                    <h3><?php _e('Average Open Rate', 'advnews-manager'); ?></h3>
                    <div class="stat-number"><?php echo esc_html($stats['avg_open_rate']); ?>%</div>
                    <div class="stat-detail">
                        <?php _e('Industry avg: 20%', 'advnews-manager'); ?>
                    </div>
                </div>
                <div class="advnews-stat-card">
                    <h3><?php _e('Average Click Rate', 'advnews-manager'); ?></h3>
                    <div class="stat-number"><?php echo esc_html($stats['avg_click_rate']); ?>%</div>
                    <div class="stat-detail">
                        <?php _e('Industry avg: 2.5%', 'advnews-manager'); ?>
                    </div>
                </div>
            </div>
            <div class="advnews-dashboard-row">
                <div class="advnews-dashboard-column">
                    <div class="postbox">
                        <h2 class="hndle"><?php _e('Recent Activity', 'advnews-manager'); ?></h2>
                        <div class="inside">
                            <?php if (empty($stats['recent_activity'])): ?>
                                <p><?php _e('No recent activity.', 'advnews-manager'); ?></p>
                            <?php else: ?>
                                <ul class="advnews-activity-list">
                                    <?php foreach ($stats['recent_activity'] as $activity): ?>
                                        <li class="activity-<?php echo esc_attr($activity['type']); ?>">
                                            <span class="activity-date">
                                                <?php echo esc_html(human_time_diff(strtotime($activity['date']), current_time('timestamp')) . ' ' . __('ago', 'advnews-manager')); ?>
                                            </span>
                                            <span class="activity-text">
                                                <?php echo esc_html($activity['activity']); ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="advnews-dashboard-column">
                    <div class="postbox">
                        <h2 class="hndle"><?php _e('Queue Status', 'advnews-manager'); ?></h2>
                        <div class="inside">
                            <div class="advnews-queue-stats">
                                <div class="queue-stat">
                                    <span class="stat-label"><?php _e('Queued:', 'advnews-manager'); ?></span>
                                    <span class="stat-value"><?php echo esc_html($stats['queue_status']['queued']); ?></span>
                                </div>
                                <div class="queue-stat">
                                    <span class="stat-label"><?php _e('Sending:', 'advnews-manager'); ?></span>
                                    <span class="stat-value"><?php echo esc_html($stats['queue_status']['sending']); ?></span>
                                </div>
                                <div class="queue-stat">
                                    <span class="stat-label"><?php _e('Failed:', 'advnews-manager'); ?></span>
                                    <span class="stat-value"><?php echo esc_html($stats['queue_status']['failed']); ?></span>
                                </div>
                                <div class="queue-stat">
                                    <span class="stat-label"><?php _e('Delivered:', 'advnews-manager'); ?></span>
                                    <span class="stat-value"><?php echo esc_html($stats['queue_status']['delivered']); ?></span>
                                </div>
                            </div>
                            <div class="advnews-queue-actions">
                                <button type="button" id="advnews-refresh-queue" class="button button-small">
                                    <?php _e('Refresh', 'advnews-manager'); ?>
                                </button>
                                <button type="button" id="advnews-clear-stuck" class="button button-small">
                                    <?php _e('Clear Stuck', 'advnews-manager'); ?>
                                </button>
                                <button type="button" id="advnews-retry-failed" class="button button-small">
                                    <?php _e('Retry Failed', 'advnews-manager'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="postbox">
                        <h2 class="hndle"><?php _e('System Status', 'advnews-manager'); ?></h2>
                        <div class="inside">
                            <table class="widefat">
                                <tbody>
                                <tr>
                                    <td><?php _e('Cron Job Status', 'advnews-manager'); ?></td>
                                    <td>
                                        <?php if (wp_next_scheduled('advnews_process_queue')): ?>
                                            <span class="status-ok"><?php _e('Running', 'advnews-manager'); ?></span>
                                        <?php else: ?>
                                            <span class="status-error"><?php _e('Not Running', 'advnews-manager'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><?php _e('Database Health', 'advnews-manager'); ?></td>
                                    <td>
                                        <?php if ($this->wpdb->get_var("SHOW TABLES LIKE '{$this->wpdb->prefix}{$this->table_prefix}subscribers'")): ?>
                                            <span class="status-ok"><?php _e('OK', 'advnews-manager'); ?></span>
                                        <?php else: ?>
                                            <span class="status-error"><?php _e('Issues Found', 'advnews-manager'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><?php _e('Last Backup', 'advnews-manager'); ?></td>
                                    <td><?php echo esc_html($stats['last_backup']); ?></td>
                                </tr>
                                <tr>
                                    <td><?php _e('Plugin Version', 'advnews-manager'); ?></td>
                                    <td><?php echo esc_html(ADVNEWS_VERSION); ?></td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="advnews-quick-actions">
                <h3><?php _e('Quick Actions', 'advnews-manager'); ?></h3>
                <div class="quick-actions-grid">
                    <a href="<?php echo admin_url('admin.php?page=advnews-campaigns&action=add'); ?>" class="quick-action-card">
                        <span class="dashicons dashicons-email-alt"></span>
                        <span class="action-label"><?php _e('Create Campaign', 'advnews-manager'); ?></span>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=advnews-subscribers&action=add'); ?>" class="quick-action-card">
                        <span class="dashicons dashicons-admin-users"></span>
                        <span class="action-label"><?php _e('Add Subscriber', 'advnews-manager'); ?></span>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=advnews-templates&action=add'); ?>" class="quick-action-card">
                        <span class="dashicons dashicons-layout"></span>
                        <span class="action-label"><?php _e('Create Template', 'advnews-manager'); ?></span>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=advnews-analytics'); ?>" class="quick-action-card">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <span class="action-label"><?php _e('View Reports', 'advnews-manager'); ?></span>
                    </a>
                </div>
            </div>
            <?php if (get_option('advnews_show_credit_link')): ?>
                <div class="advnews-credit">
                    <p>
                        <?php printf(
                            __('Powered by %sAdvNews Manager%s - Professional Newsletter System', 'advnews-manager'),
                            '<a href="https://example.com/advnews-manager" target="_blank">',
                            '</a>'
                        ); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <script>
            jQuery(document).ready(function($) {
                // Queue actions
                $('#advnews-refresh-queue').on('click', function() {
                    location.reload();
                });
                $('#advnews-clear-stuck').on('click', function() {
                    if (confirm('<?php _e('Are you sure you want to clear stuck emails?', 'advnews-manager'); ?>')) {
                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'advnews_clear_stuck_queue',
                                nonce: advnews_ajax.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert(response.data.message);
                                    location.reload();
                                } else {
                                    alert(response.data.message);
                                }
                            },
                            error: function() {
                                alert(advnews_ajax.i18n.error);
                            }
                        });
                    }
                });
                $('#advnews-retry-failed').on('click', function() {
                    if (confirm('<?php _e('Are you sure you want to retry all failed emails?', 'advnews-manager'); ?>')) {
                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'advnews_retry_failed_queue',
                                nonce: advnews_ajax.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert(response.data.message);
                                    location.reload();
                                } else {
                                    alert(response.data.message);
                                }
                            },
                            error: function() {
                                alert(advnews_ajax.i18n.error);
                            }
                        });
                    }
                });
            });
        </script>
        <style>
            .advnews-dashboard-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin: 20px 0;
            }
            .advnews-activity-list {
                margin: 0;
                padding: 0;
                list-style: none;
            }
            .advnews-activity-list li {
                padding: 10px 0;
                border-bottom: 1px solid #f0f0f0;
            }
            .advnews-activity-list li:last-child {
                border-bottom: none;
            }
            .activity-date {
                display: block;
                font-size: 11px;
                color: #999;
                margin-bottom: 3px;
            }
            .advnews-queue-stats {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 10px;
                margin-bottom: 15px;
            }
            .queue-stat {
                background: #f8f9fa;
                padding: 8px;
                border-radius: 4px;
                text-align: center;
            }
            .queue-stat .stat-label {
                display: block;
                font-size: 11px;
                color: #666;
            }
            .queue-stat .stat-value {
                display: block;
                font-size: 16px;
                font-weight: 600;
            }
            .advnews-queue-actions {
                display: flex;
                gap: 5px;
                justify-content: flex-end;
            }
            .quick-actions-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
                margin-top: 15px;
            }
            .quick-action-card {
                background: #f8f9fa;
                border: 1px solid #e9ecef;
                border-radius: 4px;
                padding: 20px;
                text-align: center;
                text-decoration: none;
                color: #1d2327;
                transition: all 0.3s;
            }
            .quick-action-card:hover {
                background: #fff;
                border-color: #2271b1;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }
            .quick-action-card .dashicons {
                font-size: 30px;
                width: 30px;
                height: 30px;
                color: #2271b1;
                margin-bottom: 10px;
            }
            .action-label {
                display: block;
                font-size: 14px;
                font-weight: 500;
            }
            .advnews-credit {
                margin-top: 30px;
                padding: 15px;
                background: #fff;
                border: 1px solid #e9ecef;
                border-radius: 4px;
                text-align: center;
                font-size: 12px;
                color: #666;
            }
            .status-ok {
                color: #00a32a;
                font-weight: 600;
            }
            .status-error {
                color: #d63638;
                font-weight: 600;
            }
            @media (max-width: 1200px) {
                .advnews-dashboard-row {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <?php
    }

    /**
     * Render campaigns page
     */
    public function render_campaigns()
    {
        AdvNews_Security::check_capability();
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $campaign_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        switch ($action) {
            case 'edit':
            case 'add':
                include ADVNEWS_PLUGIN_DIR . 'admin/partials/campaigns-editor.php';
                break;
            case 'view':
                include ADVNEWS_PLUGIN_DIR . 'admin/partials/campaigns-view.php';
                break;
            default:
                include ADVNEWS_PLUGIN_DIR . 'admin/partials/campaigns-list.php';
                break;
        }
    }

    /**
     * Render subscribers page
     */
    public function render_subscribers()
    {
        AdvNews_Security::check_capability();
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $subscriber_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        switch ($action) {
            case 'add':
            case 'edit':
                include ADVNEWS_PLUGIN_DIR . 'admin/partials/subscribers-editor.php';
                break;
            case 'view':
                // FIXED: Check if file exists before including
                if (file_exists(ADVNEWS_PLUGIN_DIR . 'admin/partials/subscribers-view.php')) {
                    include ADVNEWS_PLUGIN_DIR . 'admin/partials/subscribers-view.php';
                } else {
                    // Fallback to editor in read-only mode if view file doesn't exist
                    include ADVNEWS_PLUGIN_DIR . 'admin/partials/subscribers-editor.php';
                }
                break;
            case 'import':
                include ADVNEWS_PLUGIN_DIR . 'admin/partials/subscribers-import.php';
                break;
            case 'export':
                include ADVNEWS_PLUGIN_DIR . 'admin/partials/subscribers-export.php';
                break;
            default:
                include ADVNEWS_PLUGIN_DIR . 'admin/partials/subscribers-list.php';
                break;
        }
    }

    /**
     * Render templates page
     */
    public function render_templates()
    {
        AdvNews_Security::check_capability();
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $template_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        switch ($action) {
            case 'add':
            case 'edit':
                include ADVNEWS_PLUGIN_DIR . 'admin/partials/templates-editor.php';
                break;
            case 'preview':
                include ADVNEWS_PLUGIN_DIR . 'admin/partials/templates-preview.php';
                break;
            default:
                include ADVNEWS_PLUGIN_DIR . 'admin/partials/templates-list.php';
                break;
        }
    }

    /**
     * Render analytics page
     */
    public function render_analytics()
    {
        AdvNews_Security::check_capability();
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'overview';
        $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;

        switch ($action) {
            case 'campaign':
                include ADVNEWS_PLUGIN_DIR . 'admin/partials/analytics-campaign.php';
                break;
            case 'system':
                include ADVNEWS_PLUGIN_DIR . 'admin/partials/analytics-system.php';
                break;
            default:
                include ADVNEWS_PLUGIN_DIR . 'admin/partials/analytics-overview.php';
                break;
        }
    }

    /**
     * NEW: Render Email Logs page
     */
    public function render_email_logs()
    {
        AdvNews_Security::check_capability();
        include ADVNEWS_PLUGIN_DIR . 'admin/partials/email-logs-list.php';
    }

    /**
     * Render settings page
     */
    public function render_settings()
    {
        AdvNews_Security::check_capability();
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        ?>
        <div class="wrap">
            <h1><?php _e('AdvNews Manager Settings', 'advnews-manager'); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=advnews-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('General', 'advnews-manager'); ?>
                </a>
                <a href="?page=advnews-settings&tab=smtp" class="nav-tab <?php echo $active_tab == 'smtp' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('SMTP', 'advnews-manager'); ?>
                </a>
                <a href="?page=advnews-settings&tab=cron" class="nav-tab <?php echo $active_tab == 'cron' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Cron & Scheduling', 'advnews-manager'); ?>
                </a>
                <a href="?page=advnews-settings&tab=tracking" class="nav-tab <?php echo $active_tab == 'tracking' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Tracking', 'advnews-manager'); ?>
                </a>
                <a href="?page=advnews-settings&tab=subscribers" class="nav-tab <?php echo $active_tab == 'subscribers' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Subscribers', 'advnews-manager'); ?>
                </a>
                <a href="?page=advnews-settings&tab=gdpr" class="nav-tab <?php echo $active_tab == 'gdpr' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('GDPR', 'advnews-manager'); ?>
                </a>
            </h2>
            <form method="post" action="options.php" class="advnews-settings-form">
                <?php
                switch ($active_tab) {
                    case 'general':
                        settings_fields('advnews_general_settings');
                        do_settings_sections('advnews_general_settings');
                        break;
                    case 'smtp':
                        settings_fields('advnews_smtp_settings');
                        do_settings_sections('advnews_smtp_settings');
                        break;
                    case 'cron':
                        settings_fields('advnews_cron_settings');
                        do_settings_sections('advnews_cron_settings');
                        break;
                    case 'tracking':
                        settings_fields('advnews_tracking_settings');
                        do_settings_sections('advnews_tracking_settings');
                        break;
                    case 'subscribers':
                        settings_fields('advnews_subscriber_settings');
                        do_settings_sections('advnews_subscriber_settings');
                        break;
                    case 'gdpr':
                        settings_fields('advnews_gdpr_settings');
                        do_settings_sections('advnews_gdpr_settings');
                        break;
                }
                submit_button();
                ?>
            </form>
        </div>
        <script>
        jQuery(document).ready(function($) {
            console.log('[AdvNews] Settings JS Initialized');

            // Explicitly define AJAX URL and Nonce (prevents ajaxurl undefined issues)
            var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
            var securityNonce = '<?php echo wp_create_nonce('advnews_ajax_nonce'); ?>';

            // Test SMTP connection
            $('#advnews_test_smtp').on('click', function() {
                var testEmail = $('#advnews_test_email').val();
                if (!testEmail) {
                    alert('<?php _e('Please enter a test email address.', 'advnews-manager'); ?>');
                    return;
                }
                var button = $(this);
                var spinner = $('#test-spinner');
                var resultDiv = $('#test-result');
                button.prop('disabled', true);
                spinner.addClass('is-active');
                resultDiv.hide();
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'advnews_test_smtp',
                        test_email: testEmail,
                        _wpnonce: securityNonce,
                        nonce: securityNonce
                    },
                    success: function(response) {
                        console.log('[AdvNews] SMTP Test Response:', response);
                        if (response.success) {
                            resultDiv.removeClass('error').addClass('updated')
                                .html('<p><strong><?php _e('Success!', 'advnews-manager'); ?></strong> ' + response.data.message + '</p>').show();
                        } else {
                            resultDiv.removeClass('updated').addClass('error')
                                .html('<p><strong><?php _e('Error!', 'advnews-manager'); ?></strong> ' + response.data.message + '</p>').show();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('[AdvNews] SMTP Test AJAX Error:', xhr.responseText);
                        resultDiv.removeClass('updated').addClass('error')
                            .html('<p><strong><?php _e('Connection Failed', 'advnews-manager'); ?></strong></p>').show();
                    },
                    complete: function() {
                        button.prop('disabled', false);
                        spinner.removeClass('is-active');
                    }
                });
            });

            // Process Queue Now
            $('#advnews_process_queue_now').on('click', function(e) {
                e.preventDefault();
                console.log('[AdvNews] Process Queue button clicked');
                var button = $(this);
                var originalText = button.text();

                button.prop('disabled', true).text('Processing...');

                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'advnews_process_queue_now',
                        _wpnonce: securityNonce,
                        nonce: securityNonce
                    },
                    success: function(response) {
                        console.log('[AdvNews] Process Queue Response:', response);
                        if (response.success) {
                            var message = response.data.message || 'Queue processed successfully.';
                            if (response.data.data && parseInt(response.data.data.on_cooldown, 10) > 0) {
                                message += '\n' + response.data.data.on_cooldown + ' queued email(s) are still waiting for cooldown.';
                            }
                            alert(message);
                            location.reload();
                        } else {
                            alert('Server Error: ' + (response.data.message || 'Unknown error.'));
                            button.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('[AdvNews] Process Queue AJAX Error:', status, error);
                        console.error('[AdvNews] Server Response:', xhr.responseText);
                        alert('AJAX Connection Failed. Check browser console (F12) for details.');
                        button.prop('disabled', false).text(originalText);
                    }
                });
            });

            // Clear cooldown delays
            $('#advnews_clear_cooldown_delays').on('click', function(e) {
                e.preventDefault();
                console.log('[AdvNews] Clear Cooldown button clicked');

                if (!confirm('This will remove cooldown delays from queued emails and allow them to send on the next queue run. Continue?')) {
                    return;
                }

                var button = $(this);
                var originalText = button.text();
                button.prop('disabled', true).text('Clearing...');

                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'advnews_clear_cooldown_delays',
                        _wpnonce: securityNonce,
                        nonce: securityNonce
                    },
                    success: function(response) {
                        console.log('[AdvNews] Clear Cooldown Response:', response);
                        if (response.success) {
                            alert(response.data.message || 'Cooldown delays cleared successfully.');
                            location.reload();
                        } else {
                            alert('Server Error: ' + (response.data.message || 'Unknown error.'));
                            button.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('[AdvNews] Clear Cooldown AJAX Error:', status, error);
                        console.error('[AdvNews] Server Response:', xhr.responseText);
                        alert('AJAX Connection Failed. Check browser console (F12) for details.');
                        button.prop('disabled', false).text(originalText);
                    }
                });
            });

            // Clear Stuck Queue
            $('#advnews_clear_stuck_queue').on('click', function(e) {
                e.preventDefault();
                console.log('[AdvNews] Clear Stuck button clicked');

                if (!confirm('Are you sure you want to clear stuck emails from the queue?')) {
                    return;
                }

                var button = $(this);
                var originalText = button.text();
                button.prop('disabled', true).text('Clearing...');

                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'advnews_clear_stuck_queue',
                        _wpnonce: securityNonce,
                        nonce: securityNonce
                    },
                    success: function(response) {
                        console.log('[AdvNews] Clear Stuck Response:', response);
                        if (response.success) {
                            alert(response.data.message || 'Stuck emails cleared successfully.');
                            location.reload();
                        } else {
                            alert('Server Error: ' + (response.data.message || 'Unknown error.'));
                            button.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('[AdvNews] Clear Stuck AJAX Error:', status, error);
                        console.error('[AdvNews] Server Response:', xhr.responseText);
                        alert('AJAX Connection Failed. Check browser console (F12) for details.');
                        button.prop('disabled', false).text(originalText);
                    }
                });
            });

            // Clear tracking data
            $('#advnews_clear_tracking_data').on('click', function() {
                if (confirm('<?php _e('Clear all tracking data older than retention period?', 'advnews-manager'); ?>')) {
                    $.ajax({
                        url: ajaxUrl, type: 'POST',
                        data: { action: 'advnews_clear_tracking_data', nonce: securityNonce },
                        success: function(response) { if(response.success) { alert(response.data.message); location.reload(); } },
                        error: function() { alert('<?php _e('An error occurred.', 'advnews-manager'); ?>'); }
                    });
                }
            });

            // Test subscription
            $('#advnews_test_subscription').on('click', function() {
                var email = prompt('<?php _e('Enter test email address:', 'advnews-manager'); ?>', '<?php echo esc_js(get_option('admin_email')); ?>');
                if (email) {
                    $.ajax({
                        url: ajaxUrl, type: 'POST',
                        data: { action: 'advnews_test_subscription', email: email, nonce: securityNonce },
                        success: function(response) { alert(response.success ? response.data.message : response.data.message); },
                        error: function() { alert('<?php _e('An error occurred.', 'advnews-manager'); ?>'); }
                    });
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Render subscriber import
     */
    public function render_subscriber_import()
    {
        include ADVNEWS_PLUGIN_DIR . 'admin/partials/subscribers-import.php';
    }

    /**
     * Render subscriber export
     */
    public function render_subscriber_export()
    {
        include ADVNEWS_PLUGIN_DIR . 'admin/partials/subscribers-export.php';
    }

    /**
     * Handle save campaign
     */
    public function handle_save_campaign()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AdvNews: handle_save_campaign started');
        }
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'advnews_save_campaign')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AdvNews: Nonce verification failed');
            }
            wp_die(__('Security check failed.', 'advnews-manager'));
        }
        if (!current_user_can('manage_options')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AdvNews: Permission check failed');
            }
            wp_die(__('You do not have sufficient permissions.', 'advnews-manager'));
        }
        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AdvNews: Campaign ID: ' . $campaign_id);
        }

        // FIXED: Use custom email sanitizer instead of wp_kses_post to preserve styles/tables
        $content_raw = isset($_POST['content']) ? $_POST['content'] : '';
        $sanitized_content = $this->sanitize_email_html($content_raw);

        // Handle Multiple Categories
        $category_ids = array();
        if (isset($_POST['category_ids']) && is_array($_POST['category_ids'])) {
            $category_ids = array_map('intval', $_POST['category_ids']);
        }

        // Fallback for legacy single category_id if present (for backward compatibility during transition)
        if (empty($category_ids) && isset($_POST['category_id']) && intval($_POST['category_id']) > 0) {
            $category_ids[] = intval($_POST['category_id']);
        }

        $campaign_class = new AdvNews_Campaign();
        $existing_campaign = $campaign_id ? $campaign_class->get_campaign($campaign_id) : null;

        // Prepare data for Campaign Class (which handles junction table)
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'subject' => sanitize_text_field($_POST['subject']),
            'category_ids' => $category_ids, // Pass array to campaign class
            'content' => $sanitized_content,
            'template_id' => isset($_POST['template_id']) && !empty($_POST['template_id']) ? intval($_POST['template_id']) : null,
            'from_name' => isset($_POST['from_name']) && !empty($_POST['from_name']) ? sanitize_text_field($_POST['from_name']) : null,
            'from_email' => isset($_POST['from_email']) && !empty($_POST['from_email']) ? sanitize_email($_POST['from_email']) : null,
            'reply_to' => isset($_POST['reply_to']) && !empty($_POST['reply_to']) ? sanitize_email($_POST['reply_to']) : null,
            'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'draft',
            'priority' => isset($_POST['priority']) ? sanitize_text_field($_POST['priority']) : 'normal',
            'track_opens' => isset($_POST['track_opens']) ? 1 : 0,
            'track_clicks' => isset($_POST['track_clicks']) ? 1 : 0,
            'respect_cooldown' => isset($_POST['respect_cooldown']) ? 1 : 0
        );

        if (isset($_POST['send_now'])) {
            // FORCE IMMEDIATE SEND: Clear future schedule and let send_campaign() handle status
            $data['scheduled_for'] = null;
            $data['status'] = ($existing_campaign && $existing_campaign->status === 'sent') ? 'sent' : 'draft';
        } elseif (isset($_POST['schedule_campaign'])) {
            if (empty($_POST['scheduled_for'])) {
                wp_die(__('Select a date and time before scheduling this campaign.', 'advnews-manager'));
            }
            $raw_time = sanitize_text_field(str_replace('T', ' ', $_POST['scheduled_for']));
            $data['scheduled_for'] = get_gmt_from_date($raw_time);
            $data['status'] = 'scheduled';
        } elseif (!empty($_POST['scheduled_for'])) {
            // Normalize input format (replace T with space) and convert to GMT for storage
            $raw_time = sanitize_text_field(str_replace('T', ' ', $_POST['scheduled_for']));
            $data['scheduled_for'] = get_gmt_from_date($raw_time);
            $data['status'] = 'scheduled';
        } elseif (isset($_POST['status'])) {
            $data['status'] = sanitize_text_field($_POST['status']);
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AdvNews: Prepared data: ' . print_r($data, true));
        }

        if ($campaign_id) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AdvNews: Updating campaign ' . $campaign_id);
            }
            $result = $campaign_class->update_campaign($campaign_id, $data);
            $message = isset($_POST['schedule_campaign']) ? 'campaign_scheduled' : 'campaign_updated';
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AdvNews: Creating new campaign');
            }
            $result = $campaign_class->create_campaign($data);
            $message = isset($_POST['schedule_campaign']) ? 'campaign_scheduled' : 'campaign_saved';
        }

        if (is_wp_error($result)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AdvNews: Error saving campaign: ' . $result->get_error_message());
            }
            wp_die($result->get_error_message());
        }

        $campaign_id = $campaign_id ?: $result;
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AdvNews: Campaign saved with ID: ' . $campaign_id);
        }

        if (isset($_POST['send_now'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AdvNews: Sending campaign now');
            }
            $send_result = $campaign_class->send_campaign($campaign_id);
            if (!is_wp_error($send_result)) {
                $message = 'campaign_sent';
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('AdvNews: Send error: ' . $send_result->get_error_message());
                }
            }
        }

        $redirect_url = add_query_arg(array(
            'page' => 'advnews-campaigns',
            'action' => 'edit',
            'id' => $campaign_id,
            'message' => $message
        ), admin_url('admin.php'));

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AdvNews: Redirecting to: ' . $redirect_url);
        }
        wp_redirect($redirect_url);
        exit;
    }

    /**
    * Sanitize HTML specifically for Email Campaigns
    * Allows table attributes, inline styles, and common email tags
    */
    private function sanitize_email_html($html) {
        // Convert Word HTML to email-friendly HTML first
        $html = $this->convert_word_html_to_email_html($html);
        
        // CRITICAL FIX: Wrap consecutive span blocks separated by newlines in <p> tags.
        // This is much more reliable than injecting <br> tags. Email clients natively 
        // respect <p> tags for line spacing, and wp_kses will preserve them perfectly.
        $html = preg_replace('/(<\/span>)[ \t]*[\r\n]+[ \t]*(<span)/i', '$1</p><p>$2', $html);
        
        // Sanitize the HTML
        $allowed_html = array(
            'a' => array('href' => array(), 'title' => array(), 'target' => array(), 'rel' => array(), 'style' => array(), 'class' => array()),
            'br' => array(),
            'em' => array(),
            'strong' => array(),
            'p' => array('style' => array(), 'class' => array(), 'align' => array()),
            'div' => array('style' => array(), 'class' => array(), 'align' => array()),
            'span' => array('style' => array(), 'class' => array(), 'align' => array()),
            'h1' => array('style' => array(), 'align' => array()),
            'h2' => array('style' => array(), 'align' => array()),
            'h3' => array('style' => array(), 'align' => array()),
            'h4' => array('style' => array(), 'align' => array()),
            'h5' => array('style' => array(), 'align' => array()),
            'h6' => array('style' => array(), 'align' => array()),
            'ul' => array('style' => array(), 'class' => array()),
            'ol' => array('style' => array(), 'class' => array()),
            'li' => array('style' => array(), 'class' => array(), 'value' => array()),
            'table' => array('border' => array(), 'cellpadding' => array(), 'cellspacing' => array(), 'width' => array(), 'height' => array(), 'style' => array(), 'class' => array(), 'align' => array(), 'bgcolor' => array()),
            'tr' => array('style' => array(), 'class' => array(), 'align' => array(), 'valign' => array(), 'height' => array(), 'bgcolor' => array()),
            'td' => array('style' => array(), 'class' => array(), 'align' => array(), 'valign' => array(), 'width' => array(), 'height' => array(), 'colspan' => array(), 'rowspan' => array(), 'bgcolor' => array()),
            'th' => array('style' => array(), 'class' => array(), 'align' => array(), 'valign' => array(), 'width' => array(), 'colspan' => array(), 'rowspan' => array(), 'bgcolor' => array()),
            'img' => array('src' => array(), 'alt' => array(), 'width' => array(), 'height' => array(), 'style' => array(), 'class' => array(), 'border' => array()),
            'font' => array('color' => array(), 'size' => array(), 'face' => array()),
            'center' => array()
        );
        
        return wp_kses($html, $allowed_html);
    }   

    /**
     * Convert Word-generated HTML to email-friendly HTML
     */
    private function convert_word_html_to_email_html($html) {
        error_log("the convert_word_html_to_email_html method is triggered \n");
        // Only process if there's Word-specific formatting or typical Word span soup
        /* if (!preg_match('/<o:|<w:|<v:|<m:|class\s*=\s*"[^"]*Mso[^"]*"|<span[^>]*style="[^"]*font-family/i', $html)) {
            error_log('[AdvNews Debug] convert_word_html: SKIPPED (No Word-specific tags or font-family spans found).');
            return $html;
        } */
        error_log('[AdvNews Debug] convert_word_html: Word formatting DETECTED. Processing...');

        // 1. Remove Office-specific XML namespaces
        $html = preg_replace('/<o:[^>]+>/i', '', $html);
        $html = preg_replace('/<\/o:[^>]+>/i', '', $html);
        $html = preg_replace('/<w:[^>]+>/i', '', $html);
        $html = preg_replace('/<\/w:[^>]+>/i', '', $html);
        $html = preg_replace('/<v:[^>]+>/i', '', $html);
        $html = preg_replace('/<\/v:[^>]+>/i', '', $html);
        $html = preg_replace('/<m:[^>]+>/i', '', $html);
        $html = preg_replace('/<\/m:[^>]+>/i', '', $html);

        // 2. Remove Office-specific attributes
        $html = preg_replace('/\s+style\s*=\s*"[^"]*mso-[^"]*"/i', '', $html);
        $html = preg_replace('/\s+class\s*=\s*"[^"]*Mso[^"]*"/i', '', $html);

        // 3. Convert Word-specific paragraph styles to standard CSS
        $html = preg_replace_callback('/<p\s+[^>]*class\s*=\s*"MsoNormal"[^>]*>(.*?)<\/p>/is', function($matches) {
            return '<p style="margin: 0; padding: 0; line-height: 1.6;">' . $matches[1] . '</p>';
        }, $html);

        // 4. Simplify tables
        $html = preg_replace('/<table[^>]*class\s*=\s*"[^"]*Mso[^"]*"[^>]*>/i', '<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse;">', $html);

        // 5. Remove unnecessary divs and spans with Mso classes
        $html = preg_replace('/<div\s+[^>]*class\s*=\s*"[^"]*Mso[^"]*"[^>]*>(.*?)<\/div>/is', '<p style="margin: 0; padding: 0; line-height: 1.6;">$1</p>', $html);
        $html = preg_replace('/<span\s+[^>]*class\s*=\s*"[^"]*Mso[^"]*"[^>]*>(.*?)<\/span>/is', '$1', $html);
        error_log("this is the full template content just one line before the step 6:  ".$html);

        // 6. CRITICAL FIX: Fix "Word Span Soup" and missing line breaks between spans
        // This handles content pasted from Word/LibreOffice where spans are separated
        // by newlines that need to become <br> tags in the email output.

        // DEBUG: Log entry into Step 6
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews Debug] Step 6: Processing span gaps...');
        }

        // A. Flatten redundant nested spans (e.g., <span><span>Text</span></span> -> <span>Text</span>)
        $html = preg_replace('/<span[^>]*>(\s*<span[^>]*>(?:(?!<span).)*<\/span>\s*)<\/span>/is', '$1', $html);

        // B. DEBUG: Examine all gaps between </span> and <span to understand the content
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // Find all gaps between closing and opening span tags
            preg_match_all('/(<\/span>)(.*?)(<span)/is', $html, $gap_matches, PREG_SET_ORDER);
            if (!empty($gap_matches)) {
                foreach ($gap_matches as $index => $match) {
                    $gap_content = $match[2];
                    // Log readable version (truncated for safety)
                    $readable = mb_substr($gap_content, 0, 200);
                    // Log hex version for invisible characters
                    $hex = bin2hex($gap_content);
                    error_log('[AdvNews Debug] Gap ' . $index . ' between spans -> Readable: \'' . $readable . '\' | Hex: ' . $hex);
                }
            } else {
                error_log('[AdvNews Debug] Step 6: No gaps found between spans.');
            }
        }

        // C. Convert newlines between spans to <br> tags
        // This regex matches: </span> followed by optional whitespace, then one or more
        // newlines (with optional surrounding whitespace), then <span
        $html = preg_replace_callback('/(<\/span>)[ \t]*([\r\n]+)[ \t\r\n]*(<span)/i', function($matches) {
            $newlines = $matches[2];
            $count = substr_count($newlines, "\n") + substr_count($newlines, "\r");
            $br_count = ($count > 1) ? 2 : 1; // Insert 1 or 2 <br> tags
            $br_tags = str_repeat('<br>', $br_count);

            // DEBUG: Log each regex match
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews Debug] REGEX MATCHED! Injecting <br> tag. Newline count: ' . $count . ', BR count: ' . $br_count);
            }

            return $matches[1] . $br_tags . $matches[3];
        }, $html);

        // D. DEBUG: Also check for the pattern where </span></p><p><span occurs
        // (paragraph boundaries between spans - these are already handled by <p> tags)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            preg_match_all('/(<\/span>\s*<\/p>\s*<p[^>]*>\s*<span)/is', $html, $para_gaps, PREG_SET_ORDER);
            if (!empty($para_gaps)) {
                error_log('[AdvNews Debug] Step 6: Found ' . count($para_gaps) . ' paragraph-boundary span gaps (handled by <p> tags, no <br> needed).');
            }
        }

        // E. DEBUG: Log the final result of step 6 (snippet around MEDIA CONTACT area for verification)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $media_pos = strpos($html, 'MEDIA CONTACT');
            if ($media_pos !== false) {
                $snippet = substr($html, max(0, $media_pos - 50), 300);
                error_log('[AdvNews Debug] Step 6 result snippet near MEDIA CONTACT: ' . $snippet);
            }
        }
        error_log("this is the full template content just after the step 6:  ".$html);

        // 7-15. (Keep the rest of your existing cleanup steps exactly as they are)
        $html = preg_replace('/style\s*=\s*"position:\s*absolute;[^"]*"/i', 'style="position: static;"', $html);
        $html = preg_replace('/\s+o:.*?="[^"]*"/i', '', $html);
        $html = preg_replace('/\s+w:.*?="[^"]*"/i', '', $html);
        $html = preg_replace('/\s+v:.*?="[^"]*"/i', '', $html);
        $html = preg_replace('/font-size:\s*([\d.]+)pt/i', 'font-size: ${1}em', $html);
        $html = preg_replace('/<!--.*?-->/s', '', $html);
        $html = preg_replace('/<p\s*[^>]*>\s*<\/p>/i', '', $html);
        $html = preg_replace('/<img\s+[^>]*style\s*=\s*"[^"]*mso-[^"]*"[^>]*>/i', '<img style="max-width: 100%; height: auto;">', $html);
        $html = preg_replace('/<ul\s+[^>]*class\s*=\s*"[^"]*Mso[^"]*"[^>]*>/i', '<ul style="margin: 0; padding: 0; list-style: disc; margin-left: 20px;">', $html);
        $html = preg_replace('/<ol\s+[^>]*class\s*=\s*"[^"]*Mso[^"]*"[^>]*>/i', '<ol style="margin: 0; padding: 0; list-style: decimal; margin-left: 20px;">', $html);
        $html = preg_replace('/<li\s+[^>]*class\s*=\s*"[^"]*Mso[^"]*"[^>]*>/i', '<li style="margin-bottom: 10px;">', $html);
        $html = preg_replace('/<html[^>]*>/i', '<html>', $html);
        $html = preg_replace('/<body[^>]*>/i', '<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; line-height: 1.6; color: #333333;">', $html);
        $html = preg_replace('/[ \t]+/', ' ', $html);

        return $html;
    }

    /**
     * Handle export subscribers
     */
    public function handle_export_subscribers()
    {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'advnews_export_subscribers')) {
            wp_die(__('Security check failed.', 'advnews-manager'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'advnews-manager'));
        }

        $args = array();
        if (isset($_POST['status']) && !empty($_POST['status'])) {
            $args['status'] = sanitize_text_field($_POST['status']);
        }
        if (isset($_POST['category_ids']) && is_array($_POST['category_ids'])) {
            $category_ids = array_values(array_unique(array_filter(array_map('intval', $_POST['category_ids']))));
            if (!empty($category_ids)) {
                $args['category_ids'] = $category_ids;
            }
        } elseif (isset($_POST['category_id']) && !empty($_POST['category_id'])) {
            $args['category_id'] = intval($_POST['category_id']);
        }
        if (isset($_POST['search']) && !empty($_POST['search'])) {
            $args['search'] = sanitize_text_field($_POST['search']);
        }
        if (isset($_POST['date_from']) && !empty($_POST['date_from'])) {
            $args['date_from'] = sanitize_text_field($_POST['date_from']);
        }
        if (isset($_POST['date_to']) && !empty($_POST['date_to'])) {
            $args['date_to'] = sanitize_text_field($_POST['date_to']);
        }
        $args['limit'] = 0;
        $args['offset'] = 0;

        $fields = isset($_POST['fields']) && is_array($_POST['fields']) ? $_POST['fields'] : array('email', 'first_name', 'last_name', 'organization', 'title', 'website_url', 'description', 'country');
        $fields = array_map('sanitize_text_field', $fields);
        if (!in_array('email', $fields, true)) {
            array_unshift($fields, 'email');
        }
        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'csv';
        if ($format !== 'csv') {
            $format = 'csv';
        }
        $filename = isset($_POST['filename']) ? sanitize_file_name($_POST['filename']) : '';
        if (empty($filename)) {
            $filename = 'subscribers-export-' . date('Y-m-d-H-i-s');
        }

        switch ($format) {
            case 'json':
                $filename .= '.json';
                break;
            case 'excel':
                $filename .= '.xlsx';
                break;
            case 'csv':
            default:
                $filename .= '.csv';
                break;
        }

        $subscriber_class = new AdvNews_Subscriber();
        $subscribers = $subscriber_class->get_all_subscribers($args);
        if (empty($subscribers)) {
            wp_die(__('No subscribers found matching your criteria.', 'advnews-manager'));
        }

        if (isset($_POST['schedule_export']) && $_POST['schedule_export'] == '1') {
            $this->schedule_export($args, $fields, $format, $filename, $_POST);
            return;
        }

        $this->generate_export_file($subscribers, $fields, $format, $filename);
    }

    /**
     * Generate export file for download
     */
    private function generate_export_file($subscribers, $fields, $format, $filename, $return_content = false)
    {
        if ($return_content) {
            ob_start();
        } else {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename);
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        $headers = array();
        foreach ($fields as $field) {
            switch ($field) {
                case 'email':
                    $headers[] = __('Email', 'advnews-manager');
                    break;
                case 'first_name':
                    $headers[] = __('First Name', 'advnews-manager');
                    break;
                case 'last_name':
                    $headers[] = __('Last Name', 'advnews-manager');
                    break;
                case 'organization':
                    $headers[] = __('Organization', 'advnews-manager');
                    break;
                case 'title':
                    $headers[] = __('Title/Role', 'advnews-manager');
                    break;
                case 'website_url':
                    $headers[] = __('URL/Website', 'advnews-manager');
                    break;
                case 'description':
                    $headers[] = __('Description', 'advnews-manager');
                    break;
                case 'country':
                    $headers[] = __('Country', 'advnews-manager');
                    break;
                case 'categories':
                    $headers[] = __('Categories', 'advnews-manager');
                    break;
                case 'status':
                    $headers[] = __('Status', 'advnews-manager');
                    break;
                case 'subscribed_date':
                    $headers[] = __('Subscribed Date', 'advnews-manager');
                    break;
                case 'open_rate':
                    $headers[] = __('Open Rate', 'advnews-manager');
                    break;
                case 'click_rate':
                    $headers[] = __('Click Rate', 'advnews-manager');
                    break;
                default:
                    $headers[] = ucfirst(str_replace('_', ' ', $field));
            }
        }
        fputcsv($output, $headers);

        foreach ($subscribers as $subscriber) {
            $row = array();
            $subscriber_class = new AdvNews_Subscriber();
            foreach ($fields as $field) {
                switch ($field) {
                    case 'email':
                        $row[] = $subscriber->email;
                        break;
                    case 'first_name':
                        $row[] = $subscriber->first_name;
                        break;
                    case 'last_name':
                        $row[] = $subscriber->last_name;
                        break;
                    case 'organization':
                        $row[] = $subscriber->organization;
                        break;
                    case 'title':
                        $row[] = isset($subscriber->title) ? $subscriber->title : '';
                        break;
                    case 'website_url':
                        $row[] = isset($subscriber->website_url) ? $subscriber->website_url : '';
                        break;
                    case 'description':
                        $row[] = isset($subscriber->description) ? $subscriber->description : '';
                        break;
                    case 'country':
                        $row[] = isset($subscriber->country) ? $subscriber->country : '';
                        break;
                    case 'categories':
                        $categories = $subscriber_class->get_subscriber_categories($subscriber->id);
                        $category_names = array();
                        foreach ($categories as $category) {
                            $category_names[] = $category->name;
                        }
                        $row[] = implode(', ', $category_names);
                        break;
                    case 'status':
                        $row[] = $subscriber->status;
                        break;
                    case 'subscribed_date':
                        $row[] = $subscriber->subscribed_at;
                        break;
                    case 'open_rate':
                        $row[] = $subscriber->open_rate . '%';
                        break;
                    case 'click_rate':
                        $row[] = $subscriber->click_rate . '%';
                        break;
                    default:
                        $row[] = isset($subscriber->$field) ? $subscriber->$field : '';
                }
            }
            fputcsv($output, $row);
        }

        fclose($output);
        if ($return_content) {
            return ob_get_clean();
        }
        exit;
    }

    /**
     * Schedule recurring export
     */
    private function schedule_export($args, $fields, $format, $filename, $post_data)
    {
        $schedule = isset($post_data['schedule_frequency']) ? sanitize_text_field($post_data['schedule_frequency']) : 'weekly';
        $email = isset($post_data['schedule_email']) ? sanitize_email($post_data['schedule_email']) : get_option('admin_email');
        if (!is_email($email)) {
            wp_die(__('Invalid email address for scheduled exports.', 'advnews-manager'));
        }

        $schedule_data = array(
            'id' => uniqid('export_'),
            'args' => $args,
            'fields' => $fields,
            'format' => $format,
            'filename' => $filename,
            'email' => $email,
            'next_run' => current_time('mysql'),
            'frequency' => $schedule,
            'created_at' => current_time('mysql')
        );

        $scheduled_exports = get_option('advnews_scheduled_exports', array());
        $scheduled_exports[] = $schedule_data;
        update_option('advnews_scheduled_exports', $scheduled_exports);

        if (!wp_next_scheduled('advnews_process_scheduled_exports')) {
            wp_schedule_event(time(), 'hourly', 'advnews_process_scheduled_exports');
        }

        wp_redirect(add_query_arg(array(
            'page' => 'advnews-subscribers',
            'message' => 'export_scheduled'
        ), admin_url('admin.php')));
        exit;
    }

    /**
     * Process scheduled exports (called by cron)
     */
    public function process_scheduled_exports()
    {
        $scheduled_exports = get_option('advnews_scheduled_exports', array());
        $now = current_time('timestamp');
        $updated_exports = array();

        foreach ($scheduled_exports as $export) {
            $next_run = strtotime($export['next_run']);
            if ($next_run <= $now) {
                $this->run_scheduled_export($export);
                switch ($export['frequency']) {
                    case 'daily':
                        $next_run = strtotime('+1 day', $now);
                        break;
                    case 'weekly':
                        $next_run = strtotime('+1 week', $now);
                        break;
                    case 'monthly':
                        $next_run = strtotime('+1 month', $now);
                        break;
                    default:
                        $next_run = strtotime('+1 week', $now);
                }
                $export['next_run'] = date('Y-m-d H:i:s', $next_run);
            }
            $updated_exports[] = $export;
        }

        update_option('advnews_scheduled_exports', $updated_exports);
    }

    /**
     * Run a single scheduled export
     */
    private function run_scheduled_export($export)
    {
        $subscriber_class = new AdvNews_Subscriber();
        $args = isset($export['args']) && is_array($export['args']) ? $export['args'] : array();
        $args['limit'] = 0;
        $args['offset'] = 0;
        $subscribers = $subscriber_class->get_all_subscribers($args);
        if (empty($subscribers)) {
            return;
        }

        $content = $this->generate_export_file($subscribers, $export['fields'], $export['format'], $export['filename'], true);

        $to = $export['email'];
        $subject = sprintf(__('Scheduled Subscriber Export - %s', 'advnews-manager'), date_i18n(get_option('date_format')));
        $message = '<p>' . __('Attached is your scheduled subscriber export.', 'advnews-manager') . '</p>';
        $message .= '<p>' . sprintf(__('Export Date: %s', 'advnews-manager'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'))) . '</p>';
        $message .= '<p>' . sprintf(__('Total Subscribers: %d', 'advnews-manager'), count($subscribers)) . '</p>';
        $headers = array('Content-Type: text/html; charset=UTF-8');

        $upload_dir = wp_upload_dir();
        $temp_file = $upload_dir['path'] . '/' . $export['filename'];
        file_put_contents($temp_file, $content);
        $attachments = array($temp_file);

        wp_mail($to, $subject, $message, $headers, $attachments);
        unlink($temp_file);
    }

    /**
     * Handle preview export (AJAX)
     */
    public function ajax_preview_export()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'advnews_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'advnews-manager')));
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'advnews-manager')));
        }

        $args = array(
            'limit' => 10,
            'offset' => 0
        );
        if (isset($_POST['status']) && !empty($_POST['status'])) {
            $args['status'] = sanitize_text_field($_POST['status']);
        }
        if (isset($_POST['category_ids']) && is_array($_POST['category_ids'])) {
            $category_ids = array_values(array_unique(array_filter(array_map('intval', $_POST['category_ids']))));
            if (!empty($category_ids)) {
                $args['category_ids'] = $category_ids;
            }
        } elseif (isset($_POST['category_id']) && !empty($_POST['category_id'])) {
            $args['category_id'] = intval($_POST['category_id']);
        }
        if (isset($_POST['search']) && !empty($_POST['search'])) {
            $args['search'] = sanitize_text_field($_POST['search']);
        }
        if (isset($_POST['date_from']) && !empty($_POST['date_from'])) {
            $args['date_from'] = sanitize_text_field($_POST['date_from']);
        }
        if (isset($_POST['date_to']) && !empty($_POST['date_to'])) {
            $args['date_to'] = sanitize_text_field($_POST['date_to']);
        }

        $subscriber_class = new AdvNews_Subscriber();
        $subscribers = $subscriber_class->get_all_subscribers($args);

        $preview = array();
        foreach ($subscribers as $subscriber) {
            $preview[] = array(
                'email' => $subscriber->email,
                'first_name' => $subscriber->first_name,
                'last_name' => $subscriber->last_name,
                'organization' => $subscriber->organization,
                'status' => $subscriber->status
            );
        }
        wp_send_json_success($preview);
    }

    /**
     * Handle cancel scheduled export
     */
    public function ajax_cancel_scheduled_export()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'advnews_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'advnews-manager')));
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'advnews-manager')));
        }

        $export_id = isset($_POST['export_id']) ? sanitize_text_field($_POST['export_id']) : '';
        if (empty($export_id)) {
            wp_send_json_error(array('message' => __('Invalid export ID.', 'advnews-manager')));
        }

        $scheduled_exports = get_option('advnews_scheduled_exports', array());
        $updated_exports = array();
        foreach ($scheduled_exports as $export) {
            if ($export['id'] !== $export_id) {
                $updated_exports[] = $export;
            }
        }
        update_option('advnews_scheduled_exports', $updated_exports);
        wp_send_json_success(array(
            'message' => __('Scheduled export cancelled successfully.', 'advnews-manager')
        ));
    }


    /**
    * Check server configuration for import compatibility
    */
    public function check_import_requirements() {
        $issues = [];

        // PHP-FPM timeout check
        if (function_exists('ini_get')) {
            $max_exec = ini_get('max_execution_time');
            $fpm_timeout = @ini_get('request_terminate_timeout'); // PHP-FPM specific

            if ($fpm_timeout && $fpm_timeout > 0 && $fpm_timeout < 300) {
                $issues[] = sprintf(
                    __('PHP-FPM timeout (%ds) may interrupt large imports. Recommended: 300s or use chunked import.', 'advnews-manager'),
                    $fpm_timeout
                );
            }

            $memory = ini_get('memory_limit');
            if (preg_match('/^(\d+)([KMGT])?$/', $memory, $matches)) {
                $mem_mb = $matches[1] * ('K' === $matches[2] ? 0.001 : ('M' === $matches[2] ? 1 : ('G' === $matches[2] ? 1024 : 1048576)));
                if ($mem_mb < 256) {
                    $issues[] = sprintf(__('Memory limit (%s) is low for large imports. Recommended: 256M+', 'advnews-manager'), $memory);
                }
            }
        }

        // Upload limits
        $upload_max = wp_convert_hr_to_bytes(ini_get('upload_max_filesize'));
        $post_max = wp_convert_hr_to_bytes(ini_get('post_max_size'));
        if ($upload_max < 10 * 1024 * 1024) { // < 10MB
            $issues[] = __('upload_max_filesize is too small for CSV imports. Recommended: 10M+', 'advnews-manager');
        }

        return $issues;
    }



    /**
     * Add plugin action links
     */
    public function add_plugin_action_links($links)
    {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=advnews-settings') . '">' . __('Settings', 'advnews-manager') . '</a>',
            '<a href="' . admin_url('admin.php?page=advnews-campaigns&action=add') . '">' . __('New Campaign', 'advnews-manager') . '</a>',
            '<a href="' . admin_url('admin.php?page=advnews-subscribers&action=add') . '">' . __('Add Subscriber', 'advnews-manager') . '</a>'
        );
        return array_merge($plugin_links, $links);
    }

    /**
     * Admin footer text
     */
    public function admin_footer_text($text)
    {
        $screen = get_current_screen();
        if (strpos($screen->id, 'advnews') !== false) {
            $text = sprintf(
                __('Thank you for using AdvNews Manager. Please <a href="%s" target="_blank">rate it 5 stars</a> on WordPress.org.', 'advnews-manager'),
                'https://wordpress.org/support/plugin/advnews-manager/reviews/#new-post'
            );
        }
        return $text;
    }

    /**
    * Show admin notices
    */
    public function show_admin_notices()
    {
        // Check for messages
        if (isset($_GET['message'])) {
            $this->show_message_notice(sanitize_text_field($_GET['message']));
        }

        // Check for required settings
        if (!get_option('advnews_from_email')) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <?php _e('AdvNews Manager: Please configure your email settings.', 'advnews-manager'); ?>
                    <a href="<?php echo admin_url('admin.php?page=advnews-settings'); ?>">
                        <?php _e('Go to Settings', 'advnews-manager'); ?>
                    </a>
                </p>
            </div>
            <?php
        }

        // Check SMTP connection if configured - UPDATED
        if (get_option('advnews_smtp_host') && !get_option('advnews_smtp_test_notice_dismissed')) {
            ?>
            <div class="notice notice-info is-dismissible" id="advnews-smtp-test-notice" data-notice="smtp_test">
                <p>
                    <?php _e('AdvNews Manager: Please test your SMTP connection to ensure emails can be sent.', 'advnews-manager'); ?>
                    <button type="button" id="advnews-test-smtp-notice" class="button button-small">
                        <?php _e('Test Now', 'advnews-manager'); ?>
                    </button>
                    <span id="notice-test-spinner" class="spinner" style="float:none; margin: 0 5px;"></span>
                    <span id="notice-test-result" style="display:none; margin-left: 10px;"></span>
                </p>
            </div>
            <script>
            jQuery(document).ready(function($) {
                // Handle notice dismissal
                $(document).on('click', '.notice.is-dismissible .notice-dismiss', function() {
                    var notice = $(this).closest('.notice');
                    var noticeType = notice.data('notice');

                    if (noticeType === 'smtp_test') {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'advnews_dismiss_notice',
                                notice: 'smtp_test',
                                nonce: '<?php echo wp_create_nonce('advnews_ajax_nonce'); ?>'
                            }
                        });
                    }
                });

                // Test SMTP from notice
                $('#advnews-test-smtp-notice').on('click', function() {
                    var button = $(this);
                    var spinner = $('#notice-test-spinner');
                    var result = $('#notice-test-result');
                    var testEmail = '<?php echo esc_js(get_option('admin_email')); ?>';

                    button.prop('disabled', true);
                    spinner.addClass('is-active');
                    result.hide();

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'advnews_test_smtp',
                            test_email: testEmail,
                            _wpnonce: '<?php echo wp_create_nonce('advnews_ajax_nonce'); ?>',
                            nonce: '<?php echo wp_create_nonce('advnews_ajax_nonce'); ?>'
                        },
                        success: function(response) {
                            result.show();
                            if (response.success) {
                                result.html('<span style="color: #00a32a;">✔ ' + response.data.message + '</span>');
                                // Dismiss notice after successful test
                                setTimeout(function() {
                                    $('#advnews-smtp-test-notice').fadeOut();
                                    $.ajax({
                                        url: ajaxurl,
                                        type: 'POST',
                                        data: {
                                            action: 'advnews_dismiss_notice',
                                            notice: 'smtp_test',
                                            nonce: '<?php echo wp_create_nonce('advnews_ajax_nonce'); ?>'
                                        }
                                    });
                                }, 2000);
                            } else {
                                result.html('<span style="color: #d63638;">✘ ' + response.data.message + '</span>');
                            }
                        },
                        error: function() {
                            result.show().html('<span style="color: #d63638;">✘ <?php _e('Connection failed.', 'advnews-manager'); ?></span>');
                        },
                        complete: function() {
                            button.prop('disabled', false);
                            spinner.removeClass('is-active');
                        }
                    });
                });
            });
            </script>
            <?php
        }
    }

    /**
     * Show message notice
     */
    private function show_message_notice($message)
    {
        $messages = array(
            'campaign_saved' => __('Campaign saved successfully.', 'advnews-manager'),
            'campaign_updated' => __('Campaign updated successfully.', 'advnews-manager'),
            'campaign_sent' => __('Campaign queued for sending.', 'advnews-manager'),
            'campaign_deleted' => __('Campaign deleted successfully.', 'advnews-manager'),
            'campaign_duplicated' => __('Campaign duplicated successfully.', 'advnews-manager'),
            'template_created' => __('Template created successfully.', 'advnews-manager'),
            'template_updated' => __('Template updated successfully.', 'advnews-manager'),
            'template_deleted' => __('Template deleted successfully.', 'advnews-manager'),
            'template_duplicated' => __('Template duplicated successfully.', 'advnews-manager'),
            'subscriber_created' => __('Subscriber created successfully.', 'advnews-manager'),
            'subscriber_updated' => __('Subscriber updated successfully.', 'advnews-manager'),
            'subscriber_deleted' => __('Subscriber deleted successfully.', 'advnews-manager'),
            'subscriber_unsubscribed' => __('Subscriber unsubscribed successfully.', 'advnews-manager'),
            'subscriber_resubscribed' => __('Subscriber resubscribed successfully.', 'advnews-manager'),
            'category_created' => __('Category created successfully.', 'advnews-manager'),
            'category_updated' => __('Category updated successfully.', 'advnews-manager'),
            'category_deleted' => __('Category deleted successfully.', 'advnews-manager'),

            // ✅ NEW: Cooldown reset success message
            'cooldown_reset' => __('Cooldown delay reset successfully. This subscriber can now receive emails immediately.', 'advnews-manager')
        );

        if (isset($messages[$message])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$message]) . '</p></div>';
        }
    }

    /**
     * Get database size
     */
    private function get_database_size()
    {
        $total = 0;
        $tables = $this->wpdb->get_results("SHOW TABLE STATUS LIKE '{$this->wpdb->prefix}{$this->table_prefix}%'");
        foreach ($tables as $table) {
            $total += $table->Data_length + $table->Index_length;
        }
        return size_format($total);
    }

    /**
     * Get table sizes
     */
    private function get_table_sizes()
    {
        $sizes = array();
        $tables = $this->wpdb->get_results("SHOW TABLE STATUS LIKE '{$this->wpdb->prefix}{$this->table_prefix}%'");
        foreach ($tables as $table) {
            $sizes[$table->Name] = $table->Data_length + $table->Index_length;
        }
        return $sizes;
    }

    /**
     * Identify SMTP provider
     */
    private function identify_smtp_provider($host)
    {
        if (strpos($host, 'gmail') !== false || strpos($host, 'google') !== false) {
            return 'gmail';
        }
        if (strpos($host, 'sendgrid') !== false) {
            return 'sendgrid';
        }
        if (strpos($host, 'mailgun') !== false) {
            return 'mailgun';
        }
        if (strpos($host, 'amazonaws') !== false) {
            return 'amazon';
        }
        if (strpos($host, 'office365') !== false || strpos($host, 'outlook') !== false) {
            return 'office365';
        }
        return false;
    }
}
