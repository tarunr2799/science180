<?php
// File: includes/class-queue.php
if (!defined('ABSPATH')) {
    exit;
}

class AdvNews_Queue
{
    private $wpdb;
    private $table_prefix;
    private $processing_lock_token = '';

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = ADVNEWS_TABLE_PREFIX;
    }

    /**
     * Add email to queue
     */
    public function add_to_queue($campaign_id, $subscriber_id, $respect_cooldown = true)
    {
        $campaign_class = new AdvNews_Campaign();
        $campaign = $campaign_class->get_campaign($campaign_id);
        $subscriber_class = new AdvNews_Subscriber();
        $subscriber = $subscriber_class->get_subscriber($subscriber_id);

        if (!$campaign || !$subscriber) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews Queue] Invalid campaign or subscriber ID.');
            }
            return false;
        }

        $table_name = $this->wpdb->prefix . $this->table_prefix . 'campaign_logs';

        $existing = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT id, status, send_after, retry_count, bounce_message FROM $table_name
            WHERE campaign_id = %d AND subscriber_id = %d",
            $campaign_id,
            $subscriber_id
        ));

        if ($existing) {
            if (in_array($existing->status, array('sent', 'delivered', 'opened', 'clicked'))) {
                return false;
            }
            if ($existing->status === 'queued') {
                return false;
            }
            if ($existing->status === 'failed') {
                if ($existing->bounce_message === __('Campaign ended by admin', 'advnews-manager')) {
                    return false;
                }
                $this->wpdb->update(
                    $table_name,
                    array(
                        'status' => 'queued',
                        'sent_at' => null,
                        'send_after' => null,
                        'retry_count' => ($existing->retry_count ?? 0) + 1
                    ),
                    array('id' => $existing->id)
                );
                return true;
            }
            return false;
        }

        $data = array(
            'campaign_id' => $campaign_id,
            'subscriber_id' => $subscriber_id,
            'email' => $subscriber->email,
            'status' => 'queued',
            'created_at' => current_time('mysql')
        );

        $send_after = null;
        if ($respect_cooldown && !empty($campaign->respect_cooldown) && !empty($subscriber->last_email_sent)) {
            $cooldown_days = intval(get_option('advnews_cooldown_days', 5));
            if ($cooldown_days > 0) {
                $last_sent_gmt = get_gmt_from_date($subscriber->last_email_sent);
                $last_sent_timestamp = strtotime($last_sent_gmt);
                $cooldown_seconds = $cooldown_days * DAY_IN_SECONDS;
                $send_after_timestamp = $last_sent_timestamp + $cooldown_seconds;
                if ($last_sent_timestamp && $send_after_timestamp > current_time('timestamp', true)) {
                    $send_after = gmdate('Y-m-d H:i:s', $send_after_timestamp);
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[AdvNews Queue] Subscriber ' . $subscriber_id . ' in cooldown. Will send after: ' . $send_after);
                    }
                }
            }
        }
        if ($send_after) {
            $data['send_after'] = $send_after;
        }

        $result = $this->wpdb->insert($table_name, $data);
        if ($result === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews Queue] Failed to insert into queue: ' . $this->wpdb->last_error);
            }
            return false;
        }

        return true;
    }

    /**
     * Process queue (send emails in batches)
     * FIXED: Resolved SQL ID collision between campaign_logs.id and campaigns.id
     */
    public function process_queue($batch_size = 50)
    {
        $batch_size = max(1, min(500, absint($batch_size)));

        if (!$this->acquire_processing_lock()) {
            return $this->deferred_queue_result(array('processing_locked' => true));
        }

        try {
            $wait_seconds = $this->seconds_until_next_batch();
            if ($wait_seconds > 0) {
                return $this->deferred_queue_result(array(
                    'throttled' => true,
                    'wait_seconds' => $wait_seconds,
                    'next_batch_at' => time() + $wait_seconds,
                ));
            }

            return $this->process_queue_batch($batch_size);
        } finally {
            $this->release_processing_lock();
        }
    }

    private function acquire_processing_lock()
    {
        $option = 'advnews_queue_processing_lock';
        $now = time();
        $existing = get_option($option, array());
        $acquired_at = is_array($existing) ? absint($existing['acquired_at'] ?? 0) : absint($existing);

        if ($acquired_at && ($now - $acquired_at) < (15 * MINUTE_IN_SECONDS)) {
            return false;
        }

        if ($existing) {
            delete_option($option);
        }

        $this->processing_lock_token = wp_generate_uuid4();
        return add_option($option, array(
            'token' => $this->processing_lock_token,
            'acquired_at' => $now,
        ), '', false);
    }

    private function release_processing_lock()
    {
        if ($this->processing_lock_token === '') {
            return;
        }

        $lock = get_option('advnews_queue_processing_lock', array());
        if (is_array($lock) && hash_equals($this->processing_lock_token, (string) ($lock['token'] ?? ''))) {
            delete_option('advnews_queue_processing_lock');
        }
        $this->processing_lock_token = '';
    }

    private function seconds_until_next_batch()
    {
        $minutes = max(1, min(120, absint(get_option('advnews_minutes_between_batches', 20))));
        $last_batch = absint(get_option('advnews_last_batch_processed_at', 0));
        if (!$last_batch) {
            return 0;
        }

        return max(0, ($last_batch + ($minutes * MINUTE_IN_SECONDS)) - time());
    }

    private function deferred_queue_result($extra = array())
    {
        $table_logs = $this->wpdb->prefix . $this->table_prefix . 'campaign_logs';
        $current_time = current_time('mysql', true);
        $remaining = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM $table_logs WHERE status = 'queued'");
        $on_cooldown = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $table_logs WHERE status = 'queued' AND send_after IS NOT NULL AND send_after > %s AND send_after != '0000-00-00 00:00:00'",
            $current_time
        ));

        return array_merge(array(
            'sent' => 0,
            'failed' => 0,
            'remaining' => $remaining,
            'on_cooldown' => $on_cooldown,
        ), $extra);
    }

    private function process_queue_batch($batch_size)
    {
        $table_logs = $this->wpdb->prefix . $this->table_prefix . 'campaign_logs';
        $table_campaigns = $this->wpdb->prefix . $this->table_prefix . 'campaigns';
        $table_subscribers = $this->wpdb->prefix . $this->table_prefix . 'subscribers';

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews Queue] Starting queue processing with batch size: ' . $batch_size);
        }

        $current_time = current_time('mysql', true); // GMT for accurate scheduling

        // FIXED: Explicitly select and alias columns to prevent c.id from overwriting cl.id
        $queued_emails = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT cl.id as log_id, cl.subscriber_id, cl.status, cl.send_after, cl.retry_count,
                    c.id as campaign_id, c.name as campaign_name, c.subject, c.content, c.from_name, c.from_email, c.reply_to, c.priority, c.track_opens, c.track_clicks, c.respect_cooldown,
                    s.email as subscriber_email, s.first_name, s.last_name, s.organization
            FROM $table_logs cl
            INNER JOIN $table_campaigns c ON cl.campaign_id = c.id
            INNER JOIN $table_subscribers s ON cl.subscriber_id = s.id
            WHERE cl.status = 'queued'
            AND c.status IN ('scheduled', 'sending', 'sent')
            AND (c.scheduled_for IS NULL OR c.scheduled_for <= %s OR c.scheduled_for = '0000-00-00 00:00:00')
            AND (cl.send_after IS NULL OR cl.send_after <= %s OR cl.send_after = '0000-00-00 00:00:00')
            ORDER BY c.priority DESC, cl.send_after ASC, cl.created_at ASC
            LIMIT %d",
            $current_time,
            $current_time,
            $batch_size
        ));

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews Queue] Found ' . count($queued_emails) . ' emails to process');
            if (!empty($queued_emails)) {
                error_log('[AdvNews Queue] First Log ID: ' . $queued_emails[0]->log_id);
                error_log('[AdvNews Queue] First Campaign ID: ' . $queued_emails[0]->campaign_id);
                error_log('[AdvNews Queue] First email subscriber_email: ' . $queued_emails[0]->subscriber_email);
            }
            if (empty($queued_emails)) {
                $total_queued = $this->wpdb->get_var("SELECT COUNT(*) FROM $table_logs WHERE status = 'queued'");
                error_log('[AdvNews Queue] Total queued emails in database: ' . $total_queued);
            }
        }

        if (empty($queued_emails)) {
            $this->update_campaign_statuses();
            $remaining = $this->wpdb->get_var("SELECT COUNT(*) FROM $table_logs WHERE status = 'queued'");
            $on_cooldown = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM $table_logs WHERE status = 'queued' AND send_after IS NOT NULL AND send_after > %s AND send_after != '0000-00-00 00:00:00'",
                $current_time
            ));

            return array(
                'sent' => 0,
                'failed' => 0,
                'remaining' => intval($remaining),
                'on_cooldown' => intval($on_cooldown),
                'blockers' => $this->get_queue_blockers($current_time),
            );
        }

        update_option('advnews_last_batch_processed_at', time(), false);

        $sent = 0;
        $failed = 0;

        foreach ($queued_emails as $email) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews Queue] Processing Log ID: ' . $email->log_id . ' to: ' . $email->subscriber_email);
            }

            // FIXED: Use log_id to check the correct row
            $current_status = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT status FROM $table_logs WHERE id = %d",
                $email->log_id
            ));

            if ($current_status !== 'queued') {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[AdvNews Queue] Skipping Log ID ' . $email->log_id . ' - status changed to: ' . $current_status);
                }
                continue;
            }

            $bypass_cooldown = $this->consume_cooldown_bypass($email->log_id);
            $cooldown_until = $bypass_cooldown ? '' : $this->subscriber_cooldown_until($email->subscriber_id, !empty($email->respect_cooldown));
            if ($cooldown_until !== '') {
                $this->wpdb->update(
                    $table_logs,
                    array('send_after' => $cooldown_until),
                    array('id' => $email->log_id)
                );
                continue;
            }

            // FIXED: Update the correct Log ID
            $this->wpdb->update(
                $table_logs,
                array('status' => 'sent', 'sent_at' => current_time('mysql')),
                array('id' => $email->log_id)
            );

            $subscriber_data = array(
                'email' => $email->subscriber_email,
                'first_name' => $email->first_name,
                'last_name' => $email->last_name,
                'organization' => $email->organization ?? ''
            );

            $campaign_class = new AdvNews_Campaign();

            // Process merge tags for BOTH content and subject
            $content = $campaign_class->process_merge_tags($email->content, $subscriber_data);
            $content = $campaign_class->prepare_email_content($content);
            $subject = $campaign_class->process_merge_tags($email->subject, $subscriber_data);

            // From address fallback chain
            $from_email = !empty($email->from_email) ? $email->from_email : get_option('advnews_smtp_from_email');
            if (empty($from_email)) {
                $from_email = get_option('advnews_smtp_username');
            }
            if (empty($from_email)) {
                $from_email = get_option('admin_email');
            }
            $from_name = !empty($email->from_name) ? $email->from_name : get_option('advnews_smtp_from_name', get_bloginfo('name'));
            $reply_to = !empty($email->reply_to) ? $email->reply_to : get_option('advnews_reply_to', 'contact@science180.com');
            if (empty($reply_to) || !is_email($reply_to)) {
                $reply_to = 'contact@science180.com';
            }

            $headers = array(
                'From: ' . $from_name . ' <' . $from_email . '>',
                'Reply-To: ' . $reply_to,
                'Content-Type: text/html; charset=UTF-8'
            );

            // FIXED: Pass log_id correctly to tracking functions
            if ($email->track_opens) {
                $tracking_pixel = $this->add_tracking_pixel($email->log_id, $email->campaign_id, $email->subscriber_id);
                if (stripos($content, '</body>') !== false) {
                    $content = preg_replace('/<\/body>/i', $tracking_pixel . '</body>', $content, 1);
                } else {
                    $content .= $tracking_pixel;
                }
            }

            if ($email->track_clicks) {
                $content = $this->replace_tracking_links($content, $email->campaign_id, $email->log_id);
            }

            // Send email
            $result = wp_mail($email->subscriber_email, $subject, $content, $headers); // <-- USE $subject HERE

            if ($result) {
                $this->wpdb->update(
                    $table_logs,
                    array('status' => 'delivered', 'delivered_at' => current_time('mysql')),
                    array('id' => $email->log_id)
                );
                $this->wpdb->update(
                    $table_subscribers,
                    array('last_email_sent' => current_time('mysql')),
                    array('id' => $email->subscriber_id)
                );
                $sent++;
            } else {
                $retry_count = ($email->retry_count ?? 0) + 1;
                $max_retries = get_option('advnews_bounce_attempts', 3);

                if ($retry_count >= $max_retries) {
                    $this->wpdb->update(
                        $table_logs,
                        array(
                            'status' => 'failed',
                            'retry_count' => $retry_count,
                            'bounce_message' => 'Max retry attempts reached'
                        ),
                        array('id' => $email->log_id)
                    );
                    if (get_option('advnews_auto_clean_bounced', true)) {
                        $this->wpdb->update(
                            $table_subscribers,
                            array('status' => 'bounced'),
                            array('id' => $email->subscriber_id)
                        );
                    }
                } else {
                    $this->wpdb->update(
                        $table_logs,
                        array(
                            'status' => 'queued',
                            'retry_count' => $retry_count,
                            'sent_at' => null
                        ),
                        array('id' => $email->log_id)
                    );
                }
                $failed++;
            }
            usleep(100000); // 100ms delay between emails
        }

        $this->update_campaign_statuses();

        $remaining = $this->wpdb->get_var("SELECT COUNT(*) FROM $table_logs WHERE status = 'queued'");
        $on_cooldown = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $table_logs WHERE status = 'queued' AND send_after IS NOT NULL AND send_after > %s AND send_after != '0000-00-00 00:00:00'",
            $current_time
        ));

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews Queue] Final result - Sent: ' . $sent . ', Failed: ' . $failed . ', Remaining: ' . $remaining);
        }

        return array(
            'sent' => $sent,
            'failed' => $failed,
            'remaining' => $remaining,
            'on_cooldown' => $on_cooldown,
            'blockers' => $this->get_queue_blockers($current_time),
        );
    }

    /**
     * Recheck cooldown immediately before sending. This prevents overlapping
     * campaigns from sending to the same subscriber before the first send has
     * updated their last_email_sent timestamp.
     */
    private function subscriber_cooldown_until($subscriber_id, $respect_cooldown)
    {
        if (!$respect_cooldown) {
            return '';
        }

        $cooldown_days = max(0, absint(get_option('advnews_cooldown_days', 5)));
        if ($cooldown_days === 0) {
            return '';
        }

        $table_subscribers = $this->wpdb->prefix . $this->table_prefix . 'subscribers';
        $last_sent = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT last_email_sent FROM {$table_subscribers} WHERE id = %d",
            $subscriber_id
        ));
        if (empty($last_sent)) {
            return '';
        }

        $last_sent_timestamp = strtotime(get_gmt_from_date($last_sent));
        $send_after_timestamp = $last_sent_timestamp + ($cooldown_days * DAY_IN_SECONDS);
        if (!$last_sent_timestamp || $send_after_timestamp <= current_time('timestamp', true)) {
            return '';
        }

        return gmdate('Y-m-d H:i:s', $send_after_timestamp);
    }

    /**
     * Clear cooldown delays for queued emails
     */
    public function clear_cooldown_delays($campaign_id = null)
    {
        $table_logs = $this->wpdb->prefix . $this->table_prefix . 'campaign_logs';
        $table_campaigns = $this->wpdb->prefix . $this->table_prefix . 'campaigns';

        $where = "status = 'queued' AND send_after IS NOT NULL AND send_after > %s";
        $params = array(current_time('mysql', true));

        if ($campaign_id) {
            $where .= " AND campaign_id = %d";
            $params[] = $campaign_id;
        }

        $query = "SELECT id FROM $table_logs WHERE $where";
        $log_ids = $this->wpdb->get_col($this->wpdb->prepare($query, $params));
        $result = $this->wpdb->query($this->wpdb->prepare(
            "UPDATE $table_logs SET send_after = NULL WHERE $where",
            $params
        ));

        if ($result > 0) {
            $this->add_cooldown_bypasses($log_ids);
        }

        if ($result > 0 && $campaign_id) {
            $this->wpdb->update(
                $table_campaigns,
                array('status' => 'sending'),
                array('id' => $campaign_id, 'status' => 'scheduled')
            );
        }

        return $result !== false ? $result : 0;
    }

    /**
     * Store explicit one-time cooldown overrides for queue records selected by
     * the administrator. They are consumed immediately before an email sends.
     */
    private function add_cooldown_bypasses($log_ids)
    {
        $log_ids = array_filter(array_map('absint', (array) $log_ids));
        if (empty($log_ids)) {
            return;
        }

        $option = get_option('advnews_cooldown_bypasses', array());
        $option = is_array($option) ? $option : array();
        $now = time();
        foreach ($option as $stored_id => $stored_expiry) {
            if (absint($stored_expiry) < $now) {
                unset($option[$stored_id]);
            }
        }
        $expires_at = $now + (30 * DAY_IN_SECONDS);
        foreach ($log_ids as $log_id) {
            $option[(string) $log_id] = $expires_at;
        }
        update_option('advnews_cooldown_bypasses', $option, false);
    }

    private function consume_cooldown_bypass($log_id)
    {
        $option = get_option('advnews_cooldown_bypasses', array());
        if (!is_array($option)) {
            return false;
        }

        $key = (string) absint($log_id);
        if (!isset($option[$key])) {
            return false;
        }

        $bypass = absint($option[$key]) >= time();
        unset($option[$key]);
        update_option('advnews_cooldown_bypasses', $option, false);

        return $bypass;
    }
    /**
     * Add tracking pixel
     */
    private function add_tracking_pixel($log_id, $campaign_id, $subscriber_id)
    {
        $tracking_url = add_query_arg(array(
            'action' => 'track_open',
            'log_id' => $log_id,
            'campaign_id' => $campaign_id,
            'subscriber_id' => $subscriber_id,
            'token' => AdvNews_Security::generate_hash($log_id . $campaign_id . $subscriber_id)
        ), home_url('advnews-track'));
        return '<img src="' . esc_url($tracking_url) . '" width="1" height="1" alt="" style="display:none" />';
    }

    /**
     * Replace links with tracking links
     */
    private function replace_tracking_links($content, $campaign_id, $log_id)
    {
        preg_match_all('/href=["\']([^"\']+)["\']/', $content, $matches);
        if (empty($matches[1])) return $content;

        $links = array_unique($matches[1]);
        foreach ($links as $link) {
            if (strpos($link, 'mailto:') === 0 || strpos($link, '#') === 0) continue;
            $tracking_link = $this->create_tracking_link($link, $campaign_id, $log_id);
            $content = str_replace('href="' . $link . '"', 'href="' . $tracking_link . '"', $content);
            $content = str_replace("href='" . $link . "'", "href='" . $tracking_link . "'", $content);
        }
        return $content;
    }

    /**
     * Create tracking link
     */
    private function create_tracking_link($original_url, $campaign_id, $log_id)
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'links';

        $existing = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table_name WHERE campaign_id = %d AND original_url = %s",
            $campaign_id,
            $original_url
        ));

        if ($existing) {
            $hash = $existing->tracking_hash;
        } else {
            $hash = wp_generate_password(32, false);
            $this->wpdb->insert($table_name, array(
                'campaign_id' => $campaign_id,
                'original_url' => $original_url,
                'tracking_hash' => $hash
            ));
        }

        return add_query_arg(array(
            'action' => 'track_click',
            'hash' => $hash,
            'log_id' => $log_id,
            'campaign_id' => $campaign_id
        ), home_url('advnews-track'));
    }

    /**
     * Update campaign statuses
     */
    private function update_campaign_statuses()
    {
        $table_campaigns = $this->wpdb->prefix . $this->table_prefix . 'campaigns';
        $table_logs = $this->wpdb->prefix . $this->table_prefix . 'campaign_logs';

        $campaigns = $this->wpdb->get_results(
            "SELECT c.id, c.total_recipients, c.status
            FROM $table_campaigns c
            WHERE c.status = 'sending'"
        );

        foreach ($campaigns as $campaign) {
            $pending_count = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM $table_logs
                WHERE campaign_id = %d
                AND status IN ('queued', 'sent')",
                $campaign->id
            ));

            if ($pending_count == 0) {
                $final_states = "'delivered', 'opened', 'clicked', 'bounced', 'unsubscribed', 'failed'";

                $final_count = $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_logs
                    WHERE campaign_id = %d AND status IN ($final_states)",
                    $campaign->id
                ));

                $total_logs = $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_logs WHERE campaign_id = %d",
                    $campaign->id
                ));

                $campaign_class = new AdvNews_Campaign();

                if ($total_logs > 0 && $final_count == $total_logs) {
                    $campaign_class->update_campaign_stats($campaign->id);
                    $this->wpdb->update(
                        $table_campaigns,
                        array('status' => 'sent'),
                        array('id' => $campaign->id)
                    );
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[AdvNews Queue] Campaign ' . $campaign->id . ' marked as sent - all emails processed');
                    }
                }
            }
        }
    }

    /**
     * Get queue status with cooldown info
     */
    public function get_queue_status()
    {
        $table_logs = $this->wpdb->prefix . $this->table_prefix . 'campaign_logs';
        $table_campaigns = $this->wpdb->prefix . $this->table_prefix . 'campaigns';
        $current_time = current_time('mysql', true);

        $status = array(
            'queued' => 0, 'sending' => 0, 'sent' => 0, 'delivered' => 0,
            'opened' => 0, 'clicked' => 0, 'bounced' => 0, 'failed' => 0,
            'on_cooldown' => 0, 'ready' => 0, 'waiting_schedule' => 0,
            'paused_campaign' => 0, 'inactive_campaign' => 0,
            'missing_campaign' => 0, 'missing_recipient' => 0
        );

        $counts = $this->wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM $table_logs GROUP BY status"
        );
        foreach ($counts as $count) {
            $status[$count->status] = intval($count->count);
        }

        $status = array_merge($status, $this->get_queue_blockers($current_time));

        $active_campaigns = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM $table_campaigns WHERE status IN ('scheduled', 'sending', 'paused')"
        );
        $status['active_campaigns'] = intval($active_campaigns);

        return $status;
    }

    /**
     * Explain why queued records are not currently eligible to send.
     */
    private function get_queue_blockers($current_time)
    {
        $table_logs = $this->wpdb->prefix . $this->table_prefix . 'campaign_logs';
        $table_campaigns = $this->wpdb->prefix . $this->table_prefix . 'campaigns';
        $table_subscribers = $this->wpdb->prefix . $this->table_prefix . 'subscribers';
        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT
                SUM(CASE WHEN c.id IS NULL THEN 1 ELSE 0 END) AS missing_campaign,
                SUM(CASE WHEN c.id IS NOT NULL AND s.id IS NULL THEN 1 ELSE 0 END) AS missing_recipient,
                SUM(CASE WHEN c.status = 'paused' THEN 1 ELSE 0 END) AS paused_campaign,
                SUM(CASE WHEN c.id IS NOT NULL AND s.id IS NOT NULL AND c.status NOT IN ('scheduled', 'sending', 'sent', 'paused') THEN 1 ELSE 0 END) AS inactive_campaign,
                SUM(CASE WHEN c.status IN ('scheduled', 'sending', 'sent') AND c.scheduled_for IS NOT NULL AND c.scheduled_for != '0000-00-00 00:00:00' AND c.scheduled_for > %s THEN 1 ELSE 0 END) AS waiting_schedule,
                SUM(CASE WHEN c.status IN ('scheduled', 'sending', 'sent') AND (c.scheduled_for IS NULL OR c.scheduled_for <= %s OR c.scheduled_for = '0000-00-00 00:00:00') AND cl.send_after IS NOT NULL AND cl.send_after != '0000-00-00 00:00:00' AND cl.send_after > %s THEN 1 ELSE 0 END) AS on_cooldown,
                SUM(CASE WHEN c.status IN ('scheduled', 'sending', 'sent') AND s.id IS NOT NULL AND (c.scheduled_for IS NULL OR c.scheduled_for <= %s OR c.scheduled_for = '0000-00-00 00:00:00') AND (cl.send_after IS NULL OR cl.send_after <= %s OR cl.send_after = '0000-00-00 00:00:00') THEN 1 ELSE 0 END) AS ready
            FROM $table_logs cl
            LEFT JOIN $table_campaigns c ON cl.campaign_id = c.id
            LEFT JOIN $table_subscribers s ON cl.subscriber_id = s.id
            WHERE cl.status = 'queued'",
            $current_time,
            $current_time,
            $current_time,
            $current_time,
            $current_time
        ));

        $keys = array('ready', 'on_cooldown', 'waiting_schedule', 'paused_campaign', 'inactive_campaign', 'missing_campaign', 'missing_recipient');
        $result = array();
        foreach ($keys as $key) {
            $result[$key] = $row ? intval($row->$key) : 0;
        }

        return $result;
    }

    /**
    * Clear stuck emails - UPDATED: More aggressive & handles status mismatches
    */
    public function clear_stuck_emails()
    {
        $table_logs = $this->wpdb->prefix . $this->table_prefix . 'campaign_logs';
        $table_campaigns = $this->wpdb->prefix . $this->table_prefix . 'campaigns';
        $table_subscribers = $this->wpdb->prefix . $this->table_prefix . 'subscribers';
        $thirty_mins_ago = date('Y-m-d H:i:s', strtotime('-30 minutes'));
        $one_hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $cleared = 0;

        // 1. Clear emails stuck in 'sent' status for >1 hour (never marked delivered/failed)
        $stuck_sent = $this->wpdb->query($this->wpdb->prepare(
            "UPDATE $table_logs SET status = 'failed', retry_count = retry_count + 1
            WHERE status = 'sent' AND sent_at < %s",
            $one_hour_ago
        ));
        $cleared += $stuck_sent;

        // 2. Only fail genuinely orphaned queue records. Valid scheduled, paused,
        // batch-limited, and cooldown-limited emails must remain queued.
        $orphaned_queued = $this->wpdb->query($this->wpdb->prepare(
            "UPDATE $table_logs cl
            LEFT JOIN $table_campaigns c ON cl.campaign_id = c.id
            LEFT JOIN $table_subscribers s ON cl.subscriber_id = s.id
            SET cl.status = 'failed', cl.retry_count = cl.retry_count + 1,
                cl.bounce_message = %s
            WHERE cl.status = 'queued'
            AND cl.created_at < %s
            AND (c.id IS NULL OR s.id IS NULL)",
            __('Queue record is missing its campaign or subscriber', 'advnews-manager'),
            $thirty_mins_ago
        ));
        $cleared += $orphaned_queued;

        // 3. Clear 'sent' emails from campaigns that are already marked closed
        $mismatched = $this->wpdb->query($this->wpdb->prepare(
            "UPDATE $table_logs cl
            INNER JOIN $table_campaigns c ON cl.campaign_id = c.id
            SET cl.status = 'failed'
            WHERE cl.status = 'sent'
            AND c.status IN ('sent', 'draft', 'paused')
            AND cl.created_at < %s",
            $one_hour_ago
        ));
        $cleared += $mismatched;

        return $cleared;
    }

    /**
     * Retry failed emails
     */
    public function retry_failed_emails($campaign_id = null)
    {
        $table_logs = $this->wpdb->prefix . $this->table_prefix . 'campaign_logs';
        $table_campaigns = $this->wpdb->prefix . $this->table_prefix . 'campaigns';

        $where = "status = 'failed' AND (bounce_message IS NULL OR bounce_message != %s)";
        $params = array(__('Campaign ended by admin', 'advnews-manager'));

        if ($campaign_id) {
            $where .= " AND campaign_id = %d";
            $params[] = $campaign_id;
        }

        $result = $this->wpdb->query($this->wpdb->prepare(
            "UPDATE $table_logs
            SET status = 'queued', sent_at = NULL, send_after = NULL
            WHERE $where",
            $params
        ));

        if ($result > 0) {
            if ($campaign_id) {
                $affected_campaigns = array($campaign_id);
            } else {
                $affected_campaigns = $this->wpdb->get_col(
                    "SELECT DISTINCT campaign_id FROM $table_logs WHERE status = 'queued'"
                );
            }

            foreach ($affected_campaigns as $camp_id) {
                $this->wpdb->update(
                    $table_campaigns,
                    array('status' => 'sending'),
                    array('id' => $camp_id)
                );
            }

            if (!wp_next_scheduled('advnews_process_queue')) {
                wp_schedule_single_event(time(), 'advnews_process_queue');
            }
        }

        return $result !== false;
    }

    /**
     * Pause sending
     */
    public function pause_sending($campaign_id = null)
    {
        $table_campaigns = $this->wpdb->prefix . $this->table_prefix . 'campaigns';
        $where = array("status IN ('scheduled', 'sending')");
        if ($campaign_id) {
            $where[] = $this->wpdb->prepare("id = %d", $campaign_id);
        }
        $result = $this->wpdb->query(
            "UPDATE $table_campaigns SET status = 'paused' WHERE " . implode(' AND ', $where)
        );
        return $result !== false;
    }

    /**
     * Resume sending
     */
    public function resume_sending($campaign_id = null)
    {
        $table_campaigns = $this->wpdb->prefix . $this->table_prefix . 'campaigns';
        $where = array("status = 'paused'");
        if ($campaign_id) {
            $where[] = $this->wpdb->prepare("id = %d", $campaign_id);
        }
        $result = $this->wpdb->query(
            "UPDATE $table_campaigns SET status = 'sending' WHERE " . implode(' AND ', $where)
        );
        return $result !== false;
    }
}
