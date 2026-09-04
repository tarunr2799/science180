<?php
// File: includes/class-cron.php
if (!defined('ABSPATH')) {
    exit;
}

class AdvNews_Cron
{

    /**
    * Schedule cron events
    */
    public static function schedule_events()
    {
        add_filter('cron_schedules', array(__CLASS__, 'add_cron_schedules'));

        self::weekly_report_company_name();

        if (!wp_next_scheduled('advnews_process_queue')) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, 'advnews_every_minute', 'advnews_process_queue');
        }

        if (!wp_next_scheduled('advnews_daily_maintenance')) {
            wp_schedule_event(self::next_daily_maintenance_timestamp(), 'daily', 'advnews_daily_maintenance');
        }

        self::ensure_weekly_report_schedule();

        self::ensure_maxmind_update_schedule();

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews Cron] Events checked successfully');
        }
    }

    /**
     * Clear scheduled events
     */
    public static function clear_scheduled_events()
    {
        wp_clear_scheduled_hook('advnews_process_queue');
        wp_clear_scheduled_hook('advnews_daily_maintenance');
        wp_clear_scheduled_hook('advnews_weekly_reports');
        wp_clear_scheduled_hook('advnews_update_maxmind_database');

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews Cron] Events cleared');
        }
    }

    private static function next_weekly_report_timestamp()
    {
        try {
            $now = new DateTimeImmutable('now', wp_timezone());
            $next_run = $now->modify('monday this week')->setTime(8, 0);
            if ($next_run <= $now) {
                $next_run = $next_run->modify('+1 week');
            }
            $timestamp = $next_run->getTimestamp();
        } catch (Exception $error) {
            $timestamp = time() + WEEK_IN_SECONDS;
        }

        return $timestamp;
    }

    public static function next_daily_maintenance_timestamp()
    {
        $timestamp = strtotime('tomorrow 02:00:00');
        return ($timestamp && $timestamp > time()) ? $timestamp : time() + DAY_IN_SECONDS;
    }

    public static function next_weekly_report_run()
    {
        return self::next_weekly_report_timestamp();
    }

    public static function ensure_weekly_report_schedule()
    {
        $event_count = 0;
        $scheduled_timestamp = wp_next_scheduled('advnews_weekly_reports');
        $expected_timestamp = self::next_weekly_report_timestamp();
        $cron_array = _get_cron_array();
        if (is_array($cron_array)) {
            foreach ($cron_array as $hooks) {
                if (isset($hooks['advnews_weekly_reports']) && is_array($hooks['advnews_weekly_reports'])) {
                    $event_count += count($hooks['advnews_weekly_reports']);
                }
            }
        }

        if (
            $event_count === 1
            && wp_get_schedule('advnews_weekly_reports') === 'weekly'
            && $scheduled_timestamp
            && abs((int) $scheduled_timestamp - $expected_timestamp) < MINUTE_IN_SECONDS
        ) {
            return;
        }

        wp_clear_scheduled_hook('advnews_weekly_reports');
        wp_schedule_event($expected_timestamp, 'weekly', 'advnews_weekly_reports');
    }

    /**
     * Return the current mail brand and repair the legacy plugin name in saved settings.
     */
    public static function weekly_report_company_name()
    {
        $company_name = trim((string) get_option('advnews_company_name', ''));
        if ($company_name === '' || strcasecmp($company_name, 'AdvNews Manager') === 0) {
            $company_name = 'Science180 Mail';
            update_option('advnews_company_name', $company_name, false);
        }

        $from_name = trim((string) get_option('advnews_from_name', ''));
        if (strcasecmp($from_name, 'AdvNews Manager') === 0) {
            update_option('advnews_from_name', 'Science180 Mail', false);
        }

        return $company_name;
    }

    public static function ensure_maxmind_update_schedule()
    {
        $event_count = 0;
        $scheduled_timestamp = wp_next_scheduled('advnews_update_maxmind_database');
        $expected_timestamp = self::next_maxmind_update_timestamp();
        $cron_array = _get_cron_array();
        if (is_array($cron_array)) {
            foreach ($cron_array as $hooks) {
                if (isset($hooks['advnews_update_maxmind_database']) && is_array($hooks['advnews_update_maxmind_database'])) {
                    $event_count += count($hooks['advnews_update_maxmind_database']);
                }
            }
        }

        if (
            $event_count === 1
            && wp_get_schedule('advnews_update_maxmind_database') === 'daily'
            && $scheduled_timestamp
            && abs((int) $scheduled_timestamp - $expected_timestamp) < MINUTE_IN_SECONDS
        ) {
            return;
        }

        wp_clear_scheduled_hook('advnews_update_maxmind_database');
        wp_schedule_event($expected_timestamp, 'daily', 'advnews_update_maxmind_database');
    }

    /**
     * Claim the current weekly report period exactly once across every runner.
     */
    public static function claim_weekly_report_delivery()
    {
        $report_week = wp_date('o-W');
        if ((string) get_option('advnews_last_weekly_report_week', '') === $report_week) {
            return false;
        }

        $last_sent_at = (string) get_option('advnews_last_weekly_report_sent_at', '');
        if ($last_sent_at !== '') {
            try {
                $last_sent = new DateTimeImmutable($last_sent_at, wp_timezone());
                if ($last_sent->format('o-W') === $report_week) {
                    update_option('advnews_last_weekly_report_week', $report_week, false);
                    return false;
                }
            } catch (Exception $error) {
                // A malformed legacy timestamp must not prevent the atomic claim below.
            }
        }

        $claim_key = 'advnews_weekly_report_claim_' . sanitize_key($report_week);
        if (!add_option($claim_key, current_time('mysql'), '', false)) {
            return false;
        }

        // Claim before sending so an ambiguous mail response cannot cause retries.
        update_option('advnews_last_weekly_report_week', $report_week, false);
        update_option('advnews_last_weekly_report_sent_at', current_time('mysql'), false);

        return true;
    }

    public static function next_maxmind_update_timestamp()
    {
        try {
            $now = new DateTimeImmutable('now', wp_timezone());
            return $now->modify('tomorrow')->setTime(3, 0)->getTimestamp();
        } catch (Exception $error) {
            return time() + DAY_IN_SECONDS;
        }
    }

    /**
     * Add custom cron schedules
     */
    public static function add_cron_schedules($schedules)
    {
        $schedules['advnews_every_minute'] = array(
            'interval' => 60,
            'display' => __('Every Minute', 'advnews-manager')
        );

        $schedules['advnews_every_five_minutes'] = array(
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'advnews-manager')
        );

        return $schedules;
    }

    /**
    * Process email queue - UPDATED: Auto-triggers scheduled campaigns when due
    */
    public static function process_queue()
    {
        // Check if queue is paused
        if (get_option('advnews_queue_paused')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews Cron] Queue is paused');
            }
            return;
        }

        // =====================================================
        // NEW: Check for scheduled campaigns that are now due
        // =====================================================
        global $wpdb;
        $table_campaigns = $wpdb->prefix . ADVNEWS_TABLE_PREFIX . 'campaigns';
        $current_time = current_time('mysql', true); // TRUE ensures we use GMT
        $due_campaigns = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id FROM $table_campaigns
                WHERE status = 'scheduled'
                AND scheduled_for IS NOT NULL
                AND scheduled_for != '0000-00-00 00:00:00'
                AND scheduled_for <= %s",
                $current_time
            )
        );

        if (!empty($due_campaigns)) {
            require_once ADVNEWS_PLUGIN_DIR . 'includes/class-campaign.php';
            $campaign_class = new AdvNews_Campaign();

            foreach ($due_campaigns as $camp) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[AdvNews Cron] ⏰ Triggering due scheduled campaign ID: ' . $camp->id);
                }
                $result = $campaign_class->send_campaign($camp->id);
                if (is_wp_error($result) && defined('WP_DEBUG')) {
                    error_log('[AdvNews Cron] Failed to trigger campaign ' . $camp->id . ': ' . $result->get_error_message());
                }
            }
        }

        // Process the queue
        require_once ADVNEWS_PLUGIN_DIR . 'includes/class-queue.php';
        $queue_class = new AdvNews_Queue();
        // Get batch size from settings
        $batch_size = max(1, min(500, absint(get_option('advnews_emails_per_batch', 50))));
        // Process the queue
        $result = $queue_class->process_queue($batch_size);
        // Log the result
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[AdvNews Cron] Queue processed: %d sent, %d failed',
                $result['sent'],
                $result['failed']
            ));
        }
    }

    /**
     * Daily maintenance tasks
     */
    public static function daily_maintenance()
    {
        global $wpdb;
        $table_prefix = ADVNEWS_TABLE_PREFIX;

        // 1. Clear stuck emails
        require_once ADVNEWS_PLUGIN_DIR . 'includes/class-queue.php';
        $queue_class = new AdvNews_Queue();
        $cleared = $queue_class->clear_stuck_emails();
        if ($cleared > 0 && defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[AdvNews] Cleared %d stuck emails', $cleared));
        }

        // 2. Clean up old bounced emails if enabled
        if (get_option('advnews_auto_clean_bounced')) {
            $bounce_attempts = get_option('advnews_bounce_attempts', 3);
            $table_subscribers = $wpdb->prefix . $table_prefix . 'subscribers';
            $table_logs = $wpdb->prefix . $table_prefix . 'campaign_logs';

            // Find subscribers with multiple bounces
            $bounced_subscribers = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT subscriber_id, COUNT(*) as bounce_count
                    FROM $table_logs
                    WHERE status = 'bounced'
                    GROUP BY subscriber_id
                    HAVING bounce_count >= %d",
                    $bounce_attempts
                )
            );
            foreach ($bounced_subscribers as $subscriber) {
                $wpdb->update(
                    $table_subscribers,
                    array('status' => 'bounced'),
                    array('id' => $subscriber->subscriber_id)
                );
            }
            if (defined('WP_DEBUG') && !empty($bounced_subscribers)) {
                error_log(sprintf('[AdvNews] Marked %d subscribers as bounced', count($bounced_subscribers)));
            }
        }

        // 3. Backfill missing click geolocation for rows created before the IP was cached.
        if (get_option('advnews_track_geolocation', true)) {
            require_once ADVNEWS_PLUGIN_DIR . 'includes/class-tracking.php';
            $tracking_class = new AdvNews_Tracking();
            $repaired_opens = $tracking_class->backfill_missing_open_geolocation(array('limit' => 200));
            $repaired_clicks = $tracking_class->backfill_missing_click_geolocation(array('limit' => 200));
            if (($repaired_opens > 0 || $repaired_clicks > 0) && defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[AdvNews] Backfilled geolocation for %d open rows and %d click rows',
                    $repaired_opens,
                    $repaired_clicks
                ));
            }
        }

        // 4. Clean up old tracking data
        $retention_days = get_option('advnews_tracking_retention_days', 365);
        if ($retention_days > 0) {
            $cutoff_date = date('Y-m-d', strtotime("-$retention_days days"));
            $tracking_tables = array('tracking_opens' => 'opened_at', 'tracking_clicks' => 'clicked_at');
            foreach ($tracking_tables as $table => $date_col) {
                $t_name = $wpdb->prefix . $table_prefix . $table;
                $wpdb->query($wpdb->prepare("DELETE FROM $t_name WHERE $date_col < %s", $cutoff_date));
            }
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews] Daily maintenance completed.');
        }
    }

    /**
     * Run the single authenticated MaxMind updater for cron and maintenance fallback.
     */
    public static function update_maxmind_database()
    {
        if (
            get_option('advnews_geolocation_service', 'maxmind') !== 'maxmind'
            || !get_option('advnews_maxmind_auto_update', true)
        ) {
            return false;
        }

        $last_update = (int) get_option('advnews_maxmind_last_update', 0);
        if ($last_update && (time() - $last_update) < DAY_IN_SECONDS) {
            return true;
        }

        if (get_transient('advnews_maxmind_update_lock')) {
            return false;
        }

        set_transient('advnews_maxmind_update_lock', 1, 10 * MINUTE_IN_SECONDS);
        update_option('advnews_maxmind_last_attempt', time());

        require_once ADVNEWS_PLUGIN_DIR . 'includes/class-tracking.php';
        $tracking = new AdvNews_Tracking();
        $result = $tracking->update_maxmind_database_safely();
        delete_transient('advnews_maxmind_update_lock');

        if (is_wp_error($result)) {
            update_option('advnews_maxmind_last_error', $result->get_error_message());
            error_log('[Science180 Mail] MaxMind auto-update failed: ' . $result->get_error_message());
            return $result;
        }

        delete_option('advnews_maxmind_last_error');
        error_log('[Science180 Mail] MaxMind database updated successfully.');
        return true;
    }


    /**
     * Correct the known Science180 admin email typo before weekly reports are sent.
     */
    private static function normalize_weekly_report_email($email)
    {
        $email = sanitize_email($email);
        if ($email === '') {
            return $email;
        }

        if (preg_match('/@science\.net$/i', $email)) {
            $email = preg_replace('/@science\.net$/i', '@science180.net', $email);
        }

        return is_email($email) ? $email : '';
    }

    /**
     * Send weekly reports
     */
    public static function weekly_reports()
    {
        $admin_email = self::normalize_weekly_report_email(get_option('admin_email'));
        if ($admin_email === '') {
            return;
        }

        if (!self::claim_weekly_report_delivery()) {
            return;
        }
        $company_name = self::weekly_report_company_name();

        // Get weekly statistics
        global $wpdb;
        $table_prefix = ADVNEWS_TABLE_PREFIX;
        $week_start = date('Y-m-d', strtotime('-7 days'));
        $week_end = date('Y-m-d');

        // Campaign statistics
        $campaign_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
            COUNT(*) as total_campaigns,
            SUM(total_recipients) as total_emails,
            AVG(open_rate) as avg_open_rate,
            AVG(click_rate) as avg_click_rate
            FROM {$wpdb->prefix}{$table_prefix}campaigns
            WHERE sent_at BETWEEN %s AND %s",
            $week_start,
            $week_end
        ));

        // Subscriber statistics
        $subscriber_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
            COUNT(*) as total_subscribers,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_subscribers,
            SUM(CASE WHEN status = 'unsubscribed' THEN 1 ELSE 0 END) as unsubscribed,
            SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced
            FROM {$wpdb->prefix}{$table_prefix}subscribers
            WHERE subscribed_at <= %s",
            $week_end
        ));

        // Prepare email content
        $subject = sprintf(__('Weekly Report - %s', 'advnews-manager'), $company_name);
        $message = '<html><body>';
        $message .= '<h2>' . sprintf(__('Weekly Report for %s', 'advnews-manager'), $week_start . ' to ' . $week_end) . '</h2>';
        $message .= '<h3>' . __('Campaign Performance', 'advnews-manager') . '</h3>';
        $message .= '<ul>';
        $message .= '<li>' . sprintf(__('Total Campaigns: %d', 'advnews-manager'), $campaign_stats->total_campaigns ?? 0) . '</li>';
        $message .= '<li>' . sprintf(__('Total Emails Sent: %d', 'advnews-manager'), $campaign_stats->total_emails ?? 0) . '</li>';
        $message .= '<li>' . sprintf(__('Average Open Rate: %.2f%%', 'advnews-manager'), $campaign_stats->avg_open_rate ?? 0) . '</li>';
        $message .= '<li>' . sprintf(__('Average Click Rate: %.2f%%', 'advnews-manager'), $campaign_stats->avg_click_rate ?? 0) . '</li>';
        $message .= '</ul>';
        $message .= '<h3>' . __('Subscriber Overview', 'advnews-manager') . '</h3>';
        $message .= '<ul>';
        $message .= '<li>' . sprintf(__('Total Subscribers: %d', 'advnews-manager'), $subscriber_stats->total_subscribers ?? 0) . '</li>';
        $message .= '<li>' . sprintf(__('Active Subscribers: %d', 'advnews-manager'), $subscriber_stats->active_subscribers ?? 0) . '</li>';
        $message .= '<li>' . sprintf(__('Unsubscribed: %d', 'advnews-manager'), $subscriber_stats->unsubscribed ?? 0) . '</li>';
        $message .= '<li>' . sprintf(__('Bounced: %d', 'advnews-manager'), $subscriber_stats->bounced ?? 0) . '</li>';
        $message .= '</ul>';
        $message .= '<p>' . __('This is an automated weekly report from Science180 Mail.', 'advnews-manager') . '</p>';
        $message .= '</body></html>';

        // Send email
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $sent = wp_mail($admin_email, $subject, $message, $headers);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($sent ? '[AdvNews] Weekly report sent' : '[AdvNews] Weekly report failed to send');
        }
    }
}

// GLOBAL FIX: Always register custom cron schedules on every page load
add_filter('cron_schedules', array('AdvNews_Cron', 'add_cron_schedules'));

// Hook cron actions
add_action('advnews_process_queue', array('AdvNews_Cron', 'process_queue'));
add_action('advnews_daily_maintenance', array('AdvNews_Cron', 'daily_maintenance'));
add_action('advnews_weekly_reports', array('AdvNews_Cron', 'weekly_reports'));
add_action('advnews_update_maxmind_database', array('AdvNews_Cron', 'update_maxmind_database'));
