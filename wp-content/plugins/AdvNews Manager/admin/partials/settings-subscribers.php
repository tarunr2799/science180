<?php
// admin/partials/settings-subscribers.php
if (!defined('ABSPATH')) exit;

$double_optin = get_option('advnews_double_optin', false);
$welcome_email = get_option('advnews_welcome_email', false);
$confirmation_template = get_option('advnews_confirmation_template', '');
$welcome_template = get_option('advnews_welcome_template', '');
$auto_clean_bounced = get_option('advnews_auto_clean_bounced', true);
$bounce_attempts = get_option('advnews_bounce_attempts', 3);
$blacklist = get_option('advnews_blacklist', '');
$default_category = get_option('advnews_default_category', '');
$duplicate_handling = get_option('advnews_duplicate_handling', 'skip');
$email_verification = get_option('advnews_email_verification', true);
$disposable_block = get_option('advnews_disposable_block', false);
$role_based_block = get_option('advnews_role_based_block', true);
$custom_validation = get_option('advnews_custom_validation', '');
?>

<div class="advnews-settings-section">
    <h2><?php _e('Subscriber Management Settings', 'advnews-manager'); ?></h2>

    <!-- Subscription Settings -->
    <div class="settings-group">
        <h3><?php _e('Subscription Settings', 'advnews-manager'); ?></h3>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <?php _e('Double Opt-in', 'advnews-manager'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="advnews_double_optin" value="1"
                               <?php checked($double_optin, 1); ?>>
                        <?php _e('Require email confirmation before adding to list', 'advnews-manager'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Recommended for GDPR compliance and higher quality lists.', 'advnews-manager'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php _e('Welcome Email', 'advnews-manager'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="advnews_welcome_email" value="1"
                               <?php checked($welcome_email, 1); ?>>
                        <?php _e('Send welcome email to new subscribers', 'advnews-manager'); ?>
                    </label>

                    <?php if ($welcome_email): ?>
                    <div class="template-select" style="margin-top:10px;">
                        <select name="advnews_welcome_template">
                            <option value=""><?php _e('Default Template', 'advnews-manager'); ?></option>
                            <?php
                            $templates = $this->wpdb->get_results("SELECT id, name FROM {$this->wpdb->prefix}{$this->table_prefix}templates WHERE is_active = 1");
                            foreach ($templates as $template): ?>
                                <option value="<?php echo esc_attr($template->id); ?>"
                                        <?php selected($welcome_template, $template->id); ?>>
                                    <?php echo esc_html($template->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>

            <?php if ($double_optin): ?>
            <tr>
                <th scope="row">
                    <?php _e('Confirmation Email', 'advnews-manager'); ?>
                </th>
                <td>
                    <select name="advnews_confirmation_template">
                        <option value=""><?php _e('Default Template', 'advnews-manager'); ?></option>
                        <?php foreach ($templates as $template): ?>
                            <option value="<?php echo esc_attr($template->id); ?>"
                                    <?php selected($confirmation_template, $template->id); ?>>
                                <?php echo esc_html($template->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php _e('Template for double opt-in confirmation emails.', 'advnews-manager'); ?>
                    </p>
                </td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- Email Validation -->
    <div class="settings-group">
        <h3><?php _e('Email Validation', 'advnews-manager'); ?></h3>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <?php _e('Validation Rules', 'advnews-manager'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="advnews_email_verification" value="1"
                               <?php checked($email_verification, 1); ?>>
                        <?php _e('Verify email format and domain MX records', 'advnews-manager'); ?>
                    </label><br>

                    <label>
                        <input type="checkbox" name="advnews_disposable_block" value="1"
                               <?php checked($disposable_block, 1); ?>>
                        <?php _e('Block disposable email addresses (tempmail, throwaway)', 'advnews-manager'); ?>
                    </label><br>

                    <label>
                        <input type="checkbox" name="advnews_role_based_block" value="1"
                               <?php checked($role_based_block, 1); ?>>
                        <?php _e('Block role-based emails (admin@, info@, support@)', 'advnews-manager'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="advnews_blacklist"><?php _e('Email Blacklist', 'advnews-manager'); ?></label>
                </th>
                <td>
                    <textarea id="advnews_blacklist" name="advnews_blacklist" rows="5" class="large-text"><?php
                        echo esc_textarea($blacklist);
                    ?></textarea>
                    <p class="description">
                        <?php _e('One email or domain per line. Examples: spam@example.com or @spamdomain.com', 'advnews-manager'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="advnews_custom_validation"><?php _e('Custom Validation', 'advnews-manager'); ?></label>
                </th>
                <td>
                    <textarea id="advnews_custom_validation" name="advnews_custom_validation" rows="3" class="large-text code"><?php
                        echo esc_textarea($custom_validation);
                    ?></textarea>
                    <p class="description">
                        <?php _e('PHP code for custom validation. Return true to accept, false to reject.', 'advnews-manager'); ?>
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Bounce Handling -->
    <div class="settings-group">
        <h3><?php _e('Bounce Handling', 'advnews-manager'); ?></h3>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <?php _e('Auto Clean Bounced', 'advnews-manager'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="advnews_auto_clean_bounced" value="1"
                               <?php checked($auto_clean_bounced, 1); ?>>
                        <?php _e('Automatically mark subscribers as bounced after multiple failures', 'advnews-manager'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="advnews_bounce_attempts"><?php _e('Bounce Attempts', 'advnews-manager'); ?></label>
                </th>
                <td>
                    <input type="number" id="advnews_bounce_attempts" name="advnews_bounce_attempts"
                           value="<?php echo esc_attr($bounce_attempts); ?>" class="small-text" min="1" max="10" step="1">
                    <span class="description"><?php _e('attempts before marking as bounced', 'advnews-manager'); ?></span>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php _e('Bounce Actions', 'advnews-manager'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="advnews_notify_bounce" value="1"
                               <?php checked(get_option('advnews_notify_bounce', true), 1); ?>>
                        <?php _e('Send email notification on high bounce rate', 'advnews-manager'); ?>
                    </label><br>

                    <label>
                        <input type="checkbox" name="advnews_remove_bounced" value="1"
                               <?php checked(get_option('advnews_remove_bounced', false), 1); ?>>
                        <?php _e('Permanently remove bounced emails after 30 days', 'advnews-manager'); ?>
                    </label>
                </td>
            </tr>
        </table>
    </div>

    <!-- Import/Export Defaults -->
    <div class="settings-group">
        <h3><?php _e('Import/Export Defaults', 'advnews-manager'); ?></h3>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="advnews_default_category"><?php _e('Default Category', 'advnews-manager'); ?></label>
                </th>
                <td>
                    <select id="advnews_default_category" name="advnews_default_category">
                        <option value=""><?php _e('None', 'advnews-manager'); ?></option>
                        <?php
                        $categories = $this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}{$this->table_prefix}categories ORDER BY name");
                        foreach ($categories as $category): ?>
                            <option value="<?php echo esc_attr($category->id); ?>"
                                    <?php selected($default_category, $category->id); ?>>
                                <?php echo esc_html($category->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php _e('Default category for imported subscribers when not specified.', 'advnews-manager'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="advnews_duplicate_handling"><?php _e('Duplicate Handling', 'advnews-manager'); ?></label>
                </th>
                <td>
                    <select id="advnews_duplicate_handling" name="advnews_duplicate_handling">
                        <option value="skip" <?php selected($duplicate_handling, 'skip'); ?>>
                            <?php _e('Skip duplicates (keep existing)', 'advnews-manager'); ?>
                        </option>
                        <option value="update" <?php selected($duplicate_handling, 'update'); ?>>
                            <?php _e('Update existing subscribers', 'advnews-manager'); ?>
                        </option>
                        <option value="ignore" <?php selected($duplicate_handling, 'ignore'); ?>>
                            <?php _e('Ignore (allow duplicates)', 'advnews-manager'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('How to handle duplicate emails during import.', 'advnews-manager'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="advnews_export_fields"><?php _e('Default Export Fields', 'advnews-manager'); ?></label>
                </th>
                <td>
                    <select id="advnews_export_fields" name="advnews_export_fields[]" multiple size="5" style="min-width:200px;">
                        <option value="email" selected><?php _e('Email', 'advnews-manager'); ?></option>
                        <option value="first_name" selected><?php _e('First Name', 'advnews-manager'); ?></option>
                        <option value="last_name" selected><?php _e('Last Name', 'advnews-manager'); ?></option>
                        <option value="organization"><?php _e('Organization', 'advnews-manager'); ?></option>
                        <option value="categories"><?php _e('Categories', 'advnews-manager'); ?></option>
                        <option value="status"><?php _e('Status', 'advnews-manager'); ?></option>
                        <option value="subscribed_date"><?php _e('Subscribed Date', 'advnews-manager'); ?></option>
                        <option value="open_rate"><?php _e('Open Rate', 'advnews-manager'); ?></option>
                        <option value="click_rate"><?php _e('Click Rate', 'advnews-manager'); ?></option>
                    </select>
                    <p class="description">
                        <?php _e('Hold Ctrl/Cmd to select multiple default fields.', 'advnews-manager'); ?>
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Subscriber Limits -->
    <div class="settings-group">
        <h3><?php _e('Subscriber Limits', 'advnews-manager'); ?></h3>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="advnews_max_subscribers"><?php _e('Maximum Subscribers', 'advnews-manager'); ?></label>
                </th>
                <td>
                    <input type="number" id="advnews_max_subscribers" name="advnews_max_subscribers"
                           value="<?php echo esc_attr(get_option('advnews_max_subscribers', 0)); ?>" class="regular-text"
                           min="0" step="1000">
                    <p class="description">
                        <?php _e('Maximum number of active subscribers (0 for unlimited).', 'advnews-manager'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php _e('Block When Full', 'advnews-manager'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="advnews_block_when_full" value="1"
                               <?php checked(get_option('advnews_block_when_full', false), 1); ?>>
                        <?php _e('Block new subscriptions when limit is reached', 'advnews-manager'); ?>
                    </label>
                </td>
            </tr>
        </table>
    </div>

    <!-- Test Subscription -->
    <div class="settings-group">
        <h3><?php _e('Test Subscription Flow', 'advnews-manager'); ?></h3>

        <p><?php _e('Test how new subscribers experience your signup process.', 'advnews-manager'); ?></p>

        <div class="test-subscription">
            <input type="email" id="test-subscriber-email" class="regular-text"
                   placeholder="<?php _e('Enter test email', 'advnews-manager'); ?>"
                   value="<?php echo esc_attr(get_option('admin_email')); ?>">
            <button type="button" id="test-subscription" class="button"><?php _e('Test Signup', 'advnews-manager'); ?></button>
        </div>

        <div id="test-result" style="display:none; margin-top:15px;"></div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Test subscription
    $('#test-subscription').on('click', function() {
        var testEmail = $('#test-subscriber-email').val();
        if (!testEmail) {
            alert('<?php _e('Please enter a test email address.', 'advnews-manager'); ?>');
            return;
        }

        var button = $(this);
        var resultDiv = $('#test-result');

        button.prop('disabled', true).text('<?php _e('Testing...', 'advnews-manager'); ?>');
        resultDiv.hide();

        $.ajax({
            url: advnews_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'advnews_test_subscription',
                email: testEmail,
                nonce: advnews_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultDiv.removeClass('error').addClass('updated')
                        .html('<p>' + response.data.message + '</p>').show();
                } else {
                    resultDiv.removeClass('updated').addClass('error')
                        .html('<p>' + response.data.message + '</p>').show();
                }
            },
            error: function() {
                resultDiv.removeClass('updated').addClass('error')
                    .html('<p><?php _e('Test failed. Please try again.', 'advnews-manager'); ?></p>').show();
            },
            complete: function() {
                button.prop('disabled', false).text('<?php _e('Test Signup', 'advnews-manager'); ?>');
            }
        });
    });
});
</script>

<style>
.test-subscription {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.test-subscription input {
    flex: 1;
    min-width: 250px;
}

.template-select {
    margin-top: 10px;
}

#test-result.updated {
    background: #d4edda;
    border-left: 4px solid #00a32a;
    padding: 10px 15px;
}

#test-result.error {
    background: #f8d7da;
    border-left: 4px solid #d63638;
    padding: 10px 15px;
}
</style>
