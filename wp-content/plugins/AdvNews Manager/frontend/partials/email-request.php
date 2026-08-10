<?php
// frontend/partials/email-request.php
if (!defined('ABSPATH')) exit;
?>

<div class="advnews-email-request-container">
    <div class="advnews-email-request-card">
        <div class="advnews-email-request-header">
            <h2><?php _e('Access Your Subscription', 'advnews-manager'); ?></h2>
            <p class="advnews-request-description">
                <?php _e('Enter your email address below to receive a secure link to manage your subscription preferences.', 'advnews-manager'); ?>
            </p>
        </div>

        <form class="advnews-email-request-form" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" id="advnews-email-request-form">
            <input type="hidden" name="action" value="advnews_frontend_request_access">
            <?php wp_nonce_field('advnews_frontend_request_access', '_wpnonce'); ?>

            <div class="advnews-form-group">
                <label for="request_email"><?php _e('Email Address', 'advnews-manager'); ?> <span class="advnews-required">*</span></label>
                <input type="email" id="request_email" name="email" class="advnews-input"
                       required placeholder="<?php _e('your@email.com', 'advnews-manager'); ?>">
            </div>

            <?php if (get_option('advnews_gdpr_compliance')): ?>
            <div class="advnews-form-group advnews-consent-group">
                <label class="advnews-checkbox-label">
                    <input type="checkbox" name="consent" value="1" required>
                    <span class="advnews-checkbox-text">
                        <?php printf(
                            __('I agree to receive an email with access link. %s', 'advnews-manager'),
                            get_privacy_policy_url() ? '<a href="' . esc_url(get_privacy_policy_url()) . '" target="_blank">' . __('Privacy Policy', 'advnews-manager') . '</a>' : ''
                        ); ?>
                    </span>
                </label>
            </div>
            <?php endif; ?>

            <div class="advnews-form-actions">
                <button type="submit" class="advnews-button advnews-button-primary" id="send-access-link">
                    <?php _e('Send Access Link', 'advnews-manager'); ?>
                </button>
            </div>

            <div class="advnews-form-response" style="display:none;"></div>
        </form>

        <div class="advnews-request-footer">
            <p class="advnews-security-note">
                <span class="dashicons dashicons-lock"></span>
                <?php _e('We\'ll send a one-time secure link to your email address.', 'advnews-manager'); ?>
            </p>

            <p class="advnews-alternative-links">
                <?php _e('Already have a link?', 'advnews-manager'); ?>
                <a href="<?php echo esc_url(add_query_arg('email', '', get_permalink(get_option('advnews_management_page_id')))); ?>">
                    <?php _e('Enter it here', 'advnews-manager'); ?>
                </a>
            </p>
        </div>
    </div>

    <div class="advnews-request-help">
        <h3><?php _e('Why do I need to request access?', 'advnews-manager'); ?></h3>
        <p>
            <?php _e('For your security, we send a unique, time-limited link to your email address. This ensures that only you can access and manage your subscription preferences.', 'advnews-manager'); ?>
        </p>

        <div class="advnews-help-steps">
            <div class="advnews-step">
                <span class="advnews-step-number">1</span>
                <span class="advnews-step-text"><?php _e('Enter your email address', 'advnews-manager'); ?></span>
            </div>
            <div class="advnews-step">
                <span class="advnews-step-number">2</span>
                <span class="advnews-step-text"><?php _e('Check your inbox for the access link', 'advnews-manager'); ?></span>
            </div>
            <div class="advnews-step">
                <span class="advnews-step-number">3</span>
                <span class="advnews-step-text"><?php _e('Click the link to manage your preferences', 'advnews-manager'); ?></span>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#advnews-email-request-form').on('submit', function(e) {
        e.preventDefault();

        var form = $(this);
        var button = $('#send-access-link');
        var responseDiv = form.find('.advnews-form-response');
        var originalText = button.text();

        // Validate email
        var email = $('#request_email').val();
        if (!email || !isValidEmail(email)) {
            responseDiv.addClass('error').html('<p><?php _e('Please enter a valid email address.', 'advnews-manager'); ?></p>').show();
            return;
        }

        // Validate consent if required
        <?php if (get_option('advnews_gdpr_compliance')): ?>
        if (!$('input[name="consent"]').is(':checked')) {
            responseDiv.addClass('error').html('<p><?php _e('You must agree to the privacy policy.', 'advnews-manager'); ?></p>').show();
            return;
        }
        <?php endif; ?>

        button.prop('disabled', true).text(advnews_frontend.i18n.sending);
        responseDiv.hide().removeClass('success error');

        $.ajax({
            url: advnews_frontend.ajax_url,
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    responseDiv.addClass('success').html('<p>' + response.data.message + '</p>').show();
                    form[0].reset();

                    // Hide form after success
                    setTimeout(function() {
                        form.slideUp();
                        $('.advnews-request-footer').slideUp();
                    }, 3000);
                } else {
                    responseDiv.addClass('error').html('<p>' + response.data.message + '</p>').show();
                }
            },
            error: function() {
                responseDiv.addClass('error').html('<p>' + advnews_frontend.i18n.error + '</p>').show();
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });

    function isValidEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
});
</script>

<style>
.advnews-email-request-container {
    max-width: 600px;
    margin: 40px auto;
}

.advnews-email-request-card {
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    overflow: hidden;
}

.advnews-email-request-header {
    padding: 30px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    text-align: center;
}

.advnews-email-request-header h2 {
    margin: 0 0 15px;
    color: #fff;
    font-size: 28px;
    font-weight: 600;
}

.advnews-request-description {
    margin: 0;
    font-size: 16px;
    opacity: 0.9;
    line-height: 1.6;
}

.advnews-email-request-form {
    padding: 30px;
}

.advnews-form-group {
    margin-bottom: 25px;
}

.advnews-form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.advnews-required {
    color: #d63638;
}

.advnews-input {
    width: 100%;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 16px;
    transition: all 0.3s;
}

.advnews-input:focus {
    border-color: #667eea;
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.advnews-checkbox-label {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    cursor: pointer;
}

.advnews-checkbox-text {
    font-size: 14px;
    color: #666;
    line-height: 1.5;
}

.advnews-checkbox-text a {
    color: #667eea;
    text-decoration: none;
}

.advnews-checkbox-text a:hover {
    text-decoration: underline;
}

.advnews-form-actions {
    text-align: center;
}

.advnews-button {
    padding: 15px 40px;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
}

.advnews-button-primary {
    background: #667eea;
    color: #fff;
}

.advnews-button-primary:hover {
    background: #5a67d8;
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
}

.advnews-form-response {
    margin-top: 20px;
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

.advnews-request-footer {
    padding: 20px 30px;
    background: #f8f9fa;
    border-top: 1px solid #eee;
    text-align: center;
}

.advnews-security-note {
    margin: 0 0 10px;
    color: #666;
    font-size: 14px;
}

.advnews-security-note .dashicons {
    color: #28a745;
    margin-right: 5px;
}

.advnews-alternative-links {
    margin: 0;
    font-size: 13px;
    color: #999;
}

.advnews-alternative-links a {
    color: #667eea;
    text-decoration: none;
}

.advnews-alternative-links a:hover {
    text-decoration: underline;
}

.advnews-request-help {
    margin-top: 30px;
    padding: 30px;
    background: #f8f9fa;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    text-align: center;
}

.advnews-request-help h3 {
    margin: 0 0 15px;
    color: #333;
    font-size: 20px;
    font-weight: 600;
}

.advnews-request-help > p {
    color: #666;
    line-height: 1.6;
    margin-bottom: 25px;
}

.advnews-help-steps {
    display: flex;
    justify-content: space-between;
    gap: 15px;
}

.advnews-step {
    flex: 1;
    text-align: center;
}

.advnews-step-number {
    display: block;
    width: 40px;
    height: 40px;
    margin: 0 auto 10px;
    background: #667eea;
    color: #fff;
    border-radius: 50%;
    line-height: 40px;
    font-weight: 600;
}

.advnews-step-text {
    display: block;
    color: #666;
    font-size: 14px;
}

@media (max-width: 768px) {
    .advnews-email-request-container {
        margin: 20px;
    }

    .advnews-email-request-header,
    .advnews-email-request-form,
    .advnews-request-footer {
        padding: 20px;
    }

    .advnews-help-steps {
        flex-direction: column;
        gap: 20px;
    }

    .advnews-step {
        display: flex;
        align-items: center;
        gap: 15px;
        text-align: left;
    }

    .advnews-step-number {
        margin: 0;
    }
}
</style>
