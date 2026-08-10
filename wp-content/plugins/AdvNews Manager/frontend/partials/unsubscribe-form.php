<?php
// frontend/partials/unsubscribe-form.php
if (!defined('ABSPATH')) exit;

$email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
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

        <div class="advnews-form-group">
            <label for="reason"><?php _e('Reason (optional):', 'advnews-manager'); ?></label>
            <select id="reason" name="reason">
                <option value=""><?php _e('Select a reason', 'advnews-manager'); ?></option>
                <option value="too_frequent"><?php _e('Emails too frequent', 'advnews-manager'); ?></option>
                <option value="not_relevant"><?php _e('Content not relevant', 'advnews-manager'); ?></option>
                <option value="other"><?php _e('Other', 'advnews-manager'); ?></option>
            </select>
        </div>

        <div class="advnews-form-group">
            <input type="submit" value="<?php _e('Unsubscribe', 'advnews-manager'); ?>">
        </div>
    </form>
</div>
