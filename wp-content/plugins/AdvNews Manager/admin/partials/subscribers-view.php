<?php
// admin/partials/subscribers-view.php
if (!defined('ABSPATH')) exit;

$subscriber_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$subscriber_class = new AdvNews_Subscriber();
$tracking_class = new AdvNews_Tracking();
$category_class = new AdvNews_Category();

$subscriber = $subscriber_id ? $subscriber_class->get_subscriber($subscriber_id) : null;

if (!$subscriber) {
    echo '<div class="notice notice-error"><p>' . __('Subscriber not found.', 'advnews-manager') . '</p></div>';
    return;
}

$categories = $subscriber_class->get_subscriber_categories($subscriber_id);
$activity_per_page = 100;
$activity_page = isset($_GET['activity_page']) ? max(1, absint($_GET['activity_page'])) : 1;
$activity_offset = ($activity_page - 1) * $activity_per_page;
$activity_total = $tracking_class->get_subscriber_activity_count($subscriber_id);
$activity_total_pages = max(1, (int) ceil($activity_total / $activity_per_page));
if ($activity_page > $activity_total_pages) {
    $activity_page = $activity_total_pages;
    $activity_offset = ($activity_page - 1) * $activity_per_page;
}
$activity = $tracking_class->get_subscriber_activity($subscriber_id, $activity_per_page, $activity_offset);

// Get campaign statistics for this subscriber
global $wpdb;
$table_prefix = ADVNEWS_TABLE_PREFIX;
$table_logs = $wpdb->prefix . $table_prefix . 'campaign_logs';
$table_campaigns = $wpdb->prefix . $table_prefix . 'campaigns';
$table_opens = $wpdb->prefix . $table_prefix . 'tracking_opens';
$table_clicks = $wpdb->prefix . $table_prefix . 'tracking_clicks';

$campaign_stats = $wpdb->get_row($wpdb->prepare(
    "SELECT
        COUNT(DISTINCT l.id) as total_sent,
        COUNT(DISTINCT CASE WHEN l.status IN ('delivered', 'opened', 'clicked') THEN l.id END) as delivered,
        COUNT(DISTINCT CASE WHEN l.status = 'opened' OR o.campaign_log_id IS NOT NULL THEN l.id END) as opened,
        COUNT(DISTINCT CASE WHEN l.status = 'clicked' OR c.campaign_log_id IS NOT NULL THEN l.id END) as clicked,
        COUNT(DISTINCT CASE WHEN l.status = 'bounced' THEN l.id END) as bounced,
        COUNT(DISTINCT CASE WHEN l.status = 'unsubscribed' THEN l.id END) as unsubscribed
    FROM $table_logs l
    LEFT JOIN $table_opens o
        ON o.campaign_log_id = l.id
        AND o.subscriber_id = %d
    LEFT JOIN $table_clicks c
        ON c.campaign_log_id = l.id
        AND c.subscriber_id = %d
    WHERE l.subscriber_id = %d",
    $subscriber_id,
    $subscriber_id,
    $subscriber_id
));

$delivered_count = intval($campaign_stats->delivered ?? 0);
$subscriber_open_rate = $delivered_count > 0 ? round((intval($campaign_stats->opened ?? 0) / $delivered_count) * 100, 2) : 0;
$subscriber_click_rate = $delivered_count > 0 ? round((intval($campaign_stats->clicked ?? 0) / $delivered_count) * 100, 2) : 0;
?>
<div class="wrap advnews-subscriber-view">
    <h1 class="wp-heading-inline">
        <?php printf(__('View Subscriber: %s', 'advnews-manager'), esc_html($subscriber->email)); ?>
    </h1>
    <a href="<?php echo admin_url('admin.php?page=advnews-subscribers&action=edit&id=' . $subscriber_id); ?>" class="page-title-action">
        <?php _e('Edit', 'advnews-manager'); ?>
    </a>
    <a href="<?php echo admin_url('admin.php?page=advnews-subscribers'); ?>" class="page-title-action">
        <?php _e('Back to List', 'advnews-manager'); ?>
    </a>
    <hr class="wp-header-end">

    <!-- Subscriber Status Badge -->
    <div class="subscriber-status-badge status-<?php echo esc_attr($subscriber->status); ?>" style="display:inline-block; padding:5px 15px; border-radius:20px; font-weight:600; margin:10px 0; background:<?php
        echo $subscriber->status == 'active' ? '#d4edda; color:#155724;' :
            ($subscriber->status == 'unsubscribed' ? '#f8d7da; color:#721c24;' : '#fff3cd; color:#856404;');
    ?>">
        <?php echo esc_html(ucfirst($subscriber->status)); ?>
    </div>

    <div id="poststuff">
        <div id="post-body" class="metabox-holder columns-2">
            <div id="post-body-content">
                <!-- Basic Information -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Basic Information', 'advnews-manager'); ?></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><?php _e('Email Address:', 'advnews-manager'); ?></th>
                                <td>
                                    <strong><?php echo esc_html($subscriber->email); ?></strong>
                                    <?php if ($subscriber->email_verified): ?>
                                        <span class="dashicons dashicons-yes" style="color:#00a32a;" title="<?php _e('Email Verified', 'advnews-manager'); ?>"></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Name:', 'advnews-manager'); ?></th>
                                <td><?php echo esc_html(trim($subscriber->first_name . ' ' . $subscriber->last_name)); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Organization:', 'advnews-manager'); ?></th>
                                <td><?php echo esc_html($subscriber->organization); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Title/Role:', 'advnews-manager'); ?></th>
                                <td><?php echo esc_html(isset($subscriber->title) ? $subscriber->title : ''); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('URL/Website:', 'advnews-manager'); ?></th>
                                <td>
                                    <?php if (!empty($subscriber->website_url)): ?>
                                        <a href="<?php echo esc_url($subscriber->website_url); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo esc_html($subscriber->website_url); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Description:', 'advnews-manager'); ?></th>
                                <td><?php echo nl2br(esc_html(isset($subscriber->description) ? $subscriber->description : '')); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Country:', 'advnews-manager'); ?></th>
                                <td><?php echo esc_html(isset($subscriber->country) ? $subscriber->country : ''); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('IP Address:', 'advnews-manager'); ?></th>
                                <td><code><?php echo esc_html($subscriber->ip_address); ?></code></td>
                            </tr>
                            <tr>
                                <th><?php _e('Timezone:', 'advnews-manager'); ?></th>
                                <td><?php echo esc_html($subscriber->timezone ?: '—'); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Language:', 'advnews-manager'); ?></th>
                                <td><?php echo esc_html($subscriber->language ?: '—'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Categories -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Categories', 'advnews-manager'); ?></h2>
                    <div class="inside">
                        <?php if (empty($categories)): ?>
                            <p><?php _e('No categories assigned.', 'advnews-manager'); ?></p>
                        <?php else: ?>
                            <div style="display:flex; flex-wrap:wrap; gap:10px;">
                                <?php foreach ($categories as $category): ?>
                                    <span class="category-badge" style="background-color:<?php echo esc_attr($category->color); ?>; color:#fff; padding:5px 12px; border-radius:15px; font-size:13px;">
                                        <?php echo esc_html($category->name); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Campaign Statistics -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Campaign Statistics', 'advnews-manager'); ?></h2>
                    <div class="inside">
                        <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:15px;">
                            <div style="background:#f0f6fc; padding:15px; border-radius:4px; text-align:center;">
                                <div style="font-size:24px; font-weight:600; color:#2271b1;">
                                    <?php echo esc_html($campaign_stats->total_sent ?? 0); ?>
                                </div>
                                <div style="font-size:12px; color:#666;"><?php _e('Total Sent', 'advnews-manager'); ?></div>
                            </div>
                            <div style="background:#f0f6fc; padding:15px; border-radius:4px; text-align:center;">
                                <div style="font-size:24px; font-weight:600; color:#00a32a;">
                                    <?php echo esc_html($campaign_stats->opened ?? 0); ?>
                                </div>
                                <div style="font-size:12px; color:#666;"><?php _e('Opened', 'advnews-manager'); ?></div>
                            </div>
                            <div style="background:#f0f6fc; padding:15px; border-radius:4px; text-align:center;">
                                <div style="font-size:24px; font-weight:600; color:#f0c33c;">
                                    <?php echo esc_html($campaign_stats->clicked ?? 0); ?>
                                </div>
                                <div style="font-size:12px; color:#666;"><?php _e('Clicked', 'advnews-manager'); ?></div>
                            </div>
                            <div style="background:#f0f6fc; padding:15px; border-radius:4px; text-align:center;">
                                <div style="font-size:24px; font-weight:600; color:#2271b1;">
                                    <?php echo esc_html($subscriber_open_rate); ?>%
                                </div>
                                <div style="font-size:12px; color:#666;"><?php _e('Open Rate', 'advnews-manager'); ?></div>
                            </div>
                            <div style="background:#f0f6fc; padding:15px; border-radius:4px; text-align:center;">
                                <div style="font-size:24px; font-weight:600; color:#2271b1;">
                                    <?php echo esc_html($subscriber_click_rate); ?>%
                                </div>
                                <div style="font-size:12px; color:#666;"><?php _e('Click Rate', 'advnews-manager'); ?></div>
                            </div>
                            <div style="background:#f0f6fc; padding:15px; border-radius:4px; text-align:center;">
                                <div style="font-size:24px; font-weight:600; color:#2271b1;">
                                    <?php echo esc_html($subscriber->engagement_score ?? 0); ?>
                                </div>
                                <div style="font-size:12px; color:#666;"><?php _e('Engagement Score', 'advnews-manager'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Recent Activity', 'advnews-manager'); ?></h2>
                    <div class="inside">
                        <?php if (empty($activity)): ?>
                            <p><?php _e('No recent activity.', 'advnews-manager'); ?></p>
                        <?php else: ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('Sent Date', 'advnews-manager'); ?></th>
                                        <th><?php _e('Delivered Date', 'advnews-manager'); ?></th>
                                        <th><?php _e('Opened Date', 'advnews-manager'); ?></th>
                                        <th><?php _e('Clicked Date', 'advnews-manager'); ?></th>
                                        <th><?php _e('Type', 'advnews-manager'); ?></th>
                                        <th><?php _e('Campaign', 'advnews-manager'); ?></th>
                                        <th><?php _e('Link / Subject', 'advnews-manager'); ?></th>
                                        <th><?php _e('IP / Device', 'advnews-manager'); ?></th>
                                        <th><?php _e('Location', 'advnews-manager'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activity as $item): ?>
                                        <tr>
                                            <?php foreach (array('sent_at', 'delivered_at', 'opened_at', 'clicked_at') as $date_field): ?>
                                                <td>
                                                    <?php echo !empty($item[$date_field])
                                                        ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item[$date_field])))
                                                        : '—'; ?>
                                                </td>
                                            <?php endforeach; ?>
                                            <td>
                                                <span class="activity-badge activity-<?php echo esc_attr($item['type']); ?>" style="display:inline-block; padding:3px 8px; border-radius:3px; font-size:11px; font-weight:600; background:<?php
                                                    echo $item['type'] == 'open' ? '#d4edda; color:#155724;' :
                                                        ($item['type'] == 'click' ? '#cce5ff; color:#004085;' : '#f8d7da; color:#721c24;');
                                                ?>">
                                                    <?php echo esc_html(ucfirst($item['type'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo esc_html($item['campaign']); ?></strong>
                                                <br><small style="color:#666;"><?php echo esc_html($item['subject']); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($item['type'] == 'click' && !empty($item['url'])): ?>
                                                    <a href="<?php echo esc_url($item['url']); ?>" target="_blank" style="font-size:12px;">
                                                        <?php echo esc_html(wp_trim_words($item['url'], 5, '...')); ?>
                                                    </a>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <code><?php echo esc_html($item['ip_address'] ?? ''); ?></code>
                                                <br><small><?php echo esc_html(trim(($item['device'] ?? '') . ' / ' . ($item['browser'] ?? '') . ' / ' . ($item['platform'] ?? ''), ' /')); ?></small>
                                            </td>
                                            <td>
                                                <?php if (!empty($item['location'])): ?>
                                                    <?php echo esc_html($item['location']); ?>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p class="description">
                                <?php
                                $activity_first = $activity_total > 0 ? $activity_offset + 1 : 0;
                                $activity_last = min($activity_offset + count($activity), $activity_total);
                                echo esc_html(sprintf(
                                    __('Showing activities %1$d-%2$d of %3$d.', 'advnews-manager'),
                                    $activity_first,
                                    $activity_last,
                                    $activity_total
                                ));
                                ?>
                            </p>
                            <?php if ($activity_total_pages > 1): ?>
                                <div class="tablenav">
                                    <div class="tablenav-pages">
                                        <?php
                                        $pagination_base = add_query_arg(array(
                                            'page' => 'advnews-subscribers',
                                            'action' => 'view',
                                            'id' => $subscriber_id,
                                            'activity_page' => 999999999
                                        ), admin_url('admin.php'));
                                        echo wp_kses_post(paginate_links(array(
                                            'base' => str_replace('999999999', '%#%', $pagination_base),
                                            'format' => '',
                                            'current' => $activity_page,
                                            'total' => $activity_total_pages,
                                            'prev_text' => __('Previous', 'advnews-manager'),
                                            'next_text' => __('Next', 'advnews-manager')
                                        )));
                                        ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div id="postbox-container-1" class="postbox-container">
                <!-- Timeline -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Timeline', 'advnews-manager'); ?></h2>
                    <div class="inside">
                        <table class="widefat">
                            <tr>
                                <td><strong><?php _e('Subscribed:', 'advnews-manager'); ?></strong></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($subscriber->subscribed_at))); ?></td>
                            </tr>
                            <?php if ($subscriber->last_email_sent): ?>
                                <tr>
                                    <td><strong><?php _e('Last Email Sent:', 'advnews-manager'); ?></strong></td>
                                    <td>
                                        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($subscriber->last_email_sent))); ?>
                                        <br><small><?php echo esc_html(human_time_diff(strtotime($subscriber->last_email_sent), current_time('timestamp')) . ' ' . __('ago', 'advnews-manager')); ?></small>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php if ($subscriber->last_activity_at): ?>
                                <tr>
                                    <td><strong><?php _e('Last Activity:', 'advnews-manager'); ?></strong></td>
                                    <td>
                                        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($subscriber->last_activity_at))); ?>
                                        <br><small><?php echo esc_html(human_time_diff(strtotime($subscriber->last_activity_at), current_time('timestamp')) . ' ' . __('ago', 'advnews-manager')); ?></small>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php if ($subscriber->status == 'unsubscribed' && $subscriber->unsubscribed_at): ?>
                                <tr>
                                    <td><strong><?php _e('Unsubscribed:', 'advnews-manager'); ?></strong></td>
                                    <td>
                                        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($subscriber->unsubscribed_at))); ?>
                                        <br><small><?php echo esc_html(human_time_diff(strtotime($subscriber->unsubscribed_at), current_time('timestamp')) . ' ' . __('ago', 'advnews-manager')); ?></small>
                                    </td>
                                </tr>
                                <?php if ($subscriber->unsubscribe_reason): ?>
                                    <tr>
                                        <td><strong><?php _e('Unsubscribe Reason:', 'advnews-manager'); ?></strong></td>
                                        <td><?php echo esc_html($subscriber->unsubscribe_reason); ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Quick Actions', 'advnews-manager'); ?></h2>
                    <div class="inside">
                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <a href="<?php echo admin_url('admin.php?page=advnews-subscribers&action=edit&id=' . $subscriber_id); ?>" class="button button-primary" style="width:100%; text-align:center;">
                                <?php _e('Edit Subscriber', 'advnews-manager'); ?>
                            </a>
                            <?php if ($subscriber->status == 'active'): ?>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=advnews-subscribers&action=unsubscribe&id=' . $subscriber_id), 'advnews_unsubscribe_subscriber'); ?>"
                                   class="button"
                                   style="width:100%; text-align:center; color:#d63638; border-color:#d63638;"
                                   onclick="return confirm('<?php _e('Are you sure you want to unsubscribe this subscriber?', 'advnews-manager'); ?>');">
                                    <?php _e('Unsubscribe', 'advnews-manager'); ?>
                                </a>
                            <?php elseif ($subscriber->status == 'unsubscribed'): ?>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=advnews-subscribers&action=resubscribe&id=' . $subscriber_id), 'advnews_resubscribe_subscriber'); ?>"
                                   class="button"
                                   style="width:100%; text-align:center; color:#00a32a; border-color:#00a32a;">
                                    <?php _e('Resubscribe', 'advnews-manager'); ?>
                                </a>
                            <?php endif; ?>
                            <a href="<?php echo admin_url('admin.php?page=advnews-analytics&action=campaign&subscriber_id=' . $subscriber_id); ?>"
                               class="button"
                               style="width:100%; text-align:center;">
                                <?php _e('View Analytics', 'advnews-manager'); ?>
                            </a>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=advnews-subscribers&action=delete&id=' . $subscriber_id), 'advnews_delete_subscriber'); ?>"
                               class="button button-link-delete"
                               style="width:100%; text-align:center;"
                               onclick="return confirm('<?php _e('Are you sure? This will permanently delete the subscriber.', 'advnews-manager'); ?>');">
                                <?php _e('Delete Subscriber', 'advnews-manager'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- GDPR Data Export -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('GDPR Data', 'advnews-manager'); ?></h2>
                    <div class="inside">
                        <p style="font-size:13px; color:#666; margin-bottom:15px;">
                            <?php _e('Export or anonymize subscriber data for GDPR compliance.', 'advnews-manager'); ?>
                        </p>
                        <a href="<?php echo admin_url('admin-ajax.php?action=advnews_export_subscriber_gdpr&email=' . urlencode($subscriber->email) . '&_wpnonce=' . wp_create_nonce('advnews_ajax_nonce')); ?>"
                           class="button"
                           style="width:100%; text-align:center; margin-bottom:10px;">
                            <?php _e('Export Data (JSON)', 'advnews-manager'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin-ajax.php?action=advnews_anonymize_subscriber&email=' . urlencode($subscriber->email) . '&_wpnonce=' . wp_create_nonce('advnews_ajax_nonce')); ?>"
                           class="button button-link-delete"
                           style="width:100%; text-align:center;"
                           onclick="return confirm('<?php _e('This will anonymize all data for this subscriber. This action cannot be undone.', 'advnews-manager'); ?>');">
                            <?php _e('Anonymize Data', 'advnews-manager'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.advnews-subscriber-view .activity-badge {
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
.category-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 13px;
    font-weight: 500;
}
.subscriber-status-badge {
    display: inline-block;
    padding: 5px 15px;
    border-radius: 20px;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 12px;
}
.wp-list-table td {
    vertical-align: middle;
}
.wp-list-table small {
    color: #666;
    font-size: 11px;
}
.button-link-delete {
    color: #d63638;
    border-color: #d63638;
}
.button-link-delete:hover {
    background: #d63638;
    color: #fff;
    border-color: #d63638;
}
@media (max-width: 782px) {
    #post-body.columns-2 {
        display: block;
    }
    #postbox-container-1 {
        float: none;
        width: 100%;
        margin-top: 20px;
    }
}
</style>
