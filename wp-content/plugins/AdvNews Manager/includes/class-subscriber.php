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
            if (!empty($data['categories'])) {
                $categories = is_array($data['categories']) ? $data['categories'] : explode(',', $data['categories']);
                $this->add_categories_to_subscriber($existing->id, $categories, true);
            }

            if ($existing->status === 'unsubscribed') {
                return $this->resubscribe($existing->id, $data);
            }

            return $existing->id;
        }

        // Prepare subscriber data
        $subscriber_data = array(
            'email' => $email,
            'first_name' => isset($data['first_name']) ? $data['first_name'] : '',
            'last_name' => isset($data['last_name']) ? $data['last_name'] : '',
            'organization' => isset($data['organization']) ? $data['organization'] : '',
            'title' => isset($data['title']) ? $data['title'] : '',
            'website_url' => isset($data['website_url']) ? $this->sanitize_website_url($data['website_url']) : '',
            'description' => isset($data['description']) ? $data['description'] : '',
            'country' => isset($data['country']) ? $data['country'] : '',
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
        $send_welcome = !array_key_exists('send_welcome', $data) || !empty($data['send_welcome']);

        if (get_option('advnews_double_optin') && $send_welcome) {
            $this->send_confirmation_email($subscriber_id);
        } elseif (get_option('advnews_welcome_email', false) && $send_welcome) {
            // Send welcome email ONLY if enabled in settings
            $this->send_welcome_email($subscriber_id);
        }

        return $subscriber_id;
    }

    /**
     * Unsubscribe a subscriber (MARK as unsubscribed, DO NOT DELETE)
     */
    public function unsubscribe($email, $reason = '', $send_notification = true)
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

        if ($send_notification) {
            $this->send_unsubscribe_confirmation($subscriber->id);
        }

        return true;
    }

    /**
     * Resubscribe a previously unsubscribed user
     */
    public function resubscribe($subscriber_id, $data = array(), $send_notification = true)
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
        if (!empty($data['title'])) {
            $update_data['title'] = AdvNews_Security::sanitize_text($data['title']);
        }
        if (!empty($data['website_url'])) {
            $update_data['website_url'] = $this->sanitize_website_url($data['website_url']);
        }
        if (!empty($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
        }
        if (!empty($data['country'])) {
            $update_data['country'] = AdvNews_Security::sanitize_text($data['country']);
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

        if ($send_notification) {
            $this->send_resubscribe_email($subscriber_id);
        }

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
            'category_ids' => array(),
            'search' => '',
            'date_from' => '',
            'date_to' => '',
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
        $category_ids = array();
        if (!empty($args['category_ids'])) {
            $category_ids = is_array($args['category_ids']) ? $args['category_ids'] : array($args['category_ids']);
        } elseif (!empty($args['category_id'])) {
            $category_ids = array($args['category_id']);
        }
        $category_ids = array_values(array_unique(array_filter(array_map('intval', $category_ids))));

        // Status filter
        if (!empty($args['status'])) {
            $where[] = $this->wpdb->prepare("s.status = %s", $args['status']);
        }

        // Category filter
        if (!empty($category_ids)) {
            $join = "INNER JOIN $table_sub_cats sc ON s.id = sc.subscriber_id";
            $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
            $where[] = $this->wpdb->prepare("sc.category_id IN ($placeholders)", $category_ids);
            $group_by = "GROUP BY s.id";
        }

        // Search filter
        if (!empty($args['search'])) {
            $search = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where[] = $this->wpdb->prepare(
                "(s.email LIKE %s OR s.first_name LIKE %s OR s.last_name LIKE %s OR s.organization LIKE %s OR s.title LIKE %s OR s.website_url LIKE %s OR s.description LIKE %s OR s.country LIKE %s)",
                $search, $search, $search, $search, $search, $search, $search, $search
            );
        }

        if (!empty($args['date_from'])) {
            $where[] = $this->wpdb->prepare("s.subscribed_at >= %s", sanitize_text_field($args['date_from']) . ' 00:00:00');
        }

        if (!empty($args['date_to'])) {
            $where[] = $this->wpdb->prepare("s.subscribed_at <= %s", sanitize_text_field($args['date_to']) . ' 23:59:59');
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
            'category_ids' => array(),
            'search' => '',
            'date_from' => '',
            'date_to' => ''
        );
        $args = wp_parse_args($args, $defaults);

        $table_subscribers = $this->wpdb->prefix . $this->table_prefix . 'subscribers';
        $table_sub_cats = $this->wpdb->prefix . $this->table_prefix . 'subscriber_categories';

        $where = array('1=1');
        $join = '';
        $category_ids = array();
        if (!empty($args['category_ids'])) {
            $category_ids = is_array($args['category_ids']) ? $args['category_ids'] : array($args['category_ids']);
        } elseif (!empty($args['category_id'])) {
            $category_ids = array($args['category_id']);
        }
        $category_ids = array_values(array_unique(array_filter(array_map('intval', $category_ids))));

        // Status filter
        if (!empty($args['status'])) {
            $where[] = $this->wpdb->prepare("s.status = %s", $args['status']);
        }

        // Category filter
        if (!empty($category_ids)) {
            $join = "INNER JOIN $table_sub_cats sc ON s.id = sc.subscriber_id";
            $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
            $where[] = $this->wpdb->prepare("sc.category_id IN ($placeholders)", $category_ids);
        }

        // Search filter
        if (!empty($args['search'])) {
            $search = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where[] = $this->wpdb->prepare(
                "(s.email LIKE %s OR s.first_name LIKE %s OR s.last_name LIKE %s OR s.organization LIKE %s OR s.title LIKE %s OR s.website_url LIKE %s OR s.description LIKE %s OR s.country LIKE %s)",
                $search, $search, $search, $search, $search, $search, $search, $search
            );
        }

        if (!empty($args['date_from'])) {
            $where[] = $this->wpdb->prepare("s.subscribed_at >= %s", sanitize_text_field($args['date_from']) . ' 00:00:00');
        }

        if (!empty($args['date_to'])) {
            $where[] = $this->wpdb->prepare("s.subscribed_at <= %s", sanitize_text_field($args['date_to']) . ' 23:59:59');
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

        if (isset($data['website_url'])) {
            $data['website_url'] = $this->sanitize_website_url($data['website_url']);
        }
        if (isset($data['description'])) {
            $data['description'] = sanitize_textarea_field($data['description']);
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
    public function add_categories_to_subscriber($subscriber_id, $categories, $merge = false)
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'subscriber_categories';

        if (!$merge) {
            $this->wpdb->delete($table_name, array('subscriber_id' => $subscriber_id));
        }

        if (empty($categories)) {
            return true;
        }

        foreach ($categories as $category) {
            if (is_numeric($category)) {
                $category_id = intval($category);
            } else {
                $category_id = $this->get_or_create_category($category);
            }

            if ($category_id) {
                $exists = $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE subscriber_id = %d AND category_id = %d",
                    $subscriber_id,
                    $category_id
                ));

                if (!$exists) {
                    $this->wpdb->insert($table_name, array(
                        'subscriber_id' => $subscriber_id,
                        'category_id' => $category_id
                    ));
                }
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
    * Import subscribers from CSV or Excel - UPDATED for multiple default categories
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

        $file_name = isset($options['file_name']) && !empty($options['file_name']) ? $options['file_name'] : $file_path;
        if ($this->is_xlsx_import($file_name)) {
            return $this->import_from_xlsx($file_path, $options);
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
            $headers[] = $this->normalize_import_header($clean_header);
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
                $row_data = $this->normalize_import_row($row_data);

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
                    // Category membership is additive for every duplicate email,
                    // regardless of whether profile fields are being updated.
                    // This also preserves categories for unsubscribed records.
                    if (!empty($final_categories)) {
                        $this->add_categories_to_subscriber($existing->id, $final_categories, true);
                    }

                    if ($existing->status === 'unsubscribed' && !$options['update_existing']) {
                        if (!empty($final_categories)) {
                            $updated++;
                        } else {
                            $skipped++;
                        }
                        continue;
                    }

                    if ($options['skip_duplicates'] && $existing->status === 'active') {
                        if (!empty($final_categories)) {
                            $updated++;
                        } else {
                            $skipped++;
                        }
                        continue;
                    }

                    // Update existing subscriber
                    $update_data = array();
                    if (!empty($row_data['first_name'])) $update_data['first_name'] = $row_data['first_name'];
                    if (!empty($row_data['last_name']))  $update_data['last_name']  = $row_data['last_name'];
                    if (!empty($row_data['organization'])) $update_data['organization'] = $row_data['organization'];
                    if (!empty($row_data['title'])) $update_data['title'] = $row_data['title'];
                    if (!empty($row_data['website_url'])) $update_data['website_url'] = $row_data['website_url'];
                    if (!empty($row_data['description'])) $update_data['description'] = $row_data['description'];
                    if (!empty($row_data['country'])) $update_data['country'] = $row_data['country'];

                    if (!empty($update_data)) {
                        $this->update_subscriber($existing->id, $update_data);
                    }

                    $updated++;

                } else {
                    // Add new subscriber
                    $subscriber_data = array(
                        'email'        => $email,
                        'first_name'   => $row_data['first_name'] ?? '',
                        'last_name'    => $row_data['last_name'] ?? '',
                        'organization' => $row_data['organization'] ?? '',
                        'title'        => $row_data['title'] ?? '',
                        'website_url'  => $row_data['website_url'] ?? '',
                        'description'  => $row_data['description'] ?? '',
                        'country'      => $row_data['country'] ?? '',
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
     * Normalize CSV/Excel column headers to subscriber table keys.
     */
    private function normalize_import_header($header)
    {
        $key = strtolower(trim(sanitize_text_field($header)));
        $key = str_replace(array('-', '/', '\\', '.', '(', ')'), ' ', $key);
        $key = preg_replace('/\s+/', '_', $key);
        $key = trim($key, '_');

        $aliases = array(
            'email_address' => 'email',
            'e_mail' => 'email',
            'mail' => 'email',
            'name' => 'full_name',
            'recipient_name' => 'full_name',
            'first' => 'first_name',
            'firstname' => 'first_name',
            'first_name' => 'first_name',
            'last' => 'last_name',
            'lastname' => 'last_name',
            'last_name' => 'last_name',
            'company' => 'organization',
            'organisation' => 'organization',
            'organization' => 'organization',
            'institution' => 'organization',
            'title_role' => 'title',
            'role' => 'title',
            'job_title' => 'title',
            'position' => 'title',
            'title' => 'title',
            'url' => 'website_url',
            'website' => 'website_url',
            'web_site' => 'website_url',
            'url_website' => 'website_url',
            'website_url' => 'website_url',
            'recipient_url' => 'website_url',
            'description' => 'description',
            'bio' => 'description',
            'notes' => 'description',
            'recipient_description' => 'description',
            'organization_description' => 'description',
            'country' => 'country',
            'recipient_country' => 'country',
            'category' => 'categories',
            'categories' => 'categories',
        );

        return isset($aliases[$key]) ? $aliases[$key] : $key;
    }

    /**
     * Normalize a parsed import row and support combined Name columns.
     */
    private function normalize_import_row($row_data)
    {
        $row_data = wp_parse_args($row_data, array(
            'email' => '',
            'first_name' => '',
            'last_name' => '',
            'full_name' => '',
            'organization' => '',
            'title' => '',
            'website_url' => '',
            'description' => '',
            'country' => '',
            'categories' => '',
        ));

        if (!empty($row_data['full_name']) && empty($row_data['first_name']) && empty($row_data['last_name'])) {
            $name_parts = preg_split('/\s+/', trim($row_data['full_name']), 2);
            $row_data['first_name'] = $name_parts[0] ?? '';
            $row_data['last_name'] = $name_parts[1] ?? '';
        }

        $row_data['website_url'] = $this->sanitize_website_url($row_data['website_url']);

        return $row_data;
    }

    /**
     * Accept website URLs with or without a scheme and store a clean URL.
     */
    private function sanitize_website_url($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        return esc_url_raw($url);
    }


    /**
     * Detect Excel Open XML imports while preserving the existing CSV method name.
     */
    private function is_xlsx_import($file_name)
    {
        return strtolower(pathinfo($file_name, PATHINFO_EXTENSION)) === 'xlsx';
    }

    /**
     * Convert the first worksheet in an .xlsx file to a temporary CSV, then reuse
     * the existing importer so duplicate, update, and category behavior stays the same.
     */
    private function import_from_xlsx($file_path, $options = array())
    {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('xlsx_zip_missing', __('Excel import requires the PHP Zip extension. Please enable ZipArchive or upload CSV.', 'advnews-manager'));
        }

        $rows = $this->read_xlsx_rows($file_path);
        if (is_wp_error($rows)) {
            return $rows;
        }

        if (empty($rows)) {
            return new WP_Error('xlsx_empty', __('Excel file is empty or does not contain readable rows.', 'advnews-manager'));
        }

        $temp_csv = function_exists('wp_tempnam') ? wp_tempnam('advnews-import') : false;
        if (!$temp_csv) {
            $temp_dir = is_writable(dirname($file_path)) ? dirname($file_path) : sys_get_temp_dir();
            $temp_csv = tempnam($temp_dir, 'advnews-import-');
        }
        if (!$temp_csv) {
            return new WP_Error('temp_file_failed', __('Could not create a temporary file for Excel import.', 'advnews-manager'));
        }

        $handle = fopen($temp_csv, 'w');
        if (!$handle) {
            @unlink($temp_csv);
            return new WP_Error('temp_file_open_failed', __('Could not write the temporary CSV file for Excel import.', 'advnews-manager'));
        }

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        $csv_options = $options;
        $csv_options['file_name'] = 'advnews-import.csv';
        $result = $this->import_from_csv($temp_csv, $csv_options);
        @unlink($temp_csv);

        return $result;
    }

    /**
     * Read rows from the first worksheet of a simple .xlsx workbook.
     */
    private function read_xlsx_rows($file_path)
    {
        $zip = new ZipArchive();
        if ($zip->open($file_path) !== true) {
            return new WP_Error('xlsx_open_failed', __('Could not open the Excel file. Please check the file and try again.', 'advnews-manager'));
        }

        $shared_strings = $this->read_xlsx_shared_strings($zip);
        if (is_wp_error($shared_strings)) {
            $zip->close();
            return $shared_strings;
        }

        $worksheet_path = $this->get_xlsx_worksheet_path($zip);
        if (is_wp_error($worksheet_path)) {
            $zip->close();
            return $worksheet_path;
        }

        $sheet_xml = $zip->getFromName($worksheet_path);
        $zip->close();

        if ($sheet_xml === false) {
            return new WP_Error('xlsx_sheet_missing', __('Could not read the first worksheet from the Excel file.', 'advnews-manager'));
        }

        $xml = $this->parse_xlsx_xml($sheet_xml);
        if (!$xml) {
            return new WP_Error('xlsx_xml_invalid', __('The Excel worksheet XML could not be parsed.', 'advnews-manager'));
        }

        $xml->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $row_nodes = $xml->xpath('//m:sheetData/m:row');
        if (!$row_nodes) {
            return array();
        }

        $rows = array();
        foreach ($row_nodes as $row_node) {
            $row_node->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $cell_nodes = $row_node->xpath('m:c');
            $row = array();
            $max_index = -1;

            foreach ($cell_nodes as $cell_node) {
                $cell_ref = (string) $cell_node['r'];
                $column_index = $cell_ref ? $this->xlsx_column_index($cell_ref) : count($row);
                $row[$column_index] = $this->get_xlsx_cell_value($cell_node, $shared_strings);
                $max_index = max($max_index, $column_index);
            }

            if ($max_index < 0) {
                continue;
            }

            $normalized_row = array();
            for ($i = 0; $i <= $max_index; $i++) {
                $normalized_row[] = isset($row[$i]) ? $row[$i] : '';
            }

            if (count(array_filter($normalized_row, function($value) {
                return trim((string) $value) !== '';
            })) === 0) {
                continue;
            }

            $rows[] = $normalized_row;
        }

        return $rows;
    }

    /**
     * Locate the first worksheet through workbook relationships, with sheet1 fallback.
     */
    private function get_xlsx_worksheet_path($zip)
    {
        $fallback = 'xl/worksheets/sheet1.xml';
        $workbook_xml = $zip->getFromName('xl/workbook.xml');
        $rels_xml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook_xml === false || $rels_xml === false) {
            return $zip->locateName($fallback) !== false ? $fallback : new WP_Error('xlsx_sheet_missing', __('No worksheet was found in the Excel file.', 'advnews-manager'));
        }

        $workbook = $this->parse_xlsx_xml($workbook_xml);
        $rels = $this->parse_xlsx_xml($rels_xml);
        if (!$workbook || !$rels) {
            return $zip->locateName($fallback) !== false ? $fallback : new WP_Error('xlsx_workbook_invalid', __('The Excel workbook structure could not be parsed.', 'advnews-manager'));
        }

        $workbook->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $sheets = $workbook->xpath('//m:sheets/m:sheet');
        if (!$sheets || empty($sheets[0])) {
            return $zip->locateName($fallback) !== false ? $fallback : new WP_Error('xlsx_sheet_missing', __('No worksheet was found in the Excel file.', 'advnews-manager'));
        }

        $relationship_id = (string) $sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
        if (empty($relationship_id)) {
            return $zip->locateName($fallback) !== false ? $fallback : new WP_Error('xlsx_sheet_missing', __('No worksheet relationship was found in the Excel file.', 'advnews-manager'));
        }

        $rels->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $relationships = $rels->xpath('//r:Relationship');
        foreach ($relationships as $relationship) {
            if ((string) $relationship['Id'] !== $relationship_id) {
                continue;
            }

            $target = (string) $relationship['Target'];
            if (empty($target)) {
                break;
            }

            $worksheet_path = ltrim($target, '/');
            if (strpos($worksheet_path, 'xl/') !== 0) {
                $worksheet_path = 'xl/' . $worksheet_path;
            }

            return $zip->locateName($worksheet_path) !== false ? $worksheet_path : new WP_Error('xlsx_sheet_missing', __('The worksheet referenced by the Excel workbook could not be found.', 'advnews-manager'));
        }

        return $zip->locateName($fallback) !== false ? $fallback : new WP_Error('xlsx_sheet_missing', __('No worksheet was found in the Excel file.', 'advnews-manager'));
    }

    /**
     * Read the shared string table used by .xlsx cells.
     */
    private function read_xlsx_shared_strings($zip)
    {
        $shared_xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($shared_xml === false) {
            return array();
        }

        $xml = $this->parse_xlsx_xml($shared_xml);
        if (!$xml) {
            $strings = $this->read_xlsx_shared_strings_fallback($shared_xml);
            if ($strings !== false) {
                return $strings;
            }

            return new WP_Error('xlsx_shared_strings_invalid', __('The Excel shared strings table could not be parsed. Please save the file again as .xlsx or CSV and retry.', 'advnews-manager'));
        }

        $xml->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $items = $xml->xpath('//m:si');
        $strings = array();

        foreach ($items as $item) {
            $item->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $text_nodes = $item->xpath('.//m:t');
            $value = '';
            if ($text_nodes) {
                foreach ($text_nodes as $text_node) {
                    $value .= (string) $text_node;
                }
            }
            $strings[] = $value;
        }

        return $strings;
    }

    /**
     * Parse XML from an .xlsx archive without leaking libxml warnings into admin output.
     */
    private function parse_xlsx_xml($xml_string)
    {
        if (!is_string($xml_string) || $xml_string === '') {
            return false;
        }

        $xml_string = $this->sanitize_xlsx_xml_string($xml_string);
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $xml = simplexml_load_string($xml_string, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $xml ? $xml : false;
    }

    /**
     * Remove bytes that are illegal in XML 1.0 but sometimes appear in exported spreadsheets.
     */
    private function sanitize_xlsx_xml_string($xml_string)
    {
        $xml_string = preg_replace('/^\xEF\xBB\xBF/', '', $xml_string);
        $cleaned = @preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $xml_string);

        return is_string($cleaned) ? $cleaned : $xml_string;
    }

    /**
     * Best-effort reader for shared strings when SimpleXML rejects otherwise usable workbook XML.
     */
    private function read_xlsx_shared_strings_fallback($shared_xml)
    {
        $shared_xml = $this->sanitize_xlsx_xml_string($shared_xml);
        if (!preg_match_all('/<si\b[^>]*>(.*?)<\/si>/is', $shared_xml, $items)) {
            return false;
        }

        $strings = array();
        foreach ($items[1] as $item_xml) {
            $value = '';
            if (preg_match_all('/<t\b[^>]*>(.*?)<\/t>/is', $item_xml, $text_matches)) {
                foreach ($text_matches[1] as $text_part) {
                    $value .= html_entity_decode(strip_tags($text_part), ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
            $strings[] = $value;
        }

        return $strings;
    }

    /**
     * Get a readable value from an .xlsx cell.
     */
    private function get_xlsx_cell_value($cell, $shared_strings)
    {
        $cell->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $type = (string) $cell['t'];

        if ($type === 'inlineStr') {
            $text_nodes = $cell->xpath('.//m:t');
            $value = '';
            if ($text_nodes) {
                foreach ($text_nodes as $text_node) {
                    $value .= (string) $text_node;
                }
            }
            return $value;
        }

        $value_nodes = $cell->xpath('m:v');
        $value = $value_nodes && isset($value_nodes[0]) ? (string) $value_nodes[0] : '';

        if ($type === 's') {
            $index = intval($value);
            return isset($shared_strings[$index]) ? $shared_strings[$index] : '';
        }

        if ($type === 'b') {
            return $value === '1' ? 'TRUE' : 'FALSE';
        }

        return $value;
    }

    /**
     * Convert an Excel cell reference like AB12 into a zero-based column index.
     */
    private function xlsx_column_index($cell_ref)
    {
        preg_match('/^([A-Z]+)/i', $cell_ref, $matches);
        $letters = isset($matches[1]) ? strtoupper($matches[1]) : 'A';
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return $index - 1;
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
        $headers = array('Email', 'First Name', 'Last Name', 'Organization', 'Title/Role', 'URL/Website', 'Description', 'Country', 'Status', 'Categories', 'Subscribed At', 'Open Rate', 'Click Rate');
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
                $subscriber->title,
                $subscriber->website_url,
                $subscriber->description,
                $subscriber->country,
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
        $headers = array();

        $template = $this->get_active_email_template(get_option('advnews_welcome_template', 0));
        if ($template && class_exists('AdvNews_Campaign')) {
            $campaign = new AdvNews_Campaign();
            $subscriber_data = $this->get_subscriber_merge_data($subscriber);

            $subject = $campaign->process_merge_tags($template->subject, $subscriber_data);
            $message = $campaign->process_merge_tags($template->content, $subscriber_data);
            $message = $campaign->prepare_email_content($message);
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }

        return wp_mail($subscriber->email, $subject, $message, $headers);
    }

    /**
     * Get an active email template by ID.
     */
    private function get_active_email_template($template_id)
    {
        $template_id = intval($template_id);
        if (!$template_id) {
            return null;
        }

        $table_name = $this->wpdb->prefix . $this->table_prefix . 'templates';

        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND is_active = 1",
            $template_id
        ));
    }

    /**
     * Build merge-tag data for subscriber emails.
     */
    private function get_subscriber_merge_data($subscriber)
    {
        $categories = $this->get_subscriber_categories($subscriber->id);
        $category_names = array();

        foreach ($categories as $category) {
            $category_names[] = $category->name;
        }

        return array(
            'subscriber_id' => $subscriber->id,
            'email' => $subscriber->email,
            'first_name' => $subscriber->first_name,
            'last_name' => $subscriber->last_name,
            'full_name' => trim($subscriber->first_name . ' ' . $subscriber->last_name),
            'organization' => $subscriber->organization,
            'title' => $subscriber->title,
            'website_url' => $subscriber->website_url,
            'description' => $subscriber->description,
            'country' => $subscriber->country,
            'status' => $subscriber->status,
            'categories' => implode(', ', $category_names),
            'subscribed_date' => !empty($subscriber->subscribed_at) ? date_i18n(get_option('date_format'), strtotime($subscriber->subscribed_at)) : ''
        );
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
