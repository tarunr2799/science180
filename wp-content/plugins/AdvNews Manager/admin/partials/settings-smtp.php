<?php
// admin/partials/settings-smtp.php
if (!defined('ABSPATH')) exit;

// Test decryption on page load for debugging
$smtp_password = get_option('advnews_smtp_password', '');
if (!empty($smtp_password) && defined('WP_DEBUG') && WP_DEBUG) {
    $decrypted = AdvNews_Security::decrypt($smtp_password);
    error_log('[AdvNews SMTP Settings] Password stored length: ' . strlen($smtp_password));
    error_log('[AdvNews SMTP Settings] Password decrypted length: ' . strlen($decrypted));
    error_log('[AdvNews SMTP Settings] Password is_encrypted: ' . (AdvNews_Security::is_encrypted($smtp_password) ? 'YES' : 'NO'));
}



$smtp_host = get_option('advnews_smtp_host', '');
$smtp_port = get_option('advnews_smtp_port', 587);
$smtp_encryption = get_option('advnews_smtp_encryption', 'tls');
$smtp_username = get_option('advnews_smtp_username', '');
$smtp_password = get_option('advnews_smtp_password', '');
$smtp_from_email = get_option('advnews_smtp_from_email', '');
$smtp_from_name = get_option('advnews_smtp_from_name', '');
$smtp_authentication = get_option('advnews_smtp_authentication', 1);
?>
<div class="advnews-settings-section">
    <h2><?php _e('SMTP Configuration', 'advnews-manager'); ?></h2>
    <p class="description"><?php _e('Configure SMTP settings for reliable email delivery. Leave empty to use WordPress default mail function.', 'advnews-manager'); ?></p>

    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="advnews_smtp_host"><?php _e('SMTP Host', 'advnews-manager'); ?></label>
            </th>
            <td>
                <input type="text" id="advnews_smtp_host" name="advnews_smtp_host"
                    value="<?php echo esc_attr($smtp_host); ?>" class="regular-text"
                    placeholder="smtp.gmail.com">
                <p class="description"><?php _e('Your SMTP server address', 'advnews-manager'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="advnews_smtp_port"><?php _e('SMTP Port', 'advnews-manager'); ?></label>
            </th>
            <td>
                <input type="number" id="advnews_smtp_port" name="advnews_smtp_port"
                    value="<?php echo esc_attr($smtp_port); ?>" class="small-text"
                    min="1" max="65535" step="1">
                <p class="description">
                    <?php _e('Common ports: 25, 465 (SSL), 587 (TLS)', 'advnews-manager'); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="advnews_smtp_encryption"><?php _e('Encryption', 'advnews-manager'); ?></label>
            </th>
            <td>
                <select id="advnews_smtp_encryption" name="advnews_smtp_encryption">
                    <option value="none" <?php selected($smtp_encryption, 'none'); ?>><?php _e('None', 'advnews-manager'); ?></option>
                    <option value="ssl" <?php selected($smtp_encryption, 'ssl'); ?>><?php _e('SSL', 'advnews-manager'); ?></option>
                    <option value="tls" <?php selected($smtp_encryption, 'tls'); ?>><?php _e('TLS', 'advnews-manager'); ?></option>
                </select>
                <p class="description"><?php _e('Encryption method for secure connection', 'advnews-manager'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="advnews_smtp_authentication"><?php _e('Authentication', 'advnews-manager'); ?></label>
            </th>
            <td>
                <label>
                    <input type="checkbox" id="advnews_smtp_authentication" name="advnews_smtp_authentication"
                        value="1" <?php checked($smtp_authentication, 1); ?>>
                    <?php _e('Use SMTP authentication', 'advnews-manager'); ?>
                </label>
                <p class="description"><?php _e('Most SMTP providers require authentication', 'advnews-manager'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="advnews_smtp_username"><?php _e('Username', 'advnews-manager'); ?></label>
            </th>
            <td>
                <input type="text" id="advnews_smtp_username" name="advnews_smtp_username"
                    value="<?php echo esc_attr($smtp_username); ?>" class="regular-text"
                    autocomplete="off">
                <p class="description"><?php _e('Your SMTP username (usually email address)', 'advnews-manager'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="advnews_smtp_password"><?php _e('Password', 'advnews-manager'); ?></label>
            </th>
            <td>
                <input type="password" id="advnews_smtp_password" name="advnews_smtp_password"
                    value="<?php echo esc_attr($smtp_password); ?>" class="regular-text"
                    autocomplete="off">
                <p class="description">
                    <?php _e('Your SMTP password or API key', 'advnews-manager'); ?>
                    <?php if ($smtp_password): ?>
                    <br><strong><?php _e('Password is stored encrypted.', 'advnews-manager'); ?></strong>
                    <?php endif; ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="advnews_smtp_from_email"><?php _e('From Email (Optional)', 'advnews-manager'); ?></label>
            </th>
            <td>
                <input type="email" id="advnews_smtp_from_email" name="advnews_smtp_from_email"
                    value="<?php echo esc_attr($smtp_from_email); ?>" class="regular-text"
                    placeholder="<?php echo esc_attr(get_option('advnews_from_email')); ?>">
                <p class="description"><?php _e('Override the default from email for SMTP', 'advnews-manager'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="advnews_smtp_from_name"><?php _e('From Name (Optional)', 'advnews-manager'); ?></label>
            </th>
            <td>
                <input type="text" id="advnews_smtp_from_name" name="advnews_smtp_from_name"
                    value="<?php echo esc_attr($smtp_from_name); ?>" class="regular-text"
                    placeholder="<?php echo esc_attr(get_option('advnews_from_name')); ?>">
                <p class="description"><?php _e('Override the default from name for SMTP', 'advnews-manager'); ?></p>
            </td>
        </tr>
    </table>

    <h3><?php _e('Test SMTP Connection', 'advnews-manager'); ?></h3>
    <p><?php _e('Send a test email to verify your SMTP settings.', 'advnews-manager'); ?></p>
    <div class="smtp-test-area">
        <input type="email" id="test-email" class="regular-text"
            placeholder="<?php _e('Enter test email address', 'advnews-manager'); ?>"
            value="<?php echo esc_attr(get_option('admin_email')); ?>">
        <button type="button" id="test-smtp" class="button"><?php _e('Send Test Email', 'advnews-manager'); ?></button>
        <span id="test-spinner" class="spinner" style="float: none;"></span>
    </div>
    <div id="test-result" style="display:none; margin-top:15px;"></div>

    <h3><?php _e('Authentication Tips', 'advnews-manager'); ?></h3>
    <div class="auth-tips">
        <div class="tip-card">
            <h4>Gmail / Google Workspace</h4>
            <ol>
                <li><?php _e('Enable 2-factor authentication', 'advnews-manager'); ?></li>
                <li><?php _e('Generate an App Password', 'advnews-manager'); ?></li>
                <li><?php _e('Use App Password instead of regular password', 'advnews-manager'); ?></li>
            </ol>
            <p><a href="https://support.google.com/accounts/answer/185833" target="_blank"><?php _e('Learn more', 'advnews-manager'); ?></a></p>
        </div>
        <div class="tip-card">
            <h4>SendGrid</h4>
            <ol>
                <li><?php _e('Create an API Key with "Mail Send" permissions', 'advnews-manager'); ?></li>
                <li><?php _e('Use API Key as password', 'advnews-manager'); ?></li>
                <li><?php _e('Host: smtp.sendgrid.net', 'advnews-manager'); ?></li>
                <li><?php _e('Port: 587 (TLS)', 'advnews-manager'); ?></li>
            </ol>
        </div>
        <div class="tip-card">
            <h4>Amazon SES</h4>
            <ol>
                <li><?php _e('Verify your domain/email in AWS Console', 'advnews-manager'); ?></li>
                <li><?php _e('Create SMTP credentials', 'advnews-manager'); ?></li>
                <li><?php _e('Use the generated username/password', 'advnews-manager'); ?></li>
                <li><?php _e('Region-specific host: email-smtp.us-east-1.amazonaws.com', 'advnews-manager'); ?></li>
            </ol>
        </div>
        <div class="tip-card">
            <h4>Mailgun</h4>
            <ol>
                <li><?php _e('Get your SMTP credentials from Mailgun dashboard', 'advnews-manager'); ?></li>
                <li><?php _e('Use your domain as username', 'advnews-manager'); ?></li>
                <li><?php _e('Use your SMTP password', 'advnews-manager'); ?></li>
                <li><?php _e('Host: smtp.mailgun.org', 'advnews-manager'); ?></li>
            </ol>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    console.log('AdvNews SMTP Settings JS initialized');

    // Provider presets
    $('.preset-btn').on('click', function() {
        var provider = $(this).data('provider');
        var presets = {
            'sendgrid': {
                host: 'smtp.sendgrid.net',
                port: 587,
                encryption: 'tls'
            },
            'amazon': {
                host: 'email-smtp.us-east-1.amazonaws.com',
                port: 587,
                encryption: 'tls'
            },
            'mailgun': {
                host: 'smtp.mailgun.org',
                port: 587,
                encryption: 'tls'
            },
            'gmail': {
                host: 'smtp.gmail.com',
                port: 587,
                encryption: 'tls'
            },
            'office365': {
                host: 'smtp.office365.com',
                port: 587,
                encryption: 'tls'
            }
        };
        if (presets[provider]) {
            $('#advnews_smtp_host').val(presets[provider].host);
            $('#advnews_smtp_port').val(presets[provider].port);
            $('#advnews_smtp_encryption').val(presets[provider].encryption);
            console.log('Preset applied:', provider);
        }
    });

    // Test SMTP connection - FIXED VERSION
    $('#test-smtp').on('click', function() {
        var testEmail = $('#test-email').val();
        if (!testEmail) {
            alert('<?php _e('Please enter a test email address.', 'advnews-manager'); ?>');
            return;
        }

        var button = $(this);
        var spinner = $('#test-spinner');
        var resultDiv = $('#test-result');

        console.log('Starting SMTP test...');
        console.log('Test email:', testEmail);

        button.prop('disabled', true);
        spinner.addClass('is-active');
        resultDiv.hide().removeClass('updated error');

        // Use WordPress ajaxurl global (always available)
        var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var nonce = '<?php echo wp_create_nonce('advnews_ajax_nonce'); ?>';

        console.log('AJAX URL:', ajaxUrl);
        console.log('Nonce:', nonce);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'advnews_test_smtp',
                test_email: testEmail,
                _wpnonce: nonce,
                nonce: nonce
            },
            success: function(response) {
                console.log('SMTP Test Response:', response);

                if (response.success) {
                    resultDiv.removeClass('error').addClass('updated')
                        .html('<p><strong><?php _e('Success!', 'advnews-manager'); ?></strong> ' + response.data.message + '</p>')
                        .show();
                } else {
                    resultDiv.removeClass('updated').addClass('error')
                        .html('<p><strong><?php _e('Error!', 'advnews-manager'); ?></strong> ' +
                              (response.data.message || '<?php _e('Unknown error occurred.', 'advnews-manager'); ?>') +
                              (response.data.debug ? '<pre>' + JSON.stringify(response.data.debug, null, 2) + '</pre>' : '') +
                              '</p>')
                        .show();
                }
            },
            error: function(xhr, status, error) {
                console.error('SMTP Test Error:', xhr.responseText);
                console.error('Status:', status);
                console.error('Error:', error);
                resultDiv.removeClass('updated').addClass('error')
                    .html('<p><strong><?php _e('Error!', 'advnews-manager'); ?></strong> ' +
                          '<?php _e('Connection failed. Please check your settings and server logs.', 'advnews-manager'); ?>' +
                          '<pre>' + xhr.responseText + '</pre></p>')
                    .show();
            },
            complete: function() {
                button.prop('disabled', false);
                spinner.removeClass('is-active');
                console.log('SMTP test complete');
            }
        });
    });

    console.log('AdvNews SMTP Settings JS ready');
});
</script>

<style>
.advnews-settings-section {
    max-width: 800px;
}
.smtp-test-area {
    background: #f8f9fa;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}
.smtp-test-area input[type="email"] {
    flex: 1;
    min-width: 250px;
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
#test-result pre {
    background: rgba(0,0,0,0.05);
    padding: 10px;
    border-radius: 4px;
    overflow-x: auto;
    font-size: 12px;
    margin-top: 10px;
}
.auth-tips {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}
.tip-card {
    background: #f8f9fa;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 15px;
}
.tip-card h4 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #1d2327;
    font-size: 14px;
}
.tip-card ol {
    margin: 0 0 10px 20px;
    font-size: 13px;
    color: #646970;
}
.tip-card li {
    margin-bottom: 3px;
}
@media (max-width: 782px) {
    .smtp-test-area {
        flex-direction: column;
        align-items: stretch;
    }
    .smtp-test-area input[type="email"] {
        width: 100%;
    }
}
</style>
