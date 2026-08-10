<?php
// frontend/partials/manage-preferences.php
if (!defined('ABSPATH')) exit;

$email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
$token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

$subscriber_class = new AdvNews_Subscriber();
$subscriber = $subscriber_class->get_subscriber_by_email($email);

if (!$subscriber || $subscriber->status === 'bounced') {
    echo '<div class="advnews-error-message">' . __('Subscriber not found or invalid.', 'advnews-manager') . '</div>';
    return;
}

// Verify token for security (if not logged in)
if (!is_user_logged_in() && $token) {
    $transient_key = 'advnews_manage_' . $token;
    $stored_email = get_transient($transient_key);
    if ($stored_email !== $email) {
        echo '<div class="advnews-error-message">' . __('Invalid or expired access link.', 'advnews-manager') . '</div>';
        return;
    }
}

$categories = $subscriber_class->get_subscriber_categories($subscriber->id);
$all_categories = $this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}{$this->table_prefix}categories ORDER BY name");
$subscribed_category_ids = wp_list_pluck($categories, 'id');

// Get recent campaigns
$recent_campaigns = $this->wpdb->get_results($this->wpdb->prepare(
    "SELECT c.name, c.subject, cl.sent_at, cl.status, cl.opened_at, cl.clicked_at
    FROM {$this->wpdb->prefix}{$this->table_prefix}campaign_logs cl
    JOIN {$this->wpdb->prefix}{$this->table_prefix}campaigns c ON cl.campaign_id = c.id
    WHERE cl.subscriber_id = %d
    ORDER BY cl.sent_at DESC
    LIMIT 10",
    $subscriber->id
));
?>

<div class="advnews-preferences-container">
    <div class="advnews-preferences-header">
        <h2><?php _e('Manage Your Subscription Preferences', 'advnews-manager'); ?></h2>
        <p class="advnews-subscriber-email">
            <?php printf(__('Email: <strong>%s</strong>', 'advnews-manager'), esc_html($subscriber->email)); ?>
            <span class="advnews-status-badge advnews-status-<?php echo esc_attr($subscriber->status); ?>">
                <?php echo esc_html(ucfirst($subscriber->status)); ?>
            </span>
        </p>
    </div>

    <form class="advnews-preferences-form" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" id="advnews-preferences-form">
        <input type="hidden" name="action" value="advnews_frontend_update_preferences">
        <?php wp_nonce_field('advnews_frontend_update_preferences', '_wpnonce'); ?>
        <input type="hidden" name="email" value="<?php echo esc_attr($email); ?>">
        <?php if ($token): ?>
        <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
        <?php endif; ?>

        <!-- Personal Information -->
        <div class="advnews-preferences-section">
            <h3><?php _e('Personal Information', 'advnews-manager'); ?></h3>

            <div class="advnews-form-row">
                <div class="advnews-form-group">
                    <label for="first_name"><?php _e('First Name', 'advnews-manager'); ?></label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo esc_attr($subscriber->first_name); ?>"
                           class="advnews-input" placeholder="<?php _e('Your first name', 'advnews-manager'); ?>">
                </div>

                <div class="advnews-form-group">
                    <label for="last_name"><?php _e('Last Name', 'advnews-manager'); ?></label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo esc_attr($subscriber->last_name); ?>"
                           class="advnews-input" placeholder="<?php _e('Your last name', 'advnews-manager'); ?>">
                </div>
            </div>

            <div class="advnews-form-group">
                <label for="organization"><?php _e('Organization / Company', 'advnews-manager'); ?></label>
                <input type="text" id="organization" name="organization" value="<?php echo esc_attr($subscriber->organization); ?>"
                       class="advnews-input" placeholder="<?php _e('Your organization', 'advnews-manager'); ?>">
            </div>
        </div>

        <!-- Category Preferences -->
        <div class="advnews-preferences-section">
            <h3><?php _e('Email Preferences', 'advnews-manager'); ?></h3>
            <p class="advnews-section-description">
                <?php _e('Select the types of emails you want to receive:', 'advnews-manager'); ?>
            </p>

            <div class="advnews-categories-grid">
                <?php foreach ($all_categories as $category): ?>
                <label class="advnews-category-item">
                    <input type="checkbox" name="categories[]" value="<?php echo esc_attr($category->id); ?>"
                           <?php checked(in_array($category->id, $subscribed_category_ids)); ?>>
                    <span class="advnews-category-name"><?php echo esc_html($category->name); ?></span>
                    <?php if ($category->description): ?>
                    <span class="advnews-category-description"><?php echo esc_html($category->description); ?></span>
                    <?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>

            <?php if (empty($all_categories)): ?>
            <p class="advnews-no-categories"><?php _e('No categories available.', 'advnews-manager'); ?></p>
            <?php endif; ?>
        </div>

        <!-- Frequency Preferences -->
        <div class="advnews-preferences-section">
            <h3><?php _e('Email Frequency', 'advnews-manager'); ?></h3>

            <div class="advnews-frequency-options">
                <label class="advnews-radio-item">
                    <input type="radio" name="frequency" value="immediate"
                           <?php checked(get_user_meta($subscriber->id, 'email_frequency', true), 'immediate'); ?>>
                    <span class="advnews-radio-label"><?php _e('Immediate - Send emails as soon as they are published', 'advnews-manager'); ?></span>
                </label>

                <label class="advnews-radio-item">
                    <input type="radio" name="frequency" value="daily"
                           <?php checked(get_user_meta($subscriber->id, 'email_frequency', true), 'daily'); ?>>
                    <span class="advnews-radio-label"><?php _e('Daily Digest - Receive one email per day with all updates', 'advnews-manager'); ?></span>
                </label>

                <label class="advnews-radio-item">
                    <input type="radio" name="frequency" value="weekly"
                           <?php checked(get_user_meta($subscriber->id, 'email_frequency', true), 'weekly'); ?>>
                    <span class="advnews-radio-label"><?php _e('Weekly Summary - Receive a weekly roundup', 'advnews-manager'); ?></span>
                </label>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="advnews-form-actions">
            <button type="submit" class="advnews-button advnews-button-primary" id="save-preferences">
                <?php _e('Save Preferences', 'advnews-manager'); ?>
            </button>

            <?php if ($subscriber->status === 'active'): ?>
            <a href="<?php echo esc_url(add_query_arg('email', urlencode($email), get_permalink(get_option('advnews_unsubscribe_page_id')))); ?>"
               class="advnews-button advnews-button-secondary" id="unsubscribe-link">
                <?php _e('Unsubscribe from All', 'advnews-manager'); ?>
            </a>
            <?php else: ?>
            <button type="button" class="advnews-button advnews-button-secondary" id="resubscribe-btn">
                <?php _e('Resubscribe', 'advnews-manager'); ?>
            </button>
            <?php endif; ?>
        </div>

        <div class="advnews-form-response" style="display:none;"></div>
    </form>

    <!-- Recent Activity -->
    <?php if (!empty($recent_campaigns)): ?>
    <div class="advnews-recent-activity">
        <h3><?php _e('Recent Email Activity', 'advnews-manager'); ?></h3>

        <div class="advnews-activity-table">
            <table>
                <thead>
                    <tr>
                        <th><?php _e('Campaign', 'advnews-manager'); ?></th>
                        <th><?php _e('Sent', 'advnews-manager'); ?></th>
                        <th><?php _e('Status', 'advnews-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_campaigns as $campaign): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($campaign->name); ?></strong>
                            <br><small><?php echo esc_html($campaign->subject); ?></small>
                        </td>
                        <td><?php echo esc_html(human_time_diff(strtotime($campaign->sent_at), current_time('timestamp')) . ' ' . __('ago', 'advnews-manager')); ?></td>
                        <td>
                            <?php if ($campaign->opened_at): ?>
                                <span class="advnews-activity-status advnews-status-opened">
                                    <?php _e('Opened', 'advnews-manager'); ?>
                                </span>
                            <?php elseif ($campaign->clicked_at): ?>
                                <span class="advnews-activity-status advnews-status-clicked">
                                    <?php _e('Clicked', 'advnews-manager'); ?>
                                </span>
                            <?php elseif ($campaign->status === 'delivered'): ?>
                                <span class="advnews-activity-status advnews-status-delivered">
                                    <?php _e('Delivered', 'advnews-manager'); ?>
                                </span>
                            <?php else: ?>
                                <span class="advnews-activity-status advnews-status-<?php echo esc_attr($campaign->status); ?>">
                                    <?php echo esc_html(ucfirst($campaign->status)); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Data Management -->
    <div class="advnews-data-management">
        <h3><?php _e('Data Management', 'advnews-manager'); ?></h3>

        <div class="advnews-data-actions">
            <button type="button" class="advnews-button-link" id="export-data-btn">
                <?php _e('Export My Data', 'advnews-manager'); ?>
            </button>
            <span class="advnews-separator">|</span>
            <button type="button" class="advnews-button-link advnews-text-danger" id="delete-data-btn">
                <?php _e('Delete My Data (GDPR)', 'advnews-manager'); ?>
            </button>
        </div>

        <p class="advnews-data-note">
            <?php _e('You have the right to access and control your personal data under GDPR.', 'advnews-manager'); ?>
            <?php if (get_privacy_policy_url()): ?>
            <a href="<?php echo esc_url(get_privacy_policy_url()); ?>" target="_blank">
                <?php _e('View Privacy Policy', 'advnews-manager'); ?>
            </a>
            <?php endif; ?>
        </p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Save preferences
    $('#advnews-preferences-form').on('submit', function(e) {
        e.preventDefault();

        var form = $(this);
        var button = $('#save-preferences');
        var responseDiv = form.find('.advnews-form-response');
        var originalText = button.text();

        button.prop('disabled', true).text(advnews_frontend.i18n.saving);
        responseDiv.hide().removeClass('success error');

        $.ajax({
            url: advnews_frontend.ajax_url,
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    responseDiv.addClass('success').html('<p>' + response.data.message + '</p>').show();

                    // Update subscriber status if changed
                    if (response.data.status) {
                        $('.advnews-status-badge')
                            .removeClass('advnews-status-active advnews-status-unsubscribed advnews-status-bounced')
                            .addClass('advnews-status-' + response.data.status)
                            .text(response.data.status.charAt(0).toUpperCase() + response.data.status.slice(1));
                    }

                    // Scroll to message
                    $('html, body').animate({
                        scrollTop: responseDiv.offset().top - 100
                    }, 500);
                } else {
                    responseDiv.addClass('error').html('<p>' + response.data.message + '</p>').show();
                }
            },
            error: function() {
                responseDiv.addClass('error').html('<p>' + advnews_frontend.i18n.error + '</p>').show();
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);

                // Hide success message after 5 seconds
                if (responseDiv.hasClass('success')) {
                    setTimeout(function() {
                        responseDiv.fadeOut();
                    }, 5000);
                }
            }
        });
    });

    // Resubscribe
    $('#resubscribe-btn').on('click', function() {
        if (!confirm(advnews_frontend.i18n.confirm_resubscribe)) {
            return;
        }

        var button = $(this);
        var originalText = button.text();

        button.prop('disabled', true).text(advnews_frontend.i18n.processing);

        $.ajax({
            url: advnews_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'advnews_frontend_resubscribe',
                email: '<?php echo esc_js($email); ?>',
                _wpnonce: '<?php echo wp_create_nonce('advnews_frontend_resubscribe'); ?>'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message);
                    button.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                alert(advnews_frontend.i18n.error);
                button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Export data
    $('#export-data-btn').on('click', function() {
        if (confirm('<?php _e('This will generate a JSON file with all your personal data. Continue?', 'advnews-manager'); ?>')) {
            window.location.href = '<?php echo admin_url('admin-ajax.php?action=advnews_frontend_export_data&email=' . urlencode($email) . '&_wpnonce=' . wp_create_nonce('advnews_frontend_export')); ?>';
        }
    });

    // Delete data
    $('#delete-data-btn').on('click', function() {
        if (!confirm(advnews_frontend.i18n.confirm_delete)) {
            return;
        }

        if (!confirm('<?php _e('This action is permanent and cannot be undone. Are you absolutely sure?', 'advnews-manager'); ?>')) {
            return;
        }

        var button = $(this);
        var originalText = button.text();

        button.prop('disabled', true).text(advnews_frontend.i18n.processing);

        $.ajax({
            url: advnews_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'advnews_frontend_delete_data',
                email: '<?php echo esc_js($email); ?>',
                _wpnonce: '<?php echo wp_create_nonce('advnews_frontend_delete_data'); ?>'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    window.location.href = '<?php echo esc_url(home_url()); ?>';
                } else {
                    alert(response.data.message);
                    button.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                alert(advnews_frontend.i18n.error);
                button.prop('disabled', false).text(originalText);
            }
        });
    });
});
</script>

<style>
.advnews-preferences-container {
    max-width: 800px;
    margin: 40px auto;
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    overflow: hidden;
}

.advnews-preferences-header {
    padding: 30px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
}

.advnews-preferences-header h2 {
    margin: 0 0 15px;
    color: #fff;
    font-size: 28px;
    font-weight: 600;
}

.advnews-subscriber-email {
    margin: 0;
    font-size: 16px;
    opacity: 0.9;
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.advnews-status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.advnews-status-active {
    background: #d4edda;
    color: #155724;
}

.advnews-status-unsubscribed {
    background: #f8d7da;
    color: #721c24;
}

.advnews-status-bounced {
    background: #fff3cd;
    color: #856404;
}

.advnews-preferences-section {
    padding: 30px;
    border-bottom: 1px solid #eee;
}

.advnews-preferences-section:last-child {
    border-bottom: none;
}

.advnews-preferences-section h3 {
    margin: 0 0 15px;
    color: #333;
    font-size: 20px;
    font-weight: 600;
}

.advnews-section-description {
    margin-bottom: 20px;
    color: #666;
    font-style: italic;
}

.advnews-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.advnews-form-group {
    margin-bottom: 20px;
}

.advnews-form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #444;
}

.advnews-input {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 16px;
    transition: border-color 0.3s;
}

.advnews-input:focus {
    border-color: #667eea;
    outline: none;
    box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
}

/* Categories Grid */
.advnews-categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.advnews-category-item {
    display: block;
    padding: 15px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s;
}

.advnews-category-item:hover {
    background: #fff;
    border-color: #667eea;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);
}

.advnews-category-item input[type="checkbox"] {
    margin-right: 10px;
}

.advnews-category-name {
    font-weight: 600;
    color: #333;
}

.advnews-category-description {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: #666;
}

/* Frequency Options */
.advnews-frequency-options {
    margin-top: 15px;
}

.advnews-radio-item {
    display: block;
    margin-bottom: 15px;
    padding: 15px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s;
}

.advnews-radio-item:hover {
    background: #fff;
    border-color: #667eea;
}

.advnews-radio-item input[type="radio"] {
    margin-right: 15px;
}

.advnews-radio-label {
    font-size: 15px;
    color: #333;
}

/* Form Actions */
.advnews-form-actions {
    padding: 30px;
    background: #f8f9fa;
    border-top: 1px solid #eee;
    display: flex;
    gap: 15px;
    justify-content: flex-end;
}

.advnews-button {
    padding: 12px 30px;
    border: none;
    border-radius: 4px;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s;
}

.advnews-button-primary {
    background: #667eea;
    color: #fff;
}

.advnews-button-primary:hover {
    background: #5a67d8;
    color: #fff;
}

.advnews-button-secondary {
    background: #fff;
    color: #667eea;
    border: 1px solid #667eea;
}

.advnews-button-secondary:hover {
    background: #f0f3ff;
    color: #5a67d8;
}

.advnews-form-response {
    margin: 0 30px 30px;
    padding: 15px;
    border-radius: 4px;
}

.advnews-form-response.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.advnews-form-response.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Recent Activity */
.advnews-recent-activity {
    padding: 30px;
    background: #fff;
    border-top: 1px solid #eee;
}

.advnews-recent-activity h3 {
    margin: 0 0 20px;
    color: #333;
    font-size: 18px;
}

.advnews-activity-table {
    overflow-x: auto;
}

.advnews-activity-table table {
    width: 100%;
    border-collapse: collapse;
}

.advnews-activity-table th {
    text-align: left;
    padding: 12px;
    background: #f8f9fa;
    color: #333;
    font-weight: 600;
    font-size: 14px;
}

.advnews-activity-table td {
    padding: 12px;
    border-bottom: 1px solid #eee;
    color: #666;
}

.advnews-activity-table tr:last-child td {
    border-bottom: none;
}

.advnews-activity-status {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.advnews-status-delivered {
    background: #d4edda;
    color: #155724;
}

.advnews-status-opened {
    background: #cce5ff;
    color: #004085;
}

.advnews-status-clicked {
    background: #d1ecf1;
    color: #0c5460;
}

/* Data Management */
.advnews-data-management {
    padding: 30px;
    background: #fff3cd;
    border-top: 2px solid #ffc107;
}

.advnews-data-management h3 {
    margin: 0 0 15px;
    color: #856404;
    font-size: 16px;
}

.advnews-data-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.advnews-button-link {
    background: none;
    border: none;
    color: #667eea;
    text-decoration: underline;
    cursor: pointer;
    padding: 0;
    font-size: 14px;
}

.advnews-button-link:hover {
    color: #5a67d8;
}

.advnews-text-danger {
    color: #dc3545 !important;
}

.advnews-text-danger:hover {
    color: #c82333 !important;
}

.advnews-separator {
    color: #ccc;
}

.advnews-data-note {
    margin-top: 15px;
    color: #856404;
    font-size: 13px;
}

/* Responsive */
@media (max-width: 768px) {
    .advnews-preferences-container {
        margin: 20px;
    }

    .advnews-preferences-header,
    .advnews-preferences-section,
    .advnews-form-actions,
    .advnews-recent-activity,
    .advnews-data-management {
        padding: 20px;
    }

    .advnews-form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }

    .advnews-categories-grid {
        grid-template-columns: 1fr;
    }

    .advnews-form-actions {
        flex-direction: column;
    }

    .advnews-button {
        width: 100%;
        text-align: center;
    }

    .advnews-data-actions {
        flex-direction: column;
        align-items: flex-start;
    }

    .advnews-separator {
        display: none;
    }
}
</style>
