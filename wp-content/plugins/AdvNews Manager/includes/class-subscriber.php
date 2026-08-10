<?php
// File: includes/class-subscriber.php
class AdvNews_Subscriber
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
     * Add new subscriber
     */
    public function add_subscriber($data)
    {
        $data = AdvNews_Security::sanitize_array($data);

        // Validate required fields
        if (empty($data['email'])) {
            return new WP_Error('missing_email', __('Email address is required.', 'advnews-manager'));
        }

        // Validate email
        $email = AdvNews_Security::validate_email($data['email']);
        if (!$email) {
            return new WP_Error('invalid_email', __('Invalid email address.', 'advnews-manager'));
        }

        // Check if subscriber already exists
        $existing = $this->get_subscriber_by_email($email);
        if ($existing) {
            // If unsubscribed, allow resubscription
            if ($existing->status === 'unsubscribed') {
                return $this->resubscribe($existing->id, $data);
            }
            return new WP_Error('subscriber_exists', __('Subscriber already exists.', 'advnews-manager'));
        }

        // Prepare subscriber data
        $subscriber_data = array(
            'email' => $email,
            'first_name' => isset($data['first_name']) ? $data['first_name'] : '',
            'last_name' => isset($data['last_name']) ? $data['last_name'] : '',
            'organization' => isset($data['organization']) ? $data['organization'] : '',
            'status' => 'active',
            'ip_address' => AdvNews_Security::get_client_ip(),
            'email_verified' => get_option('advnews_double_optin') ? 0 : 1
        );

        // Double opt-in
        if (get_option('advnews_double_optin')) {
            $subscriber_data['confirmation_token'] = AdvNews_Security::generate_hash($email . time());
        }

        // Insert subscriber
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'subscribers';
        $result = $this->wpdb->insert($table_name, $subscriber_data);
        if (!$result) {
            return new WP_Error('db_error', __('Failed to add subscriber.', 'advnews-manager'));
        }

        $subscriber_id = $this->wpdb->insert_id;

        // Add categories if specified
        if (!empty($data['categories'])) {
            $categories = is_array($data['categories']) ? $data['categories'] : explode(',', $data['categories']);
            $this->add_categories_to_subscriber($subscriber_id, $categories);
        }

        // Log activity
        $this->log_activity($subscriber_id, 'subscribed', null, null, $subscriber_data['ip_address']);

		// Send confirmation email if double opt-in is enabled
		if (get_option('advnews_double_optin')) {
			$this->send_confirmation_email($subscriber_id);
		} elseif (get_option('advnews_welcome_email', false)) {
			// Send welcome email ONLY if enabled in settings
			$this->send_welcome_email($subscriber_id);
		}

        return $subscriber_id;
    }

    /**
     * Unsubscribe a subscriber (MARK as unsubscribed, DO NOT DELETE)
     */
    public function unsubscribe($email, $reason = '')
    {
        $email = AdvNews_Security::sanitize_email($email);

        // Find subscriber
        $subscriber = $this->get_subscriber_by_email($email);
        if (!$subscriber) {
            return new WP_Error('subscriber_not_found', __('Subscriber not found.', 'advnews-manager'));
        }

        // Mark as unsubscribed
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'subscribers';
        $result = $this->wpdb->update(
            $table_name,
            array(
                'status' => 'unsubscribed',
                'unsubscribed_at' => current_time('mysql'),
                'unsubscribe_reason' => AdvNews_Security::sanitize_text($reason)
            ),
            array('id' => $subscriber->id)
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to unsubscribe.', 'advnews-manager'));
        }

        // Add to suppression list
        $this->add_to_suppression_list($email, 'unsubscribe', 'user_action');

        // Log activity
        $this->log_activity($subscriber->id, 'unsubscribed', null, null, AdvNews_Security::get_client_ip(), array('reason' => $reason));

        // Send unsubscribe confirmation email
        $this->send_unsubscribe_confirmation($subscriber->id);

        return true;
    }

    /**
     * Resubscribe a previously unsubscribed user
     */
    public function resubscribe($subscriber_id, $data = array())
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'subscribers';
        $update_data = array(
            'status' => 'active',
            'unsubscribed_at' => null,
            'unsubscribe_reason' => null,
            'email_verified' => 1
        );

        // Update additional data if provided
        if (!empty($data['first_name'])) {
            $update_data['first_name'] = AdvNews_Security::sanitize_text($data['first_name']);
        }
        if (!empty($data['last_name'])) {
            $update_data['last_name'] = AdvNews_Security::sanitize_text($data['last_name']);
        }
        if (!empty($data['organization'])) {
            $update_data['organization'] = AdvNews_Security::sanitize_text($data['organization']);
        }

        $result = $this->wpdb->update(
            $table_name,
            $update_data,
            array('id' => $subscriber_id)
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to resubscribe.', 'advnews-manager'));
        }

        // Remove from suppression list
        $subscriber = $this->get_subscriber($subscriber_id);
        if ($subscriber) {
            $this->remove_from_suppression_list($subscriber->email);
        }

        // Update categories if specified
        if (!empty($data['categories'])) {
            $categories = is_array($data['categories']) ? $data['categories'] : explode(',', $data['categories']);
            $this->add_categories_to_subscriber($subscriber_id, $categories);
        }

        // Log activity
        $this->log_activity($subscriber_id, 'subscribed', null, null, AdvNews_Security::get_client_ip());

        // Send welcome back email
        $this->send_resubscribe_email($subscriber_id);

        return true;
    }

    /**
     * Get subscriber by email
     */
    public function get_subscriber_by_email($email)
    {
        $email = AdvNews_Security::sanitize_email($email);
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'subscribers';
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table_name WHERE email = %s",
            $email
        ));
    }

    /**
     * Get subscriber by ID
     */
    public function get_subscriber($id)
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'subscribers';
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ));
    }

    /**
     * Get subscribers by category
     */
    public function get_subscribers_by_category($category_id, $status = 'active', $limit = null, $offset = 0)
    {
        $table_subscribers = $this->wpdb->prefix . $this->table_prefix . 'subscribers';
        $table_sub_cats = $this->wpdb->prefix . $this->table_prefix . 'subscriber_categories';

        $query = $this->wpdb->prepare(
            "SELECT s.* FROM $table_subscribers s
            INNER JOIN $table_sub_cats sc ON s.id = sc.subscriber_id
            WHERE sc.category_id = %d AND s.status = %s",
            $category_id,
            $status
        );

        if ($limit) {
            $query .= $this->wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $offset);
        }

        return $this->wpdb->get_results($query);
    }

    /**
     * Count subscribers by category
     */
    public function count_subscribers_by_category($category_id, $status = 'active')
    {
        $table_subscribers = $this->wpdb->prefix . $this->table_prefix . 'subscribers';
        $table_sub_cats = $this->wpdb->prefix . $this->table_prefix . 'subscriber_categories';

        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT s.id) FROM $table_subscribers s
            INNER JOIN $table_sub_cats sc ON s.id = sc.subscriber_id
            WHERE sc.category_id = %d AND s.status = %s",
            $category_id,
            $status
        ));
    }

    /**
     * Get all subscribers with pagination
     */
    public function get_all_subscribers($args = array())
    {
        $defaults = array(
            'status' => '',
            'category_id' => null,
            'search' => '',
            'orderby' => 'subscribed_at',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0
        );
        $args = wp_parse_args($args, $defaults);

        $table_subscribers = $this->wpdb->prefix . $this->table_prefix . 'subscribers';
        $table_sub_cats = $this->wpdb->prefix . $this->table_prefix . 'subscriber_categories';

        $where = array('1=1');
        $join = '';
        $group_by = '';

        // Status filter
        if (!empty($args['status'])) {
            $where[] = $this->wpdb->prepare("s.status = %s", $args['status']);
        }

        // Category filter
        if (!empty($args['category_id'])) {
            $join = "INNER JOIN $table_sub_cats sc ON s.id = sc.subscriber_id";
            $where[] = $this->wpdb->prepare("sc.category_id = %d", $args['category_id']);
            $group_by = "GROUP BY s.id";
        }

        // Search filter
        if (!empty($args['search'])) {
            $search = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where[] = $this->wpdb->prepare(
                "(s.email LIKE %s OR s.first_name LIKE %s OR s.last_name LIKE %s OR s.organization LIKE %s)",
                $search, $search, $search, $search
            );
        }

        // Build WHERE clause
        $where_clause = 'WHERE ' . implode(' AND ', $where);

        // Build ORDER BY
        $orderby = esc_sql($args['orderby']);
        $order = $args['order'] === 'DESC' ? 'DESC' : 'ASC';
        $order_clause = "ORDER BY s.$orderby $order";

        // Get subscribers
        $query = "SELECT s.* FROM $table_subscribers s $join $where_clause $group_by $order_clause";
        if ($args['limit'] > 0) {
            $query .= $this->wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }

        return $this->wpdb->get_results($query);
    }

    /**
     * Count all subscribers
     */
    public function count_subscribers($args = array())
    {
        $defaults = array(
            'status' => '',
            'category_id' => null,
            'search' => ''
        );
        $args = wp_parse_args($args, $defaults);

        $table_subscribers = $this->wpdb->prefix . $this->table_prefix . 'subscribers';
        $table_sub_cats = $this->wpdb->prefix . $this->table_prefix . 'subscriber_categories';

        $where = array('1=1');
        $join = '';

        // Status filter
        if (!empty($args['status'])) {
            $where[] = $this->wpdb->prepare("status = %s", $args['status']);
        }

        // Category filter
        if (!empty($args['category_id'])) {
            $join = "INNER JOIN $table_sub_cats sc ON s.id = sc.subscriber_id";
            $where[] = $this->wpdb->prepare("sc.category_id = %d", $args['category_id']);
        }

        // Search filter
        if (!empty($args['search'])) {
            $search = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where[] = $this->wpdb->prepare(
                "(email LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR organization LIKE %s)",
                $search, $search, $search, $search
            );
        }

        // Build WHERE clause
        $where_clause = 'WHERE ' . implode(' AND ', $where);

        $query = "SELECT COUNT(DISTINCT s.id) FROM $table_subscribers s $join $where_clause";
        return $this->wpdb->get_var($query);
    }

    /**
     * Update subscriber
     */
    public function update_subscriber($id, $data)
    {
        $data = AdvNews_Security::sanitize_array($data);
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'subscribers';
        // Remove non-updatable fields (including 'categories' which is handled via junction table)
        unset($data['id'], $data['email'], $data['created_at'], $data['subscribed_at'], $data['categories']);

        if (empty($data)) {
            return true;
        }
        $result = $this->wpdb->update(
            $table_name,
            $data,
            array('id' => $id)
        );
        return $result !== false;
    }

    /**
     * Add categories to subscriber
     */
    public function add_categories_to_subscriber($subscriber_id, $categories)
    {
        if (empty($categories)) {
            return false;
        }

        $table_name = $this->wpdb->prefix . $this->table_prefix . 'subscriber_categories';

        // Remove existing categories
        $this->wpdb->delete($table_name, array('subscriber_id' => $subscriber_id));

        // Add new categories
        foreach ($categories as $category) {
            if (is_numeric($category)) {
                $category_id = intval($category);
            } else {
                $category_id = $this->get_or_create_category($category);
            }

            if ($category_id) {
                $this->wpdb->insert($table_name, array(
                    'subscriber_id' => $subscriber_id,
                    'category_id' => $category_id
                ));
            }
        }

        return true;
    }

    /**
     * Get or create category
     */
    private function get_or_create_category($category_name)
    {
        $category_name = AdvNews_Security::sanitize_text($category_name);
        $slug = sanitize_title($category_name);
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'categories';

        // Check if category exists
        $category = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT id FROM $table_name WHERE slug = %s",
            $slug
        ));

        if ($category) {
            return $category->id;
        }

        // Create new category
        $this->wpdb->insert($table_name, array(
            'name' => $category_name,
            'slug' => $slug
        ));

        return $this->wpdb->insert_id;
    }

    /**
     * Get subscriber categories
     */
    public function get_subscriber_categories($subscriber_id)
    {
        $table_categories = $this->wpdb->prefix . $this->table_prefix . 'categories';
        $table_sub_cats = $this->wpdb->prefix . $this->table_prefix . 'subscriber_categories';

        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT c.* FROM $table_categories c
            INNER JOIN $table_sub_cats sc ON c.id = sc.category_id
            WHERE sc.subscriber_id = %d",
            $subscriber_id
        ));
    }
    /**
    * Import subscribers from CSV - UPDATED for multiple default categories
    */
    public function import_from_csv($file_path, $options = array())
    {
        // Server-safe timeout/memory settings
        if (function_exists('set_time_limit')) @set_time_limit(300);
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '512M');
            @ini_set('max_execution_time', '300');
        }

        // Verify file is accessible
        if (!file_exists($file_path) || !is_readable($file_path)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews Import] File not accessible: ' . $file_path);
            }
            return new WP_Error('file_access', __('Cannot read uploaded CSV file. Check server permissions.', 'advnews-manager'));
        }

        $handle = fopen($file_path, 'r');
        if (!$handle) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews Import] Cannot open file: ' . $file_path);
            }
            return new WP_Error('file_open', __('Cannot open CSV file for reading.', 'advnews-manager'));
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $debug = $this->debug_csv_file($file_path);
            error_log('[AdvNews Import Debug] ' . print_r($debug, true));
        }

        // Read and validate headers - with BOM handling
        $raw_headers = fgetcsv($handle);

        // Check if headers were read successfully
        if (!$raw_headers || !is_array($raw_headers) || empty($raw_headers)) {
            fclose($handle);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews Import] Failed to read CSV headers. File may be empty or malformed.');
                error_log('[AdvNews Import] Raw headers: ' . print_r($raw_headers, true));
            }
            return new WP_Error('csv_headers', __('Invalid CSV format. Please ensure the first row contains column headers.', 'advnews-manager'));
        }

        // Clean UTF-8 BOM if present (common issue on Windows/server uploads)
        $headers = array();
        foreach ($raw_headers as $header) {
            // Remove BOM and trim whitespace
            $clean_header = trim($header);
            $clean_header = preg_replace('/^\xEF\xBB\xBF/', '', $clean_header);
            $headers[] = sanitize_text_field($clean_header);
        }

        // Validate required 'email' column exists
        if (!in_array('email', array_map('strtolower', $headers))) {
            fclose($handle);
            return new WP_Error('missing_email_column', __('CSV must contain an "email" column.', 'advnews-manager'));
        }

        $default_options = array(
            'update_existing' => false,
            'skip_duplicates' => true,
            'default_category' => array(), // Now expects an array
            'send_welcome'   => false
        );
        $options = wp_parse_args($options, $default_options);

        // Ensure default_category is always an array of integers
        $default_cats = array();
        if (!empty($options['default_category'])) {
            if (is_array($options['default_category'])) {
                $default_cats = array_filter(array_map('intval', $options['default_category']));
            } else {
                $cat_id = intval($options['default_category']);
                if ($cat_id > 0) {
                    $default_cats = array($cat_id);
                }
            }
        }

        $imported = 0;
        $updated  = 0;
        $skipped  = 0;
        $errors   = array();
        $row_count = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $row_count++;

            // Skip empty rows
            if (empty($row) || (count($row) === 1 && empty($row[0]))) {
                continue;
            }

            try {
                // Ensure row has same number of columns as headers (pad with empty if needed)
                if (count($row) < count($headers)) {
                    $row = array_pad($row, count($headers), '');
                } elseif (count($row) > count($headers)) {
                    $row = array_slice($row, 0, count($headers));
                }

                // CRITICAL: Verify headers is still an array before array_combine
                if (!is_array($headers) || empty($headers)) {
                    $errors[] = sprintf(__('Row %d error: Headers not loaded properly.', 'advnews-manager'), $row_count);
                    $skipped++;
                    continue;
                }

                $row_data = array_combine($headers, $row);

                // Sanitize the row data
                $row_data = AdvNews_Security::sanitize_csv_data(array($row_data))[0];

                // Validate email
                $email = AdvNews_Security::validate_email($row_data['email'] ?? '');
                if (!$email) {
                    $errors[] = sprintf(__('Row %d: Invalid email "%s"', 'advnews-manager'), $row_count, $row_data['email'] ?? '');
                    $skipped++;
                    continue;
                }

                // Determine final categories for this row
                // Merge CSV categories + Default Categories
                $final_categories = array();

                // 1. Add categories from CSV if present
                if (!empty($row_data['categories'])) {
                    $csv_cats = explode(',', $row_data['categories']);
                    foreach ($csv_cats as $c) {
                        $c = trim($c);
                        if (!empty($c)) {
                            $final_categories[] = $c;
                        }
                    }
                }

                // 2. Add default categories from multi-select
                if (!empty($default_cats)) {
                    $final_categories = array_merge($final_categories, $default_cats);
                }

                // Remove duplicates and re-index
                $final_categories = array_unique($final_categories);
                $final_categories = array_values($final_categories);

                // Check if subscriber exists
                $existing = $this->get_subscriber_by_email($email);

                if ($existing) {
                    if ($existing->status === 'unsubscribed' && !$options['update_existing']) {
                        $skipped++;
                        continue;
                    }

                    if ($options['skip_duplicates'] && $existing->status === 'active') {
                        // Even if skipping update, we might want to add default categories?
                        // Usually skip means skip entirely. Keeping original behavior.
                        $skipped++;
                        continue;
                    }

                    // Update existing subscriber
                    $update_data = array();
                    if (!empty($row_data['first_name'])) $update_data['first_name'] = $row_data['first_name'];
                    if (!empty($row_data['last_name']))  $update_data['last_name']  = $row_data['last_name'];
                    if (!empty($row_data['organization'])) $update_data['organization'] = $row_data['organization'];

                    if (!empty($update_data)) {
                        $this->update_subscriber($existing->id, $update_data);
                    }

                    // Update categories (merges with existing via add_categories_to_subscriber)
                    if (!empty($final_categories)) {
                        $this->add_categories_to_subscriber($existing->id, $final_categories);
                    }

                    $updated++;

                } else {
                    // Add new subscriber
                    $subscriber_data = array(
                        'email'        => $email,
                        'first_name'   => $row_data['first_name'] ?? '',
                        'last_name'    => $row_data['last_name'] ?? '',
                        'organization' => $row_data['organization'] ?? '',
                        'ip_address'   => AdvNews_Security::get_client_ip()
                    );

                    // Assign merged categories
                    if (!empty($final_categories)) {
                        $subscriber_data['categories'] = $final_categories;
                    }

                    $result = $this->add_subscriber($subscriber_data);

                    if (is_wp_error($result)) {
                        $errors[] = sprintf(__('Row %d: %s', 'advnews-manager'), $row_count, $result->get_error_message());
                        $skipped++;
                    } else {
                        $imported++;
                        if ($options['send_welcome'] && !get_option('advnews_double_optin')) {
                            $this->send_welcome_email($result);
                        }
                    }
                }

            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[AdvNews Import] Row ' . $row_count . ' error: ' . $e->getMessage());
                }
                $errors[] = sprintf(__('Row %d error: %s', 'advnews-manager'), $row_count, $e->getMessage());
                $skipped++;
                continue;
            }

            // Batch flush every 20 rows to prevent memory issues
            if ($row_count % 20 === 0) {
                $this->wpdb->flush();
                usleep(10000); // 10ms delay on production
            }
        }

        fclose($handle);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[AdvNews Import] Completed: %d imported, %d updated, %d skipped, %d errors',
                $imported, $updated, $skipped, count($errors)
            ));
        }

        return array(
            'imported' => $imported,
            'updated'  => $updated,
            'skipped'  => $skipped,
            'errors'   => array_slice($errors, 0, 10) // Limit error display
        );
    }


    /**
    * Debug CSV file structure (for troubleshooting)
    */
    private function debug_csv_file($file_path) {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return 'File not accessible';
        }

        $handle = fopen($file_path, 'r');
        if (!$handle) return 'Cannot open file';

        $first_line = fgets($handle);
        fclose($handle);

        $info = array(
            'first_bytes' => bin2hex(substr($first_line, 0, 10)),
            'has_bom' => strpos($first_line, "\xEF\xBB\xBF") === 0,
            'line_ending' => (strpos($first_line, "\r\n") !== false) ? 'CRLF' : ((strpos($first_line, "\r") !== false) ? 'CR' : 'LF'),
            'first_chars' => substr($first_line, 0, 100)
        );

        return $info;
    }



    /**
     * Export subscribers to CSV
     */
    public function export_to_csv($args = array(), $format = 'csv')
    {
        $subscribers = $this->get_all_subscribers($args);
        if (empty($subscribers)) {
            return false;
        }

        $filename = 'subscribers-export-' . date('Y-m-d-H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');

        // Add headers
        $headers = array('Email', 'First Name', 'Last Name', 'Organization', 'Status', 'Categories', 'Subscribed At', 'Open Rate', 'Click Rate');
        fputcsv($output, $headers);

        // Add data
        foreach ($subscribers as $subscriber) {
            // Get categories
            $categories = $this->get_subscriber_categories($subscriber->id);
            $category_names = array();
            foreach ($categories as $category) {
                $category_names[] = $category->name;
            }

            $row = array(
                $subscriber->email,
                $subscriber->first_name,
                $subscriber->last_name,
                $subscriber->organization,
                $subscriber->status,
                implode(', ', $category_names),
                $subscriber->subscribed_at,
                $subscriber->open_rate,
                $subscriber->click_rate
            );
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    /**
     * Log subscriber activity
     */
    private function log_activity($subscriber_id, $activity_type, $campaign_id = null, $link_id = null, $ip_address = null, $metadata = array())
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'activity_log';

        // Get geolocation if IP is provided
        $country = '';
        $city = '';
        $country_code = '';

        if ($ip_address && get_option('advnews_track_geolocation', true)) {
            $geolocation_class = new AdvNews_Geolocation();
            $location = $geolocation_class->get_location($ip_address);
            $country = $location['country'];
            $city = $location['city'];
            $country_code = $location['country_code'];
        }

        $this->wpdb->insert(
            $table_name,
            array(
                'subscriber_id' => $subscriber_id,
                'activity_type' => $activity_type,
                'campaign_id' => $campaign_id,
                'link_id' => $link_id,
                'ip_address' => $ip_address,
                'country' => $country,
                'country_code' => $country_code,
                'city' => $city,
                'metadata' => !empty($metadata) ? json_encode($metadata) : null,
                'created_at' => current_time('mysql')
            )
        );
    }

    /**
     * Add email to suppression list
     */
    private function add_to_suppression_list($email, $reason, $source)
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'suppression_list';
        $this->wpdb->insert(
            $table_name,
            array(
                'email' => $email,
                'reason' => $reason,
                'source' => $source,
                'created_at' => current_time('mysql')
            )
        );
    }

    /**
     * Remove email from suppression list
     */
    private function remove_from_suppression_list($email)
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'suppression_list';
        $this->wpdb->delete($table_name, array('email' => $email));
    }

    /**
     * Send confirmation email (for double opt-in)
     */
    private function send_confirmation_email($subscriber_id)
    {
        $subscriber = $this->get_subscriber($subscriber_id);
        if (!$subscriber) {
            return false;
        }

        $confirmation_link = add_query_arg(array(
            'action' => 'confirm_subscription',
            'token' => $subscriber->confirmation_token,
            'email' => urlencode($subscriber->email)
        ), home_url());

        $subject = __('Confirm Your Subscription', 'advnews-manager');
        $message = sprintf(
            __('Hello! Please confirm your subscription by clicking this link: %s', 'advnews-manager'),
            $confirmation_link
        );

        return wp_mail($subscriber->email, $subject, $message);
    }

    /**
     * Send welcome email
     */
    private function send_welcome_email($subscriber_id)
    {
        $subscriber = $this->get_subscriber($subscriber_id);
        if (!$subscriber) {
            return false;
        }

        $subject = __('Welcome to Our Newsletter!', 'advnews-manager');
        $message = __('Thank you for subscribing to our newsletter!', 'advnews-manager');

        return wp_mail($subscriber->email, $subject, $message);
    }

    /**
     * Send unsubscribe confirmation
     */
    private function send_unsubscribe_confirmation($subscriber_id)
    {
        $subscriber = $this->get_subscriber($subscriber_id);
        if (!$subscriber) {
            return false;
        }

        $subject = __('You have been unsubscribed', 'advnews-manager');
        $message = __('You have been successfully unsubscribed from our newsletter.', 'advnews-manager');

        return wp_mail($subscriber->email, $subject, $message);
    }

    /**
     * Send resubscribe welcome email
     */
    private function send_resubscribe_email($subscriber_id)
    {
        $subscriber = $this->get_subscriber($subscriber_id);
        if (!$subscriber) {
            return false;
        }

        $subject = __('Welcome Back!', 'advnews-manager');
        $message = __('Welcome back to our newsletter!', 'advnews-manager');

        return wp_mail($subscriber->email, $subject, $message);
    }
}
