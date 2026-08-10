<?php
// admin/partials/dashboard-overview.php
if (!defined('ABSPATH')) exit;

$stats = $this->get_dashboard_stats();
$tracking_class = new AdvNews_Tracking();
$system_analytics = $tracking_class->get_system_analytics('30days');
?>

<div class="wrap advnews-dashboard">
    <h1><?php _e('AdvNews Manager Dashboard', 'advnews-manager'); ?></h1>

    <div class="advnews-stats-grid">
        <div class="advnews-stat-card">
            <h3><?php _e('Total Subscribers', 'advnews-manager'); ?></h3>
            <div class="stat-number"><?php echo esc_html(number_format($stats['total_subscribers'])); ?></div>
            <div class="stat-trend <?php echo $stats['subscriber_growth'] >= 0 ? 'positive' : 'negative'; ?>">
                <?php echo $stats['subscriber_growth'] >= 0 ? '+' : ''; ?>
                <?php echo esc_html($stats['subscriber_growth']); ?>% <?php _e('vs last week', 'advnews-manager'); ?>
            </div>
        </div>

        <div class="advnews-stat-card">
            <h3><?php _e('Active Campaigns', 'advnews-manager'); ?></h3>
            <div class="stat-number"><?php echo esc_html($stats['active_campaigns']); ?></div>
            <div class="stat-detail">
                <?php echo esc_html($stats['queue_status']['queued']); ?> <?php _e('queued', 'advnews-manager'); ?>
            </div>
        </div>

        <div class="advnews-stat-card">
            <h3><?php _e('Emails Sent Today', 'advnews-manager'); ?></h3>
            <div class="stat-number"><?php echo esc_html(number_format($stats['emails_sent_today'])); ?></div>
        </div>

        <div class="advnews-stat-card">
            <h3><?php _e('Average Open Rate', 'advnews-manager'); ?></h3>
            <div class="stat-number"><?php echo esc_html($stats['avg_open_rate']); ?>%</div>
            <div class="stat-detail">
                <?php _e('Industry avg: 20%', 'advnews-manager'); ?>
            </div>
        </div>
    </div>

    <div class="advnews-dashboard-charts">
        <div class="chart-container">
            <h3><?php _e('Campaign Performance (Last 30 Days)', 'advnews-manager'); ?></h3>
            <canvas id="campaignChart" width="400" height="200"></canvas>
        </div>

        <div class="chart-container">
            <h3><?php _e('Subscriber Growth', 'advnews-manager'); ?></h3>
            <canvas id="subscriberChart" width="400" height="200"></canvas>
        </div>
    </div>

    <div class="advnews-dashboard-row">
        <div class="advnews-dashboard-column">
            <div class="postbox">
                <h2 class="hndle"><?php _e('Recent Activity', 'advnews-manager'); ?></h2>
                <div class="inside">
                    <?php if (empty($stats['recent_activity'])): ?>
                        <p><?php _e('No recent activity.', 'advnews-manager'); ?></p>
                    <?php else: ?>
                        <ul class="advnews-activity-list">
                            <?php foreach ($stats['recent_activity'] as $activity): ?>
                                <li class="activity-<?php echo esc_attr($activity['type']); ?>">
                                    <span class="activity-date">
                                        <?php echo esc_html(human_time_diff(strtotime($activity['date']), current_time('timestamp')) . ' ' . __('ago', 'advnews-manager')); ?>
                                    </span>
                                    <span class="activity-text">
                                        <?php echo esc_html($activity['activity']); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="advnews-dashboard-column">
            <div class="postbox">
                <h2 class="hndle"><?php _e('Queue Status', 'advnews-manager'); ?></h2>
                <div class="inside">
                    <div class="advnews-queue-stats">
                        <div class="queue-stat">
                            <span class="stat-label"><?php _e('Queued:', 'advnews-manager'); ?></span>
                            <span class="stat-value"><?php echo esc_html($stats['queue_status']['queued']); ?></span>
                        </div>
                        <div class="queue-stat">
                            <span class="stat-label"><?php _e('Sending:', 'advnews-manager'); ?></span>
                            <span class="stat-value"><?php echo esc_html($stats['queue_status']['sending']); ?></span>
                        </div>
                        <div class="queue-stat">
                            <span class="stat-label"><?php _e('Failed:', 'advnews-manager'); ?></span>
                            <span class="stat-value"><?php echo esc_html($stats['queue_status']['failed']); ?></span>
                        </div>
                        <div class="queue-stat">
                            <span class="stat-label"><?php _e('Delivered:', 'advnews-manager'); ?></span>
                            <span class="stat-value"><?php echo esc_html($stats['queue_status']['delivered']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="advnews-quick-actions">
        <h3><?php _e('Quick Actions', 'advnews-manager'); ?></h3>
        <div class="quick-actions-grid">
            <a href="<?php echo admin_url('admin.php?page=advnews-campaigns&action=add'); ?>" class="quick-action-card">
                <span class="dashicons dashicons-email-alt"></span>
                <span class="action-label"><?php _e('Create Campaign', 'advnews-manager'); ?></span>
            </a>

            <a href="<?php echo admin_url('admin.php?page=advnews-subscribers&action=import'); ?>" class="quick-action-card">
                <span class="dashicons dashicons-upload"></span>
                <span class="action-label"><?php _e('Import Subscribers', 'advnews-manager'); ?></span>
            </a>

            <a href="<?php echo admin_url('admin.php?page=advnews-templates&action=add'); ?>" class="quick-action-card">
                <span class="dashicons dashicons-layout"></span>
                <span class="action-label"><?php _e('Create Template', 'advnews-manager'); ?></span>
            </a>

            <a href="<?php echo admin_url('admin.php?page=advnews-analytics'); ?>" class="quick-action-card">
                <span class="dashicons dashicons-chart-bar"></span>
                <span class="action-label"><?php _e('View Reports', 'advnews-manager'); ?></span>
            </a>
        </div>
    </div>
</div>
