<?php
// File: includes/class-frontend.php

class AdvNews_Frontend
{
    public function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize frontend hooks
     */
    private function init_hooks()
    {
        // Register shortcodes
        add_shortcode('advnews_unsubscribe', array($this, 'unsubscribe_shortcode'));
        add_shortcode('advnews_manage_subscription', array($this, 'manage_subscription_shortcode'));
        add_shortcode('advnews_subscribe', array($this, 'subscribe_shortcode'));

        // Handle form submissions
        add_action('wp_ajax_nopriv_advnews_frontend_subscribe', array($this, 'handle_frontend_subscribe'));
        add_action('wp_ajax_advnews_frontend_subscribe', array($this, 'handle_frontend_subscribe'));

        add_action('wp_ajax_nopriv_advnews_frontend_unsubscribe', array($this, 'handle_frontend_unsubscribe'));
        add_action('wp_ajax_advnews_frontend_unsubscribe', array($this, 'handle_frontend_unsubscribe'));

        add_action('wp_ajax_nopriv_advnews_frontend_update_preferences', array($this, 'handle_frontend_update_preferences'));
        add_action('wp_ajax_advnews_frontend_update_preferences', array($this, 'handle_frontend_update_preferences'));

        // Email web version
        add_action('init', array($this, 'handle_email_web_version'));
    }

    /**
     * Unsubscribe shortcode
     */
    public function unsubscribe_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'show_reason' => 'yes'
        ), $atts);

        ob_start();

        // Check if already unsubscribed via token
        $email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

        if ($email && $token) {
            $this->render_unsubscribe_confirmation($email, $token, $atts['show_reason']);
        } else {
            $this->render_unsubscribe_form($atts['show_reason']);
        }

        return ob_get_clean();
    }

    /**
     * Manage subscription shortcode
     */
    public function manage_subscription_shortcode($atts)
    {
        $atts = shortcode_atts(array(), $atts);

        ob_start();

        // Check if user is logged in and has email
        $email = '';
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $email = $user->user_email;
        } elseif (isset($_GET['email']) && isset($_GET['token'])) {
            $email = sanitize_email($_GET['email']);
            $token = sanitize_text_field($_GET['token']);

            // Verify token
            $transient_name = 'advnews_manage_' . $token;
            $stored_email = get_transient($transient_name);

            if ($stored_email !== $email) {
                echo '<div class="advnews-error">' . __('Invalid or expired link.', 'advnews-manager') . '</div>';
                return ob_get_clean();
            }
        } else {
            // Request email
            $this->render_email_request_form();
            return ob_get_clean();
        }

        // Show subscription management
        $this->render_subscription_management($email);

        return ob_get_clean();
    }

    /**
     * Subscribe shortcode
     */
    public function subscribe_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'categories' => '',
            'show_name' => 'yes',
            'redirect' => '',
            'title' => __('Subscribe to Our Newsletter', 'advnews-manager'),
            'description' => __('Stay updated with our latest news and offers.', 'advnews-manager')
        ), $atts);

        ob_start();
        ?>
        <div class="advnews-subscription-wrapper">
            <?php if ($atts['title']): ?>
                <h3><?php echo esc_html($atts['title']); ?></h3>
            <?php endif; ?>

            <?php if ($atts['description']): ?>
                <p><?php echo esc_html($atts['description']); ?></p>
            <?php endif; ?>

            <?php echo advnews_subscription_form_shortcode($atts); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render unsubscribe form
     */
    private function render_unsubscribe_form($show_reason = 'yes')
    {
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : (isset($_GET['email']) ? sanitize_email($_GET['email']) : '');
        ?>
        <div class="advnews-unsubscribe-page">
            <h2><?php _e('Unsubscribe from Newsletter', 'advnews-manager'); ?></h2>
            <p><?php _e('Enter your email address to unsubscribe from our newsletter.', 'advnews-manager'); ?></p>

            <form class="advnews-unsubscribe-form" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
                <input type="hidden" name="action" value="advnews_frontend_unsubscribe">
                <?php AdvNews_Security::create_nonce_field('advnews_frontend_unsubscribe'); ?>

                <div class="advnews-form-group">
                    <label for="unsubscribe_email"><?php _e('Email Address', 'advnews-manager'); ?> *</label>
                    <input type="email" id="unsubscribe_email" name="email" value="<?php echo esc_attr($email); ?>" required>
                </div>

                <?php if ($show_reason === 'yes'): ?>
                    <div class="advnews-form-group">
                        <label for="reason"><?php _e('Reason for unsubscribing (optional):', 'advnews-manager'); ?></label>
                        <select id="reason" name="reason">
                            <option value=""><?php _e('Select a reason', 'advnews-manager'); ?></option>
                            <option value="too_frequent"><?php _e('Emails too frequent', 'advnews-manager'); ?></option>
                            <option value="not_relevant"><?php _e('Content not relevant', 'advnews-manager'); ?></option>
                            <option value="technical_issue"><?php _e('Technical issue', 'advnews-manager'); ?></option>
                            <option value="other"><?php _e('Other', 'advnews-manager'); ?></option>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="advnews-form-group">
                    <input type="submit" value="<?php _e('Unsubscribe', 'advnews-manager'); ?>">
                </div>

                <div class="advnews-form-response" style="display:none;"></div>
            </form>

            <script>
            jQuery(document).ready(function($) {
                $('.advnews-unsubscribe-form').on('submit', function(e) {
                    e.preventDefault();

                    var form = $(this);
                    var button = form.find('input[type="submit"]');
                    var originalText = button.val();

                    button.prop('disabled', true).val('<?php _e('Processing...', 'advnews-manager'); ?>');
                    form.find('.advnews-form-response').hide();

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: form.serialize(),
                        success: function(response) {
                            if (response.success) {
                                form.find('.advnews-form-response').html(
                                    '<div class="advnews-success">' + response.data.message + '</div>'
                                ).show();
                                form[0].reset();
                            } else {
                                form.find('.advnews-form-response').html(
                                    '<div class="advnews-error">' + response.data.message + '</div>'
                                ).show();
                            }
                        },
                        error: function() {
                            form.find('.advnews-form-response').html(
                                '<div class="advnews-error"><?php _e('An error occurred. Please try again.', 'advnews-manager'); ?></div>'
                            ).show();
                        },
                        complete: function() {
                            button.prop('disabled', false).val(originalText);
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }

    /**
     * Render unsubscribe confirmation
     */
    private function render_unsubscribe_confirmation($email, $token, $show_reason = 'yes')
    {
        // Verify token
        $transient_name = 'advnews_unsubscribe_' . $token;
        $stored_email = get_transient($transient_name);

        if ($stored_email !== $email) {
            echo '<div class="advnews-error">' . __('Invalid or expired unsubscribe link.', 'advnews-manager') . '</div>';
            return;
        }
        ?>
        <div class="advnews-unsubscribe-confirm">
            <h2><?php _e('Confirm Unsubscribe', 'advnews-manager'); ?></h2>
            <p><?php printf(__('You are about to unsubscribe %s from our newsletter.', 'advnews-manager'), '<strong>' . esc_html($email) . '</strong>'); ?></p>

            <form class="advnews-unsubscribe-confirm-form" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
                <input type="hidden" name="action" value="advnews_frontend_unsubscribe">
                <?php AdvNews_Security::create_nonce_field('advnews_frontend_unsubscribe'); ?>
                <input type="hidden" name="email" value="<?php echo esc_attr($email); ?>">
                <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">

                <?php if ($show_reason === 'yes'): ?>
                    <div class="advnews-form-group">
                        <label for="confirm_reason"><?php _e('Reason for unsubscribing (optional):', 'advnews-manager'); ?></label>
                        <select id="confirm_reason" name="reason">
                            <option value=""><?php _e('Select a reason', 'advnews-manager'); ?></option>
                            <option value="too_frequent"><?php _e('Emails too frequent', 'advnews-manager'); ?></option>
                            <option value="not_relevant"><?php _e('Content not relevant', 'advnews-manager'); ?></option>
                            <option value="technical_issue"><?php _e('Technical issue', 'advnews-manager'); ?></option>
                            <option value="other"><?php _e('Other', 'advnews-manager'); ?></option>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="advnews-form-group">
                    <input type="submit" value="<?php _e('Confirm Unsubscribe', 'advnews-manager'); ?>">
                    <a href="<?php echo home_url(); ?>" class="advnews-button-cancel"><?php _e('Cancel', 'advnews-manager'); ?></a>
                </div>

                <div class="advnews-form-response" style="display:none;"></div>
            </form>

            <script>
            jQuery(document).ready(function($) {
                $('.advnews-unsubscribe-confirm-form').on('submit', function(e) {
                    e.preventDefault();

                    var form = $(this);
                    var button = form.find('input[type="submit"]');
                    var originalText = button.val();

                    button.prop('disabled', true).val('<?php _e('Processing...', 'advnews-manager'); ?>');
                    form.find('.advnews-form-response').hide();

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: form.serialize(),
                        success: function(response) {
                            if (response.success) {
                                form.find('.advnews-form-response').html(
                                    '<div class="advnews-success">' + response.data.message + '</div>'
                                ).show();
                                form.hide();
                            } else {
                                form.find('.advnews-form-response').html(
                                    '<div class="advnews-error">' + response.data.message + '</div>'
                                ).show();
                            }
                        },
                        error: function() {
                            form.find('.advnews-form-response').html(
                                '<div class="advnews-error"><?php _e('An error occurred. Please try again.', 'advnews-manager'); ?></div>'
                            ).show();
                        },
                        complete: function() {
                            button.prop('disabled', false).val(originalText);
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }

    /**
     * Render email request form
     */
    private function render_email_request_form()
    {
        ?>
        <div class="advnews-email-request">
            <h2><?php _e('Manage Your Subscription', 'advnews-manager'); ?></h2>
            <p><?php _e('Enter your email address to manage your subscription preferences.', 'advnews-manager'); ?></p>

            <form class="advnews-email-request-form" method="get">
                <input type="hidden" name="page" value="manage-subscription">

                <div class="advnews-form-group">
                    <label for="request_email"><?php _e('Email Address', 'advnews-manager'); ?> *</label>
                    <input type="email" id="request_email" name="email" required>
                </div>

                <div class="advnews-form-group">
                    <input type="submit" value="<?php _e('Continue', 'advnews-manager'); ?>">
                </div>
            </form>

            <p class="advnews-note">
                <?php _e('You will receive an email with a secure link to manage your preferences.', 'advnews-manager'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render subscription management
     */
    private function render_subscription_management($email)
    {
        $subscriber_class = new AdvNews_Subscriber();
        $subscriber = $subscriber_class->get_subscriber_by_email($email);

        if (!$subscriber) {
            echo '<div class="advnews-error">' . __('Subscriber not found.', 'advnews-manager') . '</div>';
            return;
        }

        $categories = $subscriber_class->get_subscriber_categories($subscriber->id);
        $all_categories = $this->get_all_categories();

        $subscribed_category_ids = array();
        foreach ($categories as $category) {
            $subscribed_category_ids[] = $category->id;
        }
        ?>
        <div class="advnews-subscription-management">
            <h2><?php _e('Manage Your Subscription', 'advnews-manager'); ?></h2>

            <div class="advnews-subscriber-info">
                <p><strong><?php _e('Email:', 'advnews-manager'); ?></strong> <?php echo esc_html($subscriber->email); ?></p>
                <p><strong><?php _e('Status:', 'advnews-manager'); ?></strong>
                    <span class="advnews-status <?php echo esc_attr($subscriber->status); ?>">
                        <?php echo esc_html(ucfirst($subscriber->status)); ?>
                    </span>
                </p>
            </div>

            <form class="advnews-preferences-form" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
                <input type="hidden" name="action" value="advnews_frontend_update_preferences">
                <?php AdvNews_Security::create_nonce_field('advnews_frontend_update_preferences'); ?>
                <input type="hidden" name="email" value="<?php echo esc_attr($email); ?>">

                <div class="advnews-form-section">
                    <h3><?php _e('Email Preferences', 'advnews-manager'); ?></h3>
                    <p><?php _e('Select the categories you want to receive emails from:', 'advnews-manager'); ?></p>

                    <div class="advnews-categories-list">
                        <?php foreach ($all_categories as $category): ?>
                            <div class="advnews-category-checkbox">
                                <input type="checkbox" id="category_<?php echo esc_attr($category->id); ?>"
                                       name="categories[]" value="<?php echo esc_attr($category->id); ?>"
                                       <?php checked(in_array($category->id, $subscribed_category_ids)); ?>>
                                <label for="category_<?php echo esc_attr($category->id); ?>">
                                    <?php echo esc_html($category->name); ?>
                                    <?php if ($category->description): ?>
                                        <br><small><?php echo esc_html($category->description); ?></small>
                                    <?php endif; ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="advnews-form-section">
                    <h3><?php _e('Personal Information', 'advnews-manager'); ?></h3>

                    <div class="advnews-form-group">
                        <label for="first_name"><?php _e('First Name', 'advnews-manager'); ?></label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo esc_attr($subscriber->first_name); ?>">
                    </div>

                    <div class="advnews-form-group">
                        <label for="last_name"><?php _e('Last Name', 'advnews-manager'); ?></label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo esc_attr($subscriber->last_name); ?>">
                    </div>

                    <div class="advnews-form-group">
                        <label for="organization"><?php _e('Organization', 'advnews-manager'); ?></label>
                        <input type="text" id="organization" name="organization" value="<?php echo esc_attr($subscriber->organization); ?>">
                    </div>
                </div>

                <div class="advnews-form-actions">
                    <input type="submit" value="<?php _e('Save Preferences', 'advnews-manager'); ?>" class="advnews-button-primary">

                    <?php if ($subscriber->status === 'active'): ?>
                        <a href="<?php echo $this->get_unsubscribe_link($email); ?>" class="advnews-button-secondary">
                            <?php _e('Unsubscribe from All', 'advnews-manager'); ?>
                        </a>
                    <?php else: ?>
                        <button type="button" id="advnews-resubscribe" class="advnews-button-secondary">
                            <?php _e('Resubscribe', 'advnews-manager'); ?>
                        </button>
                    <?php endif; ?>
                </div>

                <div class="advnews-form-response" style="display:none;"></div>
            </form>

            <div class="advnews-data-actions">
                <h3><?php _e('Data Management', 'advnews-manager'); ?></h3>
                <p>
                    <button type="button" id="advnews-export-data" class="advnews-button-link">
                        <?php _e('Export My Data', 'advnews-manager'); ?>
                    </button>
                    |
                    <button type="button" id="advnews-delete-data" class="advnews-button-link advnews-text-danger">
                        <?php _e('Delete My Data', 'advnews-manager'); ?>
                    </button>
                </p>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Save preferences
            $('.advnews-preferences-form').on('submit', function(e) {
                e.preventDefault();

                var form = $(this);
                var button = form.find('input[type="submit"]');
                var originalText = button.val();

                button.prop('disabled', true).val('<?php _e('Saving...', 'advnews-manager'); ?>');
                form.find('.advnews-form-response').hide();

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: form.serialize(),
                    success: function(response) {
                        if (response.success) {
                            form.find('.advnews-form-response').html(
                                '<div class="advnews-success">' + response.data.message + '</div>'
                            ).show();
                        } else {
                            form.find('.advnews-form-response').html(
                                '<div class="advnews-error">' + response.data.message + '</div>'
                            ).show();
                        }
                    },
                    error: function() {
                        form.find('.advnews-form-response').html(
                            '<div class="advnews-error"><?php _e('An error occurred. Please try again.', 'advnews-manager'); ?></div>'
                        ).show();
                    },
                    complete: function() {
                        button.prop('disabled', false).val(originalText);
                    }
                });
            });

            // Resubscribe
            $('#advnews-resubscribe').on('click', function() {
                if (confirm('<?php _e('Are you sure you want to resubscribe to all emails?', 'advnews-manager'); ?>')) {
                    var button = $(this);
                    var originalText = button.text();

                    button.prop('disabled', true).text('<?php _e('Processing...', 'advnews-manager'); ?>');

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'advnews_frontend_resubscribe',
                            email: '<?php echo esc_js($email); ?>',
                            _wpnonce: '<?php echo wp_create_nonce('advnews_frontend_resubscribe'); ?>'
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
                            alert('<?php _e('An error occurred. Please try again.', 'advnews-manager'); ?>');
                        },
                        complete: function() {
                            button.prop('disabled', false).text(originalText);
                        }
                    });
                }
            });

            // Export data
            $('#advnews-export-data').on('click', function() {
                if (confirm('<?php _e('This will generate a file with all your data. Continue?', 'advnews-manager'); ?>')) {
                    window.location.href = '<?php echo admin_url('admin-ajax.php?action=advnews_export_subscriber_data&email=' . urlencode($email)); ?>';
                }
            });

            // Delete data
            $('#advnews-delete-data').on('click', function() {
                if (confirm('<?php _e('WARNING: This will permanently anonymize your data. This action cannot be undone. Continue?', 'advnews-manager'); ?>')) {
                    var button = $(this);
                    var originalText = button.text();

                    button.prop('disabled', true).text('<?php _e('Processing...', 'advnews-manager'); ?>');

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'advnews_frontend_delete_data',
                            email: '<?php echo esc_js($email); ?>',
                            _wpnonce: '<?php echo wp_create_nonce('advnews_frontend_delete_data'); ?>'
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
                            alert('<?php _e('An error occurred. Please try again.', 'advnews-manager'); ?>');
                        },
                        complete: function() {
                            button.prop('disabled', false).text(originalText);
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Get all categories
     */
    private function get_all_categories()
    {
        global $wpdb;
        $table_prefix = ADVNEWS_TABLE_PREFIX;
        $table_name = $wpdb->prefix . $table_prefix . 'categories';

        return $wpdb->get_results("SELECT * FROM $table_name ORDER BY name");
    }

    /**
     * Get unsubscribe link
     */
    private function get_unsubscribe_link($email)
    {
        $page_id = get_option('advnews_unsubscribe_page_id');
        if (!$page_id) {
            return home_url();
        }

        $token = AdvNews_Security::generate_hash($email . 'unsubscribe');
        $transient_name = 'advnews_unsubscribe_' . $token;
        set_transient($transient_name, $email, 7 * DAY_IN_SECONDS);

        return add_query_arg(array(
            'token' => $token,
            'email' => urlencode($email)
        ), get_permalink($page_id));
    }

    /**
     * Handle frontend subscribe
     */
    public function handle_frontend_subscribe()
    {
        if (!AdvNews_Security::verify_nonce('advnews_frontend_subscribe', '_wpnonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'advnews-manager')));
        }

        $data = AdvNews_Security::sanitize_array($_POST);

        // Validate email
        if (empty($data['email'])) {
            wp_send_json_error(array('message' => __('Email address is required.', 'advnews-manager')));
        }

        $email = AdvNews_Security::validate_email($data['email']);
        if (!$email) {
            wp_send_json_error(array('message' => __('Invalid email address.', 'advnews-manager')));
        }

        // Prepare subscriber data
        $subscriber_data = array(
            'email' => $email,
            'first_name' => isset($data['first_name']) ? $data['first_name'] : '',
            'last_name' => isset($data['last_name']) ? $data['last_name'] : ''
        );

        // Add categories if specified
        if (!empty($data['categories'])) {
            $subscriber_data['categories'] = $data['categories'];
        }

        $subscriber_class = new AdvNews_Subscriber();
        $result = $subscriber_class->add_subscriber($subscriber_data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Thank you for subscribing! Please check your email to confirm your subscription.', 'advnews-manager')
        ));
    }

    /**
     * Handle frontend unsubscribe
     */
    public function handle_frontend_unsubscribe()
    {
        if (!AdvNews_Security::verify_nonce('advnews_frontend_unsubscribe', '_wpnonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'advnews-manager')));
        }

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';

        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email address.', 'advnews-manager')));
        }

        // Verify token if provided
        if ($token) {
            $transient_name = 'advnews_unsubscribe_' . $token;
            $stored_email = get_transient($transient_name);

            if ($stored_email !== $email) {
                wp_send_json_error(array('message' => __('Invalid or expired unsubscribe link.', 'advnews-manager')));
            }

            // Delete token after use
            delete_transient($transient_name);
        }

        $subscriber_class = new AdvNews_Subscriber();
        $result = $subscriber_class->unsubscribe($email, $reason);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('You have been successfully unsubscribed from our newsletter.', 'advnews-manager')
        ));
    }

    /**
     * Handle frontend update preferences
     */
    public function handle_frontend_update_preferences()
    {
        if (!AdvNews_Security::verify_nonce('advnews_frontend_update_preferences', '_wpnonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'advnews-manager')));
        }

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
        $organization = isset($_POST['organization']) ? sanitize_text_field($_POST['organization']) : '';
        $categories = isset($_POST['categories']) ? array_map('intval', (array)$_POST['categories']) : array();

        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email address.', 'advnews-manager')));
        }

        $subscriber_class = new AdvNews_Subscriber();
        $subscriber = $subscriber_class->get_subscriber_by_email($email);

        if (!$subscriber) {
            wp_send_json_error(array('message' => __('Subscriber not found.', 'advnews-manager')));
        }

        // Update subscriber info
        $update_data = array();
        if ($first_name !== $subscriber->first_name) $update_data['first_name'] = $first_name;
        if ($last_name !== $subscriber->last_name) $update_data['last_name'] = $last_name;
        if ($organization !== $subscriber->organization) $update_data['organization'] = $organization;

        if (!empty($update_data)) {
            $subscriber_class->update_subscriber($subscriber->id, $update_data);
        }

        // Update categories
        $subscriber_class->add_categories_to_subscriber($subscriber->id, $categories);

        wp_send_json_success(array(
            'message' => __('Your preferences have been updated successfully.', 'advnews-manager')
        ));
    }

    /**
     * Handle email web version
     */
    public function handle_email_web_version()
    {
        if (isset($_GET['advnews_web_version']) && isset($_GET['campaign_id']) && isset($_GET['subscriber_id'])) {
            $campaign_id = intval($_GET['campaign_id']);
            $subscriber_id = intval($_GET['subscriber_id']);
            $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

            // Verify token
            $expected_token = AdvNews_Security::generate_hash($campaign_id . $subscriber_id . 'web_version');
            if ($token !== $expected_token) {
                wp_die(__('Invalid access token.', 'advnews-manager'));
            }

            $this->render_email_web_version($campaign_id, $subscriber_id);
            exit;
        }
    }

    /**
     * Render email web version
     */
    private function render_email_web_version($campaign_id, $subscriber_id)
    {
        $campaign_class = new AdvNews_Campaign();
        $subscriber_class = new AdvNews_Subscriber();

        $campaign = $campaign_class->get_campaign($campaign_id);
        $subscriber = $subscriber_class->get_subscriber($subscriber_id);

        if (!$campaign || !$subscriber) {
            wp_die(__('Email not found.', 'advnews-manager'));
        }

        // Process merge tags
        $subscriber_data = array(
            'email' => $subscriber->email,
            'first_name' => $subscriber->first_name,
            'last_name' => $subscriber->last_name,
            'organization' => $subscriber->organization
        );

        $content = $campaign_class->process_merge_tags($campaign->content, $subscriber_data);
        $content = $campaign_class->prepare_email_content($content);

        // Remove tracking pixel
        $content = preg_replace('/<img[^>]+src=["\'][^"\']+track_open[^"\']+["\'][^>]*>/i', '', $content);

        // Restore original links
        $content = preg_replace_callback('/href=["\']([^"\']+advnews-track[^"\']+)["\']/', function($matches) {
            // Extract original URL from tracking URL
            if (preg_match('/hash=([a-f0-9]{32})/', $matches[1], $hash_match)) {
                global $wpdb;
                $table_prefix = ADVNEWS_TABLE_PREFIX;
                $table_name = $wpdb->prefix . $table_prefix . 'links';

                $original_url = $wpdb->get_var($wpdb->prepare(
                    "SELECT original_url FROM $table_name WHERE tracking_hash = %s",
                    $hash_match[1]
                ));

                if ($original_url) {
                    return 'href="' . esc_url($original_url) . '"';
                }
            }
            return $matches[0];
        }, $content);

        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title><?php echo esc_html($campaign->subject); ?> - Web Version</title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    margin: 0;
                    padding: 20px;
                    background: #f5f5f5;
                }
                .email-wrapper {
                    max-width: 600px;
                    margin: 0 auto;
                    background: #fff;
                    padding: 20px;
                    border-radius: 5px;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                }
                .email-header {
                    margin-bottom: 20px;
                    padding-bottom: 10px;
                    border-bottom: 1px solid #eee;
                }
                .email-footer {
                    margin-top: 20px;
                    padding-top: 10px;
                    border-top: 1px solid #eee;
                    font-size: 12px;
                    color: #777;
                    text-align: center;
                }
                .unsubscribe-link {
                    color: #999;
                    text-decoration: underline;
                }
                .web-version-notice {
                    background: #f8f9fa;
                    padding: 10px;
                    margin-bottom: 20px;
                    border-left: 4px solid #0073aa;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
            <div class="email-wrapper">
                <div class="web-version-notice">
                    <?php _e('You are viewing the web version of this email.', 'advnews-manager'); ?>
                </div>

                <div class="email-header">
                    <h1><?php echo esc_html($campaign->subject); ?></h1>
                </div>

                <div class="email-content">
                    <?php echo $content; ?>
                </div>

                <div class="email-footer">
                    <p>
                        <?php printf(__('Sent by %s', 'advnews-manager'), get_option('advnews_company_name', get_bloginfo('name'))); ?><br>
                        <a href="<?php echo esc_url($this->get_unsubscribe_link($subscriber->email)); ?>" class="unsubscribe-link">
                            <?php _e('Unsubscribe', 'advnews-manager'); ?>
                        </a>
                        <?php if (get_option('advnews_unsubscribe_page_id')): ?>
                            | <a href="<?php echo esc_url(get_permalink(get_option('advnews_management_page_id'))); ?>">
                                <?php _e('Manage Preferences', 'advnews-manager'); ?>
                            </a>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}

// Initialize frontend
new AdvNews_Frontend();
