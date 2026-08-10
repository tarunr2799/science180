<?php
// cron/daily-maintenance.php
if (!defined('ABSPATH')) exit;

/**
 * AdvNews Daily Maintenance Class
 * Performs daily cleanup and maintenance tasks
 */
class AdvNews_Daily_Maintenance {

    private $wpdb;
    private $table_prefix;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = ADVNEWS_TABLE_PREFIX;
    }

    /**
     * Execute daily maintenance
     *
     * @return array Result with success status
     */
    public function execute() {
        try {
            $results = array(
                'cleared_stuck' => 0,
                'cleaned_bounced' => 0,
                'cleaned_logs' => 0,
                'optimized_tables' => 0
            );

            // Clear stuck emails (older than 2 hours)
            $queue = new AdvNews_Queue();
            $results['cleared_stuck'] = $queue->clear_stuck_emails();

            // Clean bounced emails
            if (get_option('advnews_auto_clean_bounced')) {
                $results['cleaned_bounced'] = $this->clean_bounced_subscribers();
            }

            // Clean old logs
            $results['cleaned_logs'] = $this->clean_old_logs();

            // Optimize tables (optional)
            if (get_option('advnews_auto_optimize_tables')) {
                $results['optimized_tables'] = $this->optimize_tables();
            }

            // Log if debug enabled
            if (get_option('advnews_enable_debug_log')) {
                error_log('[AdvNews] Daily maintenance completed: ' . print_r($results, true));
            }

            return array(
                'success' => true,
                'message' => __('Daily maintenance completed successfully.', 'advnews-manager'),
                'data' => $results
            );

        } catch (Exception $e) {
            if (get_option('advnews_enable_debug_log')) {
                error_log('[AdvNews] Daily maintenance error: ' . $e->getMessage());
            }

            return array(
                'success' => false,
                'message' => __('Daily maintenance failed: ', 'advnews-manager') . $e->getMessage(),
                'data' => array()
            );
        }
    }

    /**
     * Clean bounced subscribers
     *
     * @return int Number of subscribers cleaned
     */
    private function clean_bounced_subscribers() {
        $bounce_attempts = get_option('advnews_bounce_attempts', 3);
        $table_subscribers = $this->wpdb->prefix . $this->table_prefix . 'subscribers';
        $table_logs = $this->wpdb->prefix . $this->table_prefix . 'campaign_logs';

        // Get subscribers with too many bounces
        $bounced = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT subscriber_id, COUNT(*) as bounce_count
            FROM $table_logs
            WHERE status = 'bounced'
            GROUP BY subscriber_id
            HAVING bounce_count >= %d",
            $bounce_attempts
        ));

        $cleaned = 0;
        foreach ($bounced as $bounce) {
            $this->wpdb->update(
                $table_subscribers,
                array('status' => 'bounced'),
                array('id' => $bounce->subscriber_id)
            );
            $cleaned++;
        }

        return $cleaned;
    }

    /**
     * Clean old logs
     *
     * @return int Number of logs cleaned
     */
    private function clean_old_logs() {
        $retention_days = get_option('advnews_tracking_retention_days', 365);
        $cutoff_date = date('Y-m-d', strtotime("-$retention_days days"));

        $tables = array(
            'campaign_logs' => 'created_at',
            'tracking_opens' => 'opened_at',
            'tracking_clicks' => 'clicked_at',
            'activity_log' => 'created_at'
        );

        $total_cleaned = 0;
        foreach ($tables as $table => $date_column) {
            $table_name = $this->wpdb->prefix . $this->table_prefix . $table;
            $count = $this->wpdb->query($this->wpdb->prepare(
                "DELETE FROM $table_name WHERE $date_column < %s",
                $cutoff_date
            ));
            if ($count) {
                $total_cleaned += $count;
            }
        }

        return $total_cleaned;
    }

    /**
     * Optimize database tables
     *
     * @return int Number of tables optimized
     */
    private function optimize_tables() {
        $tables = array(
            'subscribers',
            'subscriber_categories',
            'campaigns',
            'campaign_logs',
            'tracking_opens',
            'tracking_clicks',
            'templates',
            'categories',
            'links'
        );

        $optimized = 0;
        foreach ($tables as $table) {
            $table_name = $this->wpdb->prefix . $this->table_prefix . $table;
            $this->wpdb->query("OPTIMIZE TABLE $table_name");
            $optimized++;
        }

        return $optimized;
    }
}

// Execute if called directly (for cron)
if (defined('DOING_CRON') && DOING_CRON) {
    $maintenance = new AdvNews_Daily_Maintenance();
    $result = $maintenance->execute();

    if (get_option('advnews_enable_debug_log')) {
        error_log('[AdvNews Cron] Daily maintenance result: ' . print_r($result, true));
    }
}
