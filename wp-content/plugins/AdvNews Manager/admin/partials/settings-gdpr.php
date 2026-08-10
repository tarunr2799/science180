<?php
// admin/partials/settings-gdpr.php
if (!defined('ABSPATH')) exit;

$gdpr_compliance = get_option('advnews_gdpr_compliance', true);
$consent_checkbox = get_option('advnews_consent_checkbox', true);
$consent_text = get_option('advnews_consent_text', __('I agree to receive newsletters and accept the privacy policy.', 'advnews-manager'));
$privacy_policy_url = get_option('advnews_privacy_policy_url', get_privacy_policy_url());
$data_retention_days = get_option('advnews_data_retention_days', 365);
$anonymize_ip = get_option('advnews_anonymize_ip', true);
$export_data = get_option('advnews_export_data', true);
$delete_data = get_option('advnews_delete_data', true);
$cookie_consent = get_option('advnews_cookie_consent', false);
$cookie_message = get_option('advnews_cookie_message', __('This website uses cookies to ensure you get the best experience.', 'advnews-manager'));
$age_verification = get_option('advnews_age_verification', false);
$minimum_age = get_option('advnews_minimum_age', 16);
?>

<div class="advnews-settings-section">
    <h2><?php _e('GDPR & Privacy Settings', 'advnews-manager'); ?></h2>

    <div class="gdpr-notice notice notice-warning">
        <p>
            <strong><?php _e('Important:', 'advnews-manager'); ?></strong>
            <?php _e('These settings help you comply with GDPR and other privacy regulations. Consult with a legal professional to ensure full compliance.', 'advnews-manager'); ?>
        </p>
    </div>

    <!-- General Compliance -->
    <div class="settings-group">
        <h3><?php _e('General Compliance', 'advnews-manager'); ?></h3>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <?php _e('GDPR Mode', 'advnews-manager'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="advnews_gdpr_compliance" value="1"
                               <?php checked($gdpr_compliance, 1); ?>>
                        <?php _e('Enable GDPR compliance features', 'advnews-manager'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Enables consent checkboxes, data export, and right to be forgotten.', 'advnews-manager'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="advnews_privacy_policy_url"><?php _e('Privacy Policy URL', 'advnews-manager'); ?></label>
                </th>
                <td>
                    <input type="url" id="advnews_privacy_policy_url" name="advnews_privacy_policy_url"
                           value="<?php echo esc_url($privacy_policy_url); ?>" class="regular-text">
                    <p class="description">
                        <?php _e('Link to your privacy policy page.', 'advnews-manager'); ?>
                        <?php if (get_privacy_policy_url()): ?>
                            <a href="<?php echo esc_url(get_privacy_policy_url()); ?>" target="_blank"><?php _e('View', 'advnews-manager'); ?></a>
                        <?php endif; ?>
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Consent Management -->
    <div class="settings-group">
        <h3><?php _e('Consent Management', 'advnews-manager'); ?></h3>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <?php _e('Consent Checkbox', 'advnews-manager'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="advnews_consent_checkbox" value="1"
                               <?php checked($consent_checkbox, 1); ?>>
                        <?php _e('Show consent checkbox on subscription forms', 'advnews-manager'); ?>
                    </label>

                    <?php if ($consent_checkbox): ?>
                    <div class="consent-settings" style="margin-top:15px;">
                        <label for="advnews_consent_text"><?php _e('Consent Text:', 'advnews-manager'); ?></label>
                        <textarea id="advnews_consent_text" name="advnews_consent_text" rows="3" class="large-text"><?php
                            echo esc_textarea($consent_text);
                        ?></textarea>
                        <p class="description">
                            <?php _e('Text displayed next to the consent checkbox.', 'advnews-manager'); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php _e('Age Verification', 'advnews-manager'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="advnews_age_verification" value="1"
                               <?php checked($age_verification, 1); ?>>
                        <?php _e('Require age verification for subscription', 'advnews-manager'); ?>
                    </label>

                    <?php if ($age_verification): ?>
                    <div class="age-settings" style="margin-top:10px;">
                        <label for="advnews_minimum_age"><?php _e('Minimum Age:', 'advnews-manager'); ?></label>
                        <input type="number" id="advnews_minimum_age" name="advnews_minimum_age"
                               value="<?php echo esc_attr($minimum_age); ?>" class="small-text" min="13" max="21" step="1">
                        <span class="description"><?php _e('years', 'advnews-manager'); ?></span>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php _e('Cookie Consent', 'advnews-manager'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="advnews_cookie_consent" value="1"
                               <?php checked($cookie_consent, 1); ?>>
                        <?php _e('Show cookie consent notice (for tracking pixels)', 'advnews-manager'); ?>
                    </label>

                    <?php if ($cookie_consent): ?>
                    <div class="cookie-settings" style="margin-top:10px;">
                        <label for="advnews_cookie_message"><?php _e('Cookie Message:', 'advnews-manager'); ?></label>
                        <input type="text" id="advnews_cookie_message" name="advnews_cookie_message"
                               value="<?php echo esc_attr($cookie_message); ?>" class="regular-text">
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>

    <!-- Data Processing -->
    <div class="settings-group">
        <h3><?php _e('Data Processing', 'advnews-manager'); ?></h3>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <?php _e('IP Anonymization', 'advnews-manager'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="advnews_anonymize_ip" value="1"
                               <?php checked($anonymize_ip, 1); ?>>
                        <?php _e('Anonymize IP addresses before storing', 'advnews-manager'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Removes the last octet from IPv4 addresses (e.g., 192.168.1.0 instead of 192.168.1.100)', 'advnews-manager'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="advnews_data_retention_days"><?php _e('Data Retention Period', 'advnews-manager'); ?></label>
                </th>
                <td>
                    <input type="number" id="advnews_data_retention_days" name="advnews_data_retention_days"
                           value="<?php echo esc_attr($data_retention_days); ?>" class="small-text" min="30" max="3650" step="30">
                    <span class="description"><?php _e('days', 'advnews-manager'); ?></span>
                    <p class="description">
                        <?php _e('How long to keep subscriber data after unsubscribing before anonymization.', 'advnews-manager'); ?>
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Data Subject Rights -->
    <div class="settings-group">
        <h3><?php _e('Data Subject Rights', 'advnews-manager'); ?></h3>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <?php _e('Right to Access', 'advnews-manager'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="advnews_export_data" value="1"
                               <?php checked($export_data, 1); ?>>
                        <?php _e('Allow subscribers to export their data', 'advnews-manager'); ?>
                    </label>

                    <?php if ($export_data): ?>
                    <p class="description">
                        <?php _e('Export link will be available in subscription management page.', 'advnews-manager'); ?>
                    </p>
                    <?php endif; ?>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php _e('Right to be Forgotten', 'advnews-manager'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="advnews_delete_data" value="1"
                               <?php checked($delete_data, 1); ?>>
                        <?php _e('Allow subscribers to request data deletion', 'advnews-manager'); ?>
                    </label>

                    <?php if ($delete_data): ?>
                    <p class="description">
                        <?php _e('Note: Email addresses will be anonymized, not permanently deleted, to prevent re-subscription.', 'advnews-manager'); ?>
                    </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>

    <!-- Data Processing Agreement -->
    <div class="settings-group">
        <h3><?php _e('Data Processing Agreement', 'advnews-manager'); ?></h3>

        <div class="dpa-section">
            <p><?php _e('If you use third-party services for email delivery, you may need a Data Processing Agreement (DPA).', 'advnews-manager'); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <?php _e('SMTP Provider DPA', 'advnews-manager'); ?>
                    </th>
                    <td>
                        <?php
                        $smtp_host = get_option('advnews_smtp_host', '');
                        $provider = $this->identify_smtp_provider($smtp_host);

                        if ($provider): ?>
                            <p>
                                <strong><?php echo esc_html(ucfirst($provider)); ?></strong>
                                <?php if ($provider === 'gmail'): ?>
                                    <a href="https://cloud.google.com/terms/data-processing-terms" target="_blank">
                                        <?php _e('View DPA', 'advnews-manager'); ?>
                                    </a>
                                <?php elseif ($provider === 'sendgrid'): ?>
                                    <a href="https://www.twilio.com/legal/data-protection" target="_blank">
                                        <?php _e('View DPA', 'advnews-manager'); ?>
                                    </a>
                                <?php elseif ($provider === 'mailgun'): ?>
                                    <a href="https://www.mailgun.com/privacy/" target="_blank">
                                        <?php _e('View DPA', 'advnews-manager'); ?>
                                    </a>
                                <?php endif; ?>
                            </p>
                        <?php else: ?>
                            <p class="description">
                                <?php _e('Configure your SMTP provider above to see DPA information.', 'advnews-manager'); ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Tools -->
    <div class="settings-group">
        <h3><?php _e('Privacy Tools', 'advnews-manager'); ?></h3>

        <div class="tools-grid">
            <div class="tool-card">
                <h4><?php _e('Export Subscriber Data', 'advnews-manager'); ?></h4>
                <p><?php _e('Export all data for a specific email address.', 'advnews-manager'); ?></p>
                <div class="tool-input">
                    <input type="email" id="export-email" class="regular-text"
                           placeholder="<?php _e('Enter email address', 'advnews-manager'); ?>">
                    <button type="button" class="button" id="export-subscriber-data"><?php _e('Export', 'advnews-manager'); ?></button>
                </div>
            </div>

            <div class="tool-card">
                <h4><?php _e('Anonymize Subscriber', 'advnews-manager'); ?></h4>
                <p><?php _e('Anonymize all data for a specific email address (Right to be Forgotten).', 'advnews-manager'); ?></p>
                <div class="tool-input">
                    <input type="email" id="anonymize-email" class="regular-text"
                           placeholder="<?php _e('Enter email address', 'advnews-manager'); ?>">
                    <button type="button" class="button" id="anonymize-subscriber"><?php _e('Anonymize', 'advnews-manager'); ?></button>
                </div>
            </div>

            <div class="tool-card">
                <h4><?php _e('Consent Log', 'advnews-manager'); ?></h4>
                <p><?php _e('View consent history for subscribers.', 'advnews-manager'); ?></p>
                <button type="button" class="button" id="view-consent-log"><?php _e('View Log', 'advnews-manager'); ?></button>
            </div>
        </div>

        <div id="privacy-result" style="display:none; margin-top:15px;"></div>
    </div>

    <!-- Privacy Policy Template -->
    <div class="settings-group">
        <h3><?php _e('Privacy Policy Template', 'advnews-manager'); ?></h3>

        <p><?php _e('Suggested text to include in your privacy policy:', 'advnews-manager'); ?></p>

        <div class="privacy-template">
            <h4><?php _e('Data Collection', 'advnews-manager'); ?></h4>
            <p>
                <?php _e('When you subscribe to our newsletter, we collect:', 'advnews-manager'); ?>
            </p>
            <ul>
                <li><?php _e('Email address (required)', 'advnews-manager'); ?></li>
                <li><?php _e('Name (optional)', 'advnews-manager'); ?></li>
                <li><?php _e('IP address (anonymized)', 'advnews-manager'); ?></li>
                <li><?php _e('Email engagement data (opens, clicks)', 'advnews-manager'); ?></li>
            </ul>

            <h4><?php _e('Data Usage', 'advnews-manager'); ?></h4>
            <p>
                <?php _e('We use this data to send you newsletters, personalize content, and improve our email campaigns.', 'advnews-manager'); ?>
            </p>

            <h4><?php _e('Data Sharing', 'advnews-manager'); ?></h4>
            <p>
                <?php _e('We use [SMTP Provider] to deliver emails. Your data is processed according to their privacy policy and data processing agreement.', 'advnews-manager'); ?>
            </p>

            <h4><?php _e('Your Rights', 'advnews-manager'); ?></h4>
            <p>
                <?php _e('You have the right to:', 'advnews-manager'); ?>
            </p>
            <ul>
                <li><?php _e('Access your personal data', 'advnews-manager'); ?></li>
                <li><?php _e('Rectify inaccurate data', 'advnews-manager'); ?></li>
                <li><?php _e('Request erasure of your data', 'advnews-manager'); ?></li>
                <li><?php _e('Withdraw consent at any time', 'advnews-manager'); ?></li>
            </ul>

            <p>
                <button type="button" class="button" id="copy-privacy-template"><?php _e('Copy to Clipboard', 'advnews-manager'); ?></button>
            </p>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Export subscriber data
    $('#export-subscriber-data').on('click', function() {
        var email = $('#export-email').val();
        if (!email) {
            alert('<?php _e('Please enter an email address.', 'advnews-manager'); ?>');
            return;
        }

        var button = $(this);
        var resultDiv = $('#privacy-result');

        button.prop('disabled', true).text('<?php _e('Exporting...', 'advnews-manager'); ?>');
        resultDiv.hide();

        $.ajax({
            url: advnews_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'advnews_export_subscriber_gdpr',
                email: email,
                nonce: advnews_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Create download link
                    var blob = new Blob([JSON.stringify(response.data, null, 2)], {type: 'application/json'});
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'subscriber-data-' + email + '.json';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);

                    resultDiv.removeClass('error').addClass('updated')
                        .html('<p><?php _e('Data exported successfully.', 'advnews-manager'); ?></p>').show();
                } else {
                    resultDiv.removeClass('updated').addClass('error')
                        .html('<p>' + response.data.message + '</p>').show();
                }
            },
            error: function() {
                resultDiv.removeClass('updated').addClass('error')
                    .html('<p><?php _e('Export failed.', 'advnews-manager'); ?></p>').show();
            },
            complete: function() {
                button.prop('disabled', false).text('<?php _e('Export', 'advnews-manager'); ?>');
            }
        });
    });

    // Anonymize subscriber
    $('#anonymize-subscriber').on('click', function() {
        var email = $('#anonymize-email').val();
        if (!email) {
            alert('<?php _e('Please enter an email address.', 'advnews-manager'); ?>');
            return;
        }

        if (!confirm('<?php _e('Are you sure? This will anonymize all data for this email address. This action cannot be undone.', 'advnews-manager'); ?>')) {
            return;
        }

        var button = $(this);
        var resultDiv = $('#privacy-result');

        button.prop('disabled', true).text('<?php _e('Anonymizing...', 'advnews-manager'); ?>');
        resultDiv.hide();

        $.ajax({
            url: advnews_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'advnews_anonymize_subscriber',
                email: email,
                nonce: advnews_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultDiv.removeClass('error').addClass('updated')
                        .html('<p>' + response.data.message + '</p>').show();
                    $('#anonymize-email').val('');
                } else {
                    resultDiv.removeClass('updated').addClass('error')
                        .html('<p>' + response.data.message + '</p>').show();
                }
            },
            error: function() {
                resultDiv.removeClass('updated').addClass('error')
                    .html('<p><?php _e('Anonymization failed.', 'advnews-manager'); ?></p>').show();
            },
            complete: function() {
                button.prop('disabled', false).text('<?php _e('Anonymize', 'advnews-manager'); ?>');
            }
        });
    });

    // View consent log
    $('#view-consent-log').on('click', function() {
        var resultDiv = $('#privacy-result');
        resultDiv.show().html('<p><?php _e('Loading...', 'advnews-manager'); ?></p>');

        $.ajax({
            url: advnews_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'advnews_get_consent_log',
                nonce: advnews_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var html = '<h4><?php _e('Recent Consent Records', 'advnews-manager'); ?></h4>';
                    html += '<table class="wp-list-table widefat fixed striped">';
                    html += '<thead><tr><th><?php _e('Email', 'advnews-manager'); ?></th><th><?php _e('Action', 'advnews-manager'); ?></th><th><?php _e('Date', 'advnews-manager'); ?></th><th><?php _e('IP', 'advnews-manager'); ?></th></tr></thead>';
                    html += '<tbody>';

                    if (response.data.length === 0) {
                        html += '<tr><td colspan="4"><?php _e('No consent records found.', 'advnews-manager'); ?></td></tr>';
                    } else {
                        $.each(response.data, function(i, record) {
                            html += '<tr>';
                            html += '<td>' + record.email + '</td>';
                            html += '<td>' + record.action + '</td>';
                            html += '<td>' + record.date + '</td>';
                            html += '<td>' + record.ip + '</td>';
                            html += '</tr>';
                        });
                    }

                    html += '</tbody></table>';
                    resultDiv.removeClass('error updated').html(html).show();
                } else {
                    resultDiv.removeClass('updated').addClass('error')
                        .html('<p>' + response.data.message + '</p>').show();
                }
            },
            error: function() {
                resultDiv.removeClass('updated').addClass('error')
                    .html('<p><?php _e('Failed to load consent log.', 'advnews-manager'); ?></p>').show();
            }
        });
    });

    // Copy privacy template
    $('#copy-privacy-template').on('click', function() {
        var template = $('.privacy-template').text();
        navigator.clipboard.writeText(template).then(function() {
            alert('<?php _e('Privacy policy template copied to clipboard!', 'advnews-manager'); ?>');
        });
    });
});
</script>

<style>
.gdpr-notice {
    margin-bottom: 20px;
}

.consent-settings,
.age-settings,
.cookie-settings {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    margin-top: 10px;
}

.tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.tool-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
}

.tool-card h4 {
    margin-top: 0;
    margin-bottom: 10px;
    font-size: 16px;
}

.tool-card p {
    color: #646970;
    margin-bottom: 15px;
}

.tool-input {
    display: flex;
    gap: 10px;
}

.tool-input input {
    flex: 1;
}

.privacy-template {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    max-height: 400px;
    overflow-y: auto;
}

.privacy-template h4 {
    margin-top: 15px;
    margin-bottom: 10px;
    color: #1d2327;
}

.privacy-template h4:first-child {
    margin-top: 0;
}

.privacy-template ul {
    margin-left: 20px;
    color: #646970;
}

#privacy-result.updated {
    background: #d4edda;
    border-left: 4px solid #00a32a;
    padding: 15px;
}

#privacy-result.error {
    background: #f8d7da;
    border-left: 4px solid #d63638;
    padding: 15px;
}

#privacy-result table {
    margin-top: 15px;
}
</style>
