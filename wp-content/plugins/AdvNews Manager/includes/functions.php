<?php
// File: includes/functions.php

/**
 * Get plugin instance
 */
function advnews_manager() {
    return AdvNews_Manager::get_instance();
}

/**
 * Add subscriber via shortcode or function
 */
function advnews_add_subscriber($email, $first_name = '', $last_name = '', $categories = array()) {
    $subscriber_class = new AdvNews_Subscriber();
    $data = array(
        'email' => $email,
        'first_name' => $first_name,
        'last_name' => $last_name
    );
    if (!empty($categories)) {
        $data['categories'] = $categories;
    }
    return $subscriber_class->add_subscriber($data);
}

/**
 * Unsubscribe subscriber
 */
function advnews_unsubscribe($email, $reason = '') {
    $subscriber_class = new AdvNews_Subscriber();
    return $subscriber_class->unsubscribe($email, $reason);
}

/**
 * Get subscriber count
 */
function advnews_get_subscriber_count($status = 'active') {
    $subscriber_class = new AdvNews_Subscriber();
    return $subscriber_class->count_subscribers(array('status' => $status));
}

/**
 * Shortcode: Subscription form
 */
function advnews_subscription_form_shortcode($atts) {
    $atts = shortcode_atts(array(
        'categories' => '',
        'show_name' => 'yes',
        'redirect' => ''
    ), $atts);
    ob_start();
    ?>
    <form class="advnews-subscription-form" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
        <input type="hidden" name="action" value="advnews_add_subscriber">
        <?php AdvNews_Security::create_nonce_field('advnews_ajax_nonce'); ?>
        <?php if ($atts['show_name'] === 'yes'): ?>
            <p>
                <label for="first_name"><?php _e('First Name', 'advnews-manager'); ?></label>
                <input type="text" id="first_name" name="first_name">
            </p>
            <p>
                <label for="last_name"><?php _e('Last Name', 'advnews-manager'); ?></label>
                <input type="text" id="last_name" name="last_name">
            </p>
        <?php endif; ?>
        <p>
            <label for="email"><?php _e('Email Address', 'advnews-manager'); ?> *</label>
            <input type="email" id="email" name="email" required>
        </p>
        <?php if ($atts['categories']): ?>
            <input type="hidden" name="categories" value="<?php echo esc_attr($atts['categories']); ?>">
        <?php endif; ?>
        <?php if ($atts['redirect']): ?>
            <input type="hidden" name="redirect" value="<?php echo esc_attr($atts['redirect']); ?>">
        <?php endif; ?>
        <p>
            <input type="submit" value="<?php _e('Subscribe', 'advnews-manager'); ?>">
        </p>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('advnews_subscribe', 'advnews_subscription_form_shortcode');

/**
 * Shortcode: Unsubscribe form
 */
function advnews_unsubscribe_form_shortcode($atts) {
    $atts = shortcode_atts(array(
        'show_reason' => 'yes'
    ), $atts);
    $email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
    $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
    // Verify token
    if ($email && $token) {
        $transient_name = 'advnews_unsubscribe_' . $token;
        $stored_email = get_transient($transient_name);
        if ($stored_email === $email) {
            // Token is valid, show confirmation form
            ob_start();
            ?>
            <div class="advnews-unsubscribe-confirm">
                <h3><?php _e('Confirm Unsubscribe', 'advnews-manager'); ?></h3>
                <p><?php printf(__('You are about to unsubscribe %s from our newsletter.', 'advnews-manager'), esc_html($email)); ?></p>
                <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
                    <input type="hidden" name="action" value="advnews_unsubscribe">
                    <?php AdvNews_Security::create_nonce_field('advnews_ajax_nonce'); ?>
                    <input type="hidden" name="email" value="<?php echo esc_attr($email); ?>">
                    <?php if ($atts['show_reason'] === 'yes'): ?>
                        <p>
                            <label for="reason"><?php _e('Reason for unsubscribing (optional):', 'advnews-manager'); ?></label>
                            <select id="reason" name="reason">
                                <option value=""><?php _e('Select a reason', 'advnews-manager'); ?></option>
                                <option value="too_frequent"><?php _e('Emails too frequent', 'advnews-manager'); ?></option>
                                <option value="not_relevant"><?php _e('Content not relevant', 'advnews-manager'); ?></option>
                                <option value="technical_issue"><?php _e('Technical issue', 'advnews-manager'); ?></option>
                                <option value="other"><?php _e('Other', 'advnews-manager'); ?></option>
                            </select>
                        </p>
                    <?php endif; ?>
                    <p>
                        <input type="submit" value="<?php _e('Confirm Unsubscribe', 'advnews-manager'); ?>">
                    </p>
                </form>
            </div>
            <?php
            return ob_get_clean();
        }
    }
    // Show email input form
    ob_start();
    ?>
    <form class="advnews-unsubscribe-form" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
        <input type="hidden" name="action" value="advnews_unsubscribe">
        <?php AdvNews_Security::create_nonce_field('advnews_ajax_nonce'); ?>
        <p>
            <label for="unsubscribe_email"><?php _e('Email Address', 'advnews-manager'); ?> *</label>
            <input type="email" id="unsubscribe_email" name="email" value="<?php echo esc_attr($email); ?>" required>
        </p>
        <p>
            <input type="submit" value="<?php _e('Unsubscribe', 'advnews-manager'); ?>">
        </p>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('advnews_unsubscribe', 'advnews_unsubscribe_form_shortcode');

/**
 * Handle unsubscribe request
 */
function advnews_handle_unsubscribe_request() {
    if (isset($_POST['action']) && $_POST['action'] === 'advnews_unsubscribe') {
        if (!AdvNews_Security::verify_nonce('advnews_ajax_nonce', '_wpnonce')) {
            wp_die(__('Security check failed.', 'advnews-manager'));
        }
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';
        if (!is_email($email)) {
            wp_die(__('Invalid email address.', 'advnews-manager'));
        }
        $subscriber_class = new AdvNews_Subscriber();
        $result = $subscriber_class->unsubscribe($email, $reason);
        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }
        // Show success message
        wp_die(__('You have been successfully unsubscribed.', 'advnews-manager'), __('Unsubscribed', 'advnews-manager'), array('response' => 200));
    }
}
add_action('admin_post_nopriv_advnews_unsubscribe', 'advnews_handle_unsubscribe_request');
add_action('admin_post_advnews_unsubscribe', 'advnews_handle_unsubscribe_request');

/**
 * Handle tracking pixel requests
 */
function advnews_handle_tracking() {
    if (isset($_GET['action']) && $_GET['action'] === 'track_open') {
        $log_id = isset($_GET['log_id']) ? intval($_GET['log_id']) : 0;
        $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
        $subscriber_id = isset($_GET['subscriber_id']) ? intval($_GET['subscriber_id']) : 0;
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        // Verify token
        $expected_token = AdvNews_Security::generate_hash($log_id . $campaign_id . $subscriber_id);
        if ($token !== $expected_token) {
            status_header(403);
            exit;
        }
        // Record open
        $tracking_class = new AdvNews_Tracking();
        $tracking_class->record_open($log_id, $campaign_id, $subscriber_id);
        // Serve 1x1 transparent pixel
        header('Content-Type: image/gif');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;
    }
    if (isset($_GET['action']) && $_GET['action'] === 'track_click') {
        $hash = isset($_GET['hash']) ? sanitize_text_field($_GET['hash']) : '';
        $log_id = isset($_GET['log_id']) ? intval($_GET['log_id']) : 0;
        $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
        if (!$hash || !$log_id || !$campaign_id) {
            wp_redirect(home_url());
            exit;
        }
        // Record click
        $tracking_class = new AdvNews_Tracking();
        $url = $tracking_class->record_click($hash, $log_id, $campaign_id);
        if ($url) {
            wp_redirect($url);
            exit;
        }
        wp_redirect(home_url());
        exit;
    }
}
add_action('init', 'advnews_handle_tracking');

/**
 * Get campaign performance data
 */
function advnews_get_campaign_performance($campaign_id) {
    $campaign_class = new AdvNews_Campaign();
    return $campaign_class->get_campaign_stats($campaign_id);
}

/**
 * Check if email is subscribed
 */
function advnews_is_subscribed($email) {
    $subscriber_class = new AdvNews_Subscriber();
    $subscriber = $subscriber_class->get_subscriber_by_email($email);
    return $subscriber && $subscriber->status === 'active';
}

/**
 * Export plugin data for GDPR compliance
 */
function advnews_export_subscriber_data($email) {
    $subscriber_class = new AdvNews_Subscriber();
    $subscriber = $subscriber_class->get_subscriber_by_email($email);
    if (!$subscriber) {
        return false;
    }
    $data = array(
        'personal_data' => array(
            'email' => $subscriber->email,
            'first_name' => $subscriber->first_name,
            'last_name' => $subscriber->last_name,
            'organization' => $subscriber->organization,
            'subscribed_at' => $subscriber->subscribed_at,
            'status' => $subscriber->status
        ),
        'categories' => array(),
        'activity' => array()
    );
    // Get categories
    $categories = $subscriber_class->get_subscriber_categories($subscriber->id);
    foreach ($categories as $category) {
        $data['categories'][] = $category->name;
    }
    // Get activity (last 100 emails)
    global $wpdb;
    $table_prefix = ADVNEWS_TABLE_PREFIX;
    $table_logs = $wpdb->prefix . $table_prefix . 'campaign_logs';
    $table_campaigns = $wpdb->prefix . $table_prefix . 'campaigns';
    $activity = $wpdb->get_results($wpdb->prepare(
        "SELECT cl.*, c.name as campaign_name
        FROM $table_logs cl
        INNER JOIN $table_campaigns c ON cl.campaign_id = c.id
        WHERE cl.subscriber_id = %d
        ORDER BY cl.sent_at DESC
        LIMIT 100",
        $subscriber->id
    ));
    foreach ($activity as $log) {
        $data['activity'][] = array(
            'campaign' => $log->campaign_name,
            'status' => $log->status,
            'sent_at' => $log->sent_at,
            'opened_at' => $log->opened_at,
            'clicked_at' => $log->clicked_at
        );
    }
    return $data;
}

/**
 * Delete subscriber data for GDPR compliance
 */
function advnews_delete_subscriber_data($email) {
    $subscriber_class = new AdvNews_Subscriber();
    $subscriber = $subscriber_class->get_subscriber_by_email($email);
    if (!$subscriber) {
        return true;
    }
    // Anonymize data instead of deleting
    global $wpdb;
    $table_prefix = ADVNEWS_TABLE_PREFIX;
    $table_name = $wpdb->prefix . $table_prefix . 'subscribers';
    $result = $wpdb->update(
        $table_name,
        array(
            'email' => 'deleted-' . $subscriber->id . '@example.com',
            'first_name' => '[deleted]',
            'last_name' => '[deleted]',
            'organization' => '[deleted]',
            'ip_address' => '0.0.0.0'
        ),
        array('id' => $subscriber->id)
    );
    return $result !== false;
}

/*********************tempory added***********************/
add_action('admin_init', function() {
    if (isset($_GET['test_wp_mail'])) {
        $result = wp_mail(get_option('admin_email'), 'WP Mail Test', 'Test message from WordPress');
        wp_die('WP Mail result: ' . ($result ? 'SUCCESS' : 'FAILED'));
    }
});
/*****************************/

/**
 * Configure TinyMCE to preserve <br> tags and prevent HTML stripping
 */
function advnews_configure_tinymce($init) {
    // Allow <br> tags everywhere
    $init['extended_valid_elements'] = 'br[*],span[*],p[*],div[*]';

    // Don't remove trailing <br> tags
    $init['remove_trailing_brs'] = false;

    // Force <br> for newlines instead of <p>
    $init['force_br_newlines'] = true;
    $init['force_p_newlines'] = false;
    $init['forced_root_block'] = '';

    // Don't clean up HTML on paste
    $init['paste_remove_spans'] = false;
    $init['paste_remove_styles'] = false;
    $init['paste_retain_style_properties'] = 'all';
    $init['paste_word_valid_elements'] = '*[*]';
    $init['paste_auto_cleanup_on_paste'] = false;

    // Allow all content
    $init['valid_elements'] = '*[*]';
    $init['valid_children'] = '+body[style],+span[style],+p[style],+div[style],+br';

    // Don't remove empty elements
    $init['remove_empty_elements'] = false;
    $init['remove_empty_span'] = false;

    // Cleanup settings
    $init['cleanup_on_startup'] = false;
    $init['preformatted'] = true;

    // Add custom CSS to make <br> visible in visual editor
    $init['content_css'] = ADVNEWS_PLUGIN_URL . 'assets/css/tinymce-custom.css';

    return $init;
}
add_filter('tiny_mce_before_init', 'advnews_configure_tinymce');

/**
 * Add custom TinyMCE plugin to preserve formatting
 */
function advnews_add_tinymce_plugin($plugins) {
    $plugins[] = 'advnews_preserve_br';
    return $plugins;
}
add_filter('mce_external_plugins', 'advnews_add_tinymce_plugin');

/**
 * Register custom TinyMCE plugin
 */
function advnews_register_tinymce_plugin($plugin_array) {
    $plugin_array['advnews_preserve_br'] = ADVNEWS_PLUGIN_URL . 'assets/js/tinymce-preserve-br.js';
    return $plugin_array;
}
add_filter('mce_external_plugins', 'advnews_register_tinymce_plugin');

