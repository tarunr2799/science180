<?php
// admin/partials/analytics-system.php
if (!defined('ABSPATH')) exit;

$tracking_class = new AdvNews_Tracking();
$system_stats = $tracking_class->get_system_analytics('all');
?>

<div class="wrap advnews-system-analytics">
    <h1><?php _e('System Analytics', 'advnews-manager'); ?></h1>

    <!-- System Health -->
    <div class="advnews-system-health">
        <h2><?php _e('System Health', 'advnews-manager'); ?></h2>

        <div class="health-grid">
            <div class="health-card">
                <div class="health-icon <?php echo wp_next_scheduled('advnews_process_queue') ? 'healthy' : 'unhealthy'; ?>">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="health-content">
                    <h3><?php _e('Cron Jobs', 'advnews-manager'); ?></h3>
                    <div class="health-status">
                        <?php if (wp_next_scheduled('advnews_process_queue')): ?>
                            <span class="status-badge status-ok"><?php _e('Running', 'advnews-manager'); ?></span>
                            <small><?php _e('Next run:', 'advnews-manager'); ?>
                                <?php echo esc_html(human_time_diff(wp_next_scheduled('advnews_process_queue'), current_time('timestamp')) . ' ' . __('from now', 'advnews-manager')); ?>
                            </small>
                        <?php else: ?>
                            <span class="status-badge status-error"><?php _e('Not Running', 'advnews-manager'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="health-card">
                <div class="health-icon healthy">
                    <span class="dashicons dashicons-database"></span>
                </div>
                <div class="health-content">
                    <h3><?php _e('Database', 'advnews-manager'); ?></h3>
                    <div class="health-stats">
                        <?php
                        global $wpdb;
                        $table_prefix = ADVNEWS_TABLE_PREFIX;
                        $tables = array(
                            'categories', 'subscribers', 'subscriber_categories',
                            'campaigns', 'campaign_logs', 'tracking_opens',
                            'tracking_clicks', 'templates', 'links', 'settings'
                        );
                        $all_tables_exist = true;
                        foreach ($tables as $table) {
                            $table_name = $wpdb->prefix . $table_prefix . $table;
                            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                                $all_tables_exist = false;
                                break;
                            }
                        }
                        ?>
                        <span class="status-badge <?php echo $all_tables_exist ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $all_tables_exist ? __('All Tables Present', 'advnews-manager') : __('Missing Tables', 'advnews-manager'); ?>
                        </span>
                        <small><?php printf(__('Total Size: %s', 'advnews-manager'), $this->get_database_size()); ?></small>
                    </div>
                </div>
            </div>

            <div class="health-card">
                <div class="health-icon <?php echo get_option('advnews_smtp_host') ? 'healthy' : 'warning'; ?>">
                    <span class="dashicons dashicons-email"></span>
                </div>
                <div class="health-content">
                    <h3><?php _e('SMTP Configuration', 'advnews-manager'); ?></h3>
                    <div class="health-stats">
                        <?php if (get_option('advnews_smtp_host')): ?>
                            <span class="status-badge status-ok"><?php _e('Configured', 'advnews-manager'); ?></span>
                            <small><?php echo esc_html(get_option('advnews_smtp_host')); ?></small>
                        <?php else: ?>
                            <span class="status-badge status-warning"><?php _e('Not Configured', 'advnews-manager'); ?></span>
                            <small><?php _e('Using WordPress default mail', 'advnews-manager'); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="health-card">
                <div class="health-icon healthy">
                    <span class="dashicons dashicons-chart-line"></span>
                </div>
                <div class="health-content">
                    <h3><?php _e('Tracking', 'advnews-manager'); ?></h3>
                    <div class="health-stats">
                        <span class="status-badge status-ok">
                            <?php printf(__('Opens: %s', 'advnews-manager'), number_format($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$table_prefix}tracking_opens"))); ?>
                        </span>
                        <small>
                            <?php printf(__('Clicks: %s', 'advnews-manager'), number_format($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$table_prefix}tracking_clicks"))); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Metrics -->
    <div class="advnews-performance-metrics">
        <h2><?php _e('Performance Metrics', 'advnews-manager'); ?></h2>

        <div class="metrics-table-container">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Metric', 'advnews-manager'); ?></th>
                        <th><?php _e('Value', 'advnews-manager'); ?></th>
                        <th><?php _e('Benchmark', 'advnews-manager'); ?></th>
                        <th><?php _e('Status', 'advnews-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php _e('Average Open Rate', 'advnews-manager'); ?></td>
                        <td><strong><?php echo esc_html(round($system_stats['campaigns']->avg_open_rate ?? 0, 2)); ?>%</strong></td>
                        <td>20% (Industry Avg)</td>
                        <td>
                            <?php
                            $open_rate = $system_stats['campaigns']->avg_open_rate ?? 0;
                            if ($open_rate >= 25): ?>
                                <span class="performance-excellent"><?php _e('Excellent', 'advnews-manager'); ?></span>
                            <?php elseif ($open_rate >= 20): ?>
                                <span class="performance-good"><?php _e('Good', 'advnews-manager'); ?></span>
                            <?php elseif ($open_rate >= 15): ?>
                                <span class="performance-average"><?php _e('Average', 'advnews-manager'); ?></span>
                            <?php else: ?>
                                <span class="performance-poor"><?php _e('Needs Improvement', 'advnews-manager'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <td><?php _e('Average Click Rate', 'advnews-manager'); ?></td>
                        <td><strong><?php echo esc_html(round($system_stats['campaigns']->avg_click_rate ?? 0, 2)); ?>%</strong></td>
                        <td>2.5% (Industry Avg)</td>
                        <td>
                            <?php
                            $click_rate = $system_stats['campaigns']->avg_click_rate ?? 0;
                            if ($click_rate >= 4): ?>
                                <span class="performance-excellent"><?php _e('Excellent', 'advnews-manager'); ?></span>
                            <?php elseif ($click_rate >= 2.5): ?>
                                <span class="performance-good"><?php _e('Good', 'advnews-manager'); ?></span>
                            <?php elseif ($click_rate >= 1.5): ?>
                                <span class="performance-average"><?php _e('Average', 'advnews-manager'); ?></span>
                            <?php else: ?>
                                <span class="performance-poor"><?php _e('Needs Improvement', 'advnews-manager'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <td><?php _e('Bounce Rate', 'advnews-manager'); ?></td>
                        <td><strong>
                            <?php
                            $bounce_rate = $system_stats['campaigns']->total_recipients > 0 ?
                                round(($system_stats['campaigns']->bounce_count / $system_stats['campaigns']->total_recipients) * 100, 2) : 0;
                            echo esc_html($bounce_rate); ?>%
                        </strong></td>
                        <td>< 2% (Good)</td>
                        <td>
                            <?php if ($bounce_rate <= 1): ?>
                                <span class="performance-excellent"><?php _e('Excellent', 'advnews-manager'); ?></span>
                            <?php elseif ($bounce_rate <= 2): ?>
                                <span class="performance-good"><?php _e('Good', 'advnews-manager'); ?></span>
                            <?php elseif ($bounce_rate <= 5): ?>
                                <span class="performance-average"><?php _e('Average', 'advnews-manager'); ?></span>
                            <?php else: ?>
                                <span class="performance-poor"><?php _e('Needs Improvement', 'advnews-manager'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <td><?php _e('Unsubscribe Rate', 'advnews-manager'); ?></td>
                        <td><strong>
                            <?php
                            $unsubscribe_rate = $system_stats['campaigns']->delivered_count > 0 ?
                                round(($system_stats['campaigns']->unsubscribe_count / $system_stats['campaigns']->delivered_count) * 100, 2) : 0;
                            echo esc_html($unsubscribe_rate); ?>%
                        </strong></td>
                        <td>< 0.2% (Good)</td>
                        <td>
                            <?php if ($unsubscribe_rate <= 0.1): ?>
                                <span class="performance-excellent"><?php _e('Excellent', 'advnews-manager'); ?></span>
                            <?php elseif ($unsubscribe_rate <= 0.2): ?>
                                <span class="performance-good"><?php _e('Good', 'advnews-manager'); ?></span>
                            <?php elseif ($unsubscribe_rate <= 0.5): ?>
                                <span class="performance-average"><?php _e('Average', 'advnews-manager'); ?></span>
                            <?php else: ?>
                                <span class="performance-poor"><?php _e('Needs Improvement', 'advnews-manager'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Queue Statistics -->
    <div class="advnews-queue-statistics">
        <h2><?php _e('Queue Statistics', 'advnews-manager'); ?></h2>

        <?php
        $queue_class = new AdvNews_Queue();
        $queue_status = $queue_class->get_queue_status();
        ?>

        <div class="queue-stats-grid">
            <div class="queue-stat-card">
                <div class="queue-stat-value"><?php echo esc_html($queue_status['queued']); ?></div>
                <div class="queue-stat-label"><?php _e('Queued', 'advnews-manager'); ?></div>
            </div>

            <div class="queue-stat-card">
                <div class="queue-stat-value"><?php echo esc_html($queue_status['sending']); ?></div>
                <div class="queue-stat-label"><?php _e('Sending', 'advnews-manager'); ?></div>
            </div>

            <div class="queue-stat-card">
                <div class="queue-stat-value"><?php echo esc_html($queue_status['delivered']); ?></div>
                <div class="queue-stat-label"><?php _e('Delivered', 'advnews-manager'); ?></div>
            </div>

            <div class="queue-stat-card">
                <div class="queue-stat-value"><?php echo esc_html($queue_status['failed']); ?></div>
                <div class="queue-stat-label"><?php _e('Failed', 'advnews-manager'); ?></div>
            </div>

            <div class="queue-stat-card">
                <div class="queue-stat-value"><?php echo esc_html($queue_status['opened']); ?></div>
                <div class="queue-stat-label"><?php _e('Opened', 'advnews-manager'); ?></div>
            </div>

            <div class="queue-stat-card">
                <div class="queue-stat-value"><?php echo esc_html($queue_status['clicked']); ?></div>
                <div class="queue-stat-label"><?php _e('Clicked', 'advnews-manager'); ?></div>
            </div>
        </div>

        <div class="queue-actions">
            <button type="button" class="button" id="clear-stuck-queue"><?php _e('Clear Stuck Emails', 'advnews-manager'); ?></button>
            <button type="button" class="button" id="retry-failed-queue"><?php _e('Retry Failed Emails', 'advnews-manager'); ?></button>
        </div>
    </div>

    <!-- Database Optimization -->
    <div class="advnews-database-optimization">
        <h2><?php _e('Database Optimization', 'advnews-manager'); ?></h2>

        <div class="optimization-stats">
            <?php
            $table_sizes = $this->get_table_sizes();
            $total_size = array_sum($table_sizes);
            ?>
            <p>
                <strong><?php _e('Total Database Size:', 'advnews-manager'); ?></strong>
                <?php echo esc_html(size_format($total_size)); ?>
            </p>
            <p>
                <strong><?php _e('Tables:', 'advnews-manager'); ?></strong>
                <?php echo count($table_sizes); ?>
            </p>
        </div>

        <div class="optimization-actions">
            <button type="button" class="button" id="optimize-tables"><?php _e('Optimize Tables', 'advnews-manager'); ?></button>
            <button type="button" class="button" id="clean-old-logs"><?php _e('Clean Old Logs', 'advnews-manager'); ?></button>
            <button type="button" class="button" id="backup-database"><?php _e('Backup Database', 'advnews-manager'); ?></button>
        </div>

        <div id="optimization-result" style="display:none; margin-top:15px;"></div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Clear stuck queue
    $('#clear-stuck-queue').on('click', function() {
        if (confirm('<?php _e('Are you sure you want to clear all stuck emails from the queue?', 'advnews-manager'); ?>')) {
            var button = $(this);
            button.prop('disabled', true).text('<?php _e('Processing...', 'advnews-manager'); ?>');

            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_clear_stuck_queue',
                    nonce: advnews_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('<?php _e('An error occurred.', 'advnews-manager'); ?>');
                },
                complete: function() {
                    button.prop('disabled', false).text('<?php _e('Clear Stuck Emails', 'advnews-manager'); ?>');
                }
            });
        }
    });

    // Retry failed queue
    $('#retry-failed-queue').on('click', function() {
        if (confirm('<?php _e('Are you sure you want to retry all failed emails?', 'advnews-manager'); ?>')) {
            var button = $(this);
            button.prop('disabled', true).text('<?php _e('Processing...', 'advnews-manager'); ?>');

            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_retry_failed_queue',
                    nonce: advnews_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('<?php _e('An error occurred.', 'advnews-manager'); ?>');
                },
                complete: function() {
                    button.prop('disabled', false).text('<?php _e('Retry Failed Emails', 'advnews-manager'); ?>');
                }
            });
        }
    });

    // Optimize tables
    $('#optimize-tables').on('click', function() {
        if (confirm('<?php _e('This will optimize all Science180 Mail database tables. Continue?', 'advnews-manager'); ?>')) {
            var button = $(this);
            var resultDiv = $('#optimization-result');

            button.prop('disabled', true).text('<?php _e('Optimizing...', 'advnews-manager'); ?>');
            resultDiv.hide();

            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_optimize_tables',
                    nonce: advnews_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        resultDiv.removeClass('error').addClass('updated')
                            .html('<p>' + response.data.message + '</p>').show();
                    } else {
                        resultDiv.removeClass('updated').addClass('error')
                            .html('<p>' + response.data.message + '</p>').show();
                    }
                },
                error: function() {
                    resultDiv.removeClass('updated').addClass('error')
                        .html('<p><?php _e('An error occurred.', 'advnews-manager'); ?></p>').show();
                },
                complete: function() {
                    button.prop('disabled', false).text('<?php _e('Optimize Tables', 'advnews-manager'); ?>');
                }
            });
        }
    });

    // Clean old logs
    $('#clean-old-logs').on('click', function() {
        if (confirm('<?php _e('This will remove all logs older than 365 days. Continue?', 'advnews-manager'); ?>')) {
            var button = $(this);
            var resultDiv = $('#optimization-result');

            button.prop('disabled', true).text('<?php _e('Cleaning...', 'advnews-manager'); ?>');
            resultDiv.hide();

            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_clean_old_logs',
                    nonce: advnews_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        resultDiv.removeClass('error').addClass('updated')
                            .html('<p>' + response.data.message + '</p>').show();
                    } else {
                        resultDiv.removeClass('updated').addClass('error')
                            .html('<p>' + response.data.message + '</p>').show();
                    }
                },
                error: function() {
                    resultDiv.removeClass('updated').addClass('error')
                        .html('<p><?php _e('An error occurred.', 'advnews-manager'); ?></p>').show();
                },
                complete: function() {
                    button.prop('disabled', false).text('<?php _e('Clean Old Logs', 'advnews-manager'); ?>');
                }
            });
        }
    });
});
</script>

<style>
.advnews-system-analytics {
    padding: 20px 0;
}

.advnews-system-analytics h2 {
    font-size: 1.3em;
    margin: 30px 0 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
}

/* Health Grid */
.health-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.health-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
}

.health-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
}

.health-icon.healthy { background: rgba(0, 163, 42, 0.1); color: #00a32a; }
.health-icon.unhealthy { background: rgba(214, 54, 56, 0.1); color: #d63638; }
.health-icon.warning { background: rgba(240, 195, 60, 0.1); color: #f0c33c; }

.health-content {
    flex: 1;
}

.health-content h3 {
    margin: 0 0 10px;
    font-size: 16px;
}

.health-status,
.health-stats {
    font-size: 13px;
}

.health-status small,
.health-stats small {
    display: block;
    color: #646970;
    margin-top: 5px;
}

/* Status Badges */
.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.status-badge.status-ok {
    background: #d4edda;
    color: #155724;
}

.status-badge.status-error {
    background: #f8d7da;
    color: #721c24;
}

.status-badge.status-warning {
    background: #fff3cd;
    color: #856404;
}

/* Performance Metrics */
.performance-excellent {
    color: #00a32a;
    font-weight: 600;
}

.performance-good {
    color: #2271b1;
    font-weight: 600;
}

.performance-average {
    color: #f0c33c;
    font-weight: 600;
}

.performance-poor {
    color: #d63638;
    font-weight: 600;
}

/* Queue Statistics */
.queue-stats-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.queue-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 6px;
    padding: 15px;
    text-align: center;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
}

.queue-stat-value {
    font-size: 24px;
    font-weight: 600;
    color: #2271b1;
    line-height: 1.2;
    margin-bottom: 5px;
}

.queue-stat-label {
    font-size: 12px;
    color: #646970;
}

.queue-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

/* Database Optimization */
.optimization-stats {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.optimization-stats p {
    margin: 5px 0;
}

.optimization-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

#optimization-result.updated {
    background: #d4edda;
    border-left: 4px solid #00a32a;
    padding: 10px 15px;
}

#optimization-result.error {
    background: #f8d7da;
    border-left: 4px solid #d63638;
    padding: 10px 15px;
}

/* Responsive */
@media (max-width: 1200px) {
    .queue-stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 782px) {
    .health-grid {
        grid-template-columns: 1fr;
    }

    .queue-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .optimization-actions {
        flex-direction: column;
    }

    .optimization-actions .button {
        width: 100%;
    }
}
</style>
