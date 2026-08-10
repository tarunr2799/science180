<?php
// frontend/partials/unsubscribe-form.php
if (!defined('ABSPATH')) exit;

$email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
$token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
$show_reason = isset($atts['show_reason']) ? $atts['show_reason'] : 'yes';
?>

<div class="advnews-unsubscribe-container">
    <?php if ($token && $email): ?>
        <!-- Confirmation Form -->
        <div class="advnews-unsubscribe-confirm">
            <h2><?php _e('Confirm Unsubscribe', 'advnews-manager'); ?></h2>
            <p class="advnews-confirm-message">
                <?php printf(
                    __('Are you sure you want to unsubscribe %s from our newsletter?', 'advnews-manager'),
                    '<strong>' . esc_html($email) . '</strong>'
                ); ?>
            </p>

            <form class="advnews-form advnews-confirm-form" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
                <input type="hidden" name="action" value="advnews_frontend_unsubscribe">
                <?php wp_nonce_field('advnews_frontend_unsubscribe', '_wpnonce'); ?>
                <input type="hidden" name="email" value="<?php echo esc_attr($email); ?>">
                <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">

                <?php if ($show_reason === 'yes'): ?>
                <div class="advnews-form-group">
                    <label for="unsubscribe_reason"><?php _e('Reason for leaving (optional):', 'advnews-manager'); ?></label>
                    <select id="unsubscribe_reason" name="reason" class="advnews-select">
                        <option value=""><?php _e('-- Please select --', 'advnews-manager'); ?></option>
                        <option value="too_many_emails"><?php _e('Too many emails', 'advnews-manager'); ?></option>
                        <option value="not_relevant"><?php _e('Content not relevant', 'advnews-manager'); ?></option>
                        <option value="spam"><?php _e('Emails marked as spam', 'advnews-manager'); ?></option>
                        <option value="privacy"><?php _e('Privacy concerns', 'advnews-manager'); ?></option>
                        <option value="other"><?php _e('Other reason', 'advnews-manager'); ?></option>
                    </select>
                </div>

                <div class="advnews-form-group advnews-other-reason" style="display:none;">
                    <label for="other_reason"><?php _e('Please specify:', 'advnews-manager'); ?></label>
                    <input type="text" id="other_reason" name="other_reason" class="advnews-input">
                </div>
                <?php endif; ?>

                <div class="advnews-form-actions">
                    <button type="submit" class="advnews-button advnews-button-primary"><?php _e('Confirm Unsubscribe', 'advnews-manager'); ?></button>
                    <a href="<?php echo esc_url(home_url()); ?>" class="advnews-button advnews-button-secondary"><?php _e('Cancel', 'advnews-manager'); ?></a>
                </div>

                <div class="advnews-form-response" style="display:none;"></div>
            </form>

            <p class="advnews-note">
                <?php _e('You can also manage your email preferences or resubscribe at any time.', 'advnews-manager'); ?>
            </p>
        </div>
    <?php else: ?>
        <!-- Email Input Form -->
        <div class="advnews-unsubscribe-form">
            <h2><?php _e('Unsubscribe from Newsletter', 'advnews-manager'); ?></h2>
            <p class="advnews-description">
                <?php _e('Enter your email address to unsubscribe from our newsletter. We\'ll send you a confirmation link.', 'advnews-manager'); ?>
            </p>

            <form class="advnews-form" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" id="advnews-unsubscribe-form">
                <input type="hidden" name="action" value="advnews_frontend_unsubscribe_request">
                <?php wp_nonce_field('advnews_frontend_unsubscribe', '_wpnonce'); ?>

                <div class="advnews-form-group">
                    <label for="unsubscribe_email"><?php _e('Email Address', 'advnews-manager'); ?> <span class="advnews-required">*</span></label>
                    <input type="email" id="unsubscribe_email" name="email" value="<?php echo esc_attr($email); ?>"
                           class="advnews-input" required placeholder="<?php _e('your@email.com', 'advnews-manager'); ?>">
                </div>

                <div class="advnews-form-actions">
                    <button type="submit" class="advnews-button advnews-button-primary"><?php _e('Send Unsubscribe Link', 'advnews-manager'); ?></button>
                </div>

                <div class="advnews-form-response" style="display:none;"></div>
            </form>

            <p class="advnews-links">
                <a href="<?php echo esc_url(get_permalink(get_option('advnews_management_page_id'))); ?>">
                    <?php _e('Manage Preferences', 'advnews-manager'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Show/hide other reason field
    $('#unsubscribe_reason').on('change', function() {
        if ($(this).val() === 'other') {
            $('.advnews-other-reason').slideDown();
        } else {
            $('.advnews-other-reason').slideUp();
        }
    });

    // Handle form submission
    $('#advnews-unsubscribe-form, .advnews-confirm-form').on('submit', function(e) {
        e.preventDefault();

        var form = $(this);
        var button = form.find('button[type="submit"]');
        var responseDiv = form.find('.advnews-form-response');
        var originalText = button.text();

        button.prop('disabled', true).text(advnews_frontend.i18n.processing);
        responseDiv.hide().removeClass('success error');

        $.ajax({
            url: advnews_frontend.ajax_url,
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    responseDiv.addClass('success').html('<p>' + response.data.message + '</p>').show();
                    if (response.data.redirect) {
                        setTimeout(function() {
                            window.location.href = response.data.redirect;
                        }, 2000);
                    } else if (response.data.reload) {
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        form[0].reset();
                    }
                } else {
                    responseDiv.addClass('error').html('<p>' + response.data.message + '</p>').show();
                }
            },
            error: function(xhr, status, error) {
                responseDiv.addClass('error').html('<p>' + advnews_frontend.i18n.error + '</p>').show();
                console.error('AJAX Error:', error);
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
});
</script>

<style>
.advnews-unsubscribe-container {
    max-width: 600px;
    margin: 40px auto;
    padding: 30px;
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.advnews-unsubscribe-container h2 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #333;
    font-size: 24px;
    font-weight: 600;
}

.advnews-description {
    margin-bottom: 25px;
    color: #666;
    line-height: 1.6;
}

.advnews-confirm-message {
    margin-bottom: 25px;
    padding: 15px;
    background: #f8f9fa;
    border-left: 4px solid #d63638;
    border-radius: 4px;
    font-size: 16px;
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

.advnews-required {
    color: #d63638;
}

.advnews-input,
.advnews-select {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 16px;
    transition: border-color 0.3s;
}

.advnews-input:focus,
.advnews-select:focus {
    border-color: #2271b1;
    outline: none;
    box-shadow: 0 0 0 1px #2271b1;
}

.advnews-form-actions {
    display: flex;
    gap: 15px;
    margin-top: 25px;
}

.advnews-button {
    padding: 12px 24px;
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
    background: #2271b1;
    color: #fff;
}

.advnews-button-primary:hover {
    background: #135e96;
    color: #fff;
}

.advnews-button-secondary {
    background: #f8f9fa;
    color: #444;
    border: 1px solid #ddd;
}

.advnews-button-secondary:hover {
    background: #e9ecef;
    color: #333;
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

.advnews-form-response p {
    margin: 0;
}

.advnews-note {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
    color: #666;
    font-size: 14px;
    font-style: italic;
}

.advnews-links {
    margin-top: 20px;
    text-align: center;
}

.advnews-links a {
    color: #2271b1;
    text-decoration: none;
}

.advnews-links a:hover {
    text-decoration: underline;
}

.advnews-other-reason {
    margin-top: 15px;
}

@media (max-width: 768px) {
    .advnews-unsubscribe-container {
        margin: 20px;
        padding: 20px;
    }

    .advnews-form-actions {
        flex-direction: column;
    }

    .advnews-button {
        width: 100%;
        text-align: center;
    }
}
</style>
