<?php
// admin/partials/subscribers-editor.php
if (!defined('ABSPATH')) exit;

$subscriber_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$subscriber_class = new AdvNews_Subscriber();
$category_class = new AdvNews_Category();

$subscriber = $subscriber_id ? $subscriber_class->get_subscriber($subscriber_id) : null;
$categories = $category_class->get_all_categories();
$subscriber_categories = $subscriber_id ? $subscriber_class->get_subscriber_categories($subscriber_id) : array();

$subscribed_category_ids = array();
foreach ($subscriber_categories as $cat) {
    $subscribed_category_ids[] = $cat->id;
}
?>

<div class="wrap">
    <h1><?php echo $subscriber_id ? __('Edit Subscriber', 'advnews-manager') : __('Add New Subscriber', 'advnews-manager'); ?></h1>

    <?php if (isset($_GET['message'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                if ($_GET['message'] == 'created') {
                    _e('Subscriber created successfully.', 'advnews-manager');
                } elseif ($_GET['message'] == 'updated') {
                    _e('Subscriber updated successfully.', 'advnews-manager');
                }
                ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="postbox">
        <div class="inside">
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="subscriber-form">
                <input type="hidden" name="action" value="advnews_save_subscriber">
                <?php wp_nonce_field('advnews_save_subscriber'); ?>
                <input type="hidden" name="subscriber_id" value="<?php echo esc_attr($subscriber_id); ?>">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="email"><?php _e('Email Address', 'advnews-manager'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="email" id="email" name="email"
                                   value="<?php echo $subscriber ? esc_attr($subscriber->email) : ''; ?>"
                                   class="regular-text" required <?php echo $subscriber_id ? 'readonly' : ''; ?>>
                            <?php if ($subscriber_id): ?>
                                <p class="description"><?php _e('Email cannot be changed after creation.', 'advnews-manager'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="first_name"><?php _e('First Name', 'advnews-manager'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="first_name" name="first_name"
                                   value="<?php echo $subscriber ? esc_attr($subscriber->first_name) : ''; ?>"
                                   class="regular-text">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="last_name"><?php _e('Last Name', 'advnews-manager'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="last_name" name="last_name"
                                   value="<?php echo $subscriber ? esc_attr($subscriber->last_name) : ''; ?>"
                                   class="regular-text">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="organization"><?php _e('Organization', 'advnews-manager'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="organization" name="organization"
                                   value="<?php echo $subscriber ? esc_attr($subscriber->organization) : ''; ?>"
                                   class="regular-text">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label><?php _e('Categories', 'advnews-manager'); ?></label>
                        </th>
                        <td>
                            <div class="advnews-categories-checkbox-group" style="max-height: 200px; overflow-y: auto; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                <?php if (empty($categories)): ?>
                                    <p><?php _e('No categories found.', 'advnews-manager'); ?>
                                       <a href="<?php echo admin_url('admin.php?page=advnews-categories&action=add'); ?>"><?php _e('Create one now', 'advnews-manager'); ?></a>
                                    </p>
                                <?php else: ?>
                                    <?php foreach ($categories as $category): ?>
                                        <label style="display: block; margin-bottom: 8px;">
                                            <input type="checkbox" name="categories[]" value="<?php echo esc_attr($category->id); ?>"
                                                   <?php checked(in_array($category->id, $subscribed_category_ids)); ?>>
                                            <span style="display: inline-block; width: 12px; height: 12px; background-color: <?php echo esc_attr($category->color); ?>; border-radius: 3px; margin-right: 5px;"></span>
                                            <?php echo esc_html($category->name); ?>
                                            <?php if (!empty($category->description)): ?>
                                                <br><small style="margin-left: 27px; color: #666;"><?php echo esc_html($category->description); ?></small>
                                            <?php endif; ?>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <p class="description">
                                <?php _e('Select the categories this subscriber should be assigned to.', 'advnews-manager'); ?>
                            </p>
                        </td>
                    </tr>

                    <?php if ($subscriber_id): ?>
                    <tr>
                        <th scope="row">
                            <label for="status"><?php _e('Status', 'advnews-manager'); ?></label>
                        </th>
                        <td>
                            <select id="status" name="status">
                                <option value="active" <?php selected($subscriber->status, 'active'); ?>>
                                    <?php _e('Active', 'advnews-manager'); ?>
                                </option>
                                <option value="unsubscribed" <?php selected($subscriber->status, 'unsubscribed'); ?>>
                                    <?php _e('Unsubscribed', 'advnews-manager'); ?>
                                </option>
                                <option value="bounced" <?php selected($subscriber->status, 'bounced'); ?>>
                                    <?php _e('Bounced', 'advnews-manager'); ?>
                                </option>
                            </select>

                            <?php if ($subscriber->status == 'unsubscribed' && $subscriber->unsubscribe_reason): ?>
                                <p class="description">
                                    <strong><?php _e('Unsubscribe reason:', 'advnews-manager'); ?></strong>
                                    <?php echo esc_html($subscriber->unsubscribe_reason); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php _e('Statistics', 'advnews-manager'); ?>
                        </th>
                        <td>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; max-width: 400px;">
                                <div class="stat-box" style="background: #f0f6fc; padding: 10px; text-align: center; border-radius: 4px;">
                                    <div style="font-size: 20px; font-weight: 600; color: #2271b1;"><?php echo esc_html($subscriber->total_opens); ?></div>
                                    <div style="font-size: 11px; color: #666;"><?php _e('Total Opens', 'advnews-manager'); ?></div>
                                </div>
                                <div class="stat-box" style="background: #f0f6fc; padding: 10px; text-align: center; border-radius: 4px;">
                                    <div style="font-size: 20px; font-weight: 600; color: #2271b1;"><?php echo esc_html($subscriber->total_clicks); ?></div>
                                    <div style="font-size: 11px; color: #666;"><?php _e('Total Clicks', 'advnews-manager'); ?></div>
                                </div>
                                <div class="stat-box" style="background: #f0f6fc; padding: 10px; text-align: center; border-radius: 4px;">
                                    <div style="font-size: 20px; font-weight: 600; color: #2271b1;"><?php echo esc_html($subscriber->engagement_score); ?></div>
                                    <div style="font-size: 11px; color: #666;"><?php _e('Engagement Score', 'advnews-manager'); ?></div>
                                </div>
                            </div>

                            <table class="widefat" style="margin-top: 15px; width: auto;">
                                <tr>
                                    <td><strong><?php _e('Subscribed:', 'advnews-manager'); ?></strong></td>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($subscriber->subscribed_at))); ?></td>
                                </tr>
                                <?php if ($subscriber->last_email_sent): ?>
                                <tr>
                                    <td><strong><?php _e('Last Email Sent:', 'advnews-manager'); ?></strong></td>
                                    <td><?php echo esc_html(human_time_diff(strtotime($subscriber->last_email_sent), current_time('timestamp')) . ' ' . __('ago', 'advnews-manager')); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($subscriber->last_activity_at): ?>
                                <tr>
                                    <td><strong><?php _e('Last Activity:', 'advnews-manager'); ?></strong></td>
                                    <td><?php echo esc_html(human_time_diff(strtotime($subscriber->last_activity_at), current_time('timestamp')) . ' ' . __('ago', 'advnews-manager')); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>

                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php _e('Save Subscriber', 'advnews-manager'); ?>">
                    <a href="<?php echo admin_url('admin.php?page=advnews-subscribers'); ?>" class="button"><?php _e('Cancel', 'advnews-manager'); ?></a>

                    <?php if ($subscriber_id && $subscriber->status == 'unsubscribed'): ?>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=advnews-subscribers&action=resubscribe&id=' . $subscriber_id), 'advnews_resubscribe_subscriber'); ?>"
                           class="button" style="float: right;">
                            <?php _e('Resubscribe', 'advnews-manager'); ?>
                        </a>
                    <?php endif; ?>
                </p>
            </form>
        </div>
    </div>

    <?php if ($subscriber_id && !empty($subscriber->email)): ?>
    <div class="postbox">
        <h2 class="hndle"><?php _e('Recent Activity', 'advnews-manager'); ?></h2>
        <div class="inside">
            <?php
            $tracking_class = new AdvNews_Tracking();
            $activity = $tracking_class->get_subscriber_activity($subscriber_id, 10);

            if (empty($activity)): ?>
                <p><?php _e('No recent activity for this subscriber.', 'advnews-manager'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Date', 'advnews-manager'); ?></th>
                            <th><?php _e('Type', 'advnews-manager'); ?></th>
                            <th><?php _e('Campaign', 'advnews-manager'); ?></th>
                            <th><?php _e('Details', 'advnews-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activity as $item): ?>
                            <tr>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item['date']))); ?></td>
                                <td>
                                    <span class="activity-badge activity-<?php echo esc_attr($item['type']); ?>">
                                        <?php echo esc_html(ucfirst($item['type'])); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($item['campaign']); ?></td>
                                <td>
                                    <?php if ($item['type'] == 'click'): ?>
                                        <a href="<?php echo esc_url($item['url']); ?>" target="_blank"><?php echo esc_html(wp_trim_words($item['url'], 5, '...')); ?></a>
                                    <?php else: ?>
                                        <?php echo esc_html($item['subject']); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($item['location'])): ?>
                                        <br><small>📍 <?php echo esc_html($item['location']); ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.required {
    color: #d63638;
}

.activity-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.activity-open {
    background: #d4edda;
    color: #155724;
}

.activity-click {
    background: #cce5ff;
    color: #004085;
}

.activity-unsubscribe {
    background: #f8d7da;
    color: #721c24;
}

.stat-box {
    transition: transform 0.2s;
}

.stat-box:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
</style>
