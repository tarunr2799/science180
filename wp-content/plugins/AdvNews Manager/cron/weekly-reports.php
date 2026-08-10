<?php
// cron/weekly-reports.php
if (!defined('ABSPATH')) exit;

/**
 * AdvNews Weekly Reports Class
 * Generates and sends weekly analytics reports to administrators
 */
class AdvNews_Weekly_Reports {

    private $wpdb;
    private $table_prefix;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = ADVNEWS_TABLE_PREFIX;
    }

    /**
     * Execute weekly report generation
     *
     * @return array Report results
     */
    public function execute() {
        $start_time = microtime(true);

        $this->log_event('Starting weekly report generation');

        $results = array(
            'reports_sent' => 0,
            'recipients' => array(),
            'campaign_stats' => array(),
            'subscriber_stats' => array(),
            'engagement_stats' => array(),
            'errors' => array()
        );

        try {
            // Get report period (last 7 days)
            $end_date = current_time('mysql');
            $start_date = date('Y-m-d H:i:s', strtotime('-7 days'));

            // Generate campaign statistics
            $results['campaign_stats'] = $this->get_campaign_stats($start_date, $end_date);

            // Generate subscriber statistics
            $results['subscriber_stats'] = $this->get_subscriber_stats($start_date, $end_date);

            // Generate engagement statistics
            $results['engagement_stats'] = $this->get_engagement_stats($start_date, $end_date);

            // Get report recipients
            $recipients = $this->get_report_recipients();

            // Generate report HTML
            $report_html = $this->generate_report_html($results, $start_date, $end_date);

            // Send reports
            foreach ($recipients as $recipient) {
                try {
                    $sent = $this->send_report($recipient, $report_html, $results);
                    if ($sent) {
                        $results['reports_sent']++;
                        $results['recipients'][] = $recipient['email'];
                    }
                } catch (Exception $e) {
                    $results['errors'][] = 'Failed to send report to ' . $recipient['email'] . ': ' . $e->getMessage();
                }
            }

            // Archive report
            $this->archive_report($report_html, $results);

            $execution_time = microtime(true) - $start_time;
            $results['execution_time'] = round($execution_time, 2);

            $this->log_event(sprintf(
                'Weekly reports completed: %d sent in %.2f seconds',
                $results['reports_sent'],
                $execution_time
            ));

            return array(
                'success' => true,
                'message' => sprintf(
                    __('Weekly reports completed: %d reports sent.', 'advnews-manager'),
                    $results['reports_sent']
                ),
                'data' => $results
            );

        } catch (Exception $e) {
            $this->log_event('Weekly reports failed: ' . $e->getMessage());

            return array(
                'success' => false,
                'message' => __('Weekly reports failed: ', 'advnews-manager') . $e->getMessage(),
                'data' => $results
            );
        }
    }

    /**
     * Get campaign statistics
     */
    private function get_campaign_stats($start_date, $end_date) {
        $table_campaigns = $this->wpdb->prefix . $this->table_prefix . 'campaigns';

        $stats = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT
                COUNT(*) as total_campaigns,
                SUM(total_recipients) as total_recipients,
                SUM(delivered_count) as total_delivered,
                SUM(open_count) as total_opens,
                SUM(click_count) as total_clicks,
                SUM(bounce_count) as total_bounces,
                SUM(unsubscribe_count) as total_unsubscribes,
                AVG(open_rate) as avg_open_rate,
                AVG(click_rate) as avg_click_rate
            FROM $table_campaigns
            WHERE sent_at BETWEEN %s AND %s
            AND status = 'sent'",
            $start_date,
            $end_date
        ));

        // Get top campaigns
        $top_campaigns = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT
                id,
                name,
                subject,
                total_recipients,
                delivered_count,
                open_rate,
                click_rate,
                sent_at
            FROM $table_campaigns
            WHERE sent_at BETWEEN %s AND %s
            AND status = 'sent'
            ORDER BY open_rate DESC
            LIMIT 5",
            $start_date,
            $end_date
        ));

        return array(
            'summary' => $stats,
            'top_campaigns' => $top_campaigns
        );
    }

    /**
     * Get subscriber statistics
     */
    private function get_subscriber_stats($start_date, $end_date) {
        $table_subscribers = $this->wpdb->prefix . $this->table_prefix . 'subscribers';

        $current = $this->wpdb->get_row(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'unsubscribed' THEN 1 ELSE 0 END) as unsubscribed,
                SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced
            FROM $table_subscribers"
        );

        $new = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $table_subscribers
            WHERE subscribed_at BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));

        $unsubscribed = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $table_subscribers
            WHERE unsubscribed_at BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));

        return array(
            'current' => $current,
            'new' => (int) $new,
            'unsubscribed' => (int) $unsubscribed,
            'bounced' => (int) $this->wpdb->get_var(
                "SELECT COUNT(*) FROM $table_subscribers WHERE status = 'bounced'"
            ),
            'net_growth' => (int) $new - (int) $unsubscribed
        );
    }

    /**
     * Get engagement statistics
     */
    private function get_engagement_stats($start_date, $end_date) {
        $table_opens = $this->wpdb->prefix . $this->table_prefix . 'tracking_opens';
        $table_clicks = $this->wpdb->prefix . $this->table_prefix . 'tracking_clicks';

        $opens = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT
                COUNT(*) as total_opens,
                COUNT(DISTINCT subscriber_id) as unique_openers,
                COUNT(DISTINCT campaign_id) as campaigns_opened
            FROM $table_opens
            WHERE opened_at BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));

        $clicks = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT
                COUNT(*) as total_clicks,
                COUNT(DISTINCT subscriber_id) as unique_clickers
            FROM $table_clicks
            WHERE clicked_at BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));

        return array(
            'opens' => $opens,
            'clicks' => $clicks
        );
    }

    /**
     * Get report recipients
     */
    private function get_report_recipients() {
        $recipients = array();

        // Admin email
        $recipients[] = array(
            'email' => get_option('admin_email'),
            'name' => __('Administrator', 'advnews-manager'),
            'type' => 'admin'
        );

        // Additional recipients from settings
        $additional = get_option('advnews_report_recipients', array());
        foreach ($additional as $recipient) {
            if (is_email($recipient['email'])) {
                $recipients[] = array(
                    'email' => $recipient['email'],
                    'name' => $recipient['name'] ?? '',
                    'type' => 'additional'
                );
            }
        }

        return apply_filters('advnews_report_recipients', $recipients);
    }

    /**
     * Generate report HTML
     */
    private function generate_report_html($data, $start_date, $end_date) {
        $company_name = get_option('advnews_company_name', get_bloginfo('name'));
        $date_range = date_i18n(get_option('date_format'), strtotime($start_date)) . ' - ' .
                      date_i18n(get_option('date_format'), strtotime($end_date));

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo esc_html($company_name); ?> - Weekly Report</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #0073aa; color: white; padding: 20px; text-align: center; }
                .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin: 20px 0; }
                .stat-card { background: #f8f9fa; padding: 15px; border-radius: 4px; text-align: center; }
                .stat-value { font-size: 24px; font-weight: 600; color: #0073aa; }
                .stat-label { color: #666; font-size: 12px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
                th { background: #f8f9fa; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php echo esc_html($company_name); ?></h1>
                    <p><?php _e('Weekly Newsletter Report', 'advnews-manager'); ?></p>
                    <p><?php echo esc_html($date_range); ?></p>
                </div>

                <h2><?php _e('Campaign Summary', 'advnews-manager'); ?></h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo esc_html($data['campaign_stats']['summary']->total_campaigns ?? 0); ?></div>
                        <div class="stat-label"><?php _e('Campaigns Sent', 'advnews-manager'); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo esc_html($data['campaign_stats']['summary']->avg_open_rate ?? 0); ?>%</div>
                        <div class="stat-label"><?php _e('Avg Open Rate', 'advnews-manager'); ?></div>
                    </div>
                </div>

                <h2><?php _e('Subscriber Growth', 'advnews-manager'); ?></h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value">+<?php echo esc_html($data['subscriber_stats']['new']); ?></div>
                        <div class="stat-label"><?php _e('New Subscribers', 'advnews-manager'); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo esc_html($data['subscriber_stats']['current']->total ?? 0); ?></div>
                        <div class="stat-label"><?php _e('Total Subscribers', 'advnews-manager'); ?></div>
                    </div>
                </div>

                <p style="text-align: center; margin-top: 30px;">
                    <a href="<?php echo admin_url('admin.php?page=advnews-analytics'); ?>" style="color: #0073aa;">
                        <?php _e('View Full Analytics →', 'advnews-manager'); ?>
                    </a>
                </p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Send report email
     */
    private function send_report($recipient, $html, $data) {
        $subject = sprintf(
            '[%s] Weekly Newsletter Report - %s',
            get_bloginfo('name'),
            date_i18n(get_option('date_format'))
        );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('advnews_from_name') . ' <' . get_option('advnews_from_email') . '>'
        );

        return wp_mail($recipient['email'], $subject, $html, $headers);
    }

    /**
     * Archive report to file
     */
    private function archive_report($html, $data) {
        $archive_dir = ADVNEWS_PLUGIN_DIR . 'reports/';

        if (!file_exists($archive_dir)) {
            wp_mkdir_p($archive_dir);
        }

        $filename = $archive_dir . 'weekly-report-' . date('Y-m-d') . '.html';
        file_put_contents($filename, $html);

        // Keep only last 52 weeks (1 year) of reports
        $reports = glob($archive_dir . 'weekly-report-*.html');
        if (count($reports) > 52) {
            usort($reports, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            $to_delete = array_slice($reports, 0, count($reports) - 52);
            foreach ($to_delete as $file) {
                unlink($file);
            }
        }
    }

    /**
     * Log event
     */
    private function log_event($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews Reports] ' . $message);
        }

        $log_file = ADVNEWS_PLUGIN_DIR . 'logs/reports.log';
        $log_dir = dirname($log_file);

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        file_put_contents(
            $log_file,
            '[' . current_time('mysql') . '] ' . $message . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}

// Execute if called directly (for cron)
if (defined('DOING_CRON') && DOING_CRON) {
    $reports = new AdvNews_Weekly_Reports();
    $result = $reports->execute();

    if (get_option('advnews_enable_debug_log')) {
        error_log('[AdvNews Cron] Weekly reports result: ' . print_r($result, true));
    }
}
