<?php
// frontend/partials/manage-preferences.php
if (!defined('ABSPATH')) exit;

$email = sanitize_email($_GET['email'] ?? '');
$subscriber_class = new AdvNews_Subscriber();
$subscriber = $subscriber_class->get_subscriber_by_email($email);
$categories = $subscriber_class->get_subscriber_categories($subscriber->id);
$all_categories = $this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}{$this->table_prefix}categories ORDER BY name");
?>

<div class="advnews-subscription-management">
    <h2><?php _e('Manage Your Subscription', 'advnews-manager'); ?></h2>

    <div class="advnews-subscriber-info">
        <p><strong><?php _e('Email:', 'advnews-manager'); ?></strong> <?php echo esc_html($subscriber->email); ?></p>
    </div>

    <form class="advnews-preferences-form" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
        <input type="hidden" name="action" value="advnews_frontend_update_preferences">
        <?php AdvNews_Security::create_nonce_field('advnews_frontend_update_preferences'); ?>
        <input type="hidden" name="email" value="<?php echo esc_attr($email); ?>">

        <h3><?php _e('Email Preferences', 'advnews-manager'); ?></h3>

        <div class="advnews-categories-list">
            <?php foreach ($all_categories as $category): ?>
                <div class="advnews-category-checkbox">
                    <input type="checkbox" id="cat_<?php echo esc_attr($category->id); ?>"
                           name="categories[]" value="<?php echo esc_attr($category->id); ?>"
                           <?php checked(in_array($category->id, array_column($categories, 'id'))); ?>>
                    <label for="cat_<?php echo esc_attr($category->id); ?>">
                        <?php echo esc_html($category->name); ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

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

        <div class="advnews-form-actions">
            <input type="submit" value="<?php _e('Save Preferences', 'advnews-manager'); ?>">
        </div>
    </form>
</div>
