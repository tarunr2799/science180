<?php
// File: includes/class-tracking.php
class AdvNews_Tracking
{
    private $wpdb;
    private $table_prefix;
    private $geolocation;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = ADVNEWS_TABLE_PREFIX;
        // Initialize geolocation if needed
        if (get_option('advnews_track_geolocation', true)) {
            $this->init_geolocation();
        }
    }

    /**
     * Initialize geolocation class
     */
    private function init_geolocation()
    {
        require_once ADVNEWS_PLUGIN_DIR . 'includes/class-geolocation.php';
        $this->geolocation = new AdvNews_Geolocation();
    }

    /**
     * Get geolocation instance
     *
     * @return AdvNews_Geolocation
     */
    private function get_geolocation()
    {
        if (!isset($this->geolocation)) {
            $this->init_geolocation();
        }
        return $this->geolocation;
    }

    /**
     * Check if column exists in table
     */
    private function column_exists($table, $column)
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . $table;
        $result = $this->wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE '$column'");
        return !empty($result);
    }

    /**
     * Convert a geolocation lookup response into tracking table columns.
     */
    private function geolocation_to_tracking_data($location)
    {
        $location = is_array($location) ? $location : array();

        return array(
            'country' => $location['country'] ?? '',
            'country_code' => $location['country_code'] ?? '',
            'city' => $location['city'] ?? '',
            'region' => $location['region'] ?? '',
            'latitude' => $location['latitude'] ?? null,
            'longitude' => $location['longitude'] ?? null
        );
    }

    /**
     * Only treat real public locations as usable report data.
     */
    private function has_reportable_geolocation($location)
    {
        $country = isset($location['country']) ? trim((string) $location['country']) : '';
        $country_code = isset($location['country_code']) ? trim((string) $location['country_code']) : '';

        return $country !== ''
            && !in_array($country, array('Local', 'Unknown'), true)
            && $country_code !== ''
            && $country_code !== 'XX';
    }

    /**
     * Record email open
     */
    public function record_open($log_id, $campaign_id, $subscriber_id)
    {
        // Get IP address and user agent
        $ip_address = AdvNews_Security::get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        // Parse user agent for device/browser info
        $device_info = $this->parse_user_agent($user_agent);
        // Get geolocation data if enabled
        $country = '';
        $city = '';
        $country_code = '';
        $region = '';
        $latitude = null;
        $longitude = null;
        $timezone = '';
        if (get_option('advnews_track_geolocation', true)) {
            $geolocation = $this->get_geolocation()->get_location($ip_address);
            $country = $geolocation['country'];
            $city = $geolocation['city'];
            $country_code = $geolocation['country_code'];
            $region = $geolocation['region'] ?? '';
            $latitude = $geolocation['latitude'];
            $longitude = $geolocation['longitude'];
            $timezone = $geolocation['timezone'];
        }
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'tracking_opens';
        // Check if already opened (to prevent multiple counts from same user)
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name
            WHERE campaign_log_id = %d AND subscriber_id = %d",
            $log_id,
            $subscriber_id
        ));
        if ($existing > 0) {
            $this->wpdb->update(
                $table_name,
                array_merge(
                    array(
                        'opened_at' => current_time('mysql'),
                        'ip_address' => $ip_address,
                        'user_agent' => $user_agent,
                        'device_type' => $device_info['device_type'],
                        'browser' => $device_info['browser'],
                        'platform' => $device_info['platform'],
                    ),
                    $this->geolocation_to_tracking_data(array(
                        'country' => $country,
                        'country_code' => $country_code,
                        'city' => $city,
                        'region' => $region,
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                    ))
                ),
                array('campaign_log_id' => $log_id, 'subscriber_id' => $subscriber_id)
            );
            $this->log_activity($subscriber_id, 'opened', $campaign_id, null, $ip_address, array(
                'country' => $country,
                'city' => $city,
                'device' => $device_info['device_type']
            ));
            $this->update_campaign_log_status($log_id, 'opened');
            $this->update_subscriber_stats($subscriber_id);
            $this->update_campaign_stats($campaign_id);
            return true;
        }
        // Insert new open record
        $data = array(
            'campaign_log_id' => $log_id,
            'subscriber_id' => $subscriber_id,
            'campaign_id' => $campaign_id,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'device_type' => $device_info['device_type'],
            'browser' => $device_info['browser'],
            'platform' => $device_info['platform'],
            'country' => $country,
            'country_code' => $country_code,
            'city' => $city,
            'region' => $region,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timezone' => $timezone,
            'opened_at' => current_time('mysql')
        );
        $result = $this->wpdb->insert($table_name, $data);
        if ($result) {
            // Update campaign log status
            $this->update_campaign_log_status($log_id, 'opened');
            // Update subscriber statistics
            $this->update_subscriber_stats($subscriber_id);
            // Update campaign statistics
            $this->update_campaign_stats($campaign_id);
            // Log activity
            $this->log_activity($subscriber_id, 'opened', $campaign_id, null, $ip_address, array(
                'country' => $country,
                'city' => $city,
                'device' => $device_info['device_type']
            ));
            return true;
        }
        return false;
    }

    /**
     * Record link click
     */
    public function record_click($hash, $log_id, $campaign_id)
    {
        // Get original URL from hash
        $table_links = $this->wpdb->prefix . $this->table_prefix . 'links';
        $link = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table_links WHERE tracking_hash = %s",
            $hash
        ));
        if (!$link) {
            return false;
        }
        // Get IP address and user agent
        $ip_address = AdvNews_Security::get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        // Parse user agent
        $device_info = $this->parse_user_agent($user_agent);
        // Get geolocation data if enabled
        $country = '';
        $city = '';
        $country_code = '';
        $region = '';
        $latitude = null;
        $longitude = null;
        if (get_option('advnews_track_geolocation', true)) {
            $geolocation = $this->get_geolocation()->get_location($ip_address);
            $country = $geolocation['country'];
            $city = $geolocation['city'];
            $country_code = $geolocation['country_code'];
            $region = $geolocation['region'] ?? '';
            $latitude = $geolocation['latitude'];
            $longitude = $geolocation['longitude'];
        }
        // Get subscriber_id from log
        $table_logs = $this->wpdb->prefix . $this->table_prefix . 'campaign_logs';
        $log = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT subscriber_id FROM $table_logs WHERE id = %d",
            $log_id
        ));
        if (!$log) {
            return $link->original_url;
        }
        $subscriber_id = $log->subscriber_id;
        // Check if already clicked this link
        $table_clicks = $this->wpdb->prefix . $this->table_prefix . 'tracking_clicks';
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $table_clicks
            WHERE campaign_log_id = %d AND link_id = %d AND subscriber_id = %d",
            $log_id,
            $link->id,
            $subscriber_id
        ));
        $is_unique = $existing == 0;
        // Record click
        $data = array(
            'campaign_log_id' => $log_id,
            'subscriber_id' => $subscriber_id,
            'campaign_id' => $campaign_id,
            'link_id' => $link->id,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'device_type' => $device_info['device_type'],
            'browser' => $device_info['browser'],
            'platform' => $device_info['platform'],
            'country' => $country,
            'country_code' => $country_code,
            'city' => $city,
            'region' => $region,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'clicked_at' => current_time('mysql')
        );
        $result = $this->wpdb->insert($table_clicks, $data);
        if ($result) {
            // Update link statistics
            $this->update_link_stats($link->id, $is_unique);
            // Update campaign log status
            if ($is_unique) {
                $this->update_campaign_log_status($log_id, 'clicked');
            }
            // Update subscriber statistics
            $this->update_subscriber_stats($subscriber_id);
            // Update campaign statistics
            $this->update_campaign_stats($campaign_id);
            // Log activity
            $this->log_activity($subscriber_id, 'clicked', $campaign_id, $link->id, $ip_address, array(
                'url' => $link->original_url,
                'country' => $country,
                'city' => $city
            ));
        }
        return $link->original_url;
    }

    /**
     * Repair open rows that were saved before geolocation data was available.
     *
     * @param array $args Optional filters: campaign_id, subscriber_id, limit.
     * @return int Number of open rows updated.
     */
    public function backfill_missing_open_geolocation($args = array())
    {
        if (!get_option('advnews_track_geolocation', true)) {
            return 0;
        }

        $args = wp_parse_args($args, array(
            'campaign_id' => 0,
            'subscriber_id' => 0,
            'limit' => 50
        ));

        $table_opens = $this->wpdb->prefix . $this->table_prefix . 'tracking_opens';
        $where = array(
            "(country IS NULL OR country = '' OR country = 'Unknown' OR country = 'Local' OR country_code IS NULL OR country_code = '' OR country_code = 'XX')",
            "ip_address IS NOT NULL",
            "ip_address != ''",
            "ip_address != '0.0.0.0'"
        );
        $params = array();

        if (!empty($args['campaign_id'])) {
            $where[] = 'campaign_id = %d';
            $params[] = absint($args['campaign_id']);
        }

        if (!empty($args['subscriber_id'])) {
            $where[] = 'subscriber_id = %d';
            $params[] = absint($args['subscriber_id']);
        }

        $limit = max(1, min(200, absint($args['limit'])));
        $params[] = $limit;

        $opens = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, subscriber_id, campaign_id, ip_address
            FROM $table_opens
            WHERE " . implode(' AND ', $where) . "
            ORDER BY opened_at DESC
            LIMIT %d",
            $params
        ));

        $updated = 0;
        foreach ($opens as $open) {
            $location = $this->get_geolocation()->get_location($open->ip_address);
            if (!$this->has_reportable_geolocation($location)) {
                continue;
            }

            $location_data = $this->geolocation_to_tracking_data($location);
            $result = $this->wpdb->update(
                $table_opens,
                $location_data,
                array('id' => $open->id)
            );

            if ($result !== false) {
                $updated++;
                $this->backfill_open_activity_log_location($open, $location_data);
            }
        }

        return $updated;
    }

    private function backfill_open_activity_log_location($open, $location_data)
    {
        $table_activity = $this->wpdb->prefix . $this->table_prefix . 'activity_log';

        $this->wpdb->query($this->wpdb->prepare(
            "UPDATE $table_activity
            SET country = %s, country_code = %s, city = %s
            WHERE activity_type = 'opened'
            AND ip_address = %s
            AND subscriber_id = %d
            AND campaign_id = %d
            AND (country IS NULL OR country = '' OR country = 'Unknown' OR country = 'Local' OR country_code IS NULL OR country_code = '' OR country_code = 'XX')",
            $location_data['country'],
            $location_data['country_code'],
            $location_data['city'],
            $open->ip_address,
            $open->subscriber_id,
            $open->campaign_id
        ));
    }

    /**
     * Repair click rows that were saved before geolocation data was available.
     *
     * @param array $args Optional filters: campaign_id, subscriber_id, limit.
     * @return int Number of click rows updated.
     */
    public function backfill_missing_click_geolocation($args = array())
    {
        if (!get_option('advnews_track_geolocation', true)) {
            return 0;
        }

        $args = wp_parse_args($args, array(
            'campaign_id' => 0,
            'subscriber_id' => 0,
            'limit' => 50
        ));

        $table_clicks = $this->wpdb->prefix . $this->table_prefix . 'tracking_clicks';
        $where = array(
            "(country IS NULL OR country = '' OR country = 'Unknown' OR country = 'Local' OR country_code IS NULL OR country_code = '' OR country_code = 'XX')",
            "ip_address IS NOT NULL",
            "ip_address != ''",
            "ip_address != '0.0.0.0'"
        );
        $params = array();

        if (!empty($args['campaign_id'])) {
            $where[] = 'campaign_id = %d';
            $params[] = absint($args['campaign_id']);
        }

        if (!empty($args['subscriber_id'])) {
            $where[] = 'subscriber_id = %d';
            $params[] = absint($args['subscriber_id']);
        }

        $limit = max(1, min(200, absint($args['limit'])));
        $params[] = $limit;

        $clicks = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, subscriber_id, campaign_id, link_id, ip_address
            FROM $table_clicks
            WHERE " . implode(' AND ', $where) . "
            ORDER BY clicked_at DESC
            LIMIT %d",
            $params
        ));

        if (empty($clicks)) {
            return 0;
        }

        $updated = 0;
        foreach ($clicks as $click) {
            $location = $this->get_geolocation()->get_location($click->ip_address);
            if (!$this->has_reportable_geolocation($location)) {
                continue;
            }

            $location_data = $this->geolocation_to_tracking_data($location);
            $result = $this->wpdb->update(
                $table_clicks,
                $location_data,
                array('id' => $click->id)
            );

            if ($result !== false) {
                $updated++;
                $this->backfill_activity_log_location($click, $location_data);
            }
        }

        return $updated;
    }

    /**
     * Keep the activity log location aligned with repaired click rows.
     */
    private function backfill_activity_log_location($click, $location_data)
    {
        $table_activity = $this->wpdb->prefix . $this->table_prefix . 'activity_log';

        $this->wpdb->query($this->wpdb->prepare(
            "UPDATE $table_activity
            SET country = %s, country_code = %s, city = %s
            WHERE activity_type = 'clicked'
            AND ip_address = %s
            AND subscriber_id = %d
            AND campaign_id = %d
            AND link_id = %d
            AND (country IS NULL OR country = '' OR country = 'Unknown' OR country = 'Local' OR country_code IS NULL OR country_code = '' OR country_code = 'XX')",
            $location_data['country'],
            $location_data['country_code'],
            $location_data['city'],
            $click->ip_address,
            $click->subscriber_id,
            $click->campaign_id,
            $click->link_id
        ));
    }

    /**
     * Parse user agent for device/browser info
     */
    private function parse_user_agent($user_agent)
    {
        $device_type = 'Desktop';
        $browser = 'Unknown';
        $platform = 'Unknown';
        // Device detection
        if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $user_agent)) {
            $device_type = 'Mobile';
        } elseif (preg_match('/(ipad|tablet|(android(?!.*mobile))|(kindle))|(playbook)|(silk)|(puffin)|(kf[aizs])/i', $user_agent)) {
            $device_type = 'Tablet';
        }
        // Browser detection
        if (strpos($user_agent, 'Chrome') !== false && strpos($user_agent, 'Edg') === false) {
            $browser = 'Chrome';
        } elseif (strpos($user_agent, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($user_agent, 'Safari') !== false && strpos($user_agent, 'Chrome') === false) {
            $browser = 'Safari';
        } elseif (strpos($user_agent, 'Edg') !== false) {
            $browser = 'Edge';
        } elseif (strpos($user_agent, 'Opera') !== false || strpos($user_agent, 'OPR') !== false) {
            $browser = 'Opera';
        } elseif (strpos($user_agent, 'MSIE') !== false || strpos($user_agent, 'Trident') !== false) {
            $browser = 'Internet Explorer';
        }
        // Platform detection
        if (strpos($user_agent, 'Windows') !== false) {
            $platform = 'Windows';
        } elseif (strpos($user_agent, 'Mac OS') !== false) {
            $platform = 'macOS';
        } elseif (strpos($user_agent, 'Linux') !== false) {
            $platform = 'Linux';
        } elseif (strpos($user_agent, 'Android') !== false) {
            $platform = 'Android';
        } elseif (strpos($user_agent, 'iPhone') !== false || strpos($user_agent, 'iPad') !== false || strpos($user_agent, 'iPod') !== false) {
            $platform = 'iOS';
        }
        return array(
            'device_type' => $device_type,
            'browser' => $browser,
            'platform' => $platform
        );
    }

    /**
     * Update campaign log status
     */
    private function update_campaign_log_status($log_id, $status)
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'campaign_logs';
        $update_field = $status . '_at';
        $this->wpdb->update(
            $table_name,
            array(
                'status' => $status,
                $update_field => current_time('mysql')
            ),
            array('id' => $log_id)
        );
    }

    /**
     * Update subscriber statistics
     */
    private function update_subscriber_stats($subscriber_id)
    {
        $table_subscribers = $this->wpdb->prefix . $this->table_prefix . 'subscribers';
        $table_logs = $this->wpdb->prefix . $this->table_prefix . 'campaign_logs';
        $table_opens = $this->wpdb->prefix . $this->table_prefix . 'tracking_opens';
        $table_clicks = $this->wpdb->prefix . $this->table_prefix . 'tracking_clicks';

        // Count opens/clicks from tracking tables so one mutable log status cannot hide prior opens.
        $stats = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT
                COUNT(DISTINCT l.id) as total_sent,
                COUNT(DISTINCT CASE WHEN l.status = 'opened' OR o.campaign_log_id IS NOT NULL THEN l.id END) as total_opened,
                COUNT(DISTINCT CASE WHEN l.status = 'clicked' OR c.campaign_log_id IS NOT NULL THEN l.id END) as total_clicked
            FROM $table_logs l
            LEFT JOIN $table_opens o
                ON o.campaign_log_id = l.id
                AND o.subscriber_id = %d
            LEFT JOIN $table_clicks c
                ON c.campaign_log_id = l.id
                AND c.subscriber_id = %d
            WHERE l.subscriber_id = %d
            AND l.status IN ('delivered', 'opened', 'clicked')",
            $subscriber_id,
            $subscriber_id,
            $subscriber_id
        ));
        if ($stats && $stats->total_sent > 0) {
            $open_rate = ($stats->total_opened / $stats->total_sent) * 100;
            $click_rate = ($stats->total_clicked / $stats->total_sent) * 100;
            $this->wpdb->update(
                $table_subscribers,
                array(
                    'total_opens' => $stats->total_opened,
                    'total_clicks' => $stats->total_clicked,
                    'open_rate' => round($open_rate, 2),
                    'click_rate' => round($click_rate, 2),
                    'last_activity_at' => current_time('mysql')
                ),
                array('id' => $subscriber_id)
            );
        }
    }

    /**
     * Update campaign statistics
     */
    private function update_campaign_stats($campaign_id)
    {
        $campaign_class = new AdvNews_Campaign();
        $campaign_class->update_campaign_stats($campaign_id);
    }

    /**
     * Update link statistics
     */
    private function update_link_stats($link_id, $is_unique)
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'links';
        $table_clicks = $this->wpdb->prefix . $this->table_prefix . 'tracking_clicks';
        // Get current counts
        $click_count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $table_clicks WHERE link_id = %d",
            $link_id
        ));
        $unique_click_count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT subscriber_id) FROM $table_clicks WHERE link_id = %d",
            $link_id
        ));
        // Update link
        $this->wpdb->update(
            $table_name,
            array(
                'click_count' => $click_count,
                'unique_click_count' => $unique_click_count
            ),
            array('id' => $link_id)
        );
    }

    /**
     * Log activity
     */
    private function log_activity($subscriber_id, $activity_type, $campaign_id = null, $link_id = null, $ip_address = null, $metadata = array())
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'activity_log';
        // Get geolocation if IP is provided
        $country = '';
        $city = '';
        $country_code = '';
        if ($ip_address && get_option('advnews_track_geolocation', true)) {
            $location = $this->get_geolocation()->get_location($ip_address);
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
     * Get campaign analytics with enhanced geolocation data
     *
     * @param int $campaign_id
     * @return array
     */
    public function get_campaign_analytics($campaign_id)
    {
        $this->backfill_missing_open_geolocation(array(
            'campaign_id' => $campaign_id,
            'limit' => 100
        ));
        $this->backfill_missing_click_geolocation(array(
            'campaign_id' => $campaign_id,
            'limit' => 100
        ));

        $analytics = array(
            'overview' => array(),
            'geographic' => array(),
            'geographic_map' => array(),
            'geographic_summary' => array(),
            'devices' => array(),
            'links' => array(),
            'timeline' => array(),
            'cities' => array()
        );
        // Overview statistics - Get actual data from campaigns table
        $table_campaigns = $this->wpdb->prefix . $this->table_prefix . 'campaigns';
        $overview = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT
            total_recipients,
            sent_count,
            delivered_count,
            open_count,
            click_count,
            bounce_count,
            unsubscribe_count,
            open_rate,
            click_rate
            FROM $table_campaigns
            WHERE id = %d",
            $campaign_id
        ));
        if ($overview) {
            $analytics['overview'] = (array) $overview;
            if ($overview->total_recipients > 0) {
                $analytics['overview']['delivery_rate'] = round(($overview->delivered_count / $overview->total_recipients) * 100, 2);
            } else {
                $analytics['overview']['delivery_rate'] = 0;
            }
        }
        $table_opens = $this->wpdb->prefix . $this->table_prefix . 'tracking_opens';
        $table_clicks = $this->wpdb->prefix . $this->table_prefix . 'tracking_clicks';
        // Prefer clicks because open pixels are often loaded by email-provider
        // proxies. If a campaign has no located clicks, use located opens so
        // the geographic report does not appear empty despite engagement data.
        $located_clicks = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $table_clicks
            WHERE campaign_id = %d
            AND country != '' AND country != 'Local' AND country != 'Unknown'",
            $campaign_id
        ));
        $use_click_geography = $located_clicks > 0;
        $geographic_table = $use_click_geography ? $table_clicks : $table_opens;
        $geographic_timestamp = $use_click_geography ? 'clicked_at' : 'opened_at';
        $geographic_table_key = $use_click_geography ? 'tracking_clicks' : 'tracking_opens';
        $analytics['geographic_metric'] = $use_click_geography ? 'clicks' : 'opens';
        $has_country_code = $this->column_exists($geographic_table_key, 'country_code');
        // Enhanced geographic data with country codes for maps
        if ($has_country_code) {
            $geographic = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT
                country,
                country_code,
                city,
                COUNT(*) as opens,
                COUNT(DISTINCT subscriber_id) as unique_opens,
                COUNT(DISTINCT DATE($geographic_timestamp)) as days_active,
                AVG(HOUR($geographic_timestamp)) as avg_hour
                FROM $geographic_table
                WHERE campaign_id = %d
                AND country != '' AND country != 'Local' AND country != 'Unknown'
                GROUP BY country, country_code, city
                ORDER BY opens DESC",
                $campaign_id
            ));
        } else {
            $geographic = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT
                country,
                '' as country_code,
                city,
                COUNT(*) as opens,
                COUNT(DISTINCT subscriber_id) as unique_opens,
                COUNT(DISTINCT DATE($geographic_timestamp)) as days_active,
                AVG(HOUR($geographic_timestamp)) as avg_hour
                FROM $geographic_table
                WHERE campaign_id = %d
                AND country != '' AND country != 'Local' AND country != 'Unknown'
                GROUP BY country, city
                ORDER BY opens DESC",
                $campaign_id
            ));
        }
        $analytics['geographic'] = $geographic;
        // Map-ready data (grouped by country only)
        if ($has_country_code) {
            $map_data = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT
                country_code,
                country,
                COUNT(*) as opens,
                COUNT(DISTINCT subscriber_id) as unique_visitors,
                COUNT(DISTINCT CASE WHEN city != '' AND city != 'Local' AND city != 'Unknown' THEN city END) as cities_count
                FROM $geographic_table
                WHERE campaign_id = %d
                AND country != '' AND country != 'Local' AND country != 'Unknown'
                GROUP BY country_code, country
                ORDER BY opens DESC",
                $campaign_id
            ));
        } else {
            $map_data = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT
                '' as country_code,
                country,
                COUNT(*) as opens,
                COUNT(DISTINCT subscriber_id) as unique_visitors,
                COUNT(DISTINCT CASE WHEN city != '' AND city != 'Local' AND city != 'Unknown' THEN city END) as cities_count
                FROM $geographic_table
                WHERE campaign_id = %d
                AND country != '' AND country != 'Local' AND country != 'Unknown'
                GROUP BY country
                ORDER BY opens DESC",
                $campaign_id
            ));
        }
        $analytics['geographic_map'] = $map_data;
        // Geographic summary statistics
        $summary = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT
            COUNT(DISTINCT CASE WHEN country != '' AND country != 'Local' AND country != 'Unknown' THEN country END) as total_countries,
            COUNT(DISTINCT CASE WHEN city != '' AND city != 'Local' AND city != 'Unknown' THEN city END) as total_cities,
            COUNT(DISTINCT CASE WHEN country != '' AND country != 'Local' AND country != 'Unknown' THEN country END) as tracked_countries,
            COUNT(DISTINCT CASE WHEN city != '' AND city != 'Local' AND city != 'Unknown' THEN city END) as tracked_cities
            FROM $geographic_table
            WHERE campaign_id = %d",
            $campaign_id
        ));
        $analytics['geographic_summary'] = $summary;
        // City-level data with coordinates for maps
        $cities = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT
            country,
            country_code,
            city,
            COUNT(*) as opens,
            COUNT(DISTINCT subscriber_id) as unique_visitors,
            latitude,
            longitude,
            MIN($geographic_timestamp) as first_open,
            MAX($geographic_timestamp) as last_open
            FROM $geographic_table
            WHERE campaign_id = %d
            AND city != '' AND city != 'Local' AND city != 'Unknown'
            AND latitude IS NOT NULL AND longitude IS NOT NULL
            GROUP BY country, country_code, city, latitude, longitude
            ORDER BY opens DESC",
            $campaign_id
        ));
        $analytics['cities'] = $cities;
        // Device data
        $devices = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT
            device_type,
            browser,
            platform,
            COUNT(*) as opens
            FROM $geographic_table
            WHERE campaign_id = %d
            GROUP BY device_type, browser, platform
            ORDER BY opens DESC",
            $campaign_id
        ));
        $analytics['devices'] = $devices;
        // Link data
        $table_links = $this->wpdb->prefix . $this->table_prefix . 'links';
        $links = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT
            l.id,
            l.original_url,
            l.click_count,
            l.unique_click_count,
            COUNT(DISTINCT c.subscriber_id) as clickers,
            COUNT(DISTINCT CASE WHEN c.country != '' AND c.country != 'Local' AND c.country != 'Unknown' THEN c.country END) as countries_reached
            FROM $table_links l
            LEFT JOIN $table_clicks c ON l.id = c.link_id AND c.campaign_id = %d
            WHERE l.campaign_id = %d
            GROUP BY l.id
            ORDER BY l.click_count DESC",
            $campaign_id,
            $campaign_id
        ));
        $analytics['links'] = $links;
        // Timeline data
        $timeline = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT
            DATE(opened_at) as date,
            HOUR(opened_at) as hour,
            COUNT(*) as opens,
            COUNT(DISTINCT CASE WHEN country != '' AND country != 'Local' AND country != 'Unknown' THEN country END) as countries
            FROM $table_opens
            WHERE campaign_id = %d
            GROUP BY DATE(opened_at), HOUR(opened_at)
            ORDER BY date, hour",
            $campaign_id
        ));
        $analytics['timeline'] = $timeline;
        return $analytics;
    }

    /**
     * Get subscriber activity with geolocation
     *
     * @param int $subscriber_id
     * @param int $limit
     * @return array
     */
    public function get_subscriber_activity($subscriber_id, $limit = 50, $offset = 0)
    {
        $limit = max(1, absint($limit));
        $offset = max(0, absint($offset));
        $fetch_limit = $limit + $offset;

        $this->backfill_missing_open_geolocation(array(
            'subscriber_id' => $subscriber_id,
            'limit' => min(200, $fetch_limit)
        ));
        $this->backfill_missing_click_geolocation(array(
            'subscriber_id' => $subscriber_id,
            'limit' => min(200, $fetch_limit)
        ));

        $table_opens = $this->wpdb->prefix . $this->table_prefix . 'tracking_opens';
        $table_clicks = $this->wpdb->prefix . $this->table_prefix . 'tracking_clicks';
        $table_campaigns = $this->wpdb->prefix . $this->table_prefix . 'campaigns';
        $table_logs = $this->wpdb->prefix . $this->table_prefix . 'campaign_logs';
        // Get opens with geolocation
        $opens = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT
            o.*,
            c.name as campaign_name,
            c.subject as campaign_subject,
            lg.sent_at,
            lg.delivered_at,
            lg.opened_at as log_opened_at,
            lg.clicked_at as log_clicked_at
            FROM $table_opens o
            INNER JOIN $table_campaigns c ON o.campaign_id = c.id
            INNER JOIN $table_logs lg ON o.campaign_log_id = lg.id
            WHERE o.subscriber_id = %d
            ORDER BY o.opened_at DESC
            LIMIT %d",
            $subscriber_id,
            $fetch_limit
        ));
        // Get clicks with geolocation
        $clicks = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT
            cl.*,
            l.original_url,
            c.name as campaign_name,
            c.subject as campaign_subject,
            lg.sent_at,
            lg.delivered_at,
            lg.opened_at as log_opened_at,
            lg.clicked_at as log_clicked_at
            FROM $table_clicks cl
            INNER JOIN {$this->wpdb->prefix}{$this->table_prefix}links l ON cl.link_id = l.id
            INNER JOIN $table_campaigns c ON cl.campaign_id = c.id
            INNER JOIN $table_logs lg ON cl.campaign_log_id = lg.id
            WHERE cl.subscriber_id = %d
            ORDER BY cl.clicked_at DESC
            LIMIT %d",
            $subscriber_id,
            $fetch_limit
        ));
        // Combine and sort by date
        $activity = array();
        foreach ($opens as $open) {
            $activity[] = array(
                'type' => 'open',
                'date' => $open->opened_at,
                'campaign' => $open->campaign_name,
                'subject' => $open->campaign_subject,
                'sent_at' => $open->sent_at,
                'delivered_at' => $open->delivered_at,
                'opened_at' => $open->log_opened_at ?: $open->opened_at,
                'clicked_at' => $open->log_clicked_at,
                'device' => $open->device_type,
                'browser' => $open->browser,
                'platform' => $open->platform,
                'ip_address' => $open->ip_address,
                'country' => $open->country,
                'country_code' => $open->country_code,
                'city' => $open->city,
                'location' => trim($open->city . ', ' . $open->country, ', ')
            );
        }
        foreach ($clicks as $click) {
            $activity[] = array(
                'type' => 'click',
                'date' => $click->clicked_at,
                'campaign' => $click->campaign_name,
                'subject' => $click->campaign_subject,
                'url' => $click->original_url,
                'sent_at' => $click->sent_at,
                'delivered_at' => $click->delivered_at,
                'opened_at' => $click->log_opened_at,
                'clicked_at' => $click->log_clicked_at ?: $click->clicked_at,
                'device' => $click->device_type,
                'browser' => $click->browser,
                'platform' => $click->platform,
                'ip_address' => $click->ip_address,
                'country' => $click->country,
                'country_code' => $click->country_code,
                'city' => $click->city,
                'location' => trim($click->city . ', ' . $click->country, ', ')
            );
        }
        // Sort by date descending
        usort($activity, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        return array_slice($activity, $offset, $limit);
    }

    public function get_subscriber_activity_count($subscriber_id)
    {
        $table_opens = $this->wpdb->prefix . $this->table_prefix . 'tracking_opens';
        $table_clicks = $this->wpdb->prefix . $this->table_prefix . 'tracking_clicks';

        return (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT
                (SELECT COUNT(*) FROM $table_opens WHERE subscriber_id = %d)
                + (SELECT COUNT(*) FROM $table_clicks WHERE subscriber_id = %d)",
            $subscriber_id,
            $subscriber_id
        ));
    }

    /**
     * Get system-wide analytics with geographic data
     *
     * @param string $period
     * @return array
     */
    public function get_system_analytics($period = '30days')
    {
        $this->backfill_missing_open_geolocation(array('limit' => 100));
        $this->backfill_missing_click_geolocation(array('limit' => 100));

        $analytics = array(
            'campaigns' => array(),
            'subscribers' => array(),
            'performance' => array(),
            'geographic' => array(),
            'trends' => array()
        );
        // Set date range
        $end_date = current_time('mysql');
        switch ($period) {
            case '7days':
                $start_date = date('Y-m-d H:i:s', strtotime('-7 days'));
                break;
            case '30days':
                $start_date = date('Y-m-d H:i:s', strtotime('-30 days'));
                break;
            case '90days':
                $start_date = date('Y-m-d H:i:s', strtotime('-90 days'));
                break;
            default:
                $start_date = date('Y-m-d H:i:s', strtotime('-30 days'));
        }
        // Campaign statistics
        $table_campaigns = $this->wpdb->prefix . $this->table_prefix . 'campaigns';
        $campaigns = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT
            COUNT(*) as total_campaigns,
            SUM(total_recipients) as total_recipients,
            SUM(delivered_count) as delivered_count,
            SUM(open_count) as open_count,
            SUM(click_count) as click_count,
            AVG(open_rate) as avg_open_rate,
            AVG(click_rate) as avg_click_rate
            FROM $table_campaigns
            WHERE sent_at BETWEEN %s AND %s
            AND status = 'sent'",
            $start_date,
            $end_date
        ));
        $analytics['campaigns'] = $campaigns[0] ?? (object) array(
            'total_campaigns' => 0,
            'total_recipients' => 0,
            'delivered_count' => 0,
            'open_count' => 0,
            'click_count' => 0,
            'avg_open_rate' => 0,
            'avg_click_rate' => 0
        );
        // Subscriber statistics
        $table_subscribers = $this->wpdb->prefix . $this->table_prefix . 'subscribers';
        $subscribers = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'unsubscribed' THEN 1 ELSE 0 END) as unsubscribed,
            SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced
            FROM $table_subscribers
            WHERE subscribed_at <= %s",
            $end_date
        ));
        $analytics['subscribers'] = $subscribers[0] ?? (object) array(
            'total' => 0,
            'active' => 0,
            'unsubscribed' => 0,
            'bounced' => 0
        );
        // Use click rows for geographic reports because open pixels are often loaded by email-provider proxies.
        $table_clicks = $this->wpdb->prefix . $this->table_prefix . 'tracking_clicks';
        $has_country_code = $this->column_exists('tracking_clicks', 'country_code');
        // Geographic distribution for the period
        if ($has_country_code) {
            $geographic = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT
                country,
                country_code,
                COUNT(*) as opens,
                COUNT(DISTINCT subscriber_id) as unique_visitors,
                COUNT(DISTINCT campaign_id) as campaigns
                FROM $table_clicks
                WHERE clicked_at BETWEEN %s AND %s
                AND country != '' AND country != 'Local' AND country != 'Unknown'
                GROUP BY country, country_code
                ORDER BY opens DESC",
                $start_date,
                $end_date
            ));
        } else {
            $geographic = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT
                country,
                '' as country_code,
                COUNT(*) as opens,
                COUNT(DISTINCT subscriber_id) as unique_visitors,
                COUNT(DISTINCT campaign_id) as campaigns
                FROM $table_clicks
                WHERE clicked_at BETWEEN %s AND %s
                AND country != '' AND country != 'Local' AND country != 'Unknown'
                GROUP BY country
                ORDER BY opens DESC",
                $start_date,
                $end_date
            ));
        }
        $analytics['geographic'] = $geographic;
        // Performance over time with geographic spread
        $performance = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT
            DATE(sent_at) as date,
            COUNT(*) as campaigns_sent,
            SUM(total_recipients) as emails_sent,
            AVG(open_rate) as avg_open_rate,
            AVG(click_rate) as avg_click_rate
            FROM $table_campaigns c
            WHERE sent_at BETWEEN %s AND %s
            AND status = 'sent'
            GROUP BY DATE(sent_at)
            ORDER BY date",
            $start_date,
            $end_date
        ));
        $analytics['performance'] = $performance;
        // Geographic trends follow click rows for the same reason as the main geo report.
        $trends = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT
            DATE(clicked_at) as date,
            country,
            COUNT(*) as opens
            FROM $table_clicks
            WHERE clicked_at BETWEEN %s AND %s
            AND country != '' AND country != 'Local' AND country != 'Unknown'
            GROUP BY DATE(clicked_at), country
            ORDER BY date, opens DESC",
            $start_date,
            $end_date
        ));
        $analytics['trends'] = $trends;
        return $analytics;
    }

    /**
     * Export analytics data with geographic info
     *
     * @param int $campaign_id
     * @param string $type
     */
    public function export_analytics($campaign_id, $type = 'overview')
    {
        $analytics = $this->get_campaign_analytics($campaign_id);
        $filename = 'analytics-campaign-' . $campaign_id . '-' . $type . '-' . date('Y-m-d-H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        $output = fopen('php://output', 'w');
        switch ($type) {
            case 'geographic':
                fputcsv($output, array('Country', 'Country Code', 'City', 'Clicks', 'Unique Clickers', 'Days Active', 'Avg Hour'));
                foreach ($analytics['geographic'] as $row) {
                    $average_minutes = $row->avg_hour !== null ? (int) round((float) $row->avg_hour * 60) : null;
                    $average_hour = $average_minutes !== null
                        ? sprintf('%02d:%02d', (int) floor($average_minutes / 60) % 24, $average_minutes % 60)
                        : 'N/A';
                    fputcsv($output, array(
                        $row->country,
                        $row->country_code ?? '',
                        $row->city,
                        $row->opens,
                        $row->unique_opens,
                        $row->days_active,
                        $average_hour
                    ));
                }
                break;
            case 'map':
                fputcsv($output, array('Country Code', 'Country', 'Clicks', 'Unique Clickers', 'Cities'));
                foreach ($analytics['geographic_map'] as $row) {
                    fputcsv($output, array(
                        $row->country_code ?? '',
                        $row->country,
                        $row->opens,
                        $row->unique_visitors,
                        $row->cities_count
                    ));
                }
                break;
            case 'cities':
                fputcsv($output, array('Country', 'City', 'Clicks', 'Unique Clickers', 'Latitude', 'Longitude'));
                foreach ($analytics['cities'] as $row) {
                    fputcsv($output, array(
                        $row->country,
                        $row->city,
                        $row->opens,
                        $row->unique_visitors,
                        $row->latitude,
                        $row->longitude
                    ));
                }
                break;
            case 'devices':
                $headers = array('Device Type', 'Browser', 'Platform', 'Clicks');
                fputcsv($output, $headers);
                foreach ($analytics['devices'] as $row) {
                    fputcsv($output, array(
                        $row->device_type,
                        $row->browser,
                        $row->platform,
                        $row->opens
                    ));
                }
                break;
            case 'links':
                $headers = array('URL', 'Clicks', 'Unique Clicks', 'Unique Clickers', 'Countries Reached');
                fputcsv($output, $headers);
                foreach ($analytics['links'] as $row) {
                    fputcsv($output, array(
                        $row->original_url,
                        $row->click_count,
                        $row->unique_click_count,
                        $row->clickers,
                        $row->countries_reached
                    ));
                }
                break;
            case 'overview':
            default:
                $headers = array('Metric', 'Value');
                fputcsv($output, $headers);
                foreach ($analytics['overview'] as $key => $value) {
                    $label = ucwords(str_replace('_', ' ', $key));
                    fputcsv($output, array($label, $value));
                }
                fputcsv($output, array(''));
                fputcsv($output, array('Geographic Summary', ''));
                fputcsv($output, array('Total Countries', $analytics['geographic_summary']->total_countries ?? 0));
                fputcsv($output, array('Total Cities', $analytics['geographic_summary']->total_cities ?? 0));
                fputcsv($output, array('Tracked Countries', $analytics['geographic_summary']->tracked_countries ?? 0));
                fputcsv($output, array('Tracked Cities', $analytics['geographic_summary']->tracked_cities ?? 0));
                break;
        }
        fclose($output);
        exit;
    }

    /**
    * Check and update MaxMind database if needed (once daily)
    */
    public function maybe_update_maxmind_database() {
        // Check if MaxMind is enabled
        if (get_option('advnews_geolocation_service') !== 'maxmind') {
            return false;
        }

        // Check if auto-update is enabled
        if (!get_option('advnews_maxmind_auto_update', true)) {
            return false;
        }

        // Check last update time
        $last_update = get_option('advnews_maxmind_last_update', 0);
        $now = time();

        // Only check once per day (86400 seconds)
        if ($now - $last_update < 86400) {
            return false;
        }

        // Schedule the update
        if (!wp_next_scheduled('advnews_update_maxmind_database')) {
            wp_schedule_single_event($now, 'advnews_update_maxmind_database');
        }

        return true;
    }

    /**
    * Safely update MaxMind database with strict integrity checks.
    * NO fallback to ip-api. Keeps the old DB if the new one is invalid.
    */
    public function update_maxmind_database_safely($license_key = null) {
        require_once ABSPATH . 'wp-admin/includes/file.php';

        if (!$license_key) {
            $license_key = get_option('advnews_maxmind_license_key');
        }

        if (!$license_key) {
            return new WP_Error('missing_credentials', __('MaxMind License Key is required.', 'advnews-manager'));
        }

        $upload_dir = wp_upload_dir();
        $db_dir = $upload_dir['basedir'] . '/advnews-maxmind/';

        if (!wp_mkdir_p($db_dir)) {
            return new WP_Error('dir_failed', __('Cannot create database directory.', 'advnews-manager'));
        }

        $current_db_path = $db_dir . 'GeoLite2-City.mmdb';
        $temp_extract_dir = $db_dir . 'temp-extract-' . time() . '/';

        // This is the license-key-only GeoLite2 download endpoint used by the prior working plugin.
        $download_url = 'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-City&license_key=' . rawurlencode($license_key) . '&suffix=tar.gz';
        $temp_archive = download_url($download_url, 120);
        if (is_wp_error($temp_archive)) {
            return new WP_Error('download_failed', $temp_archive->get_error_message());
        }

        // STEP 2: STRICT SIZE CHECK on Archive
        // A valid GeoLite2-City.tar.gz is typically > 5MB. If it's < 1MB, it is 100% an HTML error page.
        $file_size = filesize($temp_archive);
        if ($file_size < 1000000) {
            @unlink($temp_archive);
            return new WP_Error('invalid_file_size', sprintf(__('Downloaded file is too small (%s bytes). Your license key is likely invalid, and MaxMind returned an HTML error page instead of a database.', 'advnews-manager'), $file_size));
        }

        // STEP 3: MIME TYPE CHECK (Double verification)
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $temp_archive);
            finfo_close($finfo);

            if (strpos($mime_type, 'html') !== false || strpos($mime_type, 'text') !== false) {
                @unlink($temp_archive);
                return new WP_Error('invalid_mime_type', __('Downloaded file is an HTML/text file, not a valid database archive. Please check your MaxMind License Key.', 'advnews-manager'));
            }
        }

        // STEP 4: Extract to temporary directory
        if (!wp_mkdir_p($temp_extract_dir)) {
            @unlink($temp_archive);
            return new WP_Error('extract_failed', __('Cannot create extraction directory.', 'advnews-manager'));
        }

        try {
            $phar = new PharData($temp_archive);
            $phar->extractTo($temp_extract_dir, null, true);
        } catch (Exception $e) {
            $this->delete_directory($temp_extract_dir);
            @unlink($temp_archive);
            return new WP_Error('extract_failed', __('Failed to extract archive: ', 'advnews-manager') . $e->getMessage());
        }

        // Clean up the archive early to save server space
        @unlink($temp_archive);

        // STEP 5: Find the .mmdb file inside the extracted directory
        $mmdb_file = null;
        if (class_exists('RecursiveIteratorIterator')) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($temp_extract_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'mmdb') {
                    $mmdb_file = $file->getPathname();
                    break;
                }
            }
        } else {
            $files = glob($temp_extract_dir . '/*/GeoLite2-City.mmdb');
            if (!empty($files)) {
                $mmdb_file = $files[0];
            }
        }

        if (!$mmdb_file) {
            $this->delete_directory($temp_extract_dir);
            return new WP_Error('mmdb_not_found', __('Could not find the .mmdb file inside the downloaded archive.', 'advnews-manager'));
        }

        // STEP 6: STRICT SIZE CHECK ON MMDB FILE
        // A valid GeoLite2-City.mmdb is ALWAYS > 10MB (usually 20-30MB).
        $mmdb_size = filesize($mmdb_file);
        if ($mmdb_size < 10000000) {
            $this->delete_directory($temp_extract_dir);
            return new WP_Error('invalid_mmdb_size', sprintf(__('The extracted .mmdb file is too small (%s bytes). It is corrupted or invalid.', 'advnews-manager'), $mmdb_size));
        }

        // STEP 7: VALIDATION COMPLETE. It is safe to replace the old database.
        // We use atomic rename for safety.
        $temp_final_path = $db_dir . 'GeoLite2-City-validating.mmdb';
        if (!rename($mmdb_file, $temp_final_path)) {
            $this->delete_directory($temp_extract_dir);
            return new WP_Error('rename_failed', __('Failed to move validated database to final location.', 'advnews-manager'));
        }

        // Replace the old one (or create new if it didn't exist)
        if (!rename($temp_final_path, $current_db_path)) {
            @unlink($temp_final_path);
            $this->delete_directory($temp_extract_dir);
            return new WP_Error('replace_failed', __('Failed to replace the old database.', 'advnews-manager'));
        }

        // STEP 8: Cleanup temp directory
        $this->delete_directory($temp_extract_dir);

        // STEP 9: Update options
        update_option('advnews_maxmind_last_update', time());
        update_option('advnews_maxmind_db_path', $current_db_path);

        return array(
            'success' => true,
            'message' => __('Database downloaded, validated, and updated successfully.', 'advnews-manager'),
            'path' => $current_db_path
        );
    }

    /**
    * Validate MaxMind database file integrity
    */
    private function validate_maxmind_database($file_path) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', __('Database file not found.', 'advnews-manager'));
        }

        // Check file size (should be at least 1MB for GeoLite2-City)
        $file_size = filesize($file_path);
        if ($file_size < 1048576) { // 1MB
            return new WP_Error('invalid_size',
                sprintf(__('Database file is too small (%s bytes). File may be corrupted.', 'advnews-manager'),
                number_format($file_size)));
        }

        // Try to read the database
        try {
            require_once ADVNEWS_PLUGIN_DIR . 'includes/class-maxmind-reader.php';
            $reader = new AdvNews_MaxMind_Reader($file_path);

            // Test with a known IP
            $test_result = $reader->get('8.8.8.8');

            if (!is_array($test_result) || !isset($test_result['country'])) {
                return new WP_Error('invalid_database',
                    __('Database validation failed. Cannot read country data.', 'advnews-manager'));
            }

            unset($reader);

        } catch (Exception $e) {
            return new WP_Error('validation_failed',
                sprintf(__('Database validation error: %s', 'advnews-manager'), $e->getMessage()));
        }

        return true;
    }

    /**
    * Delete directory recursively
    */
    private function delete_directory($dir) {
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
}
