<?php
// File: includes/class-ajax.php
if (!defined('ABSPATH')) {
    exit;
}

class AdvNews_Ajax
{
    private $wpdb;
    private $table_prefix;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = ADVNEWS_TABLE_PREFIX;
        $this->init_hooks();
    }

    /**
     * Initialize AJAX hooks
     */
    private function init_hooks()
    {
        // =====================================================
        // SUBSCRIBER AJAX HANDLERS
        // =====================================================
        add_action('wp_ajax_advnews_add_subscriber', array($this, 'ajax_add_subscriber'));
        add_action('wp_ajax_nopriv_advnews_add_subscriber', array($this, 'ajax_add_subscriber'));
        add_action('wp_ajax_advnews_import_subscribers', array($this, 'ajax_import_subscribers'));
        add_action('wp_ajax_advnews_export_subscribers', array($this, 'ajax_export_subscribers'));
        add_action('wp_ajax_advnews_get_subscriber', array($this, 'ajax_get_subscriber'));
        add_action('wp_ajax_advnews_update_subscriber', array($this, 'ajax_update_subscriber'));
        add_action('wp_ajax_advnews_delete_subscriber', array($this, 'ajax_delete_subscriber'));
        add_action('wp_ajax_advnews_bulk_assign_categories', array($this, 'ajax_bulk_assign_categories'));

        // =====================================================
        // EXPORT AJAX HANDLERS
        // =====================================================
        add_action('wp_ajax_advnews_preview_export', array($this, 'ajax_preview_export'));
        add_action('wp_ajax_advnews_cancel_scheduled_export', array($this, 'ajax_cancel_scheduled_export'));

        // =====================================================
        // CAMPAIGN AJAX HANDLERS
        // =====================================================
        add_action('wp_ajax_advnews_save_campaign', array($this, 'ajax_save_campaign'));
        add_action('wp_ajax_advnews_send_campaign', array($this, 'ajax_send_campaign'));
        add_action('wp_ajax_advnews_pause_campaign', array($this, 'ajax_pause_campaign'));
        add_action('wp_ajax_advnews_resume_campaign', array($this, 'ajax_resume_campaign'));
        add_action('wp_ajax_advnews_end_campaign', array($this, 'ajax_end_campaign'));
        add_action('wp_ajax_advnews_add_campaign_recipient', array($this, 'ajax_add_campaign_recipient'));
        add_action('wp_ajax_advnews_get_campaign_stats', array($this, 'ajax_get_campaign_stats'));
        add_action('wp_ajax_advnews_count_recipients', array($this, 'ajax_count_recipients'));
        add_action('wp_ajax_advnews_send_test', array($this, 'ajax_send_test'));
        add_action('wp_ajax_advnews_duplicate_campaign', array($this, 'ajax_duplicate_campaign'));
        add_action('wp_ajax_advnews_delete_campaign', array($this, 'ajax_delete_campaign'));
        add_action('wp_ajax_advnews_count_recipients_multiple', array($this, 'ajax_count_recipients_multiple'));

        // =====================================================
        // TEMPLATE AJAX HANDLERS
        // =====================================================
        add_action('wp_ajax_advnews_save_template', array($this, 'ajax_save_template'));
        add_action('wp_ajax_advnews_get_template', array($this, 'ajax_get_template'));
        add_action('wp_ajax_advnews_get_templates_by_category', array($this, 'ajax_get_templates_by_category'));
        add_action('wp_ajax_advnews_delete_template', array($this, 'ajax_delete_template'));
        add_action('wp_ajax_advnews_preview_template', array($this, 'ajax_preview_template'));
        add_action('wp_ajax_advnews_test_template', array($this, 'ajax_test_template'));
        add_action('wp_ajax_advnews_import_template_html', array($this, 'ajax_import_template_html'));

        // =====================================================
        // QUEUE AJAX HANDLERS
        // =====================================================
        add_action('wp_ajax_advnews_get_queue_status', array($this, 'ajax_get_queue_status'));
        add_action('wp_ajax_advnews_pause_queue', array($this, 'ajax_pause_queue'));
        add_action('wp_ajax_advnews_resume_queue', array($this, 'ajax_resume_queue'));
        add_action('wp_ajax_advnews_clear_stuck_queue', array($this, 'ajax_clear_stuck_queue'));
        add_action('wp_ajax_advnews_retry_failed_queue', array($this, 'ajax_retry_failed_queue'));
        add_action('wp_ajax_advnews_process_queue_now', array($this, 'ajax_process_queue_now'));

        // =====================================================
        // NEW: EMAIL LOGS AJAX HANDLERS
        // =====================================================
        add_action('wp_ajax_advnews_get_email_logs', array($this, 'ajax_get_email_logs'));
        add_action('wp_ajax_advnews_retry_failed_email', array($this, 'ajax_retry_failed_email')); // ← THIS LINE IS REQUIRED TO HANDEL SINGLE EMAIL RESET

        // =====================================================
        // ANALYTICS AJAX HANDLERS
        // =====================================================
        add_action('wp_ajax_advnews_get_analytics', array($this, 'ajax_get_analytics'));
        add_action('wp_ajax_advnews_export_analytics', array($this, 'ajax_export_analytics'));
        add_action('wp_ajax_advnews_load_more_analytics', array($this, 'ajax_load_more_analytics'));
        add_action('wp_ajax_advnews_load_more_ips', array($this, 'ajax_load_more_ips'));
        add_action('wp_ajax_advnews_update_analytics_range', array($this, 'ajax_update_analytics_range'));

        // =====================================================
        // SETTINGS AJAX HANDLERS
        // =====================================================
        add_action('wp_ajax_advnews_test_smtp', array($this, 'ajax_test_smtp'));
        add_action('wp_ajax_advnews_test_cron', array($this, 'ajax_test_cron'));
        add_action('wp_ajax_advnews_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_advnews_clear_tracking_data', array($this, 'ajax_clear_tracking_data'));
        add_action('wp_ajax_advnews_test_subscription', array($this, 'ajax_test_subscription'));
        add_action('wp_ajax_advnews_dismiss_notice', array($this, 'ajax_dismiss_notice'));

        // =====================================================
        // DASHBOARD AJAX HANDLERS
        // =====================================================
        add_action('wp_ajax_advnews_refresh_dashboard', array($this, 'ajax_refresh_dashboard'));
        add_action('wp_ajax_advnews_dismiss_notice', array($this, 'ajax_dismiss_notice'));

        // =====================================================
        // FRONTEND AJAX HANDLERS
        // =====================================================
        add_action('wp_ajax_nopriv_advnews_frontend_subscribe', array($this, 'ajax_frontend_subscribe'));
        add_action('wp_ajax_advnews_frontend_subscribe', array($this, 'ajax_frontend_subscribe'));
        add_action('wp_ajax_nopriv_advnews_frontend_unsubscribe', array($this, 'ajax_frontend_unsubscribe'));
        add_action('wp_ajax_advnews_frontend_unsubscribe', array($this, 'ajax_frontend_unsubscribe'));
        add_action('wp_ajax_nopriv_advnews_frontend_unsubscribe_request', array($this, 'ajax_frontend_unsubscribe_request'));
        add_action('wp_ajax_advnews_frontend_unsubscribe_request', array($this, 'ajax_frontend_unsubscribe_request'));
        add_action('wp_ajax_nopriv_advnews_frontend_update_preferences', array($this, 'ajax_frontend_update_preferences'));
        add_action('wp_ajax_advnews_frontend_update_preferences', array($this, 'ajax_frontend_update_preferences'));
        add_action('wp_ajax_nopriv_advnews_frontend_resubscribe', array($this, 'ajax_frontend_resubscribe'));
        add_action('wp_ajax_advnews_frontend_resubscribe', array($this, 'ajax_frontend_resubscribe'));
        add_action('wp_ajax_nopriv_advnews_frontend_export_data', array($this, 'ajax_frontend_export_data'));
        add_action('wp_ajax_advnews_frontend_export_data', array($this, 'ajax_frontend_export_data'));
        add_action('wp_ajax_nopriv_advnews_frontend_delete_data', array($this, 'ajax_frontend_delete_data'));
        add_action('wp_ajax_advnews_frontend_delete_data', array($this, 'ajax_frontend_delete_data'));

        // =====================================================
        // TRACKING AJAX HANDLERS (Public)
        // =====================================================
        add_action('wp_ajax_nopriv_advnews_track_open', array($this, 'ajax_track_open'));
        add_action('wp_ajax_nopriv_advnews_track_click', array($this, 'ajax_track_click'));
        add_action('wp_ajax_nopriv_advnews_track_event', array($this, 'ajax_track_event'));

        // =====================================================
        // CATEGORY AJAX HANDLERS
        // =====================================================
        add_action('wp_ajax_advnews_get_categories', array($this, 'ajax_get_categories'));
        add_action('wp_ajax_advnews_save_category', array($this, 'ajax_save_category'));
        add_action('wp_ajax_advnews_delete_category', array($this, 'ajax_delete_category'));

        // =====================================================
        // IMPORT/EXPORT AJAX HANDLERS (Additional)
        // =====================================================
        add_action('wp_ajax_advnews_schedule_export', array($this, 'ajax_schedule_export'));

        // =====================================================
        // CRON AJAX HANDLERS
        // =====================================================
        add_action('wp_ajax_advnews_run_cron_task', array($this, 'ajax_run_cron_task'));
        add_action('wp_ajax_advnews_check_cron', array($this, 'ajax_check_cron'));
        add_action('wp_ajax_advnews_schedule_task', array($this, 'ajax_schedule_task'));
        add_action('wp_ajax_advnews_unschedule_task', array($this, 'ajax_unschedule_task'));

        // =====================================================
        // GDPR AJAX HANDLERS
        // =====================================================
        add_action('wp_ajax_advnews_export_subscriber_gdpr', array($this, 'ajax_export_subscriber_gdpr'));
        add_action('wp_ajax_advnews_anonymize_subscriber', array($this, 'ajax_anonymize_subscriber'));
        add_action('wp_ajax_advnews_get_consent_log', array($this, 'ajax_get_consent_log'));
        // =====================================================
        // COOLDOWN AJAX HANDLERS
        // =====================================================
        add_action('wp_ajax_advnews_clear_cooldown_delays', array($this, 'ajax_clear_cooldown_delays'));
        // =====================================================
        // GEOLOCATION AJAX HANDLERS
        // =====================================================
        add_action('wp_ajax_advnews_update_maxmind_db', array($this, 'ajax_update_maxmind_db'));
    }



    /**
    * Clear cooldown delays for queued emails
    */
    public function ajax_clear_cooldown_delays()
    {
        $this->verify_nonce();
        $this->check_capability();

        $queue_class = new AdvNews_Queue();
        $cleared = $queue_class->clear_cooldown_delays();

        wp_send_json_success(array(
            'message' => sprintf(__('Cleared cooldown delays for %d queued emails. They will be sent on next queue processing.', 'advnews-manager'), $cleared)
        ));
    }




    /**
     * Verify AJAX nonce - Checks both _wpnonce and nonce fields
     */
    private function verify_nonce()
    {
        $nonce_candidates = array();
        if (isset($_POST['_wpnonce']) && is_scalar($_POST['_wpnonce'])) {
            $nonce_candidates[] = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
        }
        if (isset($_POST['nonce']) && is_scalar($_POST['nonce'])) {
            $nonce_candidates[] = sanitize_text_field(wp_unslash($_POST['nonce']));
        }

        foreach (array_unique($nonce_candidates) as $nonce) {
            if (wp_verify_nonce($nonce, 'advnews_ajax_nonce')) {
                return;
            }
        }

        wp_send_json_error(array(
            'message' => __('Security check failed.', 'advnews-manager')
        ));
    }

    /**
     * Verify public nonce (for frontend)
     */
    private function verify_public_nonce($action)
    {
        $nonce = isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] :
            (isset($_POST['nonce']) ? $_POST['nonce'] : null);

        if (!$nonce || !wp_verify_nonce($nonce, $action)) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'advnews-manager')
            ));
        }
    }

    /**
     * Check capability
     */
    private function check_capability($capability = 'manage_options')
    {
        if (!current_user_can($capability)) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'advnews-manager')
            ));
        }
    }

    // =====================================================
    // SUBSCRIBER AJAX HANDLERS
    // =====================================================

    public function ajax_add_subscriber()
    {
        $this->verify_public_nonce('advnews_frontend_subscribe');
        $data = AdvNews_Security::sanitize_array($_POST);

        if (empty($data['email'])) {
            wp_send_json_error(array('message' => __('Email address is required.', 'advnews-manager')));
        }

        $email = AdvNews_Security::validate_email($data['email']);
        if (!$email) {
            wp_send_json_error(array('message' => __('Invalid email address.', 'advnews-manager')));
        }

        $subscriber_data = array(
            'email' => $email,
            'first_name' => isset($data['first_name']) ? $data['first_name'] : '',
            'last_name' => isset($data['last_name']) ? $data['last_name'] : '',
            'organization' => isset($data['organization']) ? $data['organization'] : '',
            'ip_address' => AdvNews_Security::get_client_ip()
        );

        if (!empty($data['categories'])) {
            $subscriber_data['categories'] = $data['categories'];
        }

        $subscriber_class = new AdvNews_Subscriber();
        $result = $subscriber_class->add_subscriber($subscriber_data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Thank you for subscribing! Please check your email to confirm your subscription.', 'advnews-manager'),
            'subscriber_id' => $result
        ));
    }

    public function ajax_import_subscribers()
    {
        // Register shutdown handler to catch fatal errors during large imports
        register_shutdown_function(function() {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR])) {
                if (function_exists('wp_send_json_error')) {
                    @wp_send_json_error([
                        'message' => __('Fatal error during import: ', 'advnews-manager') . $error['message'],
                        'error_type' => $error['type'],
                        'error_file' => $error['file'],
                        'error_line' => $error['line']
                    ]);
                }
                error_log('[AdvNews Import FATAL] ' . print_r($error, true));
            }
        });

        try {
            $this->verify_nonce();
            $this->check_capability();

            if (!isset($_FILES['csv_file']) || !is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
                wp_send_json_error(['message' => __('No valid CSV file uploaded.', 'advnews-manager')]);
            }

            $file = $_FILES['csv_file'];

            // Validate file exists and is readable BEFORE processing
            if (!file_exists($file['tmp_name']) || !is_readable($file['tmp_name'])) {
                error_log('[AdvNews Import] Temp file issue: ' . $file['tmp_name']);
                wp_send_json_error(['message' => __('Uploaded file is not accessible. Check server permissions.', 'advnews-manager')]);
            }

            $validation = AdvNews_Security::validate_csv_upload($file);
            if (is_wp_error($validation)) {
                wp_send_json_error(['message' => $validation->get_error_message()]);
            }

            // =====================================================
            // UPDATED: Handle Multiple Default Categories
            // =====================================================
            $default_categories = [];
            if (isset($_POST['default_category'])) {
                if (is_array($_POST['default_category'])) {
                    // Filter out empty values and ensure they are integers
                    $default_categories = array_filter(array_map('intval', $_POST['default_category']));
                } else {
                    // Backward compatibility for single category selection
                    $cat_id = intval($_POST['default_category']);
                    if ($cat_id > 0) {
                        $default_categories = [$cat_id];
                    }
                }
            }

            $options = [
                'update_existing'   => isset($_POST['update_existing']) && $_POST['update_existing'] == '1',
                'skip_duplicates'   => isset($_POST['skip_duplicates']) && $_POST['skip_duplicates'] == '1',
                'default_category'  => $default_categories, // Now an array of IDs
                'send_welcome'      => isset($_POST['send_welcome']) && $_POST['send_welcome'] == '1',
                'file_name'         => isset($file['name']) ? sanitize_file_name($file['name']) : ''
            ];

            $subscriber_class = new AdvNews_Subscriber();
            $result = $subscriber_class->import_from_csv($file['tmp_name'], $options);

            if (is_wp_error($result)) {
                error_log('[AdvNews Import Error] ' . $result->get_error_message());
                wp_send_json_error(['message' => $result->get_error_message()]);
            }

            wp_send_json_success([
                'message' => sprintf(
                    __('Import completed: %d imported, %d updated, %d skipped.', 'advnews-manager'),
                    $result['imported'],
                    $result['updated'],
                    $result['skipped']
                ),
                'data' => $result
            ]);

        } catch (Throwable $e) {
            // Catch ALL errors including fatal-like exceptions
            error_log('[AdvNews Import Exception] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            wp_send_json_error([
                'message' => __('Import failed: ', 'advnews-manager') . $e->getMessage(),
                'debug'   => defined('WP_DEBUG') ? $e->getTraceAsString() : null
            ]);
        }
    }

    public function ajax_export_subscribers()
    {
        $this->verify_nonce();
        $this->check_capability();

        $args = array();
        if (isset($_POST['status']) && $_POST['status']) {
            $args['status'] = sanitize_text_field($_POST['status']);
        }
        if (isset($_POST['category_ids']) && is_array($_POST['category_ids'])) {
            $category_ids = array_values(array_unique(array_filter(array_map('intval', $_POST['category_ids']))));
            if (!empty($category_ids)) {
                $args['category_ids'] = $category_ids;
            }
        } elseif (isset($_POST['category_id']) && $_POST['category_id']) {
            $args['category_id'] = intval($_POST['category_id']);
        }
        if (isset($_POST['search']) && $_POST['search']) {
            $args['search'] = sanitize_text_field($_POST['search']);
        }
        if (isset($_POST['date_from']) && $_POST['date_from']) {
            $args['date_from'] = sanitize_text_field($_POST['date_from']);
        }
        if (isset($_POST['date_to']) && $_POST['date_to']) {
            $args['date_to'] = sanitize_text_field($_POST['date_to']);
        }
        $args['limit'] = 0;
        $args['offset'] = 0;

        $subscriber_class = new AdvNews_Subscriber();
        $subscriber_class->export_to_csv($args);
    }

    public function ajax_get_subscriber()
    {
        $this->verify_nonce();
        $this->check_capability();

        $subscriber_id = isset($_POST['subscriber_id']) ? intval($_POST['subscriber_id']) : 0;
        if (!$subscriber_id) {
            wp_send_json_error(array('message' => __('Invalid subscriber ID.', 'advnews-manager')));
        }

        $subscriber_class = new AdvNews_Subscriber();
        $subscriber = $subscriber_class->get_subscriber($subscriber_id);

        if (!$subscriber) {
            wp_send_json_error(array('message' => __('Subscriber not found.', 'advnews-manager')));
        }

        $categories = $subscriber_class->get_subscriber_categories($subscriber_id);

        wp_send_json_success(array(
            'id' => $subscriber->id,
            'email' => $subscriber->email,
            'first_name' => $subscriber->first_name,
            'last_name' => $subscriber->last_name,
            'organization' => $subscriber->organization,
            'title' => $subscriber->title,
            'website_url' => $subscriber->website_url,
            'description' => $subscriber->description,
            'country' => $subscriber->country,
            'status' => $subscriber->status,
            'categories' => wp_list_pluck($categories, 'id')
        ));
    }

    public function ajax_update_subscriber()
    {
        $this->verify_nonce();
        $this->check_capability();

        $subscriber_id = isset($_POST['subscriber_id']) ? intval($_POST['subscriber_id']) : 0;
        if (!$subscriber_id) {
            wp_send_json_error(array('message' => __('Invalid subscriber ID.', 'advnews-manager')));
        }

        $data = AdvNews_Security::sanitize_array($_POST);
        $subscriber_class = new AdvNews_Subscriber();
        $result = $subscriber_class->update_subscriber($subscriber_id, $data);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to update subscriber.', 'advnews-manager')));
        }

        if (isset($data['categories'])) {
            $categories = is_array($data['categories']) ? $data['categories'] : explode(',', $data['categories']);
            $subscriber_class->add_categories_to_subscriber($subscriber_id, $categories);
        }

        wp_send_json_success(array(
            'message' => __('Subscriber updated successfully.', 'advnews-manager')
        ));
    }

    public function ajax_delete_subscriber()
    {
        $this->verify_nonce();
        $this->check_capability();

        $subscriber_id = isset($_POST['subscriber_id']) ? intval($_POST['subscriber_id']) : 0;
        if (!$subscriber_id) {
            wp_send_json_error(array('message' => __('Invalid subscriber ID.', 'advnews-manager')));
        }

        $subscriber_class = new AdvNews_Subscriber();
        $subscriber = $subscriber_class->get_subscriber($subscriber_id);

        if (!$subscriber) {
            wp_send_json_error(array('message' => __('Subscriber not found.', 'advnews-manager')));
        }

        // Use the helper function to anonymize/delete
        $result = advnews_delete_subscriber_data($subscriber->email);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to delete subscriber.', 'advnews-manager')));
        }

        wp_send_json_success(array(
            'message' => __('Subscriber data anonymized successfully.', 'advnews-manager')
        ));
    }

    public function ajax_bulk_assign_categories()
    {
        $this->verify_nonce();
        $this->check_capability();

        $subscriber_ids = isset($_POST['subscriber_ids']) ? array_map('intval', $_POST['subscriber_ids']) : array();
        $category_ids = isset($_POST['category_ids']) ? array_map('intval', $_POST['category_ids']) : array();

        if (empty($subscriber_ids)) {
            wp_send_json_error(array('message' => __('No subscribers selected.', 'advnews-manager')));
        }

        $subscriber_class = new AdvNews_Subscriber();
        $processed = 0;

        foreach ($subscriber_ids as $subscriber_id) {
            $subscriber_class->add_categories_to_subscriber($subscriber_id, $category_ids);
            $processed++;
        }

        wp_send_json_success(array(
            'message' => sprintf(__('Categories assigned to %d subscribers.', 'advnews-manager'), $processed)
        ));
    }

    // =====================================================
    // CAMPAIGN AJAX HANDLERS
    // =====================================================

    public function ajax_save_campaign()
    {
        $this->verify_nonce();
        $this->check_capability();

        $data = AdvNews_Security::sanitize_array($_POST);

        // CRITICAL FIX: Override the content sanitization with our email-safe version
        if (isset($_POST['content'])) {
            $data['content'] = $this->sanitize_email_html($_POST['content']);
        }

        $campaign_id = isset($data['campaign_id']) ? intval($data['campaign_id']) : 0;
        $campaign_class = new AdvNews_Campaign();

        if ($campaign_id) {
            $result = $campaign_class->update_campaign($campaign_id, $data);
        } else {
            $result = $campaign_class->create_campaign($data);
        }

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $message = $campaign_id ?
            __('Campaign updated successfully.', 'advnews-manager') :
            __('Campaign created successfully.', 'advnews-manager');

        wp_send_json_success(array(
            'message' => $message,
            'campaign_id' => $campaign_id ?: $result
        ));
    }

    // Added method to Handl Multiple Categories in the campaign editor
    public function ajax_count_recipients_multiple() {
        $this->verify_nonce();
        $this->check_capability();

        $category_ids = isset($_POST['category_ids']) ? array_map('intval', $_POST['category_ids']) : array();

        if (empty($category_ids)) {
            wp_send_json_success(array('count' => 0));
        }

        $subscriber_class = new AdvNews_Subscriber();
        $unique_subscribers = array();

        foreach ($category_ids as $cat_id) {
            $subscribers = $subscriber_class->get_subscribers_by_category($cat_id, 'active');
            foreach ($subscribers as $sub) {
                $unique_subscribers[$sub->id] = true;
            }
        }

        wp_send_json_success(array(
            'count' => count($unique_subscribers)
        ));
    }

    /**
     * Sanitize HTML specifically for Email Campaigns
     * Allows table attributes, inline styles, and common email tags
     */
    private function sanitize_email_html($html) {
        $allowed_html = array(
            'a' => array(
                'href' => array(),
                'title' => array(),
                'target' => array(),
                'rel' => array(),
                'style' => array(),
                'class' => array()
            ),
            'br' => array(),
            'em' => array(),
            'strong' => array(),
            'p' => array(
                'style' => array(),
                'class' => array(),
                'align' => array()
            ),
            'div' => array(
                'class' => array(),
                'style' => array(),
                'align' => array()
            ),
            'span' => array(
                'class' => array(),
                'style' => array()
            ),
            'h1' => array('style' => array(), 'align' => array()),
            'h2' => array('style' => array(), 'align' => array()),
            'h3' => array('style' => array(), 'align' => array()),
            'h4' => array('style' => array(), 'align' => array()),
            'h5' => array('style' => array(), 'align' => array()),
            'h6' => array('style' => array(), 'align' => array()),
            'ul' => array('style' => array()),
            'ol' => array('style' => array()),
            'li' => array('style' => array()),
            'table' => array(
                'border' => array(),
                'cellpadding' => array(),
                'cellspacing' => array(),
                'style' => array(),
                'width' => array(),
                'height' => array(),
                'align' => array(),
                'bgcolor' => array(),
                'class' => array()
            ),
            'tr' => array(
                'style' => array(),
                'bgcolor' => array(),
                'align' => array(),
                'valign' => array(),
                'height' => array()
            ),
            'td' => array(
                'colspan' => array(),
                'rowspan' => array(),
                'style' => array(),
                'width' => array(),
                'height' => array(),
                'align' => array(),
                'valign' => array(),
                'bgcolor' => array(),
                'class' => array()
            ),
            'th' => array(
                'style' => array(),
                'bgcolor' => array(),
                'align' => array(),
                'colspan' => array(),
                'rowspan' => array()
            ),
            'img' => array(
                'src' => array(),
                'alt' => array(),
                'width' => array(),
                'height' => array(),
                'style' => array(),
                'class' => array(),
                'border' => array(),
                'align' => array()
            ),
            'b' => array('style' => array()),
            'i' => array('style' => array()),
            'u' => array('style' => array()),
            'strike' => array(),
            'hr' => array('style' => array(), 'width' => array(), 'size' => array()),
            'blockquote' => array('style' => array()),
            'code' => array('style' => array()),
            'pre' => array('style' => array()),
            'font' => array('color' => array(), 'size' => array(), 'face' => array()), // Legacy email support
            'center' => array() // Legacy email support
        );
        return wp_kses($html, $allowed_html);
    }

    public function ajax_send_campaign()
    {
        $this->verify_nonce();
        $this->check_capability();

        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        if (!$campaign_id) {
            wp_send_json_error(array('message' => __('Invalid campaign ID.', 'advnews-manager')));
        }

        $campaign_class = new AdvNews_Campaign();
        $result = $campaign_class->send_campaign($campaign_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Campaign queued for sending.', 'advnews-manager'),
            'data' => $result
        ));
    }

    public function ajax_pause_campaign()
    {
        $this->verify_nonce();
        $this->check_capability();

        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        if (!$campaign_id) {
            wp_send_json_error(array('message' => __('Invalid campaign ID.', 'advnews-manager')));
        }

        $queue_class = new AdvNews_Queue();
        $result = $queue_class->pause_sending($campaign_id);

        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to pause campaign.', 'advnews-manager')));
        }

        wp_send_json_success(array(
            'message' => __('Campaign paused successfully.', 'advnews-manager')
        ));
    }

    public function ajax_resume_campaign()
    {
        $this->verify_nonce();
        $this->check_capability();

        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        if (!$campaign_id) {
            wp_send_json_error(array('message' => __('Invalid campaign ID.', 'advnews-manager')));
        }

        $queue_class = new AdvNews_Queue();
        $result = $queue_class->resume_sending($campaign_id);

        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to resume campaign.', 'advnews-manager')));
        }

        wp_send_json_success(array(
            'message' => __('Campaign resumed successfully.', 'advnews-manager')
        ));
    }

    public function ajax_end_campaign()
    {
        $this->verify_nonce();
        $this->check_capability();

        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        if (!$campaign_id) {
            wp_send_json_error(array('message' => __('Invalid campaign ID.', 'advnews-manager')));
        }

        $campaign_class = new AdvNews_Campaign();
        $result = $campaign_class->end_campaign($campaign_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to end campaign.', 'advnews-manager')));
        }

        wp_send_json_success(array(
            'message' => __('Campaign ended successfully.', 'advnews-manager')
        ));
    }

    public function ajax_add_campaign_recipient()
    {
        $this->verify_nonce();
        $this->check_capability();

        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        $raw_emails = isset($_POST['emails']) ? sanitize_textarea_field(wp_unslash($_POST['emails'])) : '';
        if (empty($raw_emails) && isset($_POST['email'])) {
            $raw_emails = sanitize_textarea_field(wp_unslash($_POST['email']));
        }
        $category_ids = isset($_POST['category_ids']) && is_array($_POST['category_ids']) ? array_values(array_unique(array_map('intval', $_POST['category_ids']))) : array();

        if (!$campaign_id) {
            wp_send_json_error(array('message' => __('Invalid campaign ID.', 'advnews-manager')));
        }

        if (!empty($_FILES['csv_file']['tmp_name']) && is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
            $csv_handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            if ($csv_handle) {
                while (($row = fgetcsv($csv_handle)) !== false) {
                    $raw_emails .= "\n" . implode("\n", $row);
                }
                fclose($csv_handle);
            }
        }

        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $raw_emails, $matches);
        $emails = array_values(array_unique(array_map('strtolower', $matches[0] ?? array())));

        if (empty($emails)) {
            wp_send_json_error(array('message' => __('Enter at least one valid email address or upload a CSV file.', 'advnews-manager')));
        }

        $campaign_class = new AdvNews_Campaign();
        $subscriber_class = new AdvNews_Subscriber();
        $added = 0;
        $created = 0;
        $skipped = array();

        foreach ($emails as $email) {
            $subscriber = $subscriber_class->get_subscriber_by_email($email);
            if (!$subscriber) {
                $subscriber_id = $subscriber_class->add_subscriber(array(
                    'email' => $email,
                    'categories' => $category_ids,
                    'send_welcome' => false
                ));
                if (is_wp_error($subscriber_id)) {
                    $skipped[] = $email . ' (' . $subscriber_id->get_error_message() . ')';
                    continue;
                }
                $created++;
            } elseif ($subscriber->status !== 'active') {
                $skipped[] = $email . ' (' . __('not active', 'advnews-manager') . ')';
                continue;
            } elseif (!empty($category_ids)) {
                $subscriber_class->add_categories_to_subscriber($subscriber->id, $category_ids);
            }

            $result = $campaign_class->add_recipient_to_campaign($campaign_id, $email);
            if (is_wp_error($result)) {
                $skipped[] = $email . ' (' . $result->get_error_message() . ')';
                continue;
            }
            $added++;
        }

        if ($added === 0) {
            wp_send_json_error(array(
                'message' => __('No recipients were added.', 'advnews-manager') . (!empty($skipped) ? ' ' . implode('; ', array_slice($skipped, 0, 5)) : '')
            ));
        }

        wp_send_json_success(array(
            'message' => sprintf(
                __('%1$d recipient(s) added to the campaign queue. %2$d new subscriber(s) created.%3$s', 'advnews-manager'),
                $added,
                $created,
                !empty($skipped) ? ' ' . sprintf(__('Skipped: %s', 'advnews-manager'), implode('; ', array_slice($skipped, 0, 5))) : ''
            ),
            'data' => array(
                'added' => $added,
                'created' => $created,
                'skipped' => $skipped
            )
        ));
    }

    public function ajax_get_campaign_stats()
    {
        $this->verify_nonce();
        $this->check_capability();

        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        $campaign_class = new AdvNews_Campaign();
        $stats = $campaign_class->get_campaign_stats($campaign_id);

        wp_send_json_success(array(
            'stats' => $stats
        ));
    }

    public function ajax_count_recipients()
    {
        $this->verify_nonce();
        $this->check_capability();

        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        if (!$category_id) {
            wp_send_json_success(array('count' => 0));
        }

        $subscriber_class = new AdvNews_Subscriber();
        $count = $subscriber_class->count_subscribers_by_category($category_id);

        wp_send_json_success(array(
            'count' => $count
        ));
    }

    public function ajax_send_test()
    {
        $this->verify_nonce();
        $this->check_capability();

        $test_email = isset($_POST['test_email']) ? sanitize_email($_POST['test_email']) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : __('Test Email', 'advnews-manager');
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';

        if (!is_email($test_email)) {
            wp_send_json_error(array('message' => __('Invalid email address.', 'advnews-manager')));
        }

        $headers = array('Content-Type: text/html; charset=UTF-8');
        $sample_data = array(
            'first_name' => 'John',
            'last_name' => 'Doe',
            'full_name' => 'John Doe',
            'email' => $test_email,
            'organization' => 'Test Company',
            'current_date' => date_i18n(get_option('date_format')),
            'unsubscribe_link' => '#'
        );

        $campaign_class = new AdvNews_Campaign();
        $content = $campaign_class->process_merge_tags($content, $sample_data);
        $content = $campaign_class->prepare_email_content($content);

        $result = wp_mail($test_email, $subject, $content, $headers);

        if ($result) {
            wp_send_json_success(array('message' => __('Test email sent successfully.', 'advnews-manager')));
        } else {
            wp_send_json_error(array('message' => __('Failed to send test email.', 'advnews-manager')));
        }
    }

    public function ajax_duplicate_campaign()
    {
        $this->verify_nonce();
        $this->check_capability();

        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        if (!$campaign_id) {
            wp_send_json_error(array('message' => __('Invalid campaign ID.', 'advnews-manager')));
        }

        $campaign_class = new AdvNews_Campaign();
        $result = $campaign_class->duplicate_campaign($campaign_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Campaign duplicated successfully.', 'advnews-manager'),
            'redirect_url' => admin_url('admin.php?page=advnews-campaigns&action=edit&id=' . $result)
        ));
    }

    public function ajax_delete_campaign()
    {
        $this->verify_nonce();
        $this->check_capability();

        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        if (!$campaign_id) {
            wp_send_json_error(array('message' => __('Invalid campaign ID.', 'advnews-manager')));
        }

        $campaign_class = new AdvNews_Campaign();
        $result = $campaign_class->delete_campaign($campaign_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Campaign deleted successfully.', 'advnews-manager'),
            'redirect_url' => admin_url('admin.php?page=advnews-campaigns')
        ));
    }

    // =====================================================
    // TEMPLATE AJAX HANDLERS
    // =====================================================

    public function ajax_save_template()
    {
        $this->verify_nonce();
        $this->check_capability();

        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_prefix . 'templates';
        $rel_table  = $wpdb->prefix . $this->table_prefix . 'template_categories';

        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;

        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'subject' => sanitize_text_field($_POST['subject']),
            'content' => isset($_POST['content']) ? wp_kses_post($_POST['content']) : '',
            'css' => isset($_POST['css']) ? wp_strip_all_tags($_POST['css']) : '',
            'category_id' => null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        );

        if (isset($_POST['template_html']) && !empty($_POST['template_html'])) {
            $data['content'] = wp_kses_post($_POST['template_html']);
        }

        if ($template_id) {
            $result = $wpdb->update($table_name, $data, array('id' => $template_id));
            $message = __('Template updated successfully.', 'advnews-manager');
        } else {
            $result = $wpdb->insert($table_name, $data);
            $template_id = $wpdb->insert_id;
            $message = __('Template created successfully.', 'advnews-manager');
        }

        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to save template.', 'advnews-manager')));
        }

        if ($template_id) {
            $categories = array();
            if (isset($_POST['template_categories']) && is_array($_POST['template_categories'])) {
                $categories = array_filter(array_map('intval', $_POST['template_categories']));
            }
            $wpdb->delete($rel_table, array('template_id' => $template_id));
            foreach ($categories as $cat_id) {
                $wpdb->insert($rel_table, array(
                    'template_id' => $template_id,
                    'category_id' => $cat_id
                ));
            }
        }

        wp_send_json_success(array(
            'message' => $message,
            'template_id' => $template_id
        ));
    }


    /**
     * Import HTML file for template
     */
    public function ajax_import_template_html()
    {
        $this->verify_nonce();
        $this->check_capability();

        if (empty($_FILES['html_file']) || $_FILES['html_file']['error'] !== UPLOAD_ERR_OK) {
            $error_msg = __('Invalid or missing HTML file.', 'advnews-manager');
            if (isset($_FILES['html_file']['error'])) {
                $error_msg .= ' Error Code: ' . $_FILES['html_file']['error'];
            }
            wp_send_json_error(array('message' => $error_msg));
        }

        $file = $_FILES['html_file'];

        // Validate file type
        $file_type = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        if (!in_array($file_type['type'], array('text/html', 'text/plain')) && !preg_match('/\.(html|htm)$/i', $file['name'])) {
            wp_send_json_error(array('message' => __('Only .html or .htm files are allowed.', 'advnews-manager')));
        }

        // Validate file size (2MB limit)
        if ($file['size'] > 2 * 1024 * 1024) {
            wp_send_json_error(array('message' => __('File size exceeds the 2MB limit.', 'advnews-manager')));
        }

        $raw_html = file_get_contents($file['tmp_name']);
        if ($raw_html === false) {
            wp_send_json_error(array('message' => __('Failed to read the uploaded file.', 'advnews-manager')));
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews Import] Raw HTML length: ' . strlen($raw_html));
        }

        // 1. Extract <title> for template name BEFORE sanitization
        $template_name = '';
        if (preg_match('/<title>(.*?)<\/title>/is', $raw_html, $title_matches)) {
            $template_name = trim($title_matches[1]);
        } else {
            $template_name = pathinfo($file['name'], PATHINFO_FILENAME);
        }

        // 2. Extract <style> content to CSS tab BEFORE sanitization
        $css_content = '';
        if (preg_match('/<style[^>]*>(.*?)<\/style>/is', $raw_html, $style_matches)) {
            $css_content = trim($style_matches[1]);
            // Remove style blocks from raw HTML so they don't appear in body
            $raw_html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $raw_html);
        }

        // 3. Sanitize the HTML body
        $clean_html = $this->sanitize_email_html($raw_html);

        // 4. Remove structural tags not needed in email body (head, body, html)
        // We do this AFTER sanitization to ensure wp_kses doesn't strip inner content unexpectedly
        $clean_html = preg_replace('/<\/?(html|head|body)[^>]*>/i', '', $clean_html);

        // Clean up empty lines/whitespace
        $clean_html = trim($clean_html);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews Import] Clean HTML length: ' . strlen($clean_html));
            error_log('[AdvNews Import] Template Name: ' . $template_name);
            error_log('[AdvNews Import] CSS Length: ' . strlen($css_content));
        }

        wp_send_json_success(array(
            'message' => __('HTML imported successfully. Content populated in editor.', 'advnews-manager'),
            'name'    => $template_name,
            'content' => $clean_html,
            'css'     => $css_content
        ));
    }




    public function ajax_get_template()
    {
        $this->verify_nonce();
        $this->check_capability();

        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        if (!$template_id) {
            wp_send_json_error(array('message' => __('Invalid template ID.', 'advnews-manager')));
        }

        $table_name = $this->wpdb->prefix . $this->table_prefix . 'templates';
        $template = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $template_id
        ));

        if (!$template) {
            wp_send_json_error(array('message' => __('Template not found.', 'advnews-manager')));
        }

        $rel_table = $this->wpdb->prefix . $this->table_prefix . 'template_categories';
        $category_ids = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT category_id FROM $rel_table WHERE template_id = %d",
            $template_id
        ));

        wp_send_json_success(array(
            'id' => $template->id,
            'content' => $template->content,
            'css' => $template->css,
            'subject' => $template->subject,
            'name' => $template->name,
            'category_id' => $template->category_id,
            'category_ids' => array_map('intval', $category_ids),
            'is_active' => $template->is_active
        ));
    }



    public function ajax_get_templates_by_category()
    {
        $this->verify_nonce();
        $this->check_capability();

        $table_name  = $this->wpdb->prefix . $this->table_prefix . 'templates';
        $rel_table   = $this->wpdb->prefix . $this->table_prefix . 'template_categories';

        // Fetch templates with their associated category IDs
        $templates = $this->wpdb->get_results(
            "SELECT t.id, t.name, t.subject, t.is_active, GROUP_CONCAT(tc.category_id) as category_ids
            FROM $table_name t
            LEFT JOIN $rel_table tc ON t.id = tc.template_id
            WHERE t.is_active = 1
            GROUP BY t.id
            ORDER BY t.name ASC"
        );

        $options = '<option value="">' . __('Select a template', 'advnews-manager') . '</option>';
        foreach ($templates as $template) {
            $options .= sprintf(
                '<option value="%d" data-categories="%s">%s (%s)</option>',
                $template->id,
                esc_attr($template->category_ids),
                esc_html($template->name),
                esc_html($template->subject)
            );
        }

        wp_send_json_success(array(
            'html' => $options,
            'count' => substr_count($options, '<option value=') - 1
        ));
    }


    public function ajax_delete_template()
    {
        $this->verify_nonce();
        $this->check_capability();

        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        if (!$template_id) {
            wp_send_json_error(array('message' => __('Invalid template ID.', 'advnews-manager')));
        }

        $table_name = $this->wpdb->prefix . $this->table_prefix . 'templates';
        $campaigns_table = $this->wpdb->prefix . $this->table_prefix . 'campaigns';
        $rel_table = $this->wpdb->prefix . $this->table_prefix . 'template_categories';

        $this->wpdb->update($campaigns_table, array('template_id' => null), array('template_id' => $template_id));
        $this->wpdb->delete($rel_table, array('template_id' => $template_id));

        $result = $this->wpdb->delete($table_name, array('id' => $template_id));

        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to delete template.', 'advnews-manager')));
        }

        wp_send_json_success(array(
            'message' => __('Template deleted successfully.', 'advnews-manager')
        ));
    }

    public function ajax_preview_template()
    {
        $this->verify_nonce();
        $this->check_capability();

        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'templates';

        $template = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $template_id
        ));

        if (!$template) {
            wp_send_json_error(array('message' => __('Template not found.', 'advnews-manager')));
        }

        $sample_data = array(
            'first_name' => 'John',
            'last_name' => 'Doe',
            'full_name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'organization' => 'ACME Inc',
            'current_date' => date_i18n(get_option('date_format')),
            'unsubscribe_link' => '#',
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url()
        );

        $campaign_class = new AdvNews_Campaign();
        $content = $campaign_class->process_merge_tags($template->content, $sample_data);
        $content = $campaign_class->prepare_email_content($content);

        $html = '<!DOCTYPE html><html><head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $html .= '<style>' . $template->css . '</style>';
        $html .= '</head><body>' . $content . '</body></html>';

        wp_send_json_success(array(
            'html' => $html
        ));
    }

    public function ajax_test_template()
    {
        $this->verify_nonce();
        $this->check_capability();

        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $test_data = isset($_POST['test_data']) ? $_POST['test_data'] : array();

        $campaign_class = new AdvNews_Campaign();
        $rendered = $campaign_class->process_merge_tags($content, $test_data);
        $rendered = $campaign_class->prepare_email_content($rendered);

        wp_send_json_success(array(
            'rendered' => $rendered
        ));
    }

    // =====================================================
    // QUEUE AJAX HANDLERS
    // =====================================================

    public function ajax_get_queue_status()
    {
        $this->verify_nonce();
        $this->check_capability();

        $queue_class = new AdvNews_Queue();
        $status = $queue_class->get_queue_status();

        wp_send_json_success(array(
            'status' => $status
        ));
    }

    public function ajax_pause_queue()
    {
        $this->verify_nonce();
        $this->check_capability();

        update_option('advnews_queue_paused', true);

        wp_send_json_success(array(
            'message' => __('Queue paused successfully.', 'advnews-manager')
        ));
    }

    public function ajax_resume_queue()
    {
        $this->verify_nonce();
        $this->check_capability();

        delete_option('advnews_queue_paused');

        wp_send_json_success(array(
            'message' => __('Queue resumed successfully.', 'advnews-manager')
        ));
    }

    public function ajax_clear_stuck_queue()
    {
        $this->verify_nonce();
        $this->check_capability();

        $queue_class = new AdvNews_Queue();
        $cleared = $queue_class->clear_stuck_emails();

        wp_send_json_success(array(
            'message' => sprintf(__('Cleared %d stuck emails.', 'advnews-manager'), $cleared)
        ));
    }

    public function ajax_retry_failed_queue()
    {
        $this->verify_nonce();
        $this->check_capability();

        $queue_class = new AdvNews_Queue();
        $result = $queue_class->retry_failed_emails();

        wp_send_json_success(array(
            'message' => __('Failed emails requeued for sending.', 'advnews-manager')
        ));
    }


    /**
    * Process queue now - Manual trigger from admin
    */
    public function ajax_process_queue_now()
    {
        $this->verify_nonce();
        $this->check_capability();

        // Log the manual trigger
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews AJAX] Manual queue processing triggered by admin - User ID: ' . get_current_user_id());

            // Log current SMTP config status
            $smtp_host = get_option('advnews_smtp_host');
            $smtp_pass = get_option('advnews_smtp_password');
            error_log('[AdvNews AJAX] SMTP Host configured: ' . (!empty($smtp_host) ? 'YES' : 'NO'));
            error_log('[AdvNews AJAX] SMTP Password stored: ' . (!empty($smtp_pass) ? 'YES (length: ' . strlen($smtp_pass) . ')' : 'NO'));

            // Test decryption
            $decrypted = AdvNews_Security::decrypt($smtp_pass);
            error_log('[AdvNews AJAX] Password decryption test: ' . (!empty($decrypted) ? 'SUCCESS (length: ' . strlen($decrypted) . ')' : 'FAILED'));
        }

        require_once ADVNEWS_PLUGIN_DIR . 'cron/process-queue.php';
        $processor = new AdvNews_Queue_Processor();
        $result = $processor->execute();

        // Log the result with more details
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews AJAX] Queue processing result: ' . print_r($result, true));
        }

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'data' => $result['data']
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message'],
                'data' => $result['data']
            ));
        }
    }

    // =====================================================
    // NEW: EMAIL LOGS AJAX HANDLERS
    // =====================================================

    /**
     * Get Email Logs with Pagination
     */
    public function ajax_get_email_logs()
    {
        $this->verify_nonce();
        $this->check_capability();

        $paged = isset($_POST['paged']) ? max(1, absint($_POST['paged'])) : 1;
        $stored_per_page = (int) get_user_option('advnews_email_logs_per_page', get_current_user_id());
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : $stored_per_page;
        $per_page = $per_page > 0 ? min(500, $per_page) : 20;
        if (isset($_POST['per_page'])) {
            update_user_option(get_current_user_id(), 'advnews_email_logs_per_page', $per_page);
        }
        $offset = ($paged - 1) * $per_page;

        $status_filter = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;

        $table_logs = $this->wpdb->prefix . $this->table_prefix . 'campaign_logs';
        $table_campaigns = $this->wpdb->prefix . $this->table_prefix . 'campaigns';
        $table_subscribers = $this->wpdb->prefix . $this->table_prefix . 'subscribers';

        $where = array('1=1');

        if (!empty($status_filter)) {
            $where[] = $this->wpdb->prepare("cl.status = %s", $status_filter);
        }

        if (!empty($search)) {
            $search_term = '%' . $this->wpdb->esc_like($search) . '%';
            $where[] = $this->wpdb->prepare("(s.email LIKE %s OR s.first_name LIKE %s OR c.name LIKE %s)", $search_term, $search_term, $search_term);
        }

        if ($campaign_id > 0) {
            $where[] = $this->wpdb->prepare("cl.campaign_id = %d", $campaign_id);
        }

        $where_clause = implode(' AND ', $where);

        // Get Total Count
        $total_query = "SELECT COUNT(*) FROM $table_logs cl
                        INNER JOIN $table_subscribers s ON cl.subscriber_id = s.id
                        INNER JOIN $table_campaigns c ON cl.campaign_id = c.id
                        WHERE $where_clause";
        $total_items = $this->wpdb->get_var($total_query);

        // Get Items
        $query = "SELECT cl.*, c.name as campaign_name, c.subject as campaign_subject,
                         s.id as subscriber_id, s.email, s.first_name, s.last_name
                  FROM $table_logs cl
                  INNER JOIN $table_subscribers s ON cl.subscriber_id = s.id
                  INNER JOIN $table_campaigns c ON cl.campaign_id = c.id
                  WHERE $where_clause
                  ORDER BY cl.clicked_at DESC,
                           cl.opened_at DESC,
                           cl.delivered_at DESC,
                           cl.sent_at DESC,
                           cl.created_at DESC
                  LIMIT %d OFFSET %d";

        $items = $this->wpdb->get_results($this->wpdb->prepare($query, $per_page, $offset));

        wp_send_json_success(array(
            'items' => $items,
            'total' => $total_items,
            'page' => $paged,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
    }


    /**
    * Retry a single failed email from logs page
    */
    public function ajax_retry_failed_email()
    {
        $this->verify_nonce();
        $this->check_capability();

        $log_id = isset($_POST['log_id']) ? intval($_POST['log_id']) : 0;
        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;

        if (!$log_id || !$campaign_id) {
            wp_send_json_error(array(
                'message' => __('Invalid log ID or campaign ID.', 'advnews-manager')
            ));
        }

        $table_logs = $this->wpdb->prefix . $this->table_prefix . 'campaign_logs';
        $table_campaigns = $this->wpdb->prefix . $this->table_prefix . 'campaigns';

        // Verify the log exists and is failed
        $log = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table_logs WHERE id = %d AND campaign_id = %d",
            $log_id,
            $campaign_id
        ));

        if (!$log) {
            wp_send_json_error(array(
                'message' => __('Email log not found.', 'advnews-manager')
            ));
        }

        if ($log->status !== 'failed') {
            wp_send_json_error(array(
                'message' => __('This email is not in failed status.', 'advnews-manager')
            ));
        }

        // Update log status back to queued
        $this->wpdb->update(
            $table_logs,
            array(
                'status' => 'queued',
                'sent_at' => null,
                'send_after' => null,
                'retry_count' => ($log->retry_count ?? 0) + 1
            ),
            array('id' => $log_id)
        );

        // Update campaign status back to "sending"
        $this->wpdb->update(
            $table_campaigns,
            array('status' => 'sending'),
            array('id' => $campaign_id)
        );

        // Trigger queue processing
        if (!wp_next_scheduled('advnews_process_queue')) {
            wp_schedule_single_event(time(), 'advnews_process_queue');
        }

        // Try to trigger immediate processing
        if (!defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON) {
            $cron_url = site_url('wp-cron.php?doing_wp_cron=' . time());
            wp_remote_post($cron_url, array('timeout' => 0.01, 'blocking' => false, 'sslverify' => false));
        }

        wp_send_json_success(array(
            'message' => __('Email queued for retry. Campaign will continue sending.', 'advnews-manager')
        ));
    }

    // =====================================================
    // ANALYTICS AJAX HANDLERS
    // =====================================================

    public function ajax_get_analytics()
    {
        $this->verify_nonce();
        $this->check_capability();

        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : '30days';
        $tracking_class = new AdvNews_Tracking();
        $analytics = $tracking_class->get_system_analytics($period);

        wp_send_json_success(array(
            'analytics' => $analytics
        ));
    }

    public function ajax_export_analytics()
    {
        $this->check_capability();

        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'advnews_export')) {
            wp_die(__('Security check failed.', 'advnews-manager'));
        }

        $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'overview';

        $tracking_class = new AdvNews_Tracking();

        if ($campaign_id) {
            $tracking_class->export_analytics($campaign_id, $type);
        } else {
            $analytics = $tracking_class->get_system_analytics('all');
            $this->export_system_analytics($analytics, $type);
        }
    }

    private function export_system_analytics($analytics, $type)
    {
        $filename = 'analytics-system-' . $type . '-' . date('Y-m-d-H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');

        switch ($type) {
            case 'campaigns':
                fputcsv($output, array('Campaign', 'Sent Date', 'Recipients', 'Open Rate', 'Click Rate'));
                foreach ($analytics['performance'] as $campaign) {
                    fputcsv($output, array(
                        $campaign->campaign_name,
                        $campaign->date,
                        $campaign->emails_sent,
                        $campaign->avg_open_rate . '%',
                        $campaign->avg_click_rate . '%'
                    ));
                }
                break;
            case 'subscribers':
                fputcsv($output, array('Metric', 'Value'));
                fputcsv($output, array('Total Subscribers', $analytics['subscribers']->total));
                fputcsv($output, array('Active', $analytics['subscribers']->active));
                fputcsv($output, array('Unsubscribed', $analytics['subscribers']->unsubscribed));
                fputcsv($output, array('Bounced', $analytics['subscribers']->bounced));
                break;
            default:
                fputcsv($output, array('Metric', 'Value'));
                fputcsv($output, array('Total Campaigns', $analytics['campaigns']->total_campaigns));
                fputcsv($output, array('Total Recipients', $analytics['campaigns']->total_recipients));
                fputcsv($output, array('Average Open Rate', $analytics['campaigns']->avg_open_rate . '%'));
                fputcsv($output, array('Average Click Rate', $analytics['campaigns']->avg_click_rate . '%'));
                break;
        }

        fclose($output);
        exit;
    }

    public function ajax_load_more_analytics()
    {
        $this->verify_nonce();
        $this->check_capability();

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $limit = 20;

        switch ($type) {
            case 'campaigns':
                $items = $this->wpdb->get_results($this->wpdb->prepare(
                    "SELECT * FROM {$this->wpdb->prefix}{$this->table_prefix}campaigns
                    WHERE status = 'sent'
                    ORDER BY sent_at DESC
                    LIMIT %d OFFSET %d",
                    $limit,
                    $offset
                ));
                break;
            default:
                $items = array();
        }

        $has_more = count($items) === $limit;

        ob_start();
        foreach ($items as $item) {
            echo '<tr>';
            echo '<td>' . esc_html($item->name) . '</td>';
            echo '<td>' . esc_html($item->subject) . '</td>';
            echo '<td>' . esc_html($item->sent_at) . '</td>';
            echo '<td>' . esc_html($item->open_rate) . '%</td>';
            echo '<td>' . esc_html($item->click_rate) . '%</td>';
            echo '</tr>';
        }
        $html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $html,
            'count' => count($items),
            'has_more' => $has_more
        ));
    }

    public function ajax_load_more_ips()
    {
        $this->verify_nonce();
        $this->check_capability();

        $offset = isset($_POST['offset']) ? max(0, intval($_POST['offset'])) : 0;
        $limit = isset($_POST['limit']) ? max(1, min(100, intval($_POST['limit']))) : 50;
        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : '30days';

        $end_date = current_time('mysql');
        switch ($period) {
            case '7days':
                $start_date = date('Y-m-d H:i:s', strtotime('-7 days'));
                break;
            case '90days':
                $start_date = date('Y-m-d H:i:s', strtotime('-90 days'));
                break;
            case 'year':
                $start_date = date('Y-m-d H:i:s', strtotime('-1 year'));
                break;
            case '30days':
            default:
                $start_date = date('Y-m-d H:i:s', strtotime('-30 days'));
                break;
        }

        $table_clicks = $this->wpdb->prefix . $this->table_prefix . 'tracking_clicks';
        $table_subscribers = $this->wpdb->prefix . $this->table_prefix . 'subscribers';
        $table_campaigns = $this->wpdb->prefix . $this->table_prefix . 'campaigns';

        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $ip_address = isset($_POST['ip_address']) ? sanitize_text_field(wp_unslash($_POST['ip_address'])) : '';
        $campaign_id = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;
        $country = isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : '';
        $city = isset($_POST['city']) ? sanitize_text_field(wp_unslash($_POST['city'])) : '';

        $where = array('tc.clicked_at BETWEEN %s AND %s', "tc.ip_address != ''");
        $params = array($start_date, $end_date);
        if ($search !== '') {
            $like = '%' . $this->wpdb->esc_like($search) . '%';
            $where[] = '(tc.ip_address LIKE %s OR s.email LIKE %s OR s.first_name LIKE %s OR s.last_name LIKE %s OR c.name LIKE %s)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if ($ip_address !== '') {
            $where[] = 'tc.ip_address = %s';
            $params[] = $ip_address;
        }
        if ($campaign_id > 0) {
            $where[] = 'tc.campaign_id = %d';
            $params[] = $campaign_id;
        }
        if ($country !== '') {
            $where[] = 'tc.country = %s';
            $params[] = $country;
        }
        if ($city !== '') {
            $where[] = 'tc.city = %s';
            $params[] = $city;
        }
        $where_sql = implode(' AND ', $where);
        $query_params = array_merge($params, array($limit, $offset));

        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT tc.ip_address, tc.subscriber_id, tc.country, tc.country_code, tc.city,
                    tc.device_type, tc.browser, tc.platform, tc.clicked_at AS event_at,
                    tc.campaign_id, s.email AS subscriber_email, c.name AS campaign_name
             FROM $table_clicks tc
             LEFT JOIN $table_subscribers s ON tc.subscriber_id = s.id
             LEFT JOIN $table_campaigns c ON tc.campaign_id = c.id
             WHERE {$where_sql}
             ORDER BY tc.clicked_at DESC
             LIMIT %d OFFSET %d",
            $query_params
        ));

        ob_start();
        foreach ($rows as $row) {
            $location = trim(($row->city ?: '') . ', ' . ($row->country ?: ''), ', ');
            if ($location === '') {
                $location = '—';
            }
            $subscriber_url = $row->subscriber_id ? admin_url('admin.php?page=advnews-subscribers&action=view&id=' . (int) $row->subscriber_id) : '';
            $campaign_url = $row->campaign_id ? admin_url('admin.php?page=advnews-analytics&action=campaign&campaign_id=' . (int) $row->campaign_id) : '';
            ?>
            <tr>
                <td><code style="background: #f0f0f1; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html($row->ip_address); ?></code></td>
                <td>
                    <?php if ($subscriber_url && $row->subscriber_email): ?>
                        <a href="<?php echo esc_url($subscriber_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($row->subscriber_email); ?></a>
                    <?php else: ?>
                        <span style="color: #999;">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($row->country_code)): ?>
                        <img src="https://flagcdn.com/16x12/<?php echo esc_attr(strtolower($row->country_code)); ?>.png" alt="<?php echo esc_attr($row->country); ?>" style="vertical-align: middle; margin-right: 5px;">
                    <?php endif; ?>
                    <?php echo esc_html($location); ?>
                </td>
                <td><?php echo esc_html($row->device_type ?: '—'); ?></td>
                <td><?php echo esc_html($row->browser ?: '—'); ?></td>
                <td>
                    <?php if ($campaign_url && $row->campaign_name): ?>
                        <a href="<?php echo esc_url($campaign_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($row->campaign_name); ?></a>
                    <?php else: ?>
                        <span style="color: #999;">—</span>
                    <?php endif; ?>
                </td>
                <td><span title="<?php echo esc_attr($row->event_at); ?>"><?php echo esc_html(human_time_diff(strtotime($row->event_at), current_time('timestamp')) . ' ' . __('ago', 'advnews-manager')); ?></span></td>
            </tr>
            <?php
        }
        $html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $html,
            'count' => count($rows),
            'has_more' => count($rows) === $limit
        ));
    }
    public function ajax_update_analytics_range()
    {
        $this->verify_nonce();
        $this->check_capability();

        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $user_id = get_current_user_id();

        update_user_meta($user_id, 'advnews_analytics_start', $start_date);
        update_user_meta($user_id, 'advnews_analytics_end', $end_date);

        wp_send_json_success();
    }

    // =====================================================
    // SETTINGS AJAX HANDLERS
    // =====================================================

    public function ajax_test_smtp()
    {
        // Verify nonce
        $nonce = isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] :
                (isset($_POST['nonce']) ? $_POST['nonce'] : null);

        if (!$nonce || !wp_verify_nonce($nonce, 'advnews_ajax_nonce')) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'advnews-manager'),
                'debug' => array('nonce_received' => $nonce ? 'YES' : 'NO')
            ));
        }

        $this->check_capability();

        $to = isset($_POST['test_email']) ? sanitize_email($_POST['test_email']) : get_option('admin_email');

        if (!is_email($to)) {
            wp_send_json_error(array('message' => __('Invalid email address.', 'advnews-manager')));
        }

        // Get SMTP settings
        $smtp_host = get_option('advnews_smtp_host');
        $smtp_port = get_option('advnews_smtp_port', 587);
        $smtp_encryption = get_option('advnews_smtp_encryption', 'tls');
        $smtp_username = get_option('advnews_smtp_username');
        $smtp_password = AdvNews_Security::decrypt(get_option('advnews_smtp_password', ''));
        $smtp_from_email = get_option('advnews_smtp_from_email');
        $smtp_from_name = get_option('advnews_smtp_from_name');
        $smtp_auth = get_option('advnews_smtp_authentication', 1);

        // Log SMTP configuration attempt
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews SMTP Test] ======================================');
            error_log('[AdvNews SMTP Test] Starting test to: ' . $to);
            error_log('[AdvNews SMTP Test] Host: ' . ($smtp_host ?: 'NOT SET'));
            error_log('[AdvNews SMTP Test] Port: ' . $smtp_port);
            error_log('[AdvNews SMTP Test] Encryption: ' . $smtp_encryption);
            error_log('[AdvNews SMTP Test] Username: ' . ($smtp_username ?: 'NOT SET'));
            error_log('[AdvNews SMTP Test] Auth: ' . ($smtp_auth ? 'Yes' : 'No'));
            error_log('[AdvNews SMTP Test] Password decrypted: ' . (empty($smtp_password) ? 'NO' : 'YES (length: ' . strlen($smtp_password) . ')'));

            // ProtonMail specific warning
            /*
            if (strpos($smtp_host, 'protonmail') !== false) {
                error_log('[AdvNews SMTP Test] ⚠️ PROTONMAIL DETECTED: Requires ProtonMail Bridge running locally');
                error_log('[AdvNews SMTP Test] Recommended: Use 127.0.0.1:1025 with Bridge, or switch to different SMTP provider');
            }
            */
        }

        if (empty($smtp_host)) {
            wp_send_json_error(array(
                'message' => __('SMTP Host is required. Please configure SMTP settings first.', 'advnews-manager'),
                'debug' => array('smtp_host' => 'empty')
            ));
        }

        // ProtonMail specific guidance
        /*
        if (strpos($smtp_host, 'protonmail') !== false && $smtp_port == 587) {
            wp_send_json_error(array(
                'message' => __('ProtonMail requires ProtonMail Bridge for SMTP access.', 'advnews-manager') . ' ' .
                            __('Please either:', 'advnews-manager') . ' ' .
                            __('1) Install and run ProtonMail Bridge (use localhost:1025), OR', 'advnews-manager') . ' ' .
                            __('2) Use a different SMTP provider (Brevo, SendGrid, Mailgun recommended)', 'advnews-manager'),
                'debug' => array(
                    'provider' => 'ProtonMail',
                    'issue' => 'ProtonMail Bridge required',
                    'solution' => 'Use localhost:1025 with Bridge or switch provider',
                    'recommended_providers' => array('Brevo (300/day free)', 'SendGrid (100/day free)', 'Mailgun (5000/month first month)')
                )
            ));
        }
        */

        // Store config in transient
        $smtp_config = array(
            'host' => $smtp_host,
            'port' => $smtp_port,
            'encryption' => $smtp_encryption,
            'username' => $smtp_username,
            'password' => $smtp_password,
            'auth' => $smtp_auth,
            'from_email' => $smtp_from_email,
            'from_name' => $smtp_from_name
        );

        set_transient('advnews_smtp_test_config', $smtp_config, 300);

        // Add hook with VERY HIGH priority
        add_action('phpmailer_init', array($this, 'configure_smtp_for_test'), 9999);

        $subject = __('Science180 Mail SMTP Test', 'advnews-manager');
        $message = __('This is a test email from Science180 Mail. If you receive this, your SMTP settings are working correctly.', 'advnews-manager');
        $message .= "\n\n" . sprintf(__('Test sent at: %s', 'advnews-manager'), current_time('mysql'));
        $message .= "\n\n" . sprintf(__('SMTP Host: %s', 'advnews-manager'), $smtp_host);
        $message .= "\n" . sprintf(__('SMTP Port: %s', 'advnews-manager'), $smtp_port);
        $message .= "\n" . sprintf(__('Encryption: %s', 'advnews-manager'), $smtp_encryption);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'MIME-Version: 1.0'
        );

        if (!empty($smtp_from_email)) {
            $headers[] = 'From: ' . $smtp_from_name . ' <' . $smtp_from_email . '>';
        } else {
            $headers[] = 'From: ' . get_option('admin_email');
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews SMTP Test] Calling wp_mail()...');
        }

        // debug the smtp key
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $decrypted_password = AdvNews_Security::decrypt($smtp_password);
            error_log('[AdvNews SMTP Test] Password before decrypt length: ' . strlen($smtp_password));
            error_log('[AdvNews SMTP Test] Password after decrypt length: ' . strlen($decrypted_password));
            error_log('[AdvNews SMTP Test] Password is_encrypted check: ' . (AdvNews_Security::is_encrypted($smtp_password) ? 'YES' : 'NO'));
        }

        // Enable PHPMailer debug output
        global $phpmailer;
        if (isset($phpmailer)) {
            $phpmailer->SMTPDebug = 3; // Detailed debug output
            $phpmailer->Debugoutput = function($str, $level) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[PHPMailer Debug] ' . $str);
                }
            };
        }

        $result = wp_mail($to, $subject, nl2br($message), $headers);

        // Clean up
        remove_action('phpmailer_init', array($this, 'configure_smtp_for_test'), 9999);
        delete_transient('advnews_smtp_test_config');

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews SMTP Test] wp_mail() result: ' . ($result ? 'SUCCESS' : 'FAILED'));

            // Get detailed error from PHPMailer
            if (isset($phpmailer) && !$result) {
                error_log('[AdvNews SMTP Test] PHPMailer ErrorInfo: ' . $phpmailer->ErrorInfo);
            }

            error_log('[AdvNews SMTP Test] ======================================');
        }

        if ($result) {
            set_transient('advnews_smtp_tested', true, DAY_IN_SECONDS);
            wp_send_json_success(array(
                'message' => __('Test email sent successfully. Please check your inbox.', 'advnews-manager'),
                'details' => array(
                    'host' => $smtp_host,
                    'port' => $smtp_port,
                    'encryption' => $smtp_encryption,
                    'to' => $to,
                    'auth' => $smtp_auth ? 'Yes' : 'No'
                )
            ));
        } else {
            global $wp_mail_error;
            $error_message = __('Failed to send test email.', 'advnews-manager');

            // Get detailed error from PHPMailer
            $phpmailer_error = '';
            if (isset($phpmailer)) {
                $phpmailer_error = $phpmailer->ErrorInfo;
            }

            // Provide specific guidance based on provider
            if (strpos($smtp_host, 'gmail') !== false && $smtp_port == 587) {
                $error_message .= ' ' . __('For Gmail, make sure you are using an App Password, not your regular password.', 'advnews-manager');
            } elseif (strpos($smtp_host, 'protonmail') !== false) {
                $error_message .= ' ' . __('ProtonMail requires ProtonMail Bridge. Use localhost:1025 or switch to a different SMTP provider.', 'advnews-manager');
            }

            $debug_info = array(
                'host' => $smtp_host,
                'port' => $smtp_port,
                'encryption' => $smtp_encryption,
                'wp_mail_error' => isset($wp_mail_error) ? $wp_mail_error->get_error_message() : 'Unknown',
                'phpmailer_error' => $phpmailer_error ?: 'Not available',
                'connection_time' => '7 seconds (timeout likely)'
            );

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews SMTP Test] Error details: ' . print_r($debug_info, true));
            }

            wp_send_json_error(array(
                'message' => $error_message,
                'debug' => $debug_info
            ));
        }
    }



    public function configure_smtp_for_test($phpmailer)
    {
        $config = get_transient('advnews_smtp_test_config');

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews SMTP Test] configure_smtp_for_test called');
            error_log('[AdvNews SMTP Test] Config retrieved: ' . (empty($config) ? 'NO' : 'YES'));
        }

        if (!$config || empty($config['host'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews SMTP Test] No config found, skipping SMTP configuration');
            }
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews SMTP Test] Configuring PHPMailer...');
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = $config['host'];
        $phpmailer->Port = $config['port'];
        $phpmailer->SMTPAuth = (bool) $config['auth'];

        if ($config['encryption'] === 'ssl') {
            $phpmailer->SMTPSecure = 'ssl';
            $phpmailer->SMTPAutoTLS = false;
        } elseif ($config['encryption'] === 'tls') {
            $phpmailer->SMTPSecure = 'tls';
            $phpmailer->SMTPAutoTLS = false;
        } else {
            $phpmailer->SMTPSecure = '';
            $phpmailer->SMTPAutoTLS = false;
        }

        if ($config['auth'] && !empty($config['username']) && !empty($config['password'])) {
            $phpmailer->Username = $config['username'];
            $phpmailer->Password = $config['password'];
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews SMTP Test] Credentials set');
            }
        }

        // FIXED: Always set From address with fallbacks
        $from_email = !empty($config['from_email']) ? $config['from_email'] : $config['username'];
        if (empty($from_email)) {
            $from_email = get_option('admin_email');
        }

        if (!empty($from_email) && is_email($from_email)) {
            $phpmailer->From = $from_email;
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews SMTP Test] From address set to: ' . $from_email);
            }
        }

        // Set From name
        $from_name = !empty($config['from_name']) ? $config['from_name'] : get_option('advnews_from_name', get_bloginfo('name'));
        if (!empty($from_name)) {
            $phpmailer->FromName = $from_name;
        }

        $phpmailer->SMTPKeepAlive = false;
        $phpmailer->SingleTo = false;

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews SMTP Test] PHPMailer configuration complete');
        }
    }




    public function configure_smtp($phpmailer)
    {
        $phpmailer->isSMTP();
        $phpmailer->Host = get_option('advnews_smtp_host');
        $phpmailer->Port = get_option('advnews_smtp_port', 587);
        $encryption = get_option('advnews_smtp_encryption', 'tls');

        if ($encryption !== 'none') {
            $phpmailer->SMTPSecure = $encryption;
        }

        if (get_option('advnews_smtp_authentication', 1)) {
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = get_option('advnews_smtp_username');
            $phpmailer->Password = AdvNews_Security::decrypt(get_option('advnews_smtp_password', ''));
        }

        $from_email = get_option('advnews_smtp_from_email');
        if (!empty($from_email)) {
            $phpmailer->From = $from_email;
        }

        $from_name = get_option('advnews_smtp_from_name');
        if (!empty($from_name)) {
            $phpmailer->FromName = $from_name;
        }
    }

    public function ajax_test_cron()
    {
        $this->verify_nonce();
        $this->check_capability();

        $cron_status = array(
            'wp_cron' => defined('DISABLE_WP_CRON') ? !DISABLE_WP_CRON : true,
            'next_queue' => wp_next_scheduled('advnews_process_queue'),
            'next_maintenance' => wp_next_scheduled('advnews_daily_maintenance'),
            'next_reports' => wp_next_scheduled('advnews_weekly_reports')
        );

        $message = __('Cron appears to be working.', 'advnews-manager');
        if (!$cron_status['next_queue']) {
            $message = __('Queue cron job is not scheduled. Please deactivate and reactivate the plugin.', 'advnews-manager');
        }

        wp_send_json_success(array(
            'message' => $message,
            'details' => $cron_status
        ));
    }

    public function ajax_save_settings()
    {
        $this->verify_nonce();
        $this->check_capability();

        $settings = AdvNews_Security::sanitize_array($_POST);

        foreach ($settings as $key => $value) {
            if (strpos($key, 'advnews_') === 0) {
                update_option($key, $value);
            }
        }

        wp_send_json_success(array(
            'message' => __('Settings saved successfully.', 'advnews-manager')
        ));
    }

    public function ajax_clear_tracking_data()
    {
        $this->verify_nonce();
        $this->check_capability();

        $retention_days = get_option('advnews_tracking_retention_days', 365);
        $cutoff_date = date('Y-m-d', strtotime("-$retention_days days"));

        $tables = array(
            'tracking_opens' => 'opened_at',
            'tracking_clicks' => 'clicked_at',
            'activity_log' => 'created_at'
        );

        $total = 0;
        foreach ($tables as $table => $date_column) {
            $table_name = $this->wpdb->prefix . $this->table_prefix . $table;
            $count = $this->wpdb->query($this->wpdb->prepare(
                "DELETE FROM $table_name WHERE $date_column < %s",
                $cutoff_date
            ));
            if ($count) {
                $total += $count;
            }
        }

        wp_send_json_success(array(
            'message' => sprintf(__('Cleaned up %d old tracking records.', 'advnews-manager'), $total)
        ));
    }

    public function ajax_test_subscription()
    {
        $this->verify_nonce();
        $this->check_capability();

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email address.', 'advnews-manager')));
        }

        $subscriber_class = new AdvNews_Subscriber();
        $data = array(
            'email' => $email,
            'first_name' => 'Test',
            'last_name' => 'User',
            'send_welcome' => false
        );

        $existing_subscriber = $subscriber_class->get_subscriber_by_email($email);
        $created_subscriber = false;

        if ($existing_subscriber) {
            $subscriber_id = intval($existing_subscriber->id);

            if ($existing_subscriber->status === 'unsubscribed') {
                $result = $subscriber_class->resubscribe($subscriber_id, $data, false);
                if (is_wp_error($result)) {
                    wp_send_json_error(array('message' => $result->get_error_message()));
                }
            }
        } else {
            $subscriber_id = $subscriber_class->add_subscriber($data);
            if (is_wp_error($subscriber_id)) {
                wp_send_json_error(array('message' => $subscriber_id->get_error_message()));
            }
            $created_subscriber = true;
        }

        $mail_result = $this->send_subscription_flow_test_email($subscriber_id);

        if (is_wp_error($mail_result)) {
            wp_send_json_error(array(
                'message' => sprintf(
                    __('The test subscriber was %1$s, but the email could not be sent: %2$s', 'advnews-manager'),
                    $created_subscriber ? __('created', 'advnews-manager') : __('found', 'advnews-manager'),
                    $mail_result->get_error_message()
                )
            ));
        }

        wp_send_json_success(array(
            'message' => sprintf(
                __('Test subscription successful. A %1$s email was sent to %2$s.', 'advnews-manager'),
                $mail_result['type'],
                $email
            )
        ));
    }

    private function send_subscription_flow_test_email($subscriber_id)
    {
        $subscriber_id = absint($subscriber_id);
        if (!$subscriber_id) {
            return new WP_Error('invalid_subscriber', __('Invalid subscriber record.', 'advnews-manager'));
        }

        $table_subscribers = $this->wpdb->prefix . $this->table_prefix . 'subscribers';
        $subscriber = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table_subscribers WHERE id = %d",
            $subscriber_id
        ));

        if (!$subscriber || !is_email($subscriber->email)) {
            return new WP_Error('subscriber_not_found', __('Subscriber record was not found.', 'advnews-manager'));
        }

        $headers = array('Content-Type: text/html; charset=UTF-8');

        if (get_option('advnews_double_optin')) {
            $mail = $this->build_subscription_confirmation_test_email($subscriber);
        } else {
            $mail = $this->build_subscription_welcome_test_email($subscriber);
        }

        $mail_error = null;
        $capture_error = function ($wp_error) use (&$mail_error) {
            $mail_error = $wp_error;
        };

        add_action('wp_mail_failed', $capture_error);
        $sent = wp_mail($subscriber->email, $mail['subject'], $mail['message'], $headers);
        remove_action('wp_mail_failed', $capture_error);

        if (!$sent) {
            $message = __('WordPress returned false while sending the test email.', 'advnews-manager');
            if ($mail_error instanceof WP_Error) {
                $message = $mail_error->get_error_message();
            }

            return new WP_Error('mail_failed', $message);
        }

        return array(
            'type' => $mail['type']
        );
    }

    private function build_subscription_confirmation_test_email($subscriber)
    {
        if (empty($subscriber->confirmation_token)) {
            $subscriber->confirmation_token = AdvNews_Security::generate_hash($subscriber->email . time());
            $this->wpdb->update(
                $this->wpdb->prefix . $this->table_prefix . 'subscribers',
                array('confirmation_token' => $subscriber->confirmation_token),
                array('id' => $subscriber->id)
            );
        }

        $confirmation_link = add_query_arg(array(
            'action' => 'confirm_subscription',
            'token' => $subscriber->confirmation_token,
            'email' => rawurlencode($subscriber->email)
        ), home_url());

        $subject = __('Confirm Your Subscription', 'advnews-manager');
        $message = sprintf(
            '<p>%s</p><p><a href="%s" style="display:inline-block;padding:12px 18px;background:#2271b1;color:#fff;text-decoration:none;border-radius:4px;">%s</a></p><p>%s</p>',
            esc_html__('Hello! Please confirm your subscription by clicking the button below.', 'advnews-manager'),
            esc_url($confirmation_link),
            esc_html__('Confirm Subscription', 'advnews-manager'),
            esc_html($confirmation_link)
        );

        return array(
            'type' => __('confirmation', 'advnews-manager'),
            'subject' => $subject,
            'message' => $message
        );
    }

    private function build_subscription_welcome_test_email($subscriber)
    {
        $template = $this->get_subscription_test_template(get_option('advnews_welcome_template', 0));

        if ($template && class_exists('AdvNews_Campaign')) {
            $campaign = new AdvNews_Campaign();
            $subscriber_data = $this->get_subscription_test_merge_data($subscriber);

            return array(
                'type' => __('welcome template', 'advnews-manager'),
                'subject' => $campaign->process_merge_tags($template->subject, $subscriber_data),
                'message' => $campaign->prepare_email_content(
                    $campaign->process_merge_tags($template->content, $subscriber_data)
                )
            );
        }

        $message = '<p>' . esc_html__('Thank you for subscribing to our newsletter!', 'advnews-manager') . '</p>';

        if (!get_option('advnews_welcome_email', false)) {
            $message .= '<p><em>' . esc_html__('Admin note: welcome emails are currently disabled, so live new subscribers will not receive this fallback welcome email until that setting is enabled.', 'advnews-manager') . '</em></p>';
        }

        return array(
            'type' => __('welcome test', 'advnews-manager'),
            'subject' => __('Welcome to Our Newsletter!', 'advnews-manager'),
            'message' => $message
        );
    }

    private function get_subscription_test_template($template_id)
    {
        $template_id = absint($template_id);
        if (!$template_id) {
            return null;
        }

        $table_templates = $this->wpdb->prefix . $this->table_prefix . 'templates';

        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table_templates WHERE id = %d AND is_active = 1",
            $template_id
        ));
    }

    private function get_subscription_test_merge_data($subscriber)
    {
        return array(
            'subscriber_id' => $subscriber->id,
            'email' => $subscriber->email,
            'first_name' => $subscriber->first_name,
            'last_name' => $subscriber->last_name,
            'full_name' => trim($subscriber->first_name . ' ' . $subscriber->last_name),
            'organization' => $subscriber->organization,
            'title' => $subscriber->title,
            'website_url' => $subscriber->website_url,
            'description' => $subscriber->description,
            'country' => $subscriber->country,
            'status' => $subscriber->status,
            'categories' => '',
            'subscribed_date' => !empty($subscriber->subscribed_at) ? date_i18n(get_option('date_format'), strtotime($subscriber->subscribed_at)) : ''
        );
    }

    // =====================================================
    // DASHBOARD AJAX HANDLERS
    // =====================================================

    public function ajax_refresh_dashboard()
    {
        $this->verify_nonce();
        $this->check_capability();

        $admin = new AdvNews_Admin();
        $stats = $admin->get_dashboard_stats();

        wp_send_json_success($stats);
    }


    // =====================================================
    // FRONTEND AJAX HANDLERS
    // =====================================================

    public function ajax_frontend_subscribe()
    {
        $this->verify_public_nonce('advnews_frontend_subscribe');
        $data = AdvNews_Security::sanitize_array($_POST);

        if (empty($data['email'])) {
            wp_send_json_error(array('message' => __('Email address is required.', 'advnews-manager')));
        }

        $email = AdvNews_Security::validate_email($data['email']);
        if (!$email) {
            wp_send_json_error(array('message' => __('Invalid email address.', 'advnews-manager')));
        }

        if (get_option('advnews_gdpr_compliance') && get_option('advnews_consent_checkbox')) {
            if (empty($data['consent'])) {
                wp_send_json_error(array('message' => __('You must agree to the privacy policy.', 'advnews-manager')));
            }
        }

        $subscriber_data = array(
            'email' => $email,
            'first_name' => isset($data['first_name']) ? $data['first_name'] : '',
            'last_name' => isset($data['last_name']) ? $data['last_name'] : '',
            'ip_address' => AdvNews_Security::get_client_ip()
        );

        if (!empty($data['categories'])) {
            $subscriber_data['categories'] = $data['categories'];
        }

        $subscriber_class = new AdvNews_Subscriber();
        $result = $subscriber_class->add_subscriber($subscriber_data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        if (get_option('advnews_gdpr_compliance')) {
            $this->log_consent($email, 'subscribed', $data);
        }

        $redirect = isset($data['redirect']) ? esc_url_raw($data['redirect']) : '';

        wp_send_json_success(array(
            'message' => __('Thank you for subscribing! Please check your email to confirm your subscription.', 'advnews-manager'),
            'redirect' => $redirect
        ));
    }

    public function ajax_frontend_unsubscribe_request()
    {
        $this->verify_public_nonce('advnews_frontend_unsubscribe');
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email address.', 'advnews-manager')));
        }

        $token = wp_hash($email . 'unsubscribe' . wp_salt());
        set_transient('advnews_unsubscribe_' . $token, $email, DAY_IN_SECONDS);

        $unsubscribe_page = get_permalink(get_option('advnews_unsubscribe_page_id'));
        $confirm_url = add_query_arg(array(
            'token' => $token,
            'email' => urlencode($email)
        ), $unsubscribe_page);

        $subject = __('Confirm Unsubscribe', 'advnews-manager');
        $message = sprintf(
            __('Click this link to confirm your unsubscribe: %s', 'advnews-manager'),
            $confirm_url
        );

        wp_mail($email, $subject, $message);

        wp_send_json_success(array(
            'message' => __('Check your email for the unsubscribe confirmation link.', 'advnews-manager')
        ));
    }

    public function ajax_frontend_unsubscribe()
    {
        $this->verify_public_nonce('advnews_frontend_unsubscribe');
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';
        $other_reason = isset($_POST['other_reason']) ? sanitize_text_field($_POST['other_reason']) : '';

        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email address.', 'advnews-manager')));
        }

        if ($token) {
            $stored_email = get_transient('advnews_unsubscribe_' . $token);
            if ($stored_email !== $email) {
                wp_send_json_error(array('message' => __('Invalid or expired unsubscribe link.', 'advnews-manager')));
            }
            delete_transient('advnews_unsubscribe_' . $token);
        }

        $final_reason = $reason === 'other' ? $other_reason : $reason;
        $subscriber_class = new AdvNews_Subscriber();
        $result = $subscriber_class->unsubscribe($email, $final_reason);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $this->log_consent($email, 'unsubscribed', array('reason' => $final_reason));

        wp_send_json_success(array(
            'message' => __('You have been successfully unsubscribed.', 'advnews-manager'),
            'reload' => true
        ));
    }

    public function ajax_frontend_update_preferences()
    {
        $this->verify_public_nonce('advnews_frontend_update_preferences');
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';

        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email address.', 'advnews-manager')));
        }

        if (!is_user_logged_in() && $token) {
            $stored_email = get_transient('advnews_manage_' . $token);
            if ($stored_email !== $email) {
                wp_send_json_error(array('message' => __('Invalid or expired access link.', 'advnews-manager')));
            }
        }

        $subscriber_class = new AdvNews_Subscriber();
        $subscriber = $subscriber_class->get_subscriber_by_email($email);

        if (!$subscriber) {
            wp_send_json_error(array('message' => __('Subscriber not found.', 'advnews-manager')));
        }

        $update_data = array();
        if (isset($_POST['first_name'])) {
            $update_data['first_name'] = sanitize_text_field($_POST['first_name']);
        }
        if (isset($_POST['last_name'])) {
            $update_data['last_name'] = sanitize_text_field($_POST['last_name']);
        }
        if (isset($_POST['organization'])) {
            $update_data['organization'] = sanitize_text_field($_POST['organization']);
        }

        if (!empty($update_data)) {
            $subscriber_class->update_subscriber($subscriber->id, $update_data);
        }

        if (isset($_POST['categories'])) {
            $categories = array_map('intval', (array)$_POST['categories']);
            $subscriber_class->add_categories_to_subscriber($subscriber->id, $categories);
        }

        if (isset($_POST['frequency'])) {
            update_user_meta($subscriber->id, 'email_frequency', sanitize_text_field($_POST['frequency']));
        }

        wp_send_json_success(array(
            'message' => __('Your preferences have been updated successfully.', 'advnews-manager'),
            'status' => $subscriber->status
        ));
    }

    public function ajax_frontend_resubscribe()
    {
        $this->verify_public_nonce('advnews_frontend_resubscribe');
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email address.', 'advnews-manager')));
        }

        $subscriber_class = new AdvNews_Subscriber();
        $subscriber = $subscriber_class->get_subscriber_by_email($email);

        if (!$subscriber) {
            wp_send_json_error(array('message' => __('Subscriber not found.', 'advnews-manager')));
        }

        $result = $subscriber_class->resubscribe($subscriber->id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $this->log_consent($email, 'resubscribed', array());

        wp_send_json_success(array(
            'message' => __('You have been successfully resubscribed.', 'advnews-manager')
        ));
    }

    public function ajax_frontend_export_data()
    {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'advnews_frontend_export')) {
            wp_die(__('Security check failed.', 'advnews-manager'));
        }

        $email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
        if (!is_email($email)) {
            wp_die(__('Invalid email address.', 'advnews-manager'));
        }

        $data = advnews_export_subscriber_data($email);
        if (!$data) {
            wp_die(__('No data found for this email.', 'advnews-manager'));
        }

        $filename = 'subscriber-data-' . sanitize_title($email) . '-' . date('Y-m-d') . '.json';
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename=' . $filename);
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    public function ajax_frontend_delete_data()
    {
        $this->verify_public_nonce('advnews_frontend_delete_data');
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email address.', 'advnews-manager')));
        }

        $result = advnews_delete_subscriber_data($email);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to delete data.', 'advnews-manager')));
        }

        wp_send_json_success(array(
            'message' => __('Your data has been anonymized successfully.', 'advnews-manager')
        ));
    }

    // =====================================================
    // TRACKING AJAX HANDLERS (Public)
    // =====================================================

    public function ajax_track_open()
    {
        $log_id = isset($_GET['log_id']) ? intval($_GET['log_id']) : 0;
        $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
        $subscriber_id = isset($_GET['subscriber_id']) ? intval($_GET['subscriber_id']) : 0;
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

        $expected_token = AdvNews_Security::generate_hash($log_id . $campaign_id . $subscriber_id);

        if ($token !== $expected_token) {
            status_header(403);
            $this->serve_tracking_pixel();
            exit;
        }

        $tracking_class = new AdvNews_Tracking();
        $tracking_class->record_open($log_id, $campaign_id, $subscriber_id);

        $this->serve_tracking_pixel();
        exit;
    }

    public function ajax_track_click()
    {
        $hash = isset($_GET['hash']) ? sanitize_text_field($_GET['hash']) : '';
        $log_id = isset($_GET['log_id']) ? intval($_GET['log_id']) : 0;
        $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;

        if (!$hash || !$log_id || !$campaign_id) {
            wp_redirect(home_url());
            exit;
        }

        $tracking_class = new AdvNews_Tracking();
        $url = $tracking_class->record_click($hash, $log_id, $campaign_id);

        if ($url) {
            wp_redirect($url);
        } else {
            wp_redirect(home_url());
        }
        exit;
    }

    public function ajax_track_event()
    {
        $this->verify_public_nonce('advnews_frontend_ajax');
        $event = isset($_POST['event']) ? sanitize_text_field($_POST['event']) : '';
        $data = isset($_POST['data']) ? $_POST['data'] : array();

        if (!empty($event)) {
            // Store in database or analytics service
        }

        wp_send_json_success();
    }

    private function serve_tracking_pixel()
    {
        header('Content-Type: image/gif');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    }

    // =====================================================
    // CATEGORY AJAX HANDLERS
    // =====================================================

    public function ajax_get_categories()
    {
        $this->verify_nonce();
        $this->check_capability();

        $table_name = $this->wpdb->prefix . $this->table_prefix . 'categories';
        $categories = $this->wpdb->get_results("SELECT * FROM $table_name ORDER BY name");

        wp_send_json_success(array(
            'categories' => $categories
        ));
    }

    public function ajax_save_category()
    {
        $this->verify_nonce();
        $this->check_capability();

        $table_name = $this->wpdb->prefix . $this->table_prefix . 'categories';
        $category_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'slug' => sanitize_title($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'color' => sanitize_hex_color($_POST['color'])
        );

        if ($category_id) {
            $result = $this->wpdb->update($table_name, $data, array('id' => $category_id));
            $message = __('Category updated successfully.', 'advnews-manager');
        } else {
            $result = $this->wpdb->insert($table_name, $data);
            $category_id = $this->wpdb->insert_id;
            $message = __('Category created successfully.', 'advnews-manager');
        }

        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to save category.', 'advnews-manager')));
        }

        wp_send_json_success(array(
            'message' => $message,
            'category_id' => $category_id
        ));
    }

    public function ajax_delete_category()
    {
        $this->verify_nonce();
        $this->check_capability();

        $table_name = $this->wpdb->prefix . $this->table_prefix . 'categories';
        $category_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        $result = $this->wpdb->delete($table_name, array('id' => $category_id));

        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to delete category.', 'advnews-manager')));
        }

        wp_send_json_success(array(
            'message' => __('Category deleted successfully.', 'advnews-manager')
        ));
    }

    // =====================================================
    // IMPORT/EXPORT AJAX HANDLERS
    // =====================================================

    public function ajax_preview_export()
    {
        $this->verify_nonce();
        $this->check_capability();

        $args = array(
            'limit' => 10,
            'offset' => 0
        );
        if (isset($_POST['status']) && $_POST['status']) {
            $args['status'] = sanitize_text_field($_POST['status']);
        }
        if (isset($_POST['category_ids']) && is_array($_POST['category_ids'])) {
            $category_ids = array_values(array_unique(array_filter(array_map('intval', $_POST['category_ids']))));
            if (!empty($category_ids)) {
                $args['category_ids'] = $category_ids;
            }
        } elseif (isset($_POST['category_id']) && $_POST['category_id']) {
            $args['category_id'] = intval($_POST['category_id']);
        }
        if (isset($_POST['search']) && $_POST['search']) {
            $args['search'] = sanitize_text_field($_POST['search']);
        }
        if (isset($_POST['date_from']) && $_POST['date_from']) {
            $args['date_from'] = sanitize_text_field($_POST['date_from']);
        }
        if (isset($_POST['date_to']) && $_POST['date_to']) {
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

    public function ajax_schedule_export()
    {
        $this->verify_nonce();
        $this->check_capability();

        $schedule = isset($_POST['schedule']) ? sanitize_text_field($_POST['schedule']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();

        if (!in_array($schedule, array('daily', 'weekly', 'monthly'))) {
            wp_send_json_error(array('message' => __('Invalid schedule.', 'advnews-manager')));
        }

        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email address.', 'advnews-manager')));
        }

        $scheduled_exports = get_option('advnews_scheduled_exports', array());
        $scheduled_exports[] = array(
            'id' => uniqid(),
            'schedule' => $schedule,
            'email' => $email,
            'filters' => $filters,
            'created_at' => current_time('mysql')
        );

        update_option('advnews_scheduled_exports', $scheduled_exports);

        wp_send_json_success(array(
            'message' => __('Export scheduled successfully.', 'advnews-manager')
        ));
    }

    // =====================================================
    // CRON AJAX HANDLERS
    // =====================================================

    public function ajax_run_cron_task()
    {
        $this->verify_nonce();
        $this->check_capability();

        $hook = isset($_POST['hook']) ? sanitize_text_field($_POST['hook']) : '';

        switch ($hook) {
            case 'advnews_process_queue':
                require_once ADVNEWS_PLUGIN_DIR . 'cron/process-queue.php';
                $processor = new AdvNews_Queue_Processor();
                $result = $processor->execute();
                $message = $result['message'];
                break;
            case 'advnews_daily_maintenance':
                require_once ADVNEWS_PLUGIN_DIR . 'cron/daily-maintenance.php';
                $maintenance = new AdvNews_Daily_Maintenance();
                $result = $maintenance->execute();
                $message = __('Daily maintenance completed.', 'advnews-manager');
                break;
            case 'advnews_weekly_reports':
                require_once ADVNEWS_PLUGIN_DIR . 'cron/weekly-reports.php';
                $reports = new AdvNews_Weekly_Reports();
                $result = $reports->execute();
                $message = $result['message'];
                break;
            default:
                wp_send_json_error(array('message' => __('Invalid cron task.', 'advnews-manager')));
                return;
        }

        wp_send_json_success(array(
            'message' => $message
        ));
    }

    public function ajax_check_cron()
    {
        $this->verify_nonce();
        $this->check_capability();

        $cron_jobs = array(
            'advnews_process_queue' => wp_next_scheduled('advnews_process_queue'),
            'advnews_daily_maintenance' => wp_next_scheduled('advnews_daily_maintenance'),
            'advnews_weekly_reports' => wp_next_scheduled('advnews_weekly_reports')
        );

        $status = array();
        $all_ok = true;

        foreach ($cron_jobs as $job => $next_run) {
            if ($next_run) {
                $status[$job] = array(
                    'status' => 'ok',
                    'next_run' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_run),
                    'in' => human_time_diff($next_run, current_time('timestamp')) . ' ' . __('from now', 'advnews-manager')
                );
            } else {
                $status[$job] = array(
                    'status' => 'error',
                    'message' => __('Not scheduled', 'advnews-manager')
                );
                $all_ok = false;
            }
        }

        if ($all_ok) {
            $message = __('All cron jobs are scheduled correctly.', 'advnews-manager');
        } else {
            $message = __('Some cron jobs are missing. Please deactivate and reactivate the plugin.', 'advnews-manager');
        }

        wp_send_json_success(array(
            'message' => $message,
            'details' => $status
        ));
    }

    public function ajax_schedule_task()
    {
        $this->verify_nonce();
        $this->check_capability();

        $hook = isset($_POST['hook']) ? sanitize_text_field($_POST['hook']) : '';

        switch ($hook) {
            case 'advnews_process_queue':
                wp_clear_scheduled_hook('advnews_process_queue');
                wp_schedule_event(time() + MINUTE_IN_SECONDS, 'advnews_every_minute', 'advnews_process_queue');
                break;
            case 'advnews_daily_maintenance':
                wp_clear_scheduled_hook('advnews_daily_maintenance');
                wp_schedule_event(AdvNews_Cron::next_daily_maintenance_timestamp(), 'daily', 'advnews_daily_maintenance');
                break;
            case 'advnews_weekly_reports':
                wp_clear_scheduled_hook('advnews_weekly_reports');
                wp_schedule_event(AdvNews_Cron::next_weekly_report_run(), 'weekly', 'advnews_weekly_reports');
                break;
            case 'advnews_update_maxmind_database':
                wp_clear_scheduled_hook('advnews_update_maxmind_database');
                wp_schedule_event(AdvNews_Cron::next_maxmind_update_timestamp(), 'daily', 'advnews_update_maxmind_database');
                break;
            default:
                wp_send_json_error(array('message' => __('Invalid task.', 'advnews-manager')));
                return;
        }

        wp_send_json_success(array(
            'message' => __('Task scheduled successfully.', 'advnews-manager')
        ));
    }

    public function ajax_unschedule_task()
    {
        $this->verify_nonce();
        $this->check_capability();

        $hook = isset($_POST['hook']) ? sanitize_text_field($_POST['hook']) : '';
        $timestamp = wp_next_scheduled($hook);

        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
        }

        wp_send_json_success(array(
            'message' => __('Task unscheduled successfully.', 'advnews-manager')
        ));
    }

    // =====================================================
    // GDPR AJAX HANDLERS
    // =====================================================

    public function ajax_export_subscriber_gdpr()
    {
        $this->verify_nonce();
        $this->check_capability();

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email address.', 'advnews-manager')));
        }

        $data = advnews_export_subscriber_data($email);
        if (!$data) {
            wp_send_json_error(array('message' => __('No data found for this email.', 'advnews-manager')));
        }

        wp_send_json_success($data);
    }

    public function ajax_anonymize_subscriber()
    {
        $this->verify_nonce();
        $this->check_capability();

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email address.', 'advnews-manager')));
        }

        $result = advnews_delete_subscriber_data($email);
        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to anonymize data.', 'advnews-manager')));
        }

        wp_send_json_success(array(
            'message' => __('Subscriber data anonymized successfully.', 'advnews-manager')
        ));
    }

    public function ajax_get_consent_log()
    {
        $this->verify_nonce();
        $this->check_capability();

        $table_consent = $this->wpdb->prefix . $this->table_prefix . 'consent_log';
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '$table_consent'") == $table_consent;

        if (!$table_exists) {
            wp_send_json_success(array());
            return;
        }

        $logs = $this->wpdb->get_results(
            "SELECT * FROM $table_consent
            ORDER BY created_at DESC
            LIMIT 100"
        );

        wp_send_json_success($logs);
    }

    private function log_consent($email, $action, $data = array())
    {
        $table_consent = $this->wpdb->prefix . $this->table_prefix . 'consent_log';
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '$table_consent'") == $table_consent;

        if (!$table_exists) {
            return;
        }

        $subscriber_class = new AdvNews_Subscriber();
        $subscriber = $subscriber_class->get_subscriber_by_email($email);

        $this->wpdb->insert(
            $table_consent,
            array(
                'email' => $email,
                'subscriber_id' => $subscriber ? $subscriber->id : null,
                'action' => $action,
                'ip_address' => AdvNews_Security::get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'data' => json_encode($data),
                'created_at' => current_time('mysql')
            )
        );
    }

    public function ajax_cancel_scheduled_export()
    {
        $this->verify_nonce();
        $this->check_capability();

        $export_id = isset($_POST['export_id']) ? sanitize_text_field($_POST['export_id']) : '';
        if (empty($export_id)) {
            wp_send_json_error(array('message' => __('Invalid export ID.', 'advnews-manager')));
        }

        $scheduled_exports = get_option('advnews_scheduled_exports', array());
        foreach ($scheduled_exports as $key => $export) {
            if ($export['id'] === $export_id) {
                unset($scheduled_exports[$key]);
                break;
            }
        }
        $scheduled_exports = array_values($scheduled_exports);
        update_option('advnews_scheduled_exports', $scheduled_exports);

        wp_send_json_success(array(
            'message' => __('Scheduled export cancelled successfully.', 'advnews-manager')
        ));
    }
    /**
    * AJAX handler for manual MaxMind database update
    */
    public function ajax_update_maxmind_db() {
        $this->verify_nonce();
        $this->check_capability();

        $license_key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : get_option('advnews_maxmind_license_key', '');

        if (empty($license_key)) {
            wp_send_json_error(array(
                'message' => __('MaxMind License Key is required.', 'advnews-manager')
            ));
        }

        if (isset($_POST['license_key'])) {
            update_option('advnews_maxmind_license_key', $license_key);
        }

        update_option('advnews_maxmind_last_attempt', time());
        $tracking = new AdvNews_Tracking();
        $result = $tracking->update_maxmind_database_safely($license_key);

        if (is_wp_error($result)) {
            update_option('advnews_maxmind_last_error', $result->get_error_message());
            wp_send_json_error(array(
                'message' => __('Update failed: ', 'advnews-manager') . $result->get_error_message()
            ));
        }

        delete_option('advnews_maxmind_last_error');
        wp_send_json_success(array(
            'message' => $result['message'],
            'path' => $result['path']
        ));
    }

    /**
    * Helper to delete a directory recursively
    */
    private function delete_directory($dir) {
        if (!is_dir($dir)) return;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            @$todo($fileinfo->getRealPath());
        }
        @rmdir($dir);
    }
    /**
    * Dismiss admin notice permanently
    */
    public function ajax_dismiss_notice()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'advnews_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'advnews-manager')));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'advnews-manager')));
        }

        $notice = isset($_POST['notice']) ? sanitize_text_field($_POST['notice']) : '';

        if ($notice === 'smtp_test') {
            update_option('advnews_smtp_test_notice_dismissed', true);
            wp_send_json_success(array('message' => __('Notice dismissed.', 'advnews-manager')));
        }

        wp_send_json_error(array('message' => __('Invalid notice.', 'advnews-manager')));
    }
}
