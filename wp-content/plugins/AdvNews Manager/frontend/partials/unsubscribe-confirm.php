<?php
// frontend/partials/unsubscribe-confirm.php
if (!defined('ABSPATH')) exit;

$email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
$token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

// Verify token
$transient_key = 'advnews_unsubscribe_' . $token;
$stored_email = get_transient($transient_key);
$is_valid = ($stored_email === $email && $token && $email);
?>

<div class="advnews-unsubscribe-confirm-container">
    <?php if ($is_valid): ?>
        <!-- Valid token - show success message -->
        <div class="advnews-confirm-success">
            <div class="advnews-icon advnews-icon-success">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>

            <h2><?php _e('Unsubscription Confirmed', 'advnews-manager'); ?></h2>

            <p class="advnews-confirm-message">
                <?php printf(
                    __('<strong>%s</strong> has been successfully unsubscribed from our newsletter.', 'advnews-manager'),
                    esc_html($email)
                ); ?>
            </p>

            <div class="advnews-confirm-details">
                <p><?php _e('You will no longer receive any marketing emails from us.', 'advnews-manager'); ?></p>

                <?php
                $reason = get_transient($transient_key . '_reason');
                if ($reason): ?>
                <p class="advnews-feedback-thanks">
                    <?php _e('Thank you for your feedback:', 'advnews-manager'); ?><br>
                    <em>"<?php echo esc_html($reason); ?>"</em>
                </p>
                <?php endif; ?>
            </div>

            <div class="advnews-confirm-actions">
                <a href="<?php echo esc_url(home_url()); ?>" class="advnews-button advnews-button-primary">
                    <?php _e('Return to Homepage', 'advnews-manager'); ?>
                </a>

                <?php if (get_option('advnews_management_page_id')): ?>
                <a href="<?php echo esc_url(get_permalink(get_option('advnews_management_page_id'))); ?>" class="advnews-button advnews-button-secondary">
                    <?php _e('Manage Preferences', 'advnews-manager'); ?>
                </a>
                <?php endif; ?>
            </div>

            <p class="advnews-resubscribe-note">
                <?php _e('Changed your mind?', 'advnews-manager'); ?>
                <a href="<?php echo esc_url(add_query_arg('email', urlencode($email), get_permalink(get_option('advnews_management_page_id')))); ?>">
                    <?php _e('Resubscribe here', 'advnews-manager'); ?>
                </a>
            </p>
        </div>

        <?php
        // Delete the transient after use
        delete_transient($transient_key);
        delete_transient($transient_key . '_reason');
        ?>

    <?php elseif ($token && $email): ?>
        <!-- Invalid or expired token -->
        <div class="advnews-confirm-error">
            <div class="advnews-icon advnews-icon-error">
                <span class="dashicons dashicons-warning"></span>
            </div>

            <h2><?php _e('Invalid or Expired Link', 'advnews-manager'); ?></h2>

            <p class="advnews-error-message">
                <?php _e('This unsubscribe link is invalid or has expired.', 'advnews-manager'); ?>
            </p>

            <div class="advnews-error-details">
                <p><?php _e('Please request a new unsubscribe link using the form below:', 'advnews-manager'); ?></p>
            </div>

            <?php echo do_shortcode('[advnews_unsubscribe]'); ?>
        </div>
    <?php else: ?>
        <!-- No token - redirect to unsubscribe form -->
        <?php wp_redirect(get_permalink(get_option('advnews_unsubscribe_page_id'))); exit; ?>
    <?php endif; ?>
</div>

<style>
.advnews-unsubscribe-confirm-container {
    max-width: 600px;
    margin: 40px auto;
    padding: 30px;
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    text-align: center;
}

.advnews-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.advnews-icon-success {
    background: #d4edda;
    color: #155724;
}

.advnews-icon-error {
    background: #f8d7da;
    color: #721c24;
}

.advnews-icon .dashicons {
    font-size: 40px;
    width: 40px;
    height: 40px;
}

.advnews-unsubscribe-confirm-container h2 {
    margin-bottom: 20px;
    color: #333;
    font-size: 28px;
    font-weight: 600;
}

.advnews-confirm-message {
    margin-bottom: 20px;
    font-size: 18px;
    color: #444;
}

.advnews-confirm-details {
    margin: 25px 0;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 6px;
    text-align: left;
}

.advnews-feedback-thanks {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #dee2e6;
    font-style: italic;
    color: #666;
}

.advnews-confirm-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin: 30px 0 20px;
}

.advnews-resubscribe-note {
    color: #666;
    font-size: 14px;
}

.advnews-resubscribe-note a {
    color: #2271b1;
    text-decoration: none;
    font-weight: 600;
}

.advnews-resubscribe-note a:hover {
    text-decoration: underline;
}

.advnews-error-message {
    color: #721c24;
    font-weight: 500;
    margin-bottom: 20px;
}

.advnews-error-details {
    margin: 25px 0;
    color: #666;
}

@media (max-width: 768px) {
    .advnews-unsubscribe-confirm-container {
        margin: 20px;
        padding: 20px;
    }

    .advnews-confirm-actions {
        flex-direction: column;
    }

    .advnews-button {
        width: 100%;
    }
}
</style>
