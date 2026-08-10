<?php
// cron/process-queue.php
if (!defined('ABSPATH')) exit;
/**
* AdvNews Queue Processor Class
* Processes the email queue for sending campaigns
*/
class AdvNews_Queue_Processor {
	private $wpdb;
	private $table_prefix;
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table_prefix = ADVNEWS_TABLE_PREFIX;
	}
	/**
	* Execute queue processing
	*
	* @return array Result with success status and data
	*/
	public function execute() {
		try {
			// Check if queue is paused
			if (get_option('advnews_queue_paused')) {
				return array(
					'success' => false,
					'message' => __('Queue is currently paused.', 'advnews-manager'),
					'data' => array(
						'sent' => 0,
						'failed' => 0,
						'paused' => true
					)
				);
			}
			// Get queue instance
			$queue = new AdvNews_Queue();

			// Debug logging
			if (defined('WP_DEBUG') && WP_DEBUG) {
				$total_queued = $this->wpdb->get_var(
					"SELECT COUNT(*) FROM {$this->wpdb->prefix}{$this->table_prefix}campaign_logs WHERE status = 'queued'"
				);
				error_log('[AdvNews Queue Processor] Total queued emails before processing: ' . $total_queued);
			}

			// Get batch size from settings
			$batch_size = get_option('advnews_emails_per_batch', 50);

			// Check daily limit
			$max_per_day = get_option('advnews_max_emails_per_day', 0);
			if ($max_per_day > 0) {
				$sent_today = $this->get_emails_sent_today();
				if ($sent_today >= $max_per_day) {
					return array(
						'success' => false,
						'message' => sprintf(
							__('Daily limit reached: %d/%d emails sent today.', 'advnews-manager'),
							$sent_today,
							$max_per_day
						),
						'data' => array(
							'sent' => 0,
							'failed' => 0,
							'daily_limit_reached' => true
						)
					);
				}
			}
			// Check pause schedule
			if ($this->is_pause_schedule_active()) {
				return array(
					'success' => false,
					'message' => __('Sending is paused according to schedule.', 'advnews-manager'),
					'data' => array(
						'sent' => 0,
						'failed' => 0,
						'schedule_paused' => true
					)
				);
			}
			// Process the queue
			$result = $queue->process_queue($batch_size);

			// Debug logging
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log(sprintf(
					'[AdvNews Queue Processor] Queue processed: %d sent, %d failed, %d remaining',
					$result['sent'],
					$result['failed'],
					$result['remaining'] ?? 0
				));
			}

			return array(
				'success' => true,
				'message' => sprintf(
					__('Processed queue: %d emails sent, %d failed.', 'advnews-manager'),
					$result['sent'],
					$result['failed']
				),
				'data' => array(
					'sent' => $result['sent'],
					'failed' => $result['failed'],
					'remaining' => $result['remaining'] ?? 0
				)
			);
		} catch (Exception $e) {
			if (get_option('advnews_enable_debug_log')) {
				error_log('[AdvNews] Queue processing error: ' . $e->getMessage());
			}
			return array(
				'success' => false,
				'message' => __('Error processing queue: ', 'advnews-manager') . $e->getMessage(),
				'data' => array(
					'sent' => 0,
					'failed' => 0,
					'error' => $e->getMessage()
				)
			);
		}
	}
	/**
	* Get emails sent today
	*
	* @return int
	*/
	private function get_emails_sent_today() {
		$table_logs = $this->wpdb->prefix . $this->table_prefix . 'campaign_logs';
		$today = date('Y-m-d');
		return (int) $this->wpdb->get_var($this->wpdb->prepare(
			"SELECT COUNT(*) FROM $table_logs
			WHERE DATE(sent_at) = %s
			AND status IN ('sent', 'delivered')",
			$today
		));
	}
	/**
	* Check if pause schedule is active
	*
	* @return bool
	*/
	private function is_pause_schedule_active() {
		$pause_start = get_option('advnews_pause_start_hour');
		$pause_end = get_option('advnews_pause_end_hour');
		if (empty($pause_start) || empty($pause_end)) {
			return false;
		}
		$current_hour = current_time('H');
		$current_minute = current_time('i');
		$current_time = intval($current_hour . $current_minute);
		$start_time = intval(str_replace(':', '', $pause_start));
		$end_time = intval(str_replace(':', '', $pause_end));
		if ($start_time < $end_time) {
			// Same day range (e.g., 22:00 to 06:00)
			return ($current_time >= $start_time || $current_time < $end_time);
		} else {
			// Overnight range (e.g., 22:00 to 06:00)
			return ($current_time >= $start_time || $current_time < $end_time);
		}
	}
}
// Execute if called directly (for cron)
if (defined('DOING_CRON') && DOING_CRON) {
	$processor = new AdvNews_Queue_Processor();
	$result = $processor->execute();
	if (get_option('advnews_enable_debug_log')) {
		error_log('[AdvNews Cron] Queue processor result: ' . print_r($result, true));
	}
}
