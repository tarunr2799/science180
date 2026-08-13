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
        // CRITICAL FIX: Register custom cron schedules FIRST, before any clearing or scheduling
        add_filter('cron_schedules', array(__CLASS__, 'add_cron_schedules'));

        // Clear existing hooks first to prevent duplicates
        self::clear_scheduled_events();

        // Schedule queue processing (every minute)
        if (!wp_next_scheduled('advnews_process_queue')) {
            wp_schedule_event(time(), 'advnews_every_minute', 'advnews_process_queue');
        }

        // Schedule daily maintenance
        if (!wp_next_scheduled('advnews_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'advnews_daily_maintenance');
        }

        // Schedule weekly reports
        if (!wp_next_scheduled('advnews_weekly_reports')) {
            wp_schedule_event(time(), 'weekly', 'advnews_weekly_reports');
        }

        // Log for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews Cron] Events scheduled successfully');
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

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews Cron] Events cleared');
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

        // 3. ✅ NEW: Auto-update MaxMind GeoIP2 Local Database
        if (get_option('advnews_maxmind_auto_update') && get_option('advnews_maxmind_license_key')) {
            $last_update = intval(get_option('advnews_maxmind_last_update', 0));
            if (!$last_update || (time() - $last_update) >= WEEK_IN_SECONDS) {
                if (self::update_maxmind_db_silent()) {
                    update_option('advnews_maxmind_last_update', time());
                }
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
    * Get remote file size via cURL (follows redirects)
    */
    private static function get_remote_file_size($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        curl_exec($ch);
        $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 400 || $size <= 0) {
            return false;
        }

        return (int) $size;
    }

    /**
    * Download file with resume capability and strict size verification
    */
    private static function download_file_resumable($url, $destination, $expected_size) {
        $dir = dirname($destination);
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        // 'a+' mode allows us to resume if the file was partially downloaded
        $fp = fopen($destination, 'a+');
        if (!$fp) {
            return new WP_Error('file_open_failed', 'Cannot open destination file for writing.');
        }

        $current_size = filesize($destination);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600); // 10 minutes for large files
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        // Attempt to resume if we have a partial file
        if ($current_size > 0 && $current_size < $expected_size) {
            curl_setopt($ch, CURLOPT_RESUME_FROM, $current_size);
        } elseif ($current_size >= $expected_size) {
            // File is already fully downloaded
            fclose($fp);
            return true;
        }

        $success = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (!$success || ($http_code != 200 && $http_code != 206)) {
            return new WP_Error('download_failed', 'cURL download failed with HTTP code: ' . $http_code);
        }

        // Verify final size
        clearstatcache();
        $final_size = filesize($destination);

        if ($final_size !== $expected_size) {
            // If the server doesn't support resume, it might have appended the file.
            // Delete it so the next run starts fresh.
            @unlink($destination);
            return new WP_Error('size_mismatch', sprintf(
                'Downloaded file size (%d bytes) does not match expected remote size (%d bytes). Partial file deleted.',
                $final_size,
                $expected_size
            ));
        }

        return true;
    }



    /**
    * Silently download and update MaxMind database without dying (Safe for Cron)
    * Includes remote size verification and resumable chunked downloading.
    */
    private static function update_maxmind_db_silent() {
        $license_key = get_option('advnews_maxmind_license_key', '');
        if (empty($license_key)) return false;

        $upload_dir = wp_upload_dir();
        $db_dir = $upload_dir['basedir'] . '/advnews-maxmind/';
        if (!wp_mkdir_p($db_dir)) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('[AdvNews] Cannot create MaxMind upload directory.');
            return false;
        }

        $gz_url = "https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-City&license_key={$license_key}&suffix=tar.gz";
        $temp_file = $db_dir . 'geolite2-city-temp.tar.gz';
        $final_db_path = $db_dir . 'GeoLite2-City.mmdb';

        // 1. Get remote file size BEFORE downloading
        $remote_size = self::get_remote_file_size($gz_url);
        if (!$remote_size) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('[AdvNews] MaxMind update failed: Could not determine remote file size. MaxMind may be blocking the request or the license key is invalid.');
            return false;
        }

        // 2. Download file (resumable chunked download)
        $download_result = self::download_file_resumable($gz_url, $temp_file, $remote_size);
        if (is_wp_error($download_result)) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('[AdvNews] MaxMind update failed: ' . $download_result->get_error_message());
            return false; // Temp file is kept so it can be resumed on the next cron run
        }

        // 3. STRICT CHECK: Verify size before extraction
        // If the downloaded file is 62.8MB but the remote .tar.gz is ~35MB, this catches it immediately.
        clearstatcache();
        if (filesize($temp_file) !== $remote_size) {
            @unlink($temp_file);
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('[AdvNews] MaxMind update failed: Final file size mismatch after download. File corrupted.');
            return false;
        }

        // 4. Extract to temporary directory (ONLY if size matches perfectly)
        $extract_dir = $db_dir . 'temp_extract_' . time() . '/';
        if (!is_dir($extract_dir)) {
            wp_mkdir_p($extract_dir);
        }

        try {
            $phar = new PharData($temp_file);
            $phar->extractTo($extract_dir, null, true);
        } catch (Exception $e) {
            @unlink($temp_file);
            self::delete_directory($extract_dir);
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('[AdvNews] MaxMind update failed (Extraction): ' . $e->getMessage());
            return false;
        }

        // Clean up the archive after successful extraction
        @unlink($temp_file);

        // 5. Find the .mmdb file inside the extracted folder
        $mmdb_path = null;
        if (class_exists('RecursiveIteratorIterator')) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($extract_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'mmdb') {
                    $mmdb_path = $file->getPathname();
                    break;
                }
            }
        } else {
            $files = glob($extract_dir . '/*/GeoLite2-City.mmdb');
            if (!empty($files)) $mmdb_path = $files[0];
        }

        if (!$mmdb_path) {
            self::delete_directory($extract_dir);
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('[AdvNews] MaxMind update failed: Could not find .mmdb file in archive.');
            return false;
        }

        // 6. STRICT SIZE CHECK ON EXTRACTED MMDB: A valid GeoLite2-City.mmdb is ALWAYS > 10MB.
        $mmdb_size = filesize($mmdb_path);
        if ($mmdb_size < 10000000) { // 10 MB
            self::delete_directory($extract_dir);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews] MaxMind update FAILED: Extracted .mmdb file is too small (' . $mmdb_size . ' bytes). File is corrupted or invalid. OLD DATABASE PRESERVED.');
            }
            return false; // ABORT: Do NOT overwrite the existing good database!
        }

        // 7. Validation passed! Safely replace the old database.
        if (file_exists($final_db_path)) {
            @unlink($final_db_path);
        }
        if (!rename($mmdb_path, $final_db_path)) {
            // Fallback to copy if rename fails (e.g., across different mount points)
            copy($mmdb_path, $final_db_path);
            @unlink($mmdb_path);
        }

        // 8. Cleanup and update option
        self::delete_directory($extract_dir);
        update_option('advnews_maxmind_db_path', $final_db_path);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews] MaxMind database successfully updated via daily cron. New size: ' . size_format($mmdb_size));
        }

        return true;
    }


    /**
    * Helper to delete a directory recursively
    */
    private static function delete_directory($dir) {
        if (!is_dir($dir)) return;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            @$todo($fileinfo->getRealPath());
        }
        @rmdir($dir);
    }

    /**
     * Send weekly reports
     */
    public static function weekly_reports()
    {
        $admin_email = get_option('admin_email');
        $company_name = get_option('advnews_company_name', get_bloginfo('name'));

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
        $message .= '<p>' . __('This is an automated weekly report from AdvNews Manager.', 'advnews-manager') . '</p>';
        $message .= '</body></html>';

        // Send email
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($admin_email, $subject, $message, $headers);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews] Weekly report sent');
        }
    }
}

// GLOBAL FIX: Always register custom cron schedules on every page load
add_filter('cron_schedules', array('AdvNews_Cron', 'add_cron_schedules'));

// Hook cron actions
add_action('advnews_process_queue', array('AdvNews_Cron', 'process_queue'));
add_action('advnews_daily_maintenance', array('AdvNews_Cron', 'daily_maintenance'));
add_action('advnews_weekly_reports', array('AdvNews_Cron', 'weekly_reports'));

// IMPORTANT FIX: Schedule events on init to ensure they are always registered
add_action('init', array('AdvNews_Cron', 'schedule_events'), 1);
