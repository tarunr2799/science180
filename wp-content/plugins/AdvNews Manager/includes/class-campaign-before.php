<?php
// File: includes/class-campaign.php
if (!defined('ABSPATH')) {
    exit;
}
class AdvNews_Campaign
{
    private $wpdb;
    private $table_prefix;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = ADVNEWS_TABLE_PREFIX;
    }

    /**
     * Create new campaign
     */
    public function create_campaign($data)
    {
        // 1. Extract category_ids immediately so they aren't passed to wpdb->insert
        $category_ids = isset($data['category_ids']) ? $data['category_ids'] : array();
        unset($data['category_ids']);

        // 2. Extract content to sanitize it separately with email-safe rules
        $content = isset($data['content']) ? $data['content'] : '';
        unset($data['content']);

        // 3. Sanitize the remaining data (name, subject, etc.)
        $data = AdvNews_Security::sanitize_array($data);

        // 4. Sanitize content using the EMAIL-SAFE sanitizer (preserves align, bgcolor, etc.)
        // We access the Admin class method or replicate the logic.
        // Since sanitize_email_html is private in Admin, we'll use the allowed_html array directly here
        // or call the global function if available. For simplicity, we'll use wp_kses with the same allowed tags.
        $allowed_html = array(
            'a' => array('href' => array(), 'title' => array(), 'target' => array(), 'rel' => array(), 'style' => array(), 'class' => array()),
            'br' => array(),
            'em' => array(),
            'strong' => array(),
            'p' => array('style' => array(), 'class' => array(), 'align' => array()),
            'div' => array('class' => array(), 'style' => array(), 'align' => array()),
            'span' => array('class' => array(), 'style' => array()),
            'h1' => array('style' => array(), 'align' => array()),
            'h2' => array('style' => array(), 'align' => array()),
            'h3' => array('style' => array(), 'align' => array()),
            'h4' => array('style' => array(), 'align' => array()),
            'h5' => array('style' => array(), 'align' => array()),
            'h6' => array('style' => array(), 'align' => array()),
            'ul' => array('style' => array()),
            'ol' => array('style' => array()),
            'li' => array('style' => array()),
            'table' => array('border' => array(), 'cellpadding' => array(), 'cellspacing' => array(), 'style' => array(), 'width' => array(), 'height' => array(), 'align' => array(), 'bgcolor' => array(), 'class' => array()),
            'tr' => array('style' => array(), 'bgcolor' => array(), 'align' => array(), 'valign' => array(), 'height' => array()),
            'td' => array('colspan' => array(), 'rowspan' => array(), 'style' => array(), 'width' => array(), 'height' => array(), 'align' => array(), 'valign' => array(), 'bgcolor' => array(), 'class' => array()),
            'th' => array('style' => array(), 'bgcolor' => array(), 'align' => array(), 'colspan' => array(), 'rowspan' => array()),
            'img' => array('src' => array(), 'alt' => array(), 'width' => array(), 'height' => array(), 'style' => array(), 'class' => array(), 'border' => array(), 'align' => array()),
            'b' => array('style' => array()),
            'i' => array('style' => array()),
            'u' => array('style' => array()),
            'strike' => array(),
            'hr' => array('style' => array(), 'width' => array(), 'size' => array()),
            'blockquote' => array('style' => array()),
            'code' => array('style' => array()),
            'pre' => array('style' => array()),
            'font' => array('color' => array(), 'size' => array(), 'face' => array()),
            'center' => array()
        );
        $sanitized_content = wp_kses($content, $allowed_html);

        if (empty($data['name']) || empty($data['subject']) || empty($sanitized_content)) {
            return new WP_Error('missing_fields', __('Name, subject, and content are required.', 'advnews-manager'));
        }

        // Handle Category IDs validation
        if (!is_array($category_ids)) {
            $category_ids = array($category_ids);
        }

        // Filter out empty values
        $category_ids = array_filter($category_ids, function($id) { return !empty($id); });

        if (empty($category_ids)) {
            return new WP_Error('missing_category', __('At least one category must be selected.', 'advnews-manager'));
        }

        // Calculate total unique recipients across ALL selected categories
        $subscriber_class = new AdvNews_Subscriber();
        $total_recipients = 0;
        $processed_subscribers = array();

        foreach ($category_ids as $cat_id) {
            $subscribers = $subscriber_class->get_subscribers_by_category(intval($cat_id), 'active');
            foreach ($subscribers as $sub) {
                if (!in_array($sub->id, $processed_subscribers)) {
                    $processed_subscribers[] = $sub->id;
                    $total_recipients++;
                }
            }
        }

        // Prepare data for the MAIN campaigns table
        $campaign_data = array(
            'name' => $data['name'],
            'subject' => $data['subject'],
            'category_id' => intval($category_ids[0]), // Legacy column stores the first ID
            'content' => $sanitized_content, // Use email-safe sanitized content
            'template_id' => isset($data['template_id']) ? intval($data['template_id']) : null,
            'from_name' => isset($data['from_name']) ? $data['from_name'] : null,
            'from_email' => isset($data['from_email']) ? $data['from_email'] : null,
            'reply_to' => isset($data['reply_to']) ? $data['reply_to'] : null,
            'status' => isset($data['status']) ? $data['status'] : 'draft',
            'priority' => isset($data['priority']) ? $data['priority'] : 'normal',
            'track_opens' => isset($data['track_opens']) ? intval($data['track_opens']) : 1,
            'track_clicks' => isset($data['track_clicks']) ? intval($data['track_clicks']) : 1,
            'respect_cooldown' => isset($data['respect_cooldown']) ? intval($data['respect_cooldown']) : 1,
            'total_recipients' => $total_recipients
        );

        if (!empty($data['scheduled_for'])) {
            $campaign_data['scheduled_for'] = $data['scheduled_for'];
            $campaign_data['status'] = 'scheduled';
        }

        // Insert into the MAIN campaigns table
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'campaigns';
        $result = $this->wpdb->insert($table_name, $campaign_data);

        if (!$result) {
            return new WP_Error('db_error', __('Failed to create campaign.', 'advnews-manager'));
        }

        $campaign_id = $this->wpdb->insert_id;

        // Save categories to the JUNCTION table separately
        $this->save_campaign_categories($campaign_id, $category_ids);

        return $campaign_id;
    }

    /**
     * Update campaign
     */
    public function update_campaign($id, $data)
    {
        // 1. Extract category_ids immediately so they aren't passed to wpdb->update
        $category_ids = isset($data['category_ids']) ? $data['category_ids'] : array();
        unset($data['category_ids']);

        // 2. Extract content to sanitize it separately with email-safe rules
        $content = isset($data['content']) ? $data['content'] : '';
        unset($data['content']);

        // 3. Sanitize the remaining data
        $data = AdvNews_Security::sanitize_array($data);

        // 4. Sanitize content using the EMAIL-SAFE sanitizer (preserves align, bgcolor, etc.)
        $allowed_html = array(
            'a' => array('href' => array(), 'title' => array(), 'target' => array(), 'rel' => array(), 'style' => array(), 'class' => array()),
            'br' => array(),
            'em' => array(),
            'strong' => array(),
            'p' => array('style' => array(), 'class' => array(), 'align' => array()),
            'div' => array('class' => array(), 'style' => array(), 'align' => array()),
            'span' => array('class' => array(), 'style' => array()),
            'h1' => array('style' => array(), 'align' => array()),
            'h2' => array('style' => array(), 'align' => array()),
            'h3' => array('style' => array(), 'align' => array()),
            'h4' => array('style' => array(), 'align' => array()),
            'h5' => array('style' => array(), 'align' => array()),
            'h6' => array('style' => array(), 'align' => array()),
            'ul' => array('style' => array()),
            'ol' => array('style' => array()),
            'li' => array('style' => array()),
            'table' => array('border' => array(), 'cellpadding' => array(), 'cellspacing' => array(), 'style' => array(), 'width' => array(), 'height' => array(), 'align' => array(), 'bgcolor' => array(), 'class' => array()),
            'tr' => array('style' => array(), 'bgcolor' => array(), 'align' => array(), 'valign' => array(), 'height' => array()),
            'td' => array('colspan' => array(), 'rowspan' => array(), 'style' => array(), 'width' => array(), 'height' => array(), 'align' => array(), 'valign' => array(), 'bgcolor' => array(), 'class' => array()),
            'th' => array('style' => array(), 'bgcolor' => array(), 'align' => array(), 'colspan' => array(), 'rowspan' => array()),
            'img' => array('src' => array(), 'alt' => array(), 'width' => array(), 'height' => array(), 'style' => array(), 'class' => array(), 'border' => array(), 'align' => array()),
            'b' => array('style' => array()),
            'i' => array('style' => array()),
            'u' => array('style' => array()),
            'strike' => array(),
            'hr' => array('style' => array(), 'width' => array(), 'size' => array()),
            'blockquote' => array('style' => array()),
            'code' => array('style' => array()),
            'pre' => array('style' => array()),
            'font' => array('color' => array(), 'size' => array(), 'face' => array()),
            'center' => array()
        );
        $sanitized_content = wp_kses($content, $allowed_html);

        $table_name = $this->wpdb->prefix . $this->table_prefix . 'campaigns';

        // Unset fields that shouldn't be updated directly
        unset($data['id'], $data['created_at'], $data['sent_at']);

        $campaign = $this->get_campaign($id);
        if ($campaign && $campaign->status === 'sent') {
            return new WP_Error('campaign_sent', __('Cannot update a sent campaign.', 'advnews-manager'));
        }

        // If categories are being updated, recalculate recipients
        if (!empty($category_ids)) {
            if (!is_array($category_ids)) {
                $category_ids = array($category_ids);
            }

            $subscriber_class = new AdvNews_Subscriber();
            $total_recipients = 0;
            $processed_subscribers = array();

            foreach ($category_ids as $cat_id) {
                $subscribers = $subscriber_class->get_subscribers_by_category(intval($cat_id), 'active');
                foreach ($subscribers as $sub) {
                    if (!in_array($sub->id, $processed_subscribers)) {
                        $processed_subscribers[] = $sub->id;
                        $total_recipients++;
                    }
                }
            }

            // Add calculated recipients and legacy category_id to the data array for the main table update
            $data['total_recipients'] = $total_recipients;
            $data['category_id'] = intval($category_ids[0]);

            // Save to junction table
            $this->save_campaign_categories($id, $category_ids);
        }

        // Add the email-safe sanitized content back to the data array
        $data['content'] = $sanitized_content;

        if (empty($data)) {
            return true;
        }

        // Update the MAIN campaigns table
        $result = $this->wpdb->update($table_name, $data, array('id' => $id));
        return $result !== false;
    }

    /**
     * Helper to save categories to junction table
     */
    private function save_campaign_categories($campaign_id, $category_ids) {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'campaign_categories';

        // Delete existing relationships
        $this->wpdb->delete($table_name, array('campaign_id' => $campaign_id));

        // Insert new relationships
        foreach ($category_ids as $cat_id) {
            if (!empty($cat_id)) {
                $this->wpdb->insert($table_name, array(
                    'campaign_id' => $campaign_id,
                    'category_id' => intval($cat_id)
                ));
            }
        }
    }

    /**
     * Get campaign by ID
     */
    public function get_campaign($id)
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'campaigns';
        $campaign = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT c.*, cat.name as category_name
            FROM $table_name c
            LEFT JOIN {$this->wpdb->prefix}{$this->table_prefix}categories cat ON c.category_id = cat.id
            WHERE c.id = %d",
            $id
        ));

        if ($campaign) {
            // Fetch all category IDs for this campaign from junction table
            $rel_table = $this->wpdb->prefix . $this->table_prefix . 'campaign_categories';
            $category_ids = $this->wpdb->get_col($this->wpdb->prepare(
                "SELECT category_id FROM $rel_table WHERE campaign_id = %d",
                $id
            ));

            // Add category_ids array to the campaign object for the editor to use
            $campaign->category_ids = $category_ids;
        }

        return $campaign;
    }

    /**
     * Get all campaigns with pagination
     */
    public function get_campaigns($args = array())
    {
        $defaults = array(
            'status' => '',
            'category_id' => null,
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0
        );

        $args = wp_parse_args($args, $defaults);

        $table_campaigns = $this->wpdb->prefix . $this->table_prefix . 'campaigns';
        $table_categories = $this->wpdb->prefix . $this->table_prefix . 'categories';
        $table_campaign_categories = $this->wpdb->prefix . $this->table_prefix . 'campaign_categories';

        $where = array('1=1');

        if (!empty($args['status'])) {
            $where[] = $this->wpdb->prepare("c.status = %s", $args['status']);
        }

        if (!empty($args['search'])) {
            $search = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where[] = $this->wpdb->prepare("(c.name LIKE %s OR c.subject LIKE %s)", $search, $search);
        }

        $where_clause = 'WHERE ' . implode(' AND ', $where);

        $orderby = esc_sql($args['orderby']);
        $order = $args['order'] === 'DESC' ? 'DESC' : 'ASC';
        $order_clause = "ORDER BY c.$orderby $order";

        // Modified query to include all categories using GROUP_CONCAT
        $query = "SELECT c.*,
                GROUP_CONCAT(DISTINCT cat.name ORDER BY cat.name SEPARATOR ', ') as category_names,
                GROUP_CONCAT(DISTINCT cat.id ORDER BY cat.name SEPARATOR ',') as category_ids
                FROM $table_campaigns c
                LEFT JOIN $table_campaign_categories cc ON c.id = cc.campaign_id
                LEFT JOIN $table_categories cat ON cc.category_id = cat.id
                $where_clause
                GROUP BY c.id
                $order_clause";

        if ($args['limit'] > 0) {
            $query .= $this->wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }

        return $this->wpdb->get_results($query);
    }

    /**
     * Count campaigns
     */
    public function count_campaigns($args = array())
    {
        $defaults = array('status' => '', 'category_id' => null, 'search' => '');

        $args = wp_parse_args($args, $defaults);

        $table_campaigns = $this->wpdb->prefix . $this->table_prefix . 'campaigns';
        $table_categories = $this->wpdb->prefix . $this->table_prefix . 'categories';

        $where = array('1=1');
        $join = "LEFT JOIN $table_categories cat ON c.category_id = cat.id";

        if (!empty($args['status'])) {
            $where[] = $this->wpdb->prepare("c.status = %s", $args['status']);
        }

        if (!empty($args['category_id'])) {
            $where[] = $this->wpdb->prepare("c.category_id = %d", $args['category_id']);
        }

        if (!empty($args['search'])) {
            $search = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where[] = $this->wpdb->prepare("(c.name LIKE %s OR c.subject LIKE %s OR cat.name LIKE %s)", $search, $search, $search);
        }

        $where_clause = 'WHERE ' . implode(' AND ', $where);

        return $this->wpdb->get_var("SELECT COUNT(*) FROM $table_campaigns c $join $where_clause");
    }

    /**
     * Send campaign - FIXED: Adds ALL active subscribers from ALL selected categories to queue
     */
    public function send_campaign($campaign_id)
    {
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign) {
            return new WP_Error('campaign_not_found', __('Campaign not found.', 'advnews-manager'));
        }

        if (in_array($campaign->status, array('sent', 'sending'))) {
            return new WP_Error('campaign_already_sent', __('Campaign is already sent or being sent.', 'advnews-manager'));
        }

        // Get ALL category IDs for this campaign
        $category_ids = !empty($campaign->category_ids) ? $campaign->category_ids : array($campaign->category_id);

        if (empty($category_ids)) {
             return new WP_Error('no_categories', __('No categories selected for this campaign.', 'advnews-manager'));
        }

        $subscriber_class = new AdvNews_Subscriber();
        $queue_class = new AdvNews_Queue();

        // Use a temporary array to track unique subscriber IDs to avoid duplicates
        // if a subscriber belongs to multiple selected categories
        $processed_subscribers = array();
        $queued_count = 0;

        foreach ($category_ids as $cat_id) {
            $subscribers = $subscriber_class->get_subscribers_by_category($cat_id, 'active');

            foreach ($subscribers as $subscriber) {
                // Skip if already processed for this campaign
                if (in_array($subscriber->id, $processed_subscribers)) {
                    continue;
                }

                // Attempt to add EVERY subscriber to the queue.
                // The Queue class will handle setting send_after if cooldown applies.
                $added = $queue_class->add_to_queue($campaign_id, $subscriber->id, $campaign->respect_cooldown);
                if ($added) {
                    $processed_subscribers[] = $subscriber->id;
                    $queued_count++;
                }
            }
        }

        if ($queued_count === 0) {
            return new WP_Error('no_subscribers', __('No active subscribers found for the selected categories.', 'advnews-manager'));
        }

        // Update campaign with the ACTUAL count of rows created in logs
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'campaigns';
        $this->wpdb->update(
            $table_name,
            array(
                'total_recipients' => $queued_count,
                'status' => 'sending',
                'scheduled_for' => null, // Clear schedule once sending starts
                'sent_at' => current_time('mysql')
            ),
            array('id' => $campaign_id)
        );

        $this->trigger_queue_processing();

        return array(
            'campaign_id' => $campaign_id,
            'queued' => $queued_count,
            'total_subscribers' => count($processed_subscribers)
        );
    }

    /**
     * Trigger queue processing
     */
    private function trigger_queue_processing()
    {
        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }

        // Schedule immediate processing
        if (!wp_next_scheduled('advnews_process_queue')) {
            wp_schedule_single_event(time(), 'advnews_process_queue');
        }

        // Try to trigger immediate processing via HTTP request
        if (!defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON) {
            wp_schedule_single_event(time() + 1, 'advnews_process_queue');
            $cron_url = site_url('wp-cron.php?doing_wp_cron=' . time());
            wp_remote_post($cron_url, array(
                'timeout' => 0.01,
                'blocking' => false,
                'sslverify' => false
            ));
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews] Queue processing triggered for campaign');
        }
    }

    /**
     * Schedule campaign
     */
    public function schedule_campaign($campaign_id, $datetime)
    {
        $datetime = sanitize_text_field($datetime);

        if (!strtotime($datetime)) {
            return new WP_Error('invalid_datetime', __('Invalid date/time format.', 'advnews-manager'));
        }

        $table_name = $this->wpdb->prefix . $this->table_prefix . 'campaigns';

        $result = $this->wpdb->update(
            $table_name,
            array(
                'scheduled_for' => $datetime,
                'status' => 'scheduled'
            ),
            array('id' => $campaign_id)
        );

        return $result !== false;
    }

    /**
     * Get campaign stats
     */
    public function get_campaign_stats($campaign_id)
    {
        $campaign = $this->get_campaign($campaign_id);

        if (!$campaign) {
            return null;
        }

        $table_logs = $this->wpdb->prefix . $this->table_prefix . 'campaign_logs';

        $stats = array(
            'total' => $campaign->total_recipients,
            'sent' => $campaign->sent_count,
            'delivered' => $campaign->delivered_count,
            'opened' => $campaign->open_count,
            'clicked' => $campaign->click_count,
            'bounced' => $campaign->bounce_count,
            'unsubscribed' => $campaign->unsubscribe_count,
            'open_rate' => $campaign->open_rate,
            'click_rate' => $campaign->click_rate
        );

        $status_counts = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT status, COUNT(*) as count FROM $table_logs WHERE campaign_id = %d GROUP BY status",
            $campaign_id
        ));

        foreach ($status_counts as $status_count) {
            $stats[$status_count->status] = intval($status_count->count);
        }

        return $stats;
    }

    /**
     * Get campaign recipients
     */
    public function get_campaign_recipients($campaign_id, $status = '', $limit = 100, $offset = 0)
    {
        $table_logs = $this->wpdb->prefix . $this->table_prefix . 'campaign_logs';
        $table_subscribers = $this->wpdb->prefix . $this->table_prefix . 'subscribers';

        $where = array($this->wpdb->prepare("cl.campaign_id = %d", $campaign_id));

        if (!empty($status)) {
            $where[] = $this->wpdb->prepare("cl.status = %s", $status);
        }

        $where_clause = 'WHERE ' . implode(' AND ', $where);

        $query = "SELECT cl.*, s.email, s.first_name, s.last_name, s.organization
            FROM $table_logs cl
            INNER JOIN $table_subscribers s ON cl.subscriber_id = s.id
            $where_clause
            ORDER BY cl.sent_at DESC
            LIMIT %d OFFSET %d";

        return $this->wpdb->get_results($this->wpdb->prepare($query, $limit, $offset));
    }

    /**
     * Duplicate campaign
     */
    public function duplicate_campaign($campaign_id)
    {
        $campaign = $this->get_campaign($campaign_id);

        if (!$campaign) {
            return new WP_Error('campaign_not_found', __('Campaign not found.', 'advnews-manager'));
        }

        unset($campaign->id, $campaign->category_name);

        $campaign->name .= ' - ' . __('Copy', 'advnews-manager');
        $campaign->status = 'draft';
        $campaign->scheduled_for = null;
        $campaign->sent_at = null;
        $campaign->total_recipients = 0;
        $campaign->sent_count = 0;
        $campaign->delivered_count = 0;
        $campaign->open_count = 0;
        $campaign->click_count = 0;
        $campaign->bounce_count = 0;
        $campaign->unsubscribe_count = 0;
        $campaign->open_rate = 0;
        $campaign->click_rate = 0;

        $table_name = $this->wpdb->prefix . $this->table_prefix . 'campaigns';

        $result = $this->wpdb->insert($table_name, (array) $campaign);

        if (!$result) {
            return new WP_Error('db_error', __('Failed to duplicate campaign.', 'advnews-manager'));
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Delete campaign - FIXED: Allow deletion of all campaign statuses
     */
    public function delete_campaign($campaign_id)
    {
        $campaign = $this->get_campaign($campaign_id);

        if (!$campaign) {
            return new WP_Error('campaign_not_found', __('Campaign not found.', 'advnews-manager'));
        }

        // REMOVED: Status check that prevented deletion of sent campaigns
        // This allows admins to clean up old campaigns if needed

        $table_name = $this->wpdb->prefix . $this->table_prefix . 'campaigns';

        $result = $this->wpdb->delete($table_name, array('id' => $campaign_id));

        return $result !== false;
    }

    /**
     * Process merge tags
     */
    public function process_merge_tags($content, $subscriber_data = array())
    {
        $merge_tags = array(
            '[first_name]' => $subscriber_data['first_name'] ?? '',
            '[last_name]' => $subscriber_data['last_name'] ?? '',
            '[full_name]' => trim(($subscriber_data['first_name'] ?? '') . ' ' . ($subscriber_data['last_name'] ?? '')),
            '[email]' => $subscriber_data['email'] ?? '',
            '[organization]' => $subscriber_data['organization'] ?? '',
            '[current_date]' => date_i18n(get_option('date_format')),
            '[current_time]' => date_i18n(get_option('time_format')),
            '[current_year]' => date('Y'),
            '[current_month]' => date_i18n('F'),
            '[current_day]' => date('j'),
            '[site_name]' => get_bloginfo('name'),
            '[site_url]' => home_url(),
            '[site_domain]' => parse_url(home_url(), PHP_URL_HOST),
            '[admin_email]' => get_option('admin_email'),
            '[unsubscribe_link]' => $this->get_unsubscribe_link($subscriber_data['email'] ?? ''),
            '[web_version_link]' => $this->get_web_version_link($subscriber_data['email'] ?? '')
        );

        foreach ($subscriber_data as $key => $value) {
            if (!isset($merge_tags["[$key]"])) {
                $merge_tags["[$key]"] = $value;
            }
        }

        foreach ($merge_tags as $tag => $replacement) {
            $content = str_replace($tag, $replacement, $content);
        }

        return $content;
    }

    /**
     * Get unsubscribe link
     */
    private function get_unsubscribe_link($email)
    {
        $page_id = get_option('advnews_unsubscribe_page_id');

        if (!$page_id) {
            return home_url();
        }

        $token = AdvNews_Security::generate_hash($email . 'unsubscribe');
        set_transient('advnews_unsubscribe_' . $token, $email, 7 * DAY_IN_SECONDS);

        return add_query_arg(
            array(
                'token' => $token,
                'email' => urlencode($email)
            ),
            get_permalink($page_id)
        );
    }

    /**
     * Get web version link
     */
    private function get_web_version_link($email)
    {
        $subscriber_class = new AdvNews_Subscriber();
        $subscriber = $subscriber_class->get_subscriber_by_email($email);

        if (!$subscriber) {
            return home_url();
        }

        $page_id = get_option('advnews_management_page_id');

        if (!$page_id) {
            $page_id = get_option('advnews_archive_page_id');
        }

        if (!$page_id) {
            return home_url();
        }

        $token = AdvNews_Security::generate_hash($subscriber->id . 'web_version' . time());
        set_transient('advnews_web_version_' . $token, $subscriber->id, 7 * DAY_IN_SECONDS);

        return add_query_arg(
            array(
                'token' => $token,
                'email' => urlencode($email),
                'subscriber_id' => $subscriber->id
            ),
            get_permalink($page_id)
        );
    }

    /**
     * Update campaign statistics - FIXED: Properly sets status to 'sent' when complete
     */
    public function update_campaign_stats($campaign_id)
    {
        $table_campaigns = $this->wpdb->prefix . $this->table_prefix . 'campaigns';
        $table_logs = $this->wpdb->prefix . $this->table_prefix . 'campaign_logs';

        $stats = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'opened' THEN 1 ELSE 0 END) as opened,
                SUM(CASE WHEN status = 'clicked' THEN 1 ELSE 0 END) as clicked,
                SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced,
                SUM(CASE WHEN status = 'unsubscribed' THEN 1 ELSE 0 END) as unsubscribed
            FROM $table_logs WHERE campaign_id = %d",
            $campaign_id
        ));

        if (!$stats) {
            return false;
        }

        $open_rate = $stats->delivered > 0 ? ($stats->opened / $stats->delivered) * 100 : 0;
        $click_rate = $stats->delivered > 0 ? ($stats->clicked / $stats->delivered) * 100 : 0;

        $update_data = array(
            'total_recipients' => $stats->total,
            'sent_count' => $stats->sent,
            'delivered_count' => $stats->delivered,
            'open_count' => $stats->opened,
            'click_count' => $stats->clicked,
            'bounce_count' => $stats->bounced,
            'unsubscribe_count' => $stats->unsubscribed,
            'open_rate' => round($open_rate, 2),
            'click_rate' => round($click_rate, 2),
            'status' => 'sent'  // FIXED: Always set to 'sent' when stats are updated
        );

        $campaign = $this->get_campaign($campaign_id);
        $update_data['sent_at'] = ($campaign && !empty($campaign->sent_at)) ? $campaign->sent_at : current_time('mysql');

        $this->wpdb->update($table_campaigns, $update_data, array('id' => $campaign_id));

        return true;
    }

    /**
     * Get campaigns by category
     */
    public function get_campaigns_by_category($category_id, $limit = 20)
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'campaigns';

        $campaigns = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table_name WHERE category_id = %d ORDER BY created_at DESC LIMIT %d",
            $category_id,
            $limit
        ));

        foreach ($campaigns as $campaign) {
            if (empty($campaign->created_at)) {
                $campaign->created_at = current_time('mysql');
            }
            if (empty($campaign->sent_at)) {
                $campaign->sent_at = null;
            }
            if (empty($campaign->scheduled_for)) {
                $campaign->scheduled_for = null;
            }
        }

        return $campaigns;
    }

    /**
     * Get scheduled campaigns
     */
    public function get_scheduled_campaigns()
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'campaigns';

        $campaigns = $this->wpdb->get_results(
            "SELECT * FROM $table_name
            WHERE status = 'scheduled'
            AND scheduled_for IS NOT NULL AND scheduled_for != ''
            AND scheduled_for <= '" . current_time('mysql', true) . "'
            ORDER BY scheduled_for ASC"
        );

        foreach ($campaigns as $campaign) {
            if (empty($campaign->scheduled_for)) {
                $campaign->scheduled_for = null;
            }
        }

        return $campaigns;
    }

    /**
     * Check if campaign is ready to send
     */
    public function is_campaign_ready($campaign_id)
    {
        $campaign = $this->get_campaign($campaign_id);

        if (!$campaign) {
            return false;
        }

        if ($campaign->status === 'scheduled') {
            if (!empty($campaign->scheduled_for)) {
                if (strtotime($campaign->scheduled_for) > current_time('timestamp', true)) {
                    return false;
                }
            }
        }

        if (empty($campaign->name) || empty($campaign->subject) || empty($campaign->content) || empty($campaign->category_id)) {
            return false;
        }

        return true;
    }

    /**
     * Get send history
     */
    public function get_send_history($campaign_id, $limit = 50)
    {
        $table_logs = $this->wpdb->prefix . $this->table_prefix . 'campaign_logs';
        $table_subscribers = $this->wpdb->prefix . $this->table_prefix . 'subscribers';

        $logs = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT cl.*, s.email, s.first_name, s.last_name
            FROM $table_logs cl
            INNER JOIN $table_subscribers s ON cl.subscriber_id = s.id
            WHERE cl.campaign_id = %d
            ORDER BY cl.sent_at DESC
            LIMIT %d",
            $campaign_id,
            $limit
        ));

        foreach ($logs as $log) {
            if (empty($log->sent_at)) {
                $log->sent_at = null;
            }
            if (empty($log->opened_at)) {
                $log->opened_at = null;
            }
            if (empty($log->clicked_at)) {
                $log->clicked_at = null;
            }
        }

        return $logs;
    }

    /**
     * Get time since sent
     */
    public function get_time_since_sent($campaign_id)
    {
        $campaign = $this->get_campaign($campaign_id);

        if (!$campaign || empty($campaign->sent_at)) {
            return __('Not sent yet', 'advnews-manager');
        }

        if (!empty($campaign->sent_at)) {
            $sent_time = strtotime($campaign->sent_at);
            if ($sent_time) {
                return human_time_diff($sent_time, current_time('timestamp')) . ' ' . __('ago', 'advnews-manager');
            }
        }

        return __('Unknown', 'advnews-manager');
    }

    /**
     * Get performance metrics
     */
    public function get_performance_metrics($campaign_id)
    {
        $campaign = $this->get_campaign($campaign_id);

        if (!$campaign) {
            return null;
        }

        $metrics = array(
            'campaign_id' => $campaign_id,
            'campaign_name' => $campaign->name,
            'total_recipients' => intval($campaign->total_recipients ?? 0),
            'sent_count' => intval($campaign->sent_count ?? 0),
            'delivered_count' => intval($campaign->delivered_count ?? 0),
            'open_count' => intval($campaign->open_count ?? 0),
            'click_count' => intval($campaign->click_count ?? 0),
            'bounce_count' => intval($campaign->bounce_count ?? 0),
            'unsubscribe_count' => intval($campaign->unsubscribe_count ?? 0),
            'open_rate' => floatval($campaign->open_rate ?? 0),
            'click_rate' => floatval($campaign->click_rate ?? 0),
            'status' => $campaign->status,
            'sent_at' => !empty($campaign->sent_at) ? $campaign->sent_at : null,
            'created_at' => !empty($campaign->created_at) ? $campaign->created_at : current_time('mysql'),
            'scheduled_for' => !empty($campaign->scheduled_for) ? $campaign->scheduled_for : null
        );

        $metrics['delivery_rate'] = ($metrics['delivered_count'] > 0) ? round(($metrics['delivered_count'] / $metrics['total_recipients']) * 100, 2) : 0;
        $metrics['bounce_rate'] = ($metrics['sent_count'] > 0) ? round(($metrics['bounce_count'] / $metrics['sent_count']) * 100, 2) : 0;

        return $metrics;
    }

    /**
     * Export campaign data
     */
    public function export_campaign_data($campaign_id, $format = 'csv')
    {
        $campaign = $this->get_campaign($campaign_id);

        if (!$campaign) {
            return false;
        }

        $filename = 'campaign-' . $campaign_id . '-' . date('Y-m-d-H-i-s') . '.' . $format;

        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename);

            $output = fopen('php://output', 'w');

            fputcsv($output, array(
                'Campaign ID', 'Campaign Name', 'Subject', 'Status', 'Total Recipients',
                'Sent', 'Delivered', 'Opens', 'Clicks', 'Bounces', 'Unsubscribes',
                'Open Rate', 'Click Rate', 'Created At', 'Sent At', 'Scheduled For'
            ));

            fputcsv($output, array(
                $campaign->id,
                $campaign->name,
                $campaign->subject,
                $campaign->status,
                $campaign->total_recipients ?? 0,
                $campaign->sent_count ?? 0,
                $campaign->delivered_count ?? 0,
                $campaign->open_count ?? 0,
                $campaign->click_count ?? 0,
                $campaign->bounce_count ?? 0,
                $campaign->unsubscribe_count ?? 0,
                ($campaign->open_rate ?? 0) . '%',
                ($campaign->click_rate ?? 0) . '%',
                !empty($campaign->created_at) ? $campaign->created_at : '',
                !empty($campaign->sent_at) ? $campaign->sent_at : '',
                !empty($campaign->scheduled_for) ? $campaign->scheduled_for : ''
            ));

            fclose($output);
            exit;
        }

        return false;
    }
}
